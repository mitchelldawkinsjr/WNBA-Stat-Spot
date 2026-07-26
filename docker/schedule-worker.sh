#!/bin/sh

echo "🕐 Starting Laravel scheduler..."

cd /var/www/html

# Wait briefly for DB so the first schedule tick does not fail loudly on boot.
max_attempts=30
attempt=1
while [ $attempt -le $max_attempts ]; do
    if php artisan migrate:status > /dev/null 2>&1; then
        echo "✅ Database ready for scheduler"
        break
    fi
    if [ $attempt -eq $max_attempts ]; then
        echo "⚠️  Database not ready after ${max_attempts} attempts; starting scheduler anyway"
        break
    fi
    echo "Database not ready for scheduler, waiting... (attempt $attempt/$max_attempts)"
    sleep 2
    attempt=$((attempt + 1))
done

echo "🚀 Starting schedule:work (polls every minute)..."
exec php artisan schedule:work --verbose --no-interaction
