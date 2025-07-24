<?php

namespace App\Services;

use React\Socket\Connector;
use React\EventLoop\Loop;
use React\Promise\Promise;

class IpScanService
{
    private array $commonPorts = [
        21 => 'FTP',
        22 => 'SSH',
        23 => 'Telnet',
        25 => 'SMTP',
        53 => 'DNS',
        80 => 'HTTP',
        110 => 'POP3',
        143 => 'IMAP',
        443 => 'HTTPS',
        993 => 'IMAPS',
        995 => 'POP3S',
        1433 => 'MSSQL',
        3306 => 'MySQL',
        3389 => 'RDP',
        5432 => 'PostgreSQL',
        5900 => 'VNC',
        6379 => 'Redis',
        27017 => 'MongoDB',
        161 => 'SNMP',
        162 => 'SNMP Trap',
        445 => 'SMB',
        8080 => 'HTTP Proxy',
        8443 => 'HTTPS Admin',
        1723 => 'PPTP VPN',
        5060 => 'SIP',
        5901 => 'VNC Alt',
        9200 => 'Elasticsearch',
        11211 => 'Memcached',
        8000 => 'Web Admin',
        8888 => 'Web Admin',
        10000 => 'Webmin',
        49152 => 'Windows RPC Dynamic',
        5000 => 'UPnP/Web Admin',
    ];

    private array $vulnerablePorts = [
        21 => 'FTP - Often misconfigured, anonymous access. Attackers can exploit weak or anonymous credentials to upload/download files, plant malware, or exfiltrate sensitive data.',
        22 => 'SSH - Brute force attacks possible. Weak passwords or default credentials allow attackers remote shell access, privilege escalation, and lateral movement.',
        23 => 'Telnet - Unencrypted, should be disabled. Credentials and commands are sent in plaintext, making interception and session hijacking trivial.',
        135 => 'RPC - Windows vulnerability vector. Used in DCOM and SMB exploits (e.g., MS03-026), enabling remote code execution and worm propagation.',
        139 => 'NetBIOS - File sharing vulnerabilities. Attackers can enumerate shares, access files, and exploit vulnerabilities for lateral movement.',
        445 => 'SMB - EternalBlue and other exploits. Vulnerable to ransomware (WannaCry), remote code execution, and unauthorized file access.',
        1433 => 'MSSQL - Database exposure. Weak credentials or misconfigurations allow attackers to steal, modify, or destroy data, and execute arbitrary commands.',
        5432 => 'PostgreSQL - Database exposure. Attackers can exploit weak authentication to access, modify, or exfiltrate database contents.',
        3306 => 'MySQL - Database exposure. Exposed MySQL allows brute force, data theft, and remote code execution via SQL injection or misconfigurations.',
        3389 => 'RDP - Brute force and BlueKeep. Exposed RDP is targeted for credential stuffing, ransomware, and remote desktop exploits (CVE-2019-0708).',
        5900 => 'VNC - Often unencrypted. Attackers can intercept sessions, brute force credentials, and gain remote control of desktops.',
        6379 => 'Redis - Default configuration is insecure. Unauthenticated access allows attackers to write to disk, escalate privileges, or pivot in the network.',
        27017 => 'MongoDB - Exposed database. No default authentication; attackers can read, modify, or delete data, and ransom database contents.',
        8080 => 'HTTP Proxy - Often used for admin panels, should be restricted. Attackers can access internal admin interfaces, proxy traffic, or exploit web vulnerabilities.',
        8443 => 'HTTPS Admin - Admin interfaces exposed. Attackers target weak authentication, outdated software, and misconfigurations for privilege escalation.',
        1723 => 'PPTP VPN - Weak encryption, deprecated. Susceptible to interception and brute force attacks due to obsolete cryptography.',
        5060 => 'SIP - VoIP service, often targeted. Attackers can eavesdrop, hijack calls, or launch denial-of-service attacks on VoIP infrastructure.',
        5901 => 'VNC Alt - Alternate VNC port, often unencrypted. Same risks as 5900, with attackers scanning alternate ports for remote access.',
        9200 => 'Elasticsearch - Exposed database, no auth by default. Attackers can read, modify, or delete data, and exploit for ransomware.',
        11211 => 'Memcached - Exposed cache, amplification attacks. Used in DDoS amplification, data theft, and remote code execution.',
        8000 => 'Web Admin/Dev - Should not be public. Attackers can access development/admin interfaces, exploit debug endpoints, or escalate privileges.',
        8888 => 'Web Admin/Dev - Should not be public. Same risks as 8000, with additional exposure to test/dev tools.',
        10000 => 'Webmin - Admin interface, should be restricted. Attackers exploit weak authentication and outdated software for full system control.',
        49152 => 'Windows RPC Dynamic - Lateral movement vector. Used for remote management, can be exploited for privilege escalation and worm propagation.',
        5000 => 'UPnP/Web Admin - Device exposure. Attackers can reconfigure devices, open ports, and pivot into internal networks.',
    ];  

    public function pingHost(string $ip): array
    {
        $start = microtime(true);
        
        // On Windows, use ping command
        if (PHP_OS_FAMILY === 'Windows') {
            $command = "ping -n 1 -w 1000 {$ip}";
        } else {
            $command = "ping -c 1 -W 1 {$ip}";
        }
        
        exec($command, $output, $returnCode);
        $end = microtime(true);
        
        $pingTime = round(($end - $start) * 1000);
        $isOnline = $returnCode === 0;
        
        return [
            'is_online' => $isOnline,
            'ping_time' => $isOnline ? $pingTime : null,
            'details' => implode("\n", $output)
        ];
    }

    public function scanPorts(string $ip, array $ports = null): array
    {
        $ports = $ports ?? array_keys($this->commonPorts);
        $openPorts = [];
        $vulnerablePorts = [];
        
        $loop = Loop::get();
        $connector = new Connector($loop, ['timeout' => 3]);
        
        foreach ($ports as $port) {
            try {
                $promise = $connector->connect("{$ip}:{$port}");
                $promise->then(
                    function ($connection) use ($port, &$openPorts, &$vulnerablePorts) {
                        $openPorts[$port] = $this->commonPorts[$port] ?? 'Unknown Service';
                        
                        if (isset($this->vulnerablePorts[$port])) {
                            $vulnerablePorts[$port] = $this->vulnerablePorts[$port];
                        }
                        
                        $connection->close();
                    },
                    function ($error) {
                        // Port is closed or filtered
                    }
                );
                
                // Give a small timeout for each connection attempt
                $loop->addTimer(0.1, function() {});
                $loop->run();
            } catch (\Exception $e) {
                // Continue to next port
                continue;
            }
        }
        
        return [
            'open_ports' => $openPorts,
            'vulnerable_ports' => $vulnerablePorts
        ];
    }

    public function performFullScan(string $ip): array
    {
        // First ping the host
        $pingResult = $this->pingHost($ip);
        
        $result = [
            'ip_address' => $ip,
            'is_online' => $pingResult['is_online'],
            'ping_time' => $pingResult['ping_time'],
            'open_ports' => [],
            'vulnerable_ports' => [],
            'scan_details' => $pingResult['details']
        ];
        
        // Only scan ports if host is online
        if ($pingResult['is_online']) {
            $portScanResult = $this->scanPorts($ip);
            $result['open_ports'] = $portScanResult['open_ports'];
            $result['vulnerable_ports'] = $portScanResult['vulnerable_ports'];
        }
        
        return $result;
    }

    public static function validateIpAddress(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    public function getVulnerabilityReport(array $vulnerablePorts): array
    {
        $report = [];
        
        foreach ($vulnerablePorts as $port => $description) {
            $report[] = [
                'port' => $port,
                'service' => $this->commonPorts[$port] ?? 'Unknown',
                'vulnerability' => $description,
                'severity' => $this->getPortSeverity($port),
                'recommendation' => $this->getPortRecommendation($port)
            ];
        }
        
        return $report;
    }

    private function getPortSeverity(int $port): string
    {
        $highRisk = [
            23,    // Telnet
            135,   // RPC
            139,   // NetBIOS
            445,   // SMB
            3389,  // RDP
            6379,  // Redis
            27017, // MongoDB
            8080,  // HTTP Proxy/Admin
            8443,  // HTTPS Admin
            8000,  // Web Admin/Dev
            8888,  // Web Admin/Dev
            10000, // Webmin
            49152, // Windows RPC Dynamic
            5000,  // UPnP/Web Admin
            5900,  // VNC
            5901,  // VNC Alt
            11211, // Memcached
            9200,  // Elasticsearch
        ];
        $mediumRisk = [
            21,    // FTP
            22,    // SSH
            1433,  // MSSQL
            3306,  // MySQL
            5432,  // PostgreSQL
            5060,  // SIP
            1723,  // PPTP VPN
            110,   // POP3
            143,   // IMAP
            993,   // IMAPS
            995,   // POP3S
            161,   // SNMP
            162,   // SNMP Trap
        ];
        $lowRisk = [
            25,    // SMTP
            53,    // DNS
            80,    // HTTP
            443,   // HTTPS
        ];

        if (in_array($port, $highRisk)) {
            return 'HIGH';
        } elseif (in_array($port, $mediumRisk)) {
            return 'MEDIUM';
        } elseif (in_array($port, $lowRisk)) {
            return 'LOW';
        }
        return 'INFO';
    }

    private function getPortRecommendation(int $port): string
    {
        $recommendations = [
            21 => 'Disable anonymous FTP access, use SFTP instead',
            22 => 'Use key-based authentication, disable password auth',
            23 => 'Disable Telnet service, use SSH instead',
            25 => 'Restrict SMTP relay, enable authentication, use TLS',
            53 => 'Restrict DNS zone transfers, patch DNS server',
            80 => 'Keep web server updated, use HTTPS, restrict admin panels',
            110 => 'Disable POP3 if not required, use secure alternatives',
            143 => 'Disable IMAP if not required, use secure alternatives',
            443 => 'Enforce strong TLS, disable weak ciphers, keep certificates updated',
            993 => 'Use strong authentication for IMAPS, keep server patched',
            995 => 'Use strong authentication for POP3S, keep server patched',
            1433 => 'Never expose MSSQL to the public internet. Restrict access to internal networks only, use strong authentication, and firewall this port.',
            3306 => 'Never expose MySQL to the public internet. Restrict access to internal networks only, use strong authentication, and firewall this port.',
            3389 => 'Disable public RDP access, enable NLA, use VPN, change default port',
            5432 => 'Never expose PostgreSQL to the public internet. Restrict access to internal networks only, use strong authentication, and firewall this port.',
            5900 => 'Use VNC with strong encryption and authentication, restrict access',
            6379 => 'Never expose Redis to the public internet. Bind to localhost, require authentication, and firewall this port.',
            27017 => 'Never expose MongoDB to the public internet. Bind to localhost, require authentication, and firewall this port.',
            161 => 'Secure SNMP with strong community strings, use SNMPv3, restrict access',
            162 => 'Secure SNMP Trap with strong community strings, use SNMPv3, restrict access',
            445 => 'Apply latest SMB patches, disable SMBv1, restrict file sharing',
            8080 => 'Restrict HTTP proxy/admin access, require authentication, patch software',
            8443 => 'Restrict HTTPS admin interfaces, use strong authentication, patch software',
            1723 => 'Disable PPTP VPN, use modern VPN protocols (OpenVPN, WireGuard)',
            5060 => 'Restrict SIP access, use strong authentication, patch VoIP software',
            5901 => 'Restrict alternate VNC ports, use encryption, patch software',
            9200 => 'Secure Elasticsearch with authentication, restrict network access, patch software',
            11211 => 'Never expose Memcached to the public internet. Bind to localhost, disable UDP, and firewall this port.',
            8000 => 'Never expose web admin/dev ports to the public internet. Disable in production, require authentication, and firewall this port.',
            8888 => 'Never expose web admin/dev ports to the public internet. Disable in production, require authentication, and firewall this port.',
            10000 => 'Never expose Webmin to the public internet. Restrict access to internal networks, use strong authentication, and firewall this port.',
            49152 => 'Never expose Windows RPC dynamic ports to the public internet. Restrict access, patch OS, and block at firewall.',
            5000 => 'Never expose UPnP/web admin ports to the public internet. Disable if not needed, patch software, and firewall this port.',
            135 => 'Never expose RPC to the public internet. Block at firewall, patch Windows systems, and restrict to internal networks only.',
            139 => 'Never expose NetBIOS to the public internet. Disable if not required, block at firewall, and restrict file sharing.',
        ];

        return $recommendations[$port] ?? 'Review service configuration and apply security best practices';
    }
}
