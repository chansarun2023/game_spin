# API Deployment Troubleshooting Guide / មគ្គុទ្ទេសក៍ដោះស្រាយបញ្ហាការដំឡើង API

## Issues Found / បញ្ហាដែលបានរកឃើញ:

### 1. Database Schema Issue / បញ្ហានៃ Database Schema

**Error:** `SQLSTATE[3F000]: Invalid schema name: 7 ERROR: no schema has been selected to create in`

**Solution / ដំណោះស្រាយ:**

-   Fixed PostgreSQL configuration in `config/database.php`
-   Added proper schema configuration in `vercel.json`
-   Set `DB_SCHEMA` environment variable to "public"

### 2. Production Configuration / ការកំណត់រចនាសម្ព័ន្ធផលិតកម្ម

**Issues / បញ្ហា:**

-   `APP_DEBUG` was set to "true" in production
-   Missing proper database SSL configuration

**Solution / ដំណោះស្រាយ:**

-   Set `APP_DEBUG` to "false" in production
-   Added `DB_SSLMODE` environment variable

## Steps to Fix / ជំហានដើម្បីដោះស្រាយ:

### 1. Test Database Connection / ការធ្វើតេស្តការតភ្ជាប់ Database

```bash
php test-db-connection.php
```

### 2. Run Deployment Script / ដំណើរការស្គ្រីបដំឡើង

```bash
chmod +x deploy.sh
./deploy.sh
```

### 3. Manual Database Setup / ការរៀបចំ Database ដោយដៃ

```bash
# Clear caches
php artisan config:clear
php artisan cache:clear

# Run migrations
php artisan migrate --force

# Seed database
php artisan db:seed --force
```

## Environment Variables / អថេរបរិស្ថាន:

Make sure these are set in your Vercel environment / ត្រូវប្រាកដថាទាំងនេះត្រូវបានកំណត់ក្នុងបរិស្ថាន Vercel:

```env
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=pgsql
DB_URL=your_database_url
DB_SCHEMA=public
DB_SSLMODE=require
```

## Common Issues / បញ្ហាទូទៅ:

### 1. Database Connection Timeout / ការតភ្ជាប់ Database ផុតកំណត់ពេល

-   Check if your database URL is correct
-   Verify SSL settings
-   Ensure database is accessible from Vercel

### 2. Migration Errors / កំហុសការផ្លាស់ទី

-   Run `php artisan migrate:status` to check migration status
-   Use `php artisan migrate:rollback` if needed
-   Check database permissions

### 3. Cache Issues / បញ្ហាការចងចាំ

-   Clear all caches: `php artisan config:clear && php artisan cache:clear`
-   Rebuild caches: `php artisan config:cache && php artisan route:cache`

## Testing Your API / ការធ្វើតេស្ត API:

### 1. Health Check / ការត្រួតពិនិត្យសុខភាព

```bash
curl https://your-api-url.vercel.app/api/v1/auth/login
```

### 2. Database Test / ការធ្វើតេស្ត Database

```bash
curl https://your-api-url.vercel.app/api/v1/points/calculate
```

## Monitoring / ការតាមដាន:

### 1. Check Vercel Logs / ការត្រួតពិនិត្យកំណត់ហេតុ Vercel

-   Go to your Vercel dashboard
-   Check Function Logs for errors
-   Monitor deployment status

### 2. Laravel Logs / កំណត់ហេតុ Laravel

-   Check `storage/logs/laravel.log` for application errors
-   Monitor database connection issues

## Additional Resources / ធនធានបន្ថែម:

-   [Laravel Deployment Guide](https://laravel.com/docs/deployment)
-   [Vercel PHP Runtime](https://vercel.com/docs/runtimes#official-runtimes/php)
-   [PostgreSQL Configuration](https://www.postgresql.org/docs/current/runtime-config-connection.html)

## Support / ការគាំទ្រ:

If issues persist, check:

-   Vercel deployment logs
-   Laravel application logs
-   Database connection status
-   Environment variable configuration
