<!-- Use this file to provide workspace-specific custom instructions to Copilot. For more details, visit https://code.visualstudio.com/docs/copilot/copilot-customization#_use-a-githubcopilotinstructionsmd-file -->

# Kings_Recon

This Laravel application accepts uploaded files (TXT, CSV, Excel) containing IP addresses and performs comprehensive security scanning including:

## Key Features
- **File Upload Support**: Text files, CSV, and Excel files with IP addresses
- **ICMP Ping Testing**: Checks if hosts are online with response time measurement
- **Port Scanning**: Scans common service ports (21, 22, 23, 25, 53, 80, 443, 3389, etc.)
- **Vulnerability Assessment**: Identifies potentially vulnerable services and ports
- **Detailed Reporting**: Generates comprehensive security reports with recommendations
- **Background Processing**: Uses Laravel jobs for async scanning
- **Real-time Progress**: Ajax-based progress tracking

## Architecture
- **Models**: `IpScan`, `UploadBatch` for data management
- **Jobs**: `ProcessIpScan` for background scanning
- **Services**: `IpScanService` for core scanning functionality
- **Controllers**: `IpValidatorController` for web interface
- **Views**: Bootstrap-based responsive UI with real-time updates

## Security Scanning Details
- **High Risk Ports**: 23 (Telnet), 135 (RPC), 139 (NetBIOS), 445 (SMB), 3389 (RDP)
- **Medium Risk Ports**: 21 (FTP), 22 (SSH), 1433 (MSSQL), 5900 (VNC)
- **Common Ports**: 25 (SMTP), 53 (DNS), 80 (HTTP), 443 (HTTPS), 3306 (MySQL)

## Dependencies
- **maatwebsite/excel**: For Excel/CSV file processing
- **react/socket**: For port scanning functionality
- **Bootstrap 5**: For responsive UI
- **Chart.js**: For vulnerability reporting charts

## Code Standards
- Follow Laravel conventions and PSR-12 standards
- Use proper validation and error handling
- Implement queue-based processing for scalability
- Maintain security best practices for network scanning
