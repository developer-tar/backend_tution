# Laravel Deployment Guide - Hostinger VPS

## Server Details
- **SSH Login**: `ssh root@72.61.144.140`
- **Password**: Keshnika2011?
- **Repository**: https://github.com/developer-tar/backend_tution.git
- **Branch**: local_tarun

**Note**: The SSH IP might be different from the public web IP. Check actual IP after connecting.

## Step-by-Step Deployment

### Step 1: Connect to Server and Check IP
```bash
ssh root@72.61.144.140
# Enter password: Keshnika2011?

# Check your server's actual public IP
PUBLIC_IP=$(curl -s ifconfig.me)
echo "Your server's public IP is: $PUBLIC_IP"

# Note this IP - you'll use it in configurations
```

### Step 2: Update System and Install Required Software
```bash
# Update system
apt update && apt upgrade -y

# Install essential packages
apt install -y curl wget git unzip software-properties-common

# Add PHP repository
add-apt-repository ppa:ondrej/php -y
apt update

# Install PHP 8.2 and extensions
apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-xml php8.2-gd php8.2-curl php8.2-zip php8.2-mbstring php8.2-bcmath php8.2-intl php8.2-redis php8.2-cli

# Install Nginx
apt install -y nginx

# Install MySQL
apt install -y mysql-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer

# Install Node.js (if needed for frontend)
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt install -y nodejs
```

### Step 3: Configure MySQL Database
```bash
# Secure MySQL installation
mysql_secure_installation

# Login to MySQL
mysql -u root -p

# Create database and user
CREATE DATABASE tution_backend;
CREATE USER 'tution_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON tution_backend.* TO 'tution_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 4: Clone and Setup Project
```bash
# Navigate to web directory
cd /var/www

# Clone repository
git clone -b local_tarun https://github.com/developer-tar/backend_tution.git
mv backend_tution tution-backend
cd tution-backend

# Set proper permissions
chown -R www-data:www-data /var/www/tution-backend
chmod -R 755 /var/www/tution-backend
chmod -R 775 /var/www/tution-backend/storage
chmod -R 775 /var/www/tution-backend/bootstrap/cache

# Install PHP dependencies
composer install --optimize-autoloader --no-dev

# Copy environment file
cp .env.example .env
```

### Step 5: Configure Environment (.env)
```bash
# Edit .env file
nano .env
```

**Add these configurations to .env:**
```env
APP_NAME="Tution Backend"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://72.61.144.140

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tution_backend
DB_USERNAME=tution_user
DB_PASSWORD=your_strong_password

# Cloudflare R2 Configuration
CLOUDFLARE_R2_ACCESS_KEY_ID=0ed8d789a9754db6dfcdb21dfcb830fe
CLOUDFLARE_R2_SECRET_ACCESS_KEY=67246bfea6dca933015556fe0ff2abfcb95083ef71f920b8e0d37fee2babdfc8
CLOUDFLARE_R2_REGION=auto
CLOUDFLARE_R2_BUCKET=your-bucket-name
CLOUDFLARE_R2_ENDPOINT=https://5cdf0983ec481cd7db01e38d022b7790.r2.cloudflarestorage.com
MEDIA_DISK=r2

# Queue Configuration
QUEUE_CONNECTION=database

# Cache Configuration
CACHE_STORE=database

# Session Configuration
SESSION_DRIVER=database
SESSION_LIFETIME=43200

# Mail Configuration (configure as needed)
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-email
MAIL_PASSWORD=your-email-password
MAIL_ENCRYPTION=tls

# Stripe Configuration
STRIPE_KEY=pk_test_51RrEFZPBXobf6dxwTZVQSWHkX98T1NHdzInmjT7XX8txPyp2wMEwIBmBk8VNFr6m4bZd7nSMiH2mkKFdhjq3phKL00F1xm10lM
STRIPE_SECRET=sk_test_51RrEFZPBXobf6dxwdjRjXCHBGGcyMM5JvqdUH6l1MYZy5VUTRlpTlrRb0heiJI3PpTD5ImfAfa4u70sPb8bFnscO00LdIM35CN
STRIPE_WEBHOOK_SECRET=whsec_jcBgMPU6GA1e9Tv6cKZvIM6tvzOmBkwH
```

### Step 6: Generate Application Key and Setup Database
```bash
# Generate application key
php artisan key:generate

# Clear config cache
php artisan config:clear

# Run database migrations
php artisan migrate

# Create storage link
php artisan storage:link

# Install Passport (for API authentication)
php artisan passport:install

# Cache configuration for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### Step 7: Configure Nginx
```bash
# Create Nginx configuration
nano /etc/nginx/sites-available/tution-backend
```

**Add this Nginx configuration:**
```nginx
server {
    listen 80;
    server_name 72.61.144.140;
    root /var/www/tution-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Increase upload size for course images
    client_max_body_size 100M;
}
```

### Step 8: Enable Site and Restart Services
```bash
# Enable the site
ln -s /etc/nginx/sites-available/tution-backend /etc/nginx/sites-enabled/

# Remove default site
rm /etc/nginx/sites-enabled/default

# Test Nginx configuration
nginx -t

# Restart services
systemctl restart nginx
systemctl restart php8.2-fpm

# Enable services to start on boot
systemctl enable nginx
systemctl enable php8.2-fpm
systemctl enable mysql
```

### Step 9: Setup Queue Worker (Optional but Recommended)
```bash
# Create supervisor configuration for queue worker
nano /etc/supervisor/conf.d/laravel-worker.conf
```

**Add this configuration:**
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/tution-backend/artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/www/tution-backend/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
# Install and start supervisor
apt install -y supervisor
supervisorctl reread
supervisorctl update
supervisorctl start laravel-worker:*
```

### Step 10: Setup SSL with Let's Encrypt (Recommended)
```bash
# Install Certbot
apt install -y certbot python3-certbot-nginx

# Get SSL certificate (replace with your domain)
certbot --nginx -d your-domain.com -d www.your-domain.com
```

### Step 11: Setup Firewall
```bash
# Configure UFW firewall
ufw allow OpenSSH
ufw allow 'Nginx Full'
ufw --force enable
```

### Step 12: Final Checks and Testing
```bash
# Check if all services are running
systemctl status nginx
systemctl status php8.2-fpm
systemctl status mysql

# Test the application
curl -I http://72.61.144.140

# Check logs if there are issues
tail -f /var/log/nginx/error.log
tail -f /var/www/tution-backend/storage/logs/laravel.log
```

## Important Notes

1. **Replace placeholders:**
   - `your-domain.com` with your actual domain
   - `your_strong_password` with a secure database password
   - `your-bucket-name` with your actual R2 bucket name

2. **Security:**
   - Change default SSH port
   - Setup fail2ban for additional security
   - Regular backups

3. **Monitoring:**
   - Setup log rotation
   - Monitor disk space
   - Setup uptime monitoring

## Troubleshooting

### Common Issues:
1. **Permission errors**: Check file permissions and ownership
2. **Database connection**: Verify database credentials
3. **Nginx 502 errors**: Check PHP-FPM is running
4. **Storage issues**: Ensure storage directory is writable

### Useful Commands:
```bash
# Check application status
php artisan about

# Clear all caches
php artisan optimize:clear

# Check queue status
php artisan queue:work --once

# Monitor logs
tail -f storage/logs/laravel.log
```

## Backup Strategy
```bash
# Database backup
mysqldump -u tution_user -p tution_backend > backup_$(date +%Y%m%d).sql

# File backup
tar -czf backup_files_$(date +%Y%m%d).tar.gz /var/www/tution-backend
```
