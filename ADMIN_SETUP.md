# Admin Panel Setup Guide

## Installation Steps

### 1. Database Setup

Run the SQL script `database_schema.sql` in your MySQL database (phpMyAdmin or MySQL command line):

```sql
-- This will:
-- 1. Create the admins table (with id, email, password only)
-- 2. Add a default admin account (email: admin@learninindia.com, password: admin123)
-- 3. Add profile_image and certificate_path columns to users table
```

**Important:** Change the default admin password immediately after first login!

### 2. Default Admin Credentials

- **Email:** `<YPUR_EMAIL>`
- **Password:** `<YOUR_PASSWORD>`

⚠️ **Security Note:** Change this password immediately after first login!

### 3. File Permissions

Make sure the `uploads/` directory is writable by the web server:
- `uploads/profile_images/` - for profile images
- `uploads/certificates/` - for PDF certificates

On Linux/Mac:
```bash
chmod -R 755 uploads/
```

On Windows, ensure the web server has write permissions to the uploads folder.

### 4. Access Admin Panel

1. Navigate to `admin_login.php` in your browser
2. Login with the default credentials
3. You'll be redirected to `admin_dashboard.php`

## Features

### Admin Dashboard Features:

1. **View Total Interns** - Statistics showing total number of registered interns
2. **Add Intern** - Create new intern accounts
3. **Edit Intern** - Update intern information
4. **Delete Intern** - Remove intern accounts (also deletes associated files)
5. **Upload Profile Image** - Upload profile pictures for interns (JPEG, PNG, GIF - Max 5MB)
6. **Upload Certificate** - Upload PDF certificates for interns (Max 10MB)

### File Uploads:

- Profile images are stored in: `uploads/profile_images/`
- Certificates are stored in: `uploads/certificates/`
- Old files are automatically deleted when new ones are uploaded

## Security Notes

1. Change the default admin password immediately
2. Keep the admin login URL secure
3. Regularly backup your database
4. Ensure proper file permissions on upload directories
5. Consider adding IP restrictions for admin access in production

## Troubleshooting

### Database Connection Issues
- Verify database credentials in `admin_dashboard.php` and `admin_login.php`
- Ensure MySQL is running
- Check database name matches: `registration_db`

### File Upload Issues
- Check directory permissions on `uploads/` folder
- Verify PHP `upload_max_filesize` and `post_max_size` settings
- Check PHP error logs for specific errors

### Admin Login Not Working
- Verify the `admins` table exists and has the default admin record
- Check session configuration in PHP
- Clear browser cookies and try again

