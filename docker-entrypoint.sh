#!/bin/bash
set -e

# Run migrations
php artisan migrate --force

# Run seeders (optional, but good for initial setup)
# php artisan db:seed --force

# Start Apache
apache2-foreground
