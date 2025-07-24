
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Vulnerability Report PDF</title>
    <style>
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 13px; color: #222; }
        h1, h3 { color: #1a237e; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 20px; }
        th, td { border: 1px solid #888; padding: 6px 8px; }
        th { background: #f1f1f1; }
        ul { margin-bottom: 20px; }
        .section-title { margin-top: 30px; }
    </style>
</head>
<body>
    <h1>Vulnerability Report</h1>
    <p><strong>File:</strong> {{ $batch->filename }}</p>
    <p><strong>Generated:</strong> {{ now()->format('M j, Y H:i') }}</p>

    <h3 class="section-title">Executive Summary</h3>
    <ul>
        <li><strong>Total IPs Scanned:</strong> {{ $batch->total_ips }}</li>
        <li><strong>Online Hosts:</strong> {{ $batch->online_ips }} ({{ $batch->total_ips > 0 ? round(($batch->online_ips / $batch->total_ips) * 100, 1) : 0 }}%)</li>
        <li><strong>Vulnerable Hosts:</strong> {{ $batch->vulnerable_ips }} ({{ $batch->online_ips > 0 ? round(($batch->vulnerable_ips / $batch->online_ips) * 100, 1) : 0 }}%)</li>
        <li><strong>Scan Duration:</strong> {{ $batch->started_at && $batch->completed_at ? $batch->started_at->diffForHumans($batch->completed_at, true) : 'N/A' }}</li>
    </ul>

    @php
        // Risk assessment
        $riskLevel = 'Low';
        $riskClass = 'success';
        $vulnerabilityPercentage = $batch->online_ips > 0 ? ($batch->vulnerable_ips / $batch->online_ips) * 100 : 0;
        if ($vulnerabilityPercentage > 50) {
            $riskLevel = 'Critical';
        } elseif ($vulnerabilityPercentage > 25) {
            $riskLevel = 'High';
        } elseif ($vulnerabilityPercentage > 10) {
            $riskLevel = 'Medium';
        }
        // Port and severity counts
        $portCounts = [];
        $severityCounts = ['HIGH' => 0, 'MEDIUM' => 0, 'LOW' => 0];
        foreach($vulnerableIps as $ip) {
            if (!empty($ip->vulnerable_ports)) {
                foreach($ip->vulnerable_ports as $port => $description) {
                    $portCounts[$port] = ($portCounts[$port] ?? 0) + 1;
                }
            }
        }
        arsort($portCounts);
    @endphp

    <h3 class="section-title">Risk Assessment</h3>
    <table style="width:auto; min-width:300px;">
        <tr>
            <th>Overall Risk Level</th>
            <td>{{ $riskLevel }}</td>
        </tr>
        <tr>
            <th>Vulnerability %</th>
            <td>{{ round($vulnerabilityPercentage, 1) }}%</td>
        </tr>
    </table>

    @if($vulnerableIps->count() > 0)
        <h3 class="section-title">Most Common Vulnerable Ports</h3>
        <table>
            <thead>
                <tr>
                    <th>Port</th>
                    <th>Count</th>
                    <th>Risk</th>
                </tr>
            </thead>
            <tbody>
                @foreach(array_slice($portCounts, 0, 10, true) as $port => $count)
                    @php
                        $severity = 'LOW';
                        if (in_array($port, [23, 135, 139, 445, 3389])) $severity = 'HIGH';
                        elseif (in_array($port, [21, 22, 1433, 5900])) $severity = 'MEDIUM';
                        $severityCounts[$severity]++;
                    @endphp
                    <tr>
                        <td>{{ $port }}</td>
                        <td>{{ $count }}</td>
                        <td>{{ $severity }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <h3 class="section-title">Detailed Vulnerability Report</h3>
        @foreach($vulnerableIps as $ip)
            @if(!empty($vulnerabilityReport[$ip->ip_address]))
                <div style="margin-bottom:18px; border-bottom:1px solid #ccc; padding-bottom:10px;">
                    <strong style="color:#1a237e;">{{ $ip->ip_address }}</strong>
                    @if($ip->ping_time)
                        <span style="color:#888;">({{ $ip->ping_time }}ms)</span>
                    @endif
                    <ul>
                        @foreach($vulnerabilityReport[$ip->ip_address] as $vuln)
                            <li>
                                <strong>Port {{ $vuln['port'] }} ({{ $vuln['service'] }})</strong>: {{ $vuln['vulnerability'] }}<br>
                                <em>Recommendation:</em> {{ $vuln['recommendation'] }}<br>
                                <em>Severity:</em> {{ $vuln['severity'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @endforeach
    @else
        <p>No vulnerabilities detected in the scanned IP range.</p>
    @endif

    <h3 class="section-title">Online Hosts Summary</h3>
    <table>
        <thead>
            <tr>
                <th>IP Address</th>
                <th>Response Time</th>
                <th>Open Ports</th>
                <th>Risk Level</th>
            </tr>
        </thead>
        <tbody>
            @foreach($onlineIps as $ip)
                <tr>
                    <td>{{ $ip->ip_address }}</td>
                    <td>{{ $ip->ping_time }}ms</td>
                    <td>
                        @if(!empty($ip->open_ports))
                            @foreach($ip->open_ports as $port => $service)
                                <span>{{ $port }}</span>@if(!$loop->last), @endif
                            @endforeach
                        @else
                            None detected
                        @endif
                    </td>
                    <td>
                        @if(!empty($ip->vulnerable_ports))
                            @php
                                $maxSeverity = 'LOW';
                                foreach(array_keys($ip->vulnerable_ports) as $port) {
                                    if (in_array($port, [23, 135, 139, 445, 3389])) $maxSeverity = 'HIGH';
                                    elseif (in_array($port, [21, 22, 1433, 5900]) && $maxSeverity !== 'HIGH') $maxSeverity = 'MEDIUM';
                                }
                            @endphp
                            {{ $maxSeverity }}
                        @else
                            Secure
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
