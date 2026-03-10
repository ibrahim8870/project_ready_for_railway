# Cafe Pro - Inventory Management System

A comprehensive inventory management system for cafes with features for tracking items, managing expiry dates, and automated alerts.

## Features

- User authentication and management
- Item inventory tracking
- Expiry date monitoring
- Automated email alerts
- AI-powered chat support
- Barcode scanning
- Secondary shelf management
- Weekly inventory checks

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache with mod_rewrite enabled
- Composer (for dependency management)

## Installation

### Local Development

1. Clone the repository:
```bash
git clone <repository-url>
cd project_final_for_railway
```

2. Install dependencies:
```bash
composer install
```

3. Configure the database:
   - Update `config.php` with your database credentials
   - Import the SQL files from `assets/` directory

4. Start the development server:
```bash
php -S localhost:8000
```

### Railway Deployment

1. Connect your GitHub repository to Railway
2. Railway will automatically detect the PHP application
3. Configure environment variables:
   - `MYSQLHOST` - Database host
   - `MYSQLUSER` - Database user
   - `MYSQLPASSWORD` - Database password
   - `MYSQLDATABASE` - Database name
   - `MYSQLPORT` - Database port (default: 3306)

4. Deploy automatically on push

## Environment Variables

```
MYSQLHOST=your_host
MYSQLUSER=your_user
MYSQLPASSWORD=your_password
MYSQLDATABASE=cafe_pro_db
MYSQLPORT=3306
```

## Database Setup

Import the following SQL files in order:

1. `assets/cafe_pro_db-1.sql` - Main database schema
2. `assets/link_primary_secondary.sql` - Link primary and secondary items
3. `assets/secondary_shelf_items.sql` - Secondary shelf items

## File Structure

```
project_final_for_railway/
├── index.php                 # Entry point
├── login.php                 # Login page
├── dashboard.php             # Main dashboard
├── config.php                # Database configuration
├── Dockerfile                # Docker configuration
├── Procfile                  # Railway process definition
├── composer.json             # PHP dependencies
├── assets/
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   └── *.sql                 # Database schemas
└── README.md                 # This file
```

## Troubleshooting

### Database Connection Issues
- Verify environment variables are set correctly
- Check database credentials in Railway dashboard
- Ensure database is accessible from the application

### Build Failures
- Check Railway build logs for specific errors
- Ensure all required PHP extensions are available
- Verify Dockerfile is properly configured

## Support

For issues or questions, please contact the development team.

## License

All rights reserved.
