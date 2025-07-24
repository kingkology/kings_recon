<?php

namespace App\Jobs;

use App\Models\PentestSession;
use App\Models\PentestResult;
use App\Models\DiscoveredCredential;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BruteForceJob implements ShouldQueue
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

            Log::info("Starting brute force attack for {$this->targetIp}");

            try {
                $services = $this->detectServices();
            } catch (\Exception $e) {
                $session->update([
                    'status' => 'failed',
                    'description' => 'detectServices failed: ' . $e->getMessage(),
                ]);
                throw $e;
            }

            $credentials = [];
            $vulnerabilities = [];

            foreach ($services as $service) {
                try {
                    $results = $this->bruteForceService($service);
                } catch (\Exception $e) {
                    $session->update([
                        'status' => 'failed',
                        'description' => 'bruteForceService failed: ' . $e->getMessage(),
                    ]);
                    throw $e;
                }
                $credentials = array_merge($credentials, $results['credentials']);
                $vulnerabilities = array_merge($vulnerabilities, $results['vulnerabilities']);
            }

            // Save discovered credentials
            foreach ($credentials as $cred) {
                try {
                    DiscoveredCredential::create([
                        'session_id' => $session->session_id,
                        'ip_address' => $this->targetIp,
                        'service' => $cred['service'],
                        'port' => $cred['port'] ?? 0,
                        'username' => $cred['username'],
                        'password' => $cred['password'],
                        'access_level' => $cred['access_level'] ?? 'user',
                        'verified' => true,
                        'discovered_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    $session->update([
                        'status' => 'failed',
                        'description' => 'DiscoveredCredential create failed: ' . $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            // Save vulnerabilities
            foreach ($vulnerabilities as $vuln) {
                try {
                    PentestResult::create([
                        'session_id' => $session->session_id,
                        'ip_address' => $this->targetIp,
                        'module_type' => 'brute_force',
                        'test_name' => $vuln['type'],
                        'status' => 'vulnerable',
                        'severity' => $vuln['severity'],
                        'description' => $vuln['type'] . ' vulnerability found',
                        'details' => $vuln['details'],
                        'tested_at' => now(),
                    ]);
                } catch (\Exception $e) {
                    $session->update([
                        'status' => 'failed',
                        'description' => 'PentestResult create failed: ' . $e->getMessage(),
                    ]);
                    throw $e;
                }
            }

            Log::info("Brute force attack completed for {$this->targetIp}. Found " . count($credentials) . " credentials.");

        } catch (\Exception $e) {
            $session = PentestSession::find($this->sessionId);
            if ($session) {
                $session->update([
                    'status' => 'failed',
                    'description' => 'Brute force job failed: ' . $e->getMessage(),
                ]);
            }
            Log::error("Brute force attack failed for {$this->targetIp}: " . $e->getMessage());
            throw $e;
        }
    }

    private function detectServices()
    {
        $services = [];
        $commonPorts = [
            21 => 'ftp',
            22 => 'ssh',
            23 => 'telnet',
            25 => 'smtp',
            53 => 'dns',
            80 => 'http',
            110 => 'pop3',
            135 => 'rpc',
            139 => 'netbios',
            143 => 'imap',
            443 => 'https',
            445 => 'smb',
            993 => 'imaps',
            995 => 'pop3s',
            1433 => 'mssql',
            3306 => 'mysql',
            3389 => 'rdp',
            5432 => 'postgresql',
            5900 => 'vnc',
        ];

        foreach ($commonPorts as $port => $service) {
            if ($this->isPortOpen($this->targetIp, $port)) {
                $services[] = [
                    'port' => $port,
                    'service' => $service,
                ];
            }
        }

        return $services;
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

    private function bruteForceService($service)
    {
        $credentials = [];
        $vulnerabilities = [];
        
        $commonCredentials = $this->getCommonCredentials();
        
        switch ($service['service']) {
            case 'ssh':
                $results = $this->bruteForceSSH($service['port'], $commonCredentials);
                break;
            case 'ftp':
                $results = $this->bruteForceFTP($service['port'], $commonCredentials);
                break;
            case 'telnet':
                $results = $this->bruteForceTelnet($service['port'], $commonCredentials);
                break;
            case 'rdp':
                $results = $this->bruteForceRDP($service['port'], $commonCredentials);
                break;
            case 'smb':
                $results = $this->bruteForceSMB($service['port'], $commonCredentials);
                break;
            case 'mysql':
                $results = $this->bruteForceMySQL($service['port'], $commonCredentials);
                break;
            case 'postgresql':
                $results = $this->bruteForcePostgreSQL($service['port'], $commonCredentials);
                break;
            default:
                $results = ['credentials' => [], 'vulnerabilities' => []];
        }
        
        return $results;
    }

    private function getCommonCredentials()
    {
        return [
            ['admin', 'admin'],
            ['admin', 'password'],
            ['admin', '123456'],
            ['admin', 'admin123'],
            ['administrator', 'administrator'],
            ['administrator', 'password'],
            ['root', 'root'],
            ['root', 'password'],
            ['root', 'toor'],
            ['root', '123456'],
            ['user', 'user'],
            ['user', 'password'],
            ['guest', 'guest'],
            ['guest', ''],
            ['test', 'test'],
            ['demo', 'demo'],
            ['service', 'service'],
            ['oracle', 'oracle'],
            ['postgres', 'postgres'],
            ['mysql', 'mysql'],
            ['ftp', 'ftp'],
            ['anonymous', 'anonymous'],
            ['anonymous', ''],
        ];
    }

    private function bruteForceSSH($port, $credentials)
    {
        $foundCredentials = [];
        $vulnerabilities = [];

        if (!function_exists('ssh2_connect')) {
            $session = PentestSession::find($this->sessionId);
            if ($session) {
                $session->update([
                    'status' => 'failed',
                    'description' => 'Brute force SSH failed: ssh2 extension not installed. Please install ext-ssh2.'
                ]);
            }
            Log::error("Brute force SSH failed for {$this->targetIp}: ssh2 extension not installed.");
            return ['credentials' => [], 'vulnerabilities' => []];
        }
        foreach ($credentials as $cred) {
            try {
                $connection = ssh2_connect($this->targetIp, $port);
                if ($connection && ssh2_auth_password($connection, $cred[0], $cred[1])) {
                    $foundCredentials[] = [
                        'service' => 'ssh',
                        'port' => $port,
                        'username' => $cred[0],
                        'password' => $cred[1],
                        'access_level' => $cred[0] === 'root' ? 'root' : ($cred[0] === 'admin' ? 'admin' : 'user')
                    ];
                    $vulnerabilities[] = [
                        'type' => 'Weak SSH Credentials',
                        'severity' => 'critical',
                        'details' => [
                            'service' => 'SSH',
                            'port' => $port,
                            'username' => $cred[0],
                            'password' => $cred[1],
                            'risk' => 'Allows remote system access'
                        ]
                    ];
                    break; // Stop after finding valid credentials
                }
            } catch (\Exception $e) {
                if ($e->getCode() !== 1) {
                    Log::error("SSH brute force failed for {$this->targetIp}: " . $e->getMessage());
                }
                // Continue trying other credentials
            }
        }

        return ['credentials' => $foundCredentials, 'vulnerabilities' => $vulnerabilities];
    }

    private function bruteForceFTP($port, $credentials)
    {
        $foundCredentials = [];
        $vulnerabilities = [];

        foreach ($credentials as $cred) {
            try {
                $connection = ftp_connect($this->targetIp, $port, 10);
                if ($connection && ftp_login($connection, $cred[0], $cred[1])) {
                    $foundCredentials[] = [
                        'service' => 'ftp',
                        'port' => $port,
                        'username' => $cred[0],
                        'password' => $cred[1],
                        'access_level' => $cred[0] === 'admin' ? 'admin' : 'user'
                    ];
                    
                    $vulnerabilities[] = [
                        'type' => 'Weak FTP Credentials',
                        'severity' => 'high',
                        'details' => [
                            'service' => 'FTP',
                            'port' => $port,
                            'username' => $cred[0],
                            'password' => $cred[1],
                            'risk' => 'Allows file system access'
                        ]
                    ];
                    ftp_close($connection);
                    break;
                }
                if ($connection) ftp_close($connection);
            } catch (\Exception $e) {
                // Continue trying other credentials
            }
        }

        return ['credentials' => $foundCredentials, 'vulnerabilities' => $vulnerabilities];
    }

    private function bruteForceTelnet($port, $credentials)
    {
        $foundCredentials = [];
        $vulnerabilities = [];

        foreach ($credentials as $cred) {
            try {
                $socket = fsockopen($this->targetIp, $port, $errno, $errstr, 10);
                if ($socket) {
                    // Simple telnet credential test
                    fwrite($socket, $cred[0] . "\r\n");
                    usleep(500000); // 0.5 second delay
                    fwrite($socket, $cred[1] . "\r\n");
                    usleep(500000);
                    
                    $response = fread($socket, 1024);
                    if (stripos($response, 'welcome') !== false || stripos($response, 'logged') !== false) {
                        $foundCredentials[] = [
                            'service' => 'telnet',
                            'port' => $port,
                            'username' => $cred[0],
                            'password' => $cred[1],
                            'access_level' => $cred[0] === 'root' ? 'root' : ($cred[0] === 'admin' ? 'admin' : 'user')
                        ];
                        
                        $vulnerabilities[] = [
                            'type' => 'Weak Telnet Credentials',
                            'severity' => 'critical',
                            'details' => [
                                'service' => 'Telnet',
                                'port' => $port,
                                'username' => $cred[0],
                                'password' => $cred[1],
                                'risk' => 'Allows unencrypted remote access'
                            ]
                        ];
                        fclose($socket);
                        break;
                    }
                    fclose($socket);
                }
            } catch (\Exception $e) {
                // Continue trying other credentials
            }
        }

        return ['credentials' => $foundCredentials, 'vulnerabilities' => $vulnerabilities];
    }

    private function bruteForceRDP($port, $credentials)
    {
        $vulnerabilities = [];
        
        // RDP brute force detection (cannot actually authenticate without specialized tools)
        $vulnerabilities[] = [
            'type' => 'RDP Service Detected',
            'severity' => 'medium',
            'details' => [
                'service' => 'RDP',
                'port' => $port,
                'recommendation' => 'Implement strong passwords and account lockout policies',
                'risk' => 'Vulnerable to brute force attacks'
            ]
        ];

        return ['credentials' => [], 'vulnerabilities' => $vulnerabilities];
    }

    private function bruteForceSMB($port, $credentials)
    {
        $vulnerabilities = [];
        
        // SMB service detection
        $vulnerabilities[] = [
            'type' => 'SMB Service Detected',
            'severity' => 'medium',
            'details' => [
                'service' => 'SMB',
                'port' => $port,
                'recommendation' => 'Ensure proper authentication and disable unnecessary shares',
                'risk' => 'Potential for unauthorized file access'
            ]
        ];

        return ['credentials' => [], 'vulnerabilities' => $vulnerabilities];
    }

    private function bruteForceMySQL($port, $credentials)
    {
        $foundCredentials = [];
        $vulnerabilities = [];

        foreach ($credentials as $cred) {
            try {
                $connection = @mysqli_connect($this->targetIp, $cred[0], $cred[1], '', $port);
                if ($connection) {
                    $foundCredentials[] = [
                        'service' => 'mysql',
                        'port' => $port,
                        'username' => $cred[0],
                        'password' => $cred[1],
                        'access_level' => $cred[0] === 'root' ? 'root' : 'user'
                    ];
                    
                    $vulnerabilities[] = [
                        'type' => 'Weak MySQL Credentials',
                        'severity' => 'critical',
                        'details' => [
                            'service' => 'MySQL',
                            'port' => $port,
                            'username' => $cred[0],
                            'password' => $cred[1],
                            'risk' => 'Allows database access and potential data theft'
                        ]
                    ];
                    mysqli_close($connection);
                    break;
                }
            } catch (\Exception $e) {
                // Continue trying other credentials
            }
        }

        return ['credentials' => $foundCredentials, 'vulnerabilities' => $vulnerabilities];
    }

    private function bruteForcePostgreSQL($port, $credentials)
    {
        $foundCredentials = [];
        $vulnerabilities = [];

        foreach ($credentials as $cred) {
            try {
                $connection = @pg_connect("host={$this->targetIp} port={$port} user={$cred[0]} password={$cred[1]} connect_timeout=5");
                if ($connection) {
                    $foundCredentials[] = [
                        'service' => 'postgresql',
                        'port' => $port,
                        'username' => $cred[0],
                        'password' => $cred[1],
                        'access_level' => $cred[0] === 'postgres' ? 'admin' : 'user'
                    ];
                    
                    $vulnerabilities[] = [
                        'type' => 'Weak PostgreSQL Credentials',
                        'severity' => 'critical',
                        'details' => [
                            'service' => 'PostgreSQL',
                            'port' => $port,
                            'username' => $cred[0],
                            'password' => $cred[1],
                            'risk' => 'Allows database access and potential data theft'
                        ]
                    ];
                    pg_close($connection);
                    break;
                }
            } catch (\Exception $e) {
                // Continue trying other credentials
            }
        }

        return ['credentials' => $foundCredentials, 'vulnerabilities' => $vulnerabilities];
    }
}
