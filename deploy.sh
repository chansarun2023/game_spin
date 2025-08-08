#!/bin/bash

# Deployment script for Laravel API on Vercel
# Script to help with database setup and migration

echo "🚀 Starting deployment process..."

# Check if we're in production environment
if [ "$APP_ENV" = "production" ]; then
    echo "📦 Production environment detected"

    # Clear all caches
    echo "🧹 Clearing application caches..."
    php artisan config:clear
    php artisan cache:clear
    php artisan route:clear
    php artisan view:clear

    # Optimize for production
    echo "⚡ Optimizing for production..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Run database migrations
    echo "🗄️ Running database migrations..."
    php artisan migrate --force

    # Seed database if needed
    echo "🌱 Seeding database..."
    php artisan db:seed --force

    echo "✅ Deployment completed successfully!"
else
    echo "🔧 Development environment detected"
    echo "📝 Skipping production optimizations"
fi
