# AdBeam Recycling Platform

A comprehensive web-based recycling rewards platform that encourages sustainable practices through gamification. Users can scan recyclable items, earn points, redeem rewards, and track their environmental impact.

## 🌟 Features

- **User Authentication**: Secure login/registration system with session management
- **QR/Barcode Scanning**: Scan recyclable items to earn points
- **Rewards System**: Redeem points for various rewards and incentives
- **Admin Dashboard**: Complete administrative interface for managing users, rewards, and system analytics
- **Leaderboard**: Competitive ranking system to encourage participation
- **Environmental Impact Tracking**: Monitor CO2 savings and environmental contributions
- **Responsive Design**: Mobile-friendly interface built with Bootstrap
- **Real-time Analytics**: Dashboard with charts and statistics

## 🛠️ Technology Stack

- **Frontend**: HTML5, CSS3, JavaScript (ES6+), Bootstrap 5
- **Backend**: PHP 7.4+
- **Database**: MySQL/MariaDB
- **Charts**: Chart.js for data visualization
- **Icons**: Font Awesome, Bootstrap Icons
- **Scanner**: Custom barcode/QR code scanning implementation

## 📋 Prerequisites

Before installing this project, ensure you have:

- **XAMPP** (recommended) or similar local server environment
  - Apache Web Server
  - MySQL Database
  - PHP 7.4 or higher
- **Web Browser** (Chrome, Firefox, Safari, Edge)
- **Camera-enabled device** for scanning functionality

## 🚀 Installation Guide

### Step 1: Download and Setup XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)
2. Install XAMPP following the installation wizard
3. Start Apache and MySQL services from the XAMPP Control Panel

### Step 2: Download the Project

1. Download the project ZIP file
2. Extract the contents to your XAMPP `htdocs` directory:
   \`\`\`
   C:\xampp\htdocs\adbeam-recycling\
   \`\`\`
   (On Mac/Linux: `/Applications/XAMPP/htdocs/adbeam-recycling/`)

### Step 3: Database Setup

1. Open your web browser and navigate to `http://localhost/phpmyadmin`
2. Create a new database named `adbeam_enhanced`
3. Run the database setup script by visiting:
   \`\`\`
   http://localhost/adbeam-recycling/setup_database.php
   \`\`\`
4. The script will automatically create all necessary tables and a default admin user

### Step 4: Configuration

1. Open `includes/db_connect.php` and verify the database settings:
   \`\`\`php
   $host = 'localhost';
   $dbname = 'adbeam_enhanced';
   $username = 'root';
   $password = '';
   \`\`\`

2. Ensure the database connection is working by visiting:
   \`\`\`
   http://localhost/adbeam-recycling/test-db.php
   \`\`\`

## 🎯 Usage Guide

### For Regular Users

1. **Access the Platform**
   - Navigate to `http://localhost/adbeam-recycling/`
   - You'll see the main login/registration page

2. **Create an Account**
   - Click "Sign up" to create a new account
   - Fill in your details (email, name, student ID optional)
   - Verify your email and complete registration

3. **Login**
   - Use your email and password to log in
   - You'll be redirected to your personal dashboard

4. **Dashboard Features**
   - View your points balance and statistics
   - See recent recycling activity
   - Check your environmental impact
   - Browse available rewards

5. **Scanning Items**
   - Click "Quick Scan" or navigate to the scanner
   - Allow camera permissions
   - Scan barcodes/QR codes on recyclable items
   - Earn points for each successful scan

6. **Redeem Rewards**
   - Browse the rewards catalog
   - Select rewards you can afford with your points
   - Redeem and receive confirmation codes

7. **Track Progress**
   - View leaderboard rankings
   - Monitor your environmental impact
   - Check recycling statistics and achievements

### For Administrators

1. **Admin Access**
   - Use the default admin credentials created during setup:
     - Email: `admin@recycling.local`
     - Password: `admin2024` (or `admin` + current year)

2. **Admin Dashboard**
   - Access comprehensive system analytics
   - View user statistics and activity
   - Monitor system performance

3. **User Management**
   - View all registered users
   - Manage user accounts and permissions
   - Track user activity and points

4. **Rewards Management**
   - Add new rewards to the system
   - Edit existing reward details
   - Manage reward inventory and availability
   - Set point costs and categories

5. **System Analytics**
   - View platform usage statistics
   - Monitor recycling trends
   - Generate reports on environmental impact

## 📁 Project Structure

\`\`\`
adbeam-recycling/
├── api/                          # Backend API endpoints
│   ├── admin/                    # Admin-specific APIs
│   ├── auth/                     # Authentication APIs
│   ├── recycling/                # Scanning and recycling APIs
│   ├── rewards/                  # Rewards management APIs
│   └── user/                     # User data APIs
├── assets/                       # Frontend assets
│   ├── css/                      # Stylesheets
│   ├── js/                       # JavaScript files
│   ├── images/                   # Image assets
│   └── *.html                    # HTML pages
├── includes/                     # PHP includes and utilities
│   ├── db_connect.php           # Database connection
│   ├── auth.php                 # Authentication functions
│   └── *.php                    # Other utility files
├── components/                   # Reusable UI components
├── index.html                   # Main entry point
├── setup_database.php           # Database setup script
└── README.md                    # This file
\`\`\`

## 🔧 Configuration Options

### Database Configuration
Edit `includes/db_connect.php` to modify database settings:
\`\`\`php
$host = 'localhost';        // Database host
$dbname = 'adbeam_enhanced'; // Database name
$username = 'root';         // Database username
$password = '';             // Database password
\`\`\`

### Points System
Modify point values in the scanning API (`api/recycling/scan.php`):
- Plastic items: 10 points
- Glass items: 15 points
- Aluminum cans: 20 points
- Paper products: 5 points

### Reward Categories
Add new reward categories in the admin dashboard or directly in the database.

## 🐛 Troubleshooting

### Common Issues

1. **Database Connection Failed**
   - Ensure MySQL is running in XAMPP
   - Check database credentials in `includes/db_connect.php`
   - Verify the database exists

2. **Permission Denied Errors**
   - Ensure proper file permissions on the project directory
   - Check that Apache has read/write access

3. **Scanner Not Working**
   - Ensure HTTPS is enabled for camera access (use `https://localhost/`)
   - Check browser permissions for camera access
   - Verify the device has a working camera

4. **Session Issues**
   - Clear browser cookies and cache
   - Restart Apache server
   - Check PHP session configuration

5. **Admin Access Issues**
   - Run the setup script again: `setup_database.php`
   - Check if admin user exists in the database
   - Verify admin permissions in `admin_users` table

### Debug Mode
Enable debug mode by adding `?debug=1` to any URL or by logging in as user ID 1.

## 🔒 Security Features

- Password hashing using PHP's `password_hash()`
- CSRF protection for forms
- SQL injection prevention with prepared statements
- Session security with regeneration
- Input validation and sanitization
- Admin access control and logging

## 🌱 Environmental Impact

The platform tracks and displays:
- Total CO2 saved through recycling
- Number of items recycled
- Environmental impact by material type
- Community-wide sustainability metrics

## 📱 Mobile Compatibility

The platform is fully responsive and works on:
- Desktop computers
- Tablets
- Smartphones
- Mobile browsers with camera access

## 🤝 Contributing

To contribute to this project:
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📄 License

This project is open source and available under the MIT License.

## 📞 Support

For technical support or questions:
- Check the troubleshooting section above
- Review the code comments for implementation details
- Test with the provided debug tools

## 🚀 Future Enhancements

Planned features for future versions:
- Mobile app development
- Integration with external recycling databases
- Advanced analytics and reporting
- Social sharing features
- Multi-language support
- API for third-party integrations

---

**Happy Recycling! 🌍♻️**
