# Laravel IP Validator & Port Scanner

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

---

## Future Updates (Planned Features)

- **Service & Banner Grabbing**: Detect service banners and software versions for open ports
- **Web & SSL Analysis**: Fetch HTTP/HTTPS headers, SSL certificate details, and web technologies
- **GeoIP & ASN Lookup**: Show location, ISP, and ASN for each IP
- **Vulnerability Database Integration**: Cross-reference detected services/versions with CVE/NVD
- **Screenshot & Web Content Preview**: Capture screenshots of web services
- **Export & API**: Export results to CSV/JSON/PDF and provide API access

These features are planned for future releases. Current focus is on bug-free operation and core scanning/reporting.
# IP Validator & Port Scanner

A comprehensive Laravel application for IP address validation and security scanning that accepts uploaded files containing IP addresses, performs ICMP ping tests, scans for open ports, and generates detailed vulnerability reports.

## 🚀 Features

### File Upload Support
- **Text Files (.txt)**: One IP address per line
- **CSV Files (.csv)**: IP addresses in columns named "ip", "ip_address", "address", or "host"
- **Excel Files (.xlsx, .xls)**: Same column naming convention as CSV
- **File Size Limit**: Up to 10MB per upload

### Security Scanning
- **ICMP Ping Test**: Verifies if hosts are online with response time measurement
- **Port Scanning**: Scans common service ports including:
  - Web Services: 80 (HTTP), 443 (HTTPS)
  - Remote Access: 22 (SSH), 23 (Telnet), 3389 (RDP)
  - File Sharing: 21 (FTP), 445 (SMB), 139 (NetBIOS)
  - Databases: 3306 (MySQL), 1433 (MSSQL), 5432 (PostgreSQL)
  - Other Services: 25 (SMTP), 53 (DNS), 5900 (VNC)

### Vulnerability Assessment
- **Risk Classification**: HIGH, MEDIUM, LOW risk levels
- **Service Identification**: Identifies services running on open ports
- **Security Recommendations**: Provides specific mitigation strategies
- **Vulnerability Reports**: Detailed analysis with visual charts

### Real-time Progress Tracking
- **Background Processing**: Uses Laravel queues for scalable scanning
- **Live Updates**: Ajax-based progress monitoring
- **Batch Management**: Track multiple scan batches simultaneously

### Comprehensive Reporting
- **Dashboard View**: Overview of all scan batches
- **Detailed Results**: Per-IP scanning results with filtering
- **Vulnerability Reports**: Executive summary with risk analysis
- **CSV Export**: Download complete scan results

## 🛠️ Installation

### Prerequisites
- PHP 8.1 or higher
- Composer
- SQLite (included) or MySQL/PostgreSQL

### Setup Instructions

1. **Clone the repository:**
```bash
git clone <repository-url>
cd public_ip_validator
```

2. **Install dependencies:**
```bash
composer install
```

3. **Environment configuration:**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Database setup:**
```bash
php artisan migrate
```

5. **Start the queue worker:**
```bash
php artisan queue:work
```

6. **Start the development server:**
```bash
php artisan serve
```

Visit `http://localhost:8000` to access the application.

## 📋 Usage

### 1. Upload IP List
- Navigate to the "Upload File" page
- Select a file containing IP addresses (TXT, CSV, or Excel)
- Click "Upload and Start Scanning"

### 2. Monitor Progress
- View real-time scanning progress on the dashboard
- Track individual IP scan results
- Filter results by online status or vulnerabilities

### 3. Generate Reports
- Access detailed vulnerability reports once scanning is complete
- View risk distribution and security recommendations
- Export results as CSV for further analysis

## 🏗️ Architecture

### Models
- **`UploadBatch`**: Manages file upload batches and overall statistics
- **`IpScan`**: Stores individual IP scan results and vulnerability data

### Jobs
- **`ProcessIpScan`**: Background job for scanning individual IP addresses

### Services
- **`IpScanService`**: Core scanning functionality including ping and port scanning

### Controllers
- **`IpValidatorController`**: Web interface for upload, viewing, and reporting

## 🔧 Configuration

### Queue Configuration
For production environments, configure a proper queue driver in `.env`:
```env
QUEUE_CONNECTION=redis
```

### Scanning Timeouts
Modify scanning timeouts in `IpScanService`:
- Ping timeout: 1 second
- Port scan timeout: 3 seconds per port
- Job timeout: 5 minutes

## 📊 Security Considerations

### Port Classifications
- **HIGH RISK**: Telnet (23), RPC (135), NetBIOS (139), SMB (445), RDP (3389)
- **MEDIUM RISK**: FTP (21), SSH (22), MSSQL (1433), VNC (5900)
- **LOW RISK**: HTTP/HTTPS (80/443), SMTP (25), DNS (53)

### Recommendations
- Use this tool only on networks you own or have permission to scan
- Be aware of network policies regarding port scanning
- Consider rate limiting for large IP ranges

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## 📝 License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## 🆘 Support

For issues and questions:
1. Check the existing issues on GitHub
2. Create a new issue with detailed information
3. Include steps to reproduce any bugs

## 📈 Roadmap

- [ ] IPv6 support
- [ ] Custom port range scanning
- [ ] Integration with vulnerability databases
- [ ] Scheduled scanning
- [ ] API endpoints for programmatic access
- [ ] Docker containerization

---

Built with ❤️ using Laravel, Bootstrap, and modern web technologies.

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
