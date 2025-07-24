# Laravel IP Validator & Port Scanner


This Laravel application allows you to upload files (TXT, CSV, Excel) containing IP addresses, validates them, checks their online status, and performs basic port scanning. Results are displayed in a dashboard with real-time progress and reporting.

## Key Features
- **File Upload Support**: Upload text, CSV, or Excel files with IP addresses
- **IP Validation**: Ensures only valid IP addresses are processed
- **ICMP Ping Testing**: Checks if hosts are online
- **Port Scanning**: Scans a set of common ports for each IP
- **Batch Processing**: Uses Laravel jobs for background scanning
- **Real-time Progress**: Dashboard auto-refreshes progress for ongoing scans
- **Reporting**: View scan results and export as CSV


## Architecture
- **Models**: `IpScan`, `UploadBatch` for data management
- **Jobs**: `ProcessIpScan` for background scanning
- **Services**: `IpScanService` for core scanning logic
- **Controllers**: Handles upload, dashboard, and reporting
- **Views**: Bootstrap-based responsive UI


## Dependencies
- **maatwebsite/excel**: For Excel/CSV file processing
- **Bootstrap 5**: For responsive UI


## Code Standards
- Follows Laravel conventions and PSR-12 standards
- Uses validation and error handling
- Implements queue-based processing for scalability

---


## Future Features (Planned)

- **Pentesting Tools**: Advanced pentesting features (service & banner grabbing, vulnerability database integration, web/SSL analysis, GeoIP/ASN lookup, screenshot/web content preview, etc.)
- **API Access**: Export results to JSON/PDF and provide API endpoints
- **Custom Port Range Scanning**
- **IPv6 Support**
- **Scheduled Scanning**

These features are planned for future releases. The current focus is on reliable file upload, IP validation, port scanning, and reporting.


## 🚀 Features

### File Upload Support
- **Text Files (.txt)**: One IP address per line
- **CSV Files (.csv)**: IP addresses in columns named "ip", "ip_address", "address", or "host"
- **Excel Files (.xlsx, .xls)**: Same column naming convention as CSV
- **File Size Limit**: Up to 10MB per upload


### Security Scanning
- **ICMP Ping Test**: Verifies if hosts are online
- **Port Scanning**: Scans a set of common service ports

### Real-time Progress Tracking
- **Background Processing**: Uses Laravel queues for scalable scanning
- **Live Updates**: Dashboard auto-refreshes progress
- **Batch Management**: Track multiple scan batches

### Reporting
- **Dashboard View**: Overview of all scan batches
- **Detailed Results**: Per-IP scanning results
- **CSV Export**: Download scan results

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
- Access scan results once scanning is complete
- Export results as CSV for further analysis

## 🏗️ Architecture

### Models
- **`UploadBatch`**: Manages file upload batches and overall statistics
- **`IpScan`**: Stores individual IP scan results and vulnerability data

### Jobs
- **`ProcessIpScan`**: Background job for scanning individual IP addresses

### Services
- **`IpScanService`**: Core scanning logic (ping and port scanning)

### Controllers
- Handles upload, dashboard, and reporting

## 🔧 Configuration

### Queue Configuration
For production environments, configure a proper queue driver in `.env`:
```env
QUEUE_CONNECTION=redis
```


### Scanning Timeouts
Modify scanning timeouts in `IpScanService` as needed.


## 📊 Security Considerations

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
