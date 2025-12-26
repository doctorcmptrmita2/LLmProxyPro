# CodexFlow.dev Deployment Guide

## Prerequisites

- PHP 8.3+
- MySQL 8.0+
- Redis 6.0+
- Composer
- Node.js (optional, for frontend)
- LiteLLM Proxy (separate service)

## Local Development Setup

### 1. Clone and Install

```bash
cd /path/to/LLmProxyPro
composer install
cp .env.codexflow .env
php artisan key:generate
```

### 2. Database Setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE codexflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Update .env
DB_DATABASE=codexflow
DB_USERNAME=root
DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Seed test data
php artisan db:seed
```

### 3. Redis Setup

```bash
# Verify Redis is running
redis-cli ping
# Should return: PONG

# Update .env
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
```

### 4. LiteLLM Proxy Setup

```bash
# Install LiteLLM
pip install litellm

# Set environment variables
export ANTHROPIC_KEY_1="sk-ant-..."
export ANTHROPIC_KEY_2="sk-ant-..."
export ANTHROPIC_KEY_3="sk-ant-..."

# Start proxy
litellm --config litellm-config.example.yaml --port 4000

# Verify it's running
curl http://localhost:4000/health
```

### 5. Start Laravel Services

```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:work

# Terminal 3: Scheduler
php artisan schedule:work
```

### 6. Test the Setup

```bash
# Get test API key from database
mysql -u root -p codexflow -e "SELECT * FROM project_api_keys LIMIT 1;"

# Test gateway endpoint
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer sk_test_hiEhiAeivZTAHh3xSJgrjUQTvULPijuO" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "anthropic/claude-haiku-4-5",
    "messages": [{"role": "user", "content": "Hello!"}],
    "max_tokens": 100
  }'
```

---

## Production Deployment

### 1. Server Setup

#### Ubuntu/Debian

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install PHP 8.3
sudo apt install -y php8.3 php8.3-fpm php8.3-mysql php8.3-redis php8.3-curl php8.3-xml php8.3-mbstring

# Install MySQL
sudo apt install -y mysql-server

# Install Redis
sudo apt install -y redis-server

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install Nginx
sudo apt install -y nginx
```

#### CentOS/RHEL

```bash
# Similar setup with yum instead of apt
sudo yum install -y php83 php83-fpm php83-mysql php83-redis php83-curl php83-xml php83-mbstring
sudo yum install -y mysql-server redis nginx
```

### 2. Application Setup

```bash
# Clone repository
cd /var/www
git clone https://github.com/yourusername/codexflow.git
cd codexflow

# Install dependencies
composer install --no-dev --optimize-autoloader

# Setup environment
cp .env.codexflow .env
php artisan key:generate

# Set permissions
sudo chown -R www-data:www-data /var/www/codexflow
sudo chmod -R 755 /var/www/codexflow
sudo chmod -R 775 /var/www/codexflow/storage
sudo chmod -R 775 /var/www/codexflow/bootstrap/cache
```

### 3. Database Setup

```bash
# Create database and user
mysql -u root -p << EOF
CREATE DATABASE codexflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'codexflow'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON codexflow.* TO 'codexflow'@'localhost';
FLUSH PRIVILEGES;
EOF

# Update .env
DB_DATABASE=codexflow
DB_USERNAME=codexflow
DB_PASSWORD=strong_password_here

# Run migrations
php artisan migrate --force
php artisan db:seed --force
```

### 4. Nginx Configuration

Create `/etc/nginx/sites-available/codexflow`:

```nginx
server {
    listen 80;
    server_name api.codexflow.dev;
    root /var/www/codexflow/public;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
    limit_req zone=api burst=20 nodelay;
}
```

Enable the site:

```bash
sudo ln -s /etc/nginx/sites-available/codexflow /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl restart nginx
```

### 5. SSL/TLS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.codexflow.dev
```

### 6. Systemd Services

#### Laravel Queue Worker

Create `/etc/systemd/system/codexflow-queue.service`:

```ini
[Unit]
Description=CodexFlow Queue Worker
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/codexflow
ExecStart=/usr/bin/php /var/www/codexflow/artisan queue:work redis --sleep=3 --tries=3
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### Laravel Scheduler

Create `/etc/systemd/system/codexflow-scheduler.service`:

```ini
[Unit]
Description=CodexFlow Scheduler
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/codexflow
ExecStart=/usr/bin/php /var/www/codexflow/artisan schedule:work
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start services:

```bash
sudo systemctl daemon-reload
sudo systemctl enable codexflow-queue.service
sudo systemctl enable codexflow-scheduler.service
sudo systemctl start codexflow-queue.service
sudo systemctl start codexflow-scheduler.service
```

### 7. LiteLLM Proxy Deployment

```bash
# Install LiteLLM
pip install litellm

# Create systemd service
sudo tee /etc/systemd/system/litellm.service > /dev/null << EOF
[Unit]
Description=LiteLLM Proxy
After=network.target

[Service]
Type=simple
User=litellm
WorkingDirectory=/opt/litellm
ExecStart=/usr/local/bin/litellm --config /opt/litellm/config.yaml --port 4000
Restart=always
RestartSec=10
Environment="ANTHROPIC_KEY_1=sk-ant-..."
Environment="ANTHROPIC_KEY_2=sk-ant-..."
Environment="ANTHROPIC_KEY_3=sk-ant-..."

[Install]
WantedBy=multi-user.target
EOF

sudo systemctl daemon-reload
sudo systemctl enable litellm.service
sudo systemctl start litellm.service
```

### 8. Environment Configuration

Update `/var/www/codexflow/.env`:

```env
APP_NAME=CodexFlow
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_URL=https://api.codexflow.dev

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=codexflow
DB_USERNAME=codexflow
DB_PASSWORD=strong_password_here

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

LITELLM_BASE_URL=http://localhost:4000
LITELLM_API_KEY=your_litellm_master_key
LITELLM_TIMEOUT=120
LITELLM_MAX_RETRIES=2

LOG_CHANNEL=stack
LOG_LEVEL=warning
LOG_PROMPTS=false
LOG_RESPONSES=false
```

### 9. Monitoring & Logging

```bash
# View logs
tail -f /var/www/codexflow/storage/logs/laravel.log

# Monitor queue
php artisan queue:monitor

# Monitor services
sudo systemctl status codexflow-queue.service
sudo systemctl status codexflow-scheduler.service
sudo systemctl status litellm.service
```

### 10. Backup Strategy

```bash
# Daily database backup
0 2 * * * mysqldump -u codexflow -p'password' codexflow | gzip > /backups/codexflow-$(date +\%Y\%m\%d).sql.gz

# Keep 30 days of backups
find /backups -name "codexflow-*.sql.gz" -mtime +30 -delete
```

---

## Docker Deployment (Optional)

### Dockerfile

```dockerfile
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    mysql-client \
    redis-tools \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-install pdo_mysql redis

WORKDIR /app

COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader

RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
```

### docker-compose.yml

```yaml
version: '3.8'

services:
  app:
    build: .
    container_name: codexflow-app
    working_dir: /app
    volumes:
      - ./:/app
    depends_on:
      - db
      - redis
    environment:
      - DB_HOST=db
      - REDIS_HOST=redis

  db:
    image: mysql:8.0
    container_name: codexflow-db
    environment:
      MYSQL_DATABASE: codexflow
      MYSQL_ROOT_PASSWORD: root
    volumes:
      - dbdata:/var/lib/mysql

  redis:
    image: redis:7-alpine
    container_name: codexflow-redis

  nginx:
    image: nginx:alpine
    container_name: codexflow-nginx
    ports:
      - "80:80"
    volumes:
      - ./:/app
      - ./nginx.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - app

  queue:
    build: .
    container_name: codexflow-queue
    working_dir: /app
    command: php artisan queue:work redis --sleep=3 --tries=3
    depends_on:
      - db
      - redis
    environment:
      - DB_HOST=db
      - REDIS_HOST=redis

  scheduler:
    build: .
    container_name: codexflow-scheduler
    working_dir: /app
    command: php artisan schedule:work
    depends_on:
      - db
      - redis
    environment:
      - DB_HOST=db
      - REDIS_HOST=redis

volumes:
  dbdata:
```

Start with Docker:

```bash
docker-compose up -d
docker-compose exec app php artisan migrate
docker-compose exec app php artisan db:seed
```

---

## Performance Optimization

### 1. Caching

```bash
# Cache configuration
php artisan config:cache

# Cache routes
php artisan route:cache

# Cache views
php artisan view:cache
```

### 2. Database Optimization

```sql
-- Add indexes
ALTER TABLE llm_requests ADD INDEX idx_project_created (project_id, created_at);
ALTER TABLE usage_daily_aggregates ADD UNIQUE INDEX idx_project_date (project_id, date);

-- Analyze tables
ANALYZE TABLE llm_requests;
ANALYZE TABLE usage_daily_aggregates;
```

### 3. Redis Optimization

```bash
# Enable persistence
# In /etc/redis/redis.conf:
save 900 1
save 300 10
save 60 10000
appendonly yes
```

### 4. PHP-FPM Tuning

```ini
; /etc/php/8.3/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 500
```

---

## Monitoring & Alerting

### Health Checks

```bash
# Application health
curl https://api.codexflow.dev/up

# Database connection
php artisan tinker
>>> DB::connection()->getPdo()

# Redis connection
redis-cli ping

# LiteLLM proxy
curl http://localhost:4000/health
```

### Logging

- Application logs: `/var/www/codexflow/storage/logs/laravel.log`
- Nginx logs: `/var/log/nginx/access.log`, `/var/log/nginx/error.log`
- MySQL logs: `/var/log/mysql/error.log`
- Redis logs: `/var/log/redis/redis-server.log`

### Metrics to Monitor

- Request latency (p50, p95, p99)
- Error rate
- Queue depth
- Database connection pool usage
- Redis memory usage
- API key usage per project
- Monthly token consumption

---

## Troubleshooting

### Queue Not Processing

```bash
# Check queue status
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Monitor queue
php artisan queue:monitor
```

### High Memory Usage

```bash
# Check Redis memory
redis-cli INFO memory

# Clear cache
php artisan cache:clear

# Prune old requests manually
php artisan tinker
>>> App\Models\LlmRequest::where('created_at', '<', now()->subDays(90))->delete()
```

### Database Slow Queries

```bash
# Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

# Check slow queries
tail -f /var/log/mysql/slow.log
```

---

## Rollback Procedure

```bash
# Backup current database
mysqldump -u codexflow -p codexflow > /backups/pre-rollback.sql

# Rollback migrations
php artisan migrate:rollback

# Restore from backup
mysql -u codexflow -p codexflow < /backups/pre-rollback.sql

# Restart services
sudo systemctl restart codexflow-queue.service
sudo systemctl restart codexflow-scheduler.service
```

---

## Security Checklist

- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Use strong database password
- [ ] Enable SSL/TLS
- [ ] Configure firewall rules
- [ ] Set up rate limiting
- [ ] Enable CORS only for trusted domains
- [ ] Rotate API keys regularly
- [ ] Monitor access logs
- [ ] Set up log rotation
- [ ] Configure backup retention
- [ ] Test disaster recovery

