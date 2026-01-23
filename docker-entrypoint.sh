#!/bin/bash
set -e

# Run migrations
php artisan storage:link || true
php artisan migrate --force

if [ "${RUN_SEEDERS_ON_BOOT}" = "true" ]; then
  php artisan db:seed --force
fi

# Start Apache
apache2-foreground
