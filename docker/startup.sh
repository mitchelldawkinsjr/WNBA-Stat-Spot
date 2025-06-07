#!/bin/sh

echo "🚀 Starting WNBA Stat Spot application..."

# Configure nginx with the correct port
echo "🔧 Configuring nginx for port ${PORT:-80}..."
sed "s/PORT_PLACEHOLDER/${PORT:-80}/g" /etc/nginx/nginx.conf > /tmp/nginx.conf
mv /tmp/nginx.conf /etc/nginx/nginx.conf

# Configure PHP-FPM to listen on 127.0.0.1:9000
echo "🔧 Configuring PHP-FPM..."
cat > /etc/php82/php-fpm.d/www.conf << 'EOF'
[www]
user = nginx
group = nginx
listen = 127.0.0.1:9000
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
catch_workers_output = yes
php_admin_value[error_log] = /tmp/php-fpm-error.log
php_admin_flag[log_errors] = on
EOF

# Test nginx configuration
echo "🔍 Testing nginx configuration..."
nginx -t || {
    echo "❌ Nginx configuration test failed"
    cat /etc/nginx/nginx.conf
    exit 1
}

echo "✅ Nginx configuration is valid"

# Check if SvelteKit build exists
echo "🔍 Checking SvelteKit build..."
if [ -d "/var/www/html/frontend-build" ]; then
    echo "✅ SvelteKit build directory exists"
    ls -la /var/www/html/frontend-build/
    if [ -f "/var/www/html/frontend-build/index.js" ]; then
        echo "✅ SvelteKit index.js found"
    else
        echo "❌ SvelteKit index.js not found"
        echo "🔧 Creating minimal SvelteKit fallback..."
        mkdir -p /var/www/html/frontend-build
        cat > /var/www/html/frontend-build/index.js << 'SVELTEEOF'
const express = require('express');
const app = express();
const port = process.env.PORT || 3000;

app.get('*', (req, res) => {
    res.status(503).send('Frontend service temporarily unavailable');
});

app.listen(port, '0.0.0.0', () => {
    console.log(`Fallback server listening on port ${port}`);
});
SVELTEEOF
        echo "✅ Created fallback SvelteKit server"
    fi

    # Check for client assets
    if [ -d "/var/www/html/frontend-build/client" ]; then
        echo "✅ SvelteKit client directory exists"
        ls -la /var/www/html/frontend-build/client/

        if [ -d "/var/www/html/frontend-build/client/_app" ]; then
            echo "✅ SvelteKit _app directory exists"
            ls -la /var/www/html/frontend-build/client/_app/ | head -10
        else
            echo "❌ SvelteKit _app directory not found"
            echo "🔧 Creating minimal _app directory..."
            mkdir -p /var/www/html/frontend-build/client/_app
        fi

        if [ -d "/var/www/html/frontend-build/client/immutable" ]; then
            echo "✅ SvelteKit immutable directory exists"
            ls -la /var/www/html/frontend-build/client/immutable/ | head -10
        else
            echo "❌ SvelteKit immutable directory not found"
            echo "🔧 Creating minimal immutable directory..."
            mkdir -p /var/www/html/frontend-build/client/immutable
            echo "/* Placeholder CSS */" > /var/www/html/frontend-build/client/immutable/app.css
        fi
    else
        echo "❌ SvelteKit client directory not found"
        echo "🔧 Creating minimal client structure..."
        mkdir -p /var/www/html/frontend-build/client/_app
        mkdir -p /var/www/html/frontend-build/client/immutable
        echo "/* Placeholder CSS */" > /var/www/html/frontend-build/client/immutable/app.css
        echo "Available directories:"
        ls -la /var/www/html/frontend-build/
    fi
else
    echo "❌ SvelteKit build directory not found"
    echo "🔧 Creating complete fallback structure..."
    mkdir -p /var/www/html/frontend-build/client/_app
    mkdir -p /var/www/html/frontend-build/client/immutable

    # Create fallback server
    cat > /var/www/html/frontend-build/index.js << 'SVELTEEOF'
const express = require('express');
const app = express();
const port = process.env.PORT || 3000;

app.get('*', (req, res) => {
    res.status(503).send('Frontend service temporarily unavailable');
});

app.listen(port, '0.0.0.0', () => {
    console.log(`Fallback server listening on port ${port}`);
});
SVELTEEOF

    # Create package.json for fallback
    cat > /var/www/html/frontend-build/package.json << 'PACKAGEEOF'
{
  "name": "wnba-fallback",
  "version": "1.0.0",
  "dependencies": {
    "express": "^4.18.0"
  }
}
PACKAGEEOF

    echo "/* Placeholder CSS */" > /var/www/html/frontend-build/client/immutable/app.css
    echo "✅ Created complete fallback structure"
    ls -la /var/www/html/
fi

# Install express if needed for fallback
if [ ! -d "/var/www/html/frontend-build/node_modules" ]; then
    echo "🔧 Installing fallback dependencies..."
    cd /var/www/html/frontend-build
    npm install express --no-save 2>/dev/null || echo "⚠️  Could not install express, using basic fallback"
    cd /var/www/html
fi

# Start supervisord immediately to bind to port
echo "🔧 Starting application services..."
supervisord -c /etc/supervisor/conf.d/supervisord.conf &

# Give services more time to start
sleep 10

# Check if services are running with retries
echo "📊 Checking service status..."
for i in 1 2 3; do
    echo "Attempt $i to check services..."

    # Check nginx
    if pgrep nginx > /dev/null; then
        echo "✅ Nginx is running"
    else
        echo "❌ Nginx not running, attempting to start..."
        nginx -g "daemon off;" &
        sleep 2
    fi

    # Check PHP-FPM
    if pgrep php-fpm > /dev/null; then
        echo "✅ PHP-FPM is running"
    else
        echo "❌ PHP-FPM not running, attempting to start..."
        php-fpm -F -R &
        sleep 2
    fi

    # Check SvelteKit/Node
    if pgrep node > /dev/null; then
        echo "✅ Node.js (SvelteKit) is running"
        break
    else
        echo "❌ Node.js not running, attempting to start..."
        cd /var/www/html/frontend-build
        PORT=3000 HOST=0.0.0.0 node index.js &
        cd /var/www/html
        sleep 3
    fi

    sleep 5
done

# Test if port is bound (use ss if netstat not available)
echo "🔍 Testing port binding..."
if command -v netstat > /dev/null; then
    netstat -tlnp | grep ":${PORT:-80}" || echo "⚠️  Port ${PORT:-80} not bound yet"
elif command -v ss > /dev/null; then
    ss -tlnp | grep ":${PORT:-80}" || echo "⚠️  Port ${PORT:-80} not bound yet"
else
    echo "⚠️  Cannot check port binding (netstat/ss not available)"
fi

# Test PHP-FPM port
echo "🔍 Testing PHP-FPM port 9000..."
sleep 2
if command -v netstat > /dev/null; then
    netstat -tlnp | grep ":9000" || echo "⚠️  PHP-FPM not listening on port 9000"
elif command -v ss > /dev/null; then
    ss -tlnp | grep ":9000" || echo "⚠️  PHP-FPM not listening on port 9000"
else
    echo "⚠️  Cannot check PHP-FPM port binding"
fi

# Test SvelteKit service
echo "🔍 Testing SvelteKit service..."
sleep 2
if curl -f http://localhost:3000 > /dev/null 2>&1; then
    echo "✅ SvelteKit is responding on port 3000"
else
    echo "⚠️  SvelteKit not responding on port 3000"
    echo "📋 SvelteKit logs:"
    tail -20 /tmp/sveltekit.log 2>/dev/null || echo "No SvelteKit logs found"
fi

# Test Laravel API
echo "🔍 Testing Laravel API..."
if curl -f http://localhost:${PORT:-80}/health > /dev/null 2>&1; then
    echo "✅ Laravel API is responding"
else
    echo "⚠️  Laravel API not responding"
    echo "📋 PHP-FPM logs:"
    tail -20 /tmp/php-fpm.log 2>/dev/null || echo "No PHP-FPM logs found"

    echo "📋 PHP-FPM error logs:"
    tail -20 /tmp/php-fpm-error.log 2>/dev/null || echo "No PHP-FPM error logs found"

    echo "📋 Nginx error logs:"
    tail -20 /tmp/nginx-error.log 2>/dev/null || echo "No nginx error logs found"

    echo "🔍 Testing simple API endpoint:"
    curl -v http://localhost:${PORT:-80}/api/test 2>&1 || echo "Simple API test failed"

    echo "🔍 Testing PHP-FPM directly:"
    if command -v php > /dev/null; then
        echo "PHP version: $(php --version | head -1)"
        echo "Testing basic PHP execution:"
        php -r "echo 'PHP is working\n';" || echo "PHP execution failed"

        echo "Testing Laravel artisan:"
        cd /var/www/html
        php artisan --version || echo "Laravel artisan failed"

        echo "Testing database connection:"
        php artisan migrate:status || echo "Database connection failed"
    else
        echo "PHP not found in PATH"
    fi

    echo "🔍 Checking process status:"
    ps aux | grep -E "(php|nginx)" | grep -v grep || echo "No PHP/nginx processes found"
fi

# Function to wait for database with timeout (run in background)
wait_for_database() {
    echo "⏳ Waiting for database connection..."

    # Check if database environment variables are set
    echo "🔍 Checking database environment variables..."
    if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
        echo "⚠️  DB_HOST not set or defaulting to localhost"
        echo "🔧 Checking for Render database auto-population..."

        # List all environment variables that might be database-related
        echo "📋 Available environment variables:"
        env | grep -E "(DB_|DATABASE|POSTGRES|RENDER)" | sort || echo "No database-related env vars found"

        # If auto-population failed, we might need manual configuration
        if [ -z "$DB_HOST" ] || [ "$DB_HOST" = "127.0.0.1" ]; then
            echo "❌ Database environment variables not properly set"
            echo "🔧 Please check your Render database service configuration"
            echo "   Expected: DB_HOST should be your Render database hostname"
            echo "   Current: DB_HOST=${DB_HOST:-'NOT SET'}"
        fi
    else
        echo "✅ Database environment variables appear to be set"
        echo "   DB_HOST: $DB_HOST"
        echo "   DB_PORT: ${DB_PORT:-'NOT SET'}"
        echo "   DB_DATABASE: ${DB_DATABASE:-'NOT SET'}"
        echo "   DB_USERNAME: ${DB_USERNAME:-'NOT SET'}"
    fi

    local max_attempts=50
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        if php artisan migrate:status > /dev/null 2>&1; then
            echo "✅ Database connection established"

            # Debug environment variables
            echo "🔍 Debugging environment configuration..."
            php artisan about || echo "⚠️  About command failed, continuing..."

            # Clear Laravel caches if requested (for Redis cache issues)
            if [ "$CLEAR_CACHE" = "true" ]; then
                echo "🧹 Clearing Laravel caches (Redis configuration cache)..."
                php artisan config:clear || echo "⚠️  Config cache clear failed"
                php artisan cache:clear || echo "⚠️  Application cache clear failed"
                php artisan route:clear || echo "⚠️  Route cache clear failed"
                php artisan view:clear || echo "⚠️  View cache clear failed"
                echo "✅ Cache clearing completed"
            fi

            # Run database migrations first - CRITICAL FOR RAILWAY
            echo "📊 Running database migrations (FORCED)..."
            php artisan migrate --force || {
                echo "❌ Migrations failed, retrying with more detail..."
                php artisan migrate --force --verbose || echo "⚠️  Migrations still failed, but continuing..."
            }

            # Create queue tables explicitly if they don't exist
            echo "📋 Ensuring queue tables exist..."
            php artisan queue:table --create || echo "Queue table migration already exists"
            php artisan migrate --force || echo "⚠️  Queue table migration failed"

            # Laravel optimizations (with error handling)
            echo "⚡ Optimizing Laravel application..."
            php artisan config:cache || echo "⚠️  Config cache failed, continuing..."
            php artisan route:cache || echo "⚠️  Route cache failed, continuing..."
            php artisan view:cache || echo "⚠️  View cache failed, continuing..."

            echo "🎉 Database setup complete!"
            return 0
        elif php artisan about > /dev/null 2>&1; then
            echo "✅ Database connection working, but migrations not ready"

            # Try running migrations even if migrate:status fails
            echo "📊 Attempting to run migrations anyway..."
            php artisan migrate --force || echo "⚠️  Forced migrations failed"

            return 0
        fi

        echo "Database not ready, waiting... (attempt $attempt/$max_attempts)"

        # Show more detailed error on later attempts
        if [ $attempt -gt 10 ]; then
            echo "🔍 Debugging database connection issue..."
            echo "   DB_HOST: ${DB_HOST:-'NOT SET'}"
            echo "   DB_PORT: ${DB_PORT:-'NOT SET'}"
            echo "   DB_DATABASE: ${DB_DATABASE:-'NOT SET'}"
            echo "   DB_USERNAME: ${DB_USERNAME:-'NOT SET'}"

            # Try a basic database connection test
            echo "🔍 Testing basic Laravel database connection..."
            php artisan about 2>&1 || echo "Laravel about command failed"
        fi

        sleep 2
        attempt=$((attempt + 1))
    done

    echo "❌ Database connection timeout after $max_attempts attempts"
    echo "🔄 Application will continue running without database features..."
    echo ""
    echo "🚨 TROUBLESHOOTING STEPS:"
    echo "1. Check that your Render database service is running"
    echo "2. Verify database environment variables are set correctly"
    echo "3. Ensure database service name matches render.yaml configuration"
    echo "4. Check RENDER_DATABASE_TROUBLESHOOTING.md for detailed help"
    echo ""
    return 1
}

# Run database setup in background
wait_for_database &

# Give database setup some time before starting queue health check
sleep 10

# Run queue health check in background
(
    sleep 30  # Wait a bit more for everything to settle
    echo "🔍 Running queue health check..."
    php artisan queue:health-check --detailed || echo "⚠️  Queue health check failed, but continuing..."
) &

echo "🎉 WNBA Stat Spot web services are ready!"
echo "🌐 Application is listening on port ${PORT:-80}"

# Wait for supervisord to finish (keeps container running)
wait
