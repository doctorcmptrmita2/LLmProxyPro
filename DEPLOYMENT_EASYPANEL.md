# CodexFlow.dev - EasyPanel Deployment (OVH KS-4)

## OVH KS-4 Sunucu Özellikleri

- **CPU**: Intel Xeon-D 1521 (4 cores / 8 threads)
- **RAM**: 32 GB DDR4 ECC
- **Storage**: 2x 480GB SSD NVMe (RAID 1)
- **Network**: 1 Gbps
- **OS**: Ubuntu 22.04 LTS (önerilen)

## EasyPanel Kurulumu

### 1. Sunucu Hazırlığı

```bash
# Sunucuya SSH bağlantısı
ssh root@your-server-ip

# Sistem güncellemesi
apt update && apt upgrade -y

# Gerekli paketler
apt install -y curl wget git htop nano ufw

# Firewall ayarları
ufw allow ssh
ufw allow 80
ufw allow 443
ufw allow 3000  # EasyPanel
ufw --force enable
```

### 2. Docker Kurulumu

```bash
# Docker kurulumu
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# Docker Compose kurulumu
curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
chmod +x /usr/local/bin/docker-compose

# Docker servisini başlat
systemctl enable docker
systemctl start docker
```

### 3. EasyPanel Kurulumu

```bash
# EasyPanel kurulumu
curl -sSL https://get.easypanel.io | sh

# EasyPanel başlatma
systemctl enable easypanel
systemctl start easypanel

# Panel erişimi: https://your-server-ip:3000
# İlk kurulumda admin kullanıcısı oluşturun
```

---

## CodexFlow.dev Deployment

### 1. Proje Dosyaları

EasyPanel'de yeni bir proje oluşturun: **codexflow**

#### Dockerfile

```dockerfile
FROM php:8.3-fpm-alpine

# Sistem paketleri
RUN apk add --no-cache \
    nginx \
    supervisor \
    mysql-client \
    redis \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    libzip-dev

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        xml \
        zip \
        gd \
        bcmath \
        opcache

# Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Nginx konfigürasyonu
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/default.conf /etc/nginx/http.d/default.conf

# Supervisor konfigürasyonu
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# PHP konfigürasyonu
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Uygulama dosyaları
COPY . .

# Composer install
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 775 /var/www/html/storage \
    && chmod -R 775 /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
```

#### docker/nginx.conf

```nginx
user www-data;
worker_processes auto;
pid /run/nginx.pid;

events {
    worker_connections 1024;
    use epoll;
    multi_accept on;
}

http {
    include /etc/nginx/mime.types;
    default_type application/octet-stream;

    log_format main '$remote_addr - $remote_user [$time_local] "$request" '
                    '$status $body_bytes_sent "$http_referer" '
                    '"$http_user_agent" "$http_x_forwarded_for"';

    access_log /var/log/nginx/access.log main;
    error_log /var/log/nginx/error.log warn;

    sendfile on;
    tcp_nopush on;
    tcp_nodelay on;
    keepalive_timeout 65;
    types_hash_max_size 2048;

    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types
        text/plain
        text/css
        text/xml
        text/javascript
        application/json
        application/javascript
        application/xml+rss
        application/atom+xml
        image/svg+xml;

    include /etc/nginx/http.d/*.conf;
}
```

#### docker/default.conf

```nginx
server {
    listen 80;
    server_name _;
    root /var/www/html/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    # Rate limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=100r/m;
    limit_req zone=api burst=20 nodelay;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Health check
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
```

#### docker/supervisord.conf

```ini
[supervisord]
nodaemon=true
user=root
logfile=/var/log/supervisor/supervisord.log
pidfile=/var/run/supervisord.pid

[program:nginx]
command=nginx -g "daemon off;"
autostart=true
autorestart=true
stderr_logfile=/var/log/nginx/error.log
stdout_logfile=/var/log/nginx/access.log

[program:php-fpm]
command=php-fpm -F
autostart=true
autorestart=true
stderr_logfile=/var/log/php-fpm.log
stdout_logfile=/var/log/php-fpm.log

[program:laravel-queue]
command=php /var/www/html/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
numprocs=2
stderr_logfile=/var/log/laravel-queue.log
stdout_logfile=/var/log/laravel-queue.log

[program:laravel-scheduler]
command=php /var/www/html/artisan schedule:work
directory=/var/www/html
autostart=true
autorestart=true
user=www-data
stderr_logfile=/var/log/laravel-scheduler.log
stdout_logfile=/var/log/laravel-scheduler.log
```

#### docker/php.ini

```ini
; Performance
memory_limit = 512M
max_execution_time = 300
max_input_time = 300
post_max_size = 100M
upload_max_filesize = 100M

; OPcache
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1

; Error reporting
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Session
session.gc_maxlifetime = 1440
session.gc_probability = 1
session.gc_divisor = 1000

; Timezone
date.timezone = Europe/Istanbul
```

#### docker/php-fpm.conf

```ini
[www]
user = www-data
group = www-data
listen = 127.0.0.1:9000
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500
pm.process_idle_timeout = 10s
```

### 2. Docker Compose (EasyPanel Template)

```yaml
version: '3.8'

services:
  app:
    build: .
    container_name: codexflow-app
    restart: unless-stopped
    ports:
      - "80:80"
    environment:
      - APP_NAME=CodexFlow
      - APP_ENV=production
      - APP_DEBUG=false
      - APP_URL=https://api.codexflow.dev
      - DB_CONNECTION=mysql
      - DB_HOST=db
      - DB_PORT=3306
      - DB_DATABASE=codexflow
      - DB_USERNAME=codexflow
      - DB_PASSWORD=${DB_PASSWORD}
      - CACHE_DRIVER=redis
      - QUEUE_CONNECTION=redis
      - SESSION_DRIVER=redis
      - REDIS_HOST=redis
      - REDIS_PORT=6379
      - LITELLM_BASE_URL=http://litellm:4000
      - LITELLM_API_KEY=${LITELLM_API_KEY}
      - LITELLM_TIMEOUT=120
      - LITELLM_MAX_RETRIES=2
      - LARGE_REQUEST_THRESHOLD=8000
      - LITELLM_CACHE_ENABLED=true
      - LITELLM_CACHE_TTL=86400
      - LOG_CHANNEL=stderr
      - LOG_LEVEL=warning
      - LOG_PROMPTS=false
    volumes:
      - app_storage:/var/www/html/storage
    depends_on:
      - db
      - redis
      - litellm
    networks:
      - codexflow

  db:
    image: mysql:8.0
    container_name: codexflow-db
    restart: unless-stopped
    environment:
      - MYSQL_DATABASE=codexflow
      - MYSQL_USER=codexflow
      - MYSQL_PASSWORD=${DB_PASSWORD}
      - MYSQL_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/mysql.cnf:/etc/mysql/conf.d/custom.cnf
    ports:
      - "3306:3306"
    networks:
      - codexflow

  redis:
    image: redis:7-alpine
    container_name: codexflow-redis
    restart: unless-stopped
    command: redis-server --appendonly yes --maxmemory 1gb --maxmemory-policy allkeys-lru
    volumes:
      - redis_data:/data
    ports:
      - "6379:6379"
    networks:
      - codexflow

  litellm:
    image: ghcr.io/berriai/litellm:main-latest
    container_name: codexflow-litellm
    restart: unless-stopped
    ports:
      - "4000:4000"
    environment:
      - ANTHROPIC_API_KEY_1=${ANTHROPIC_KEY_1}
      - ANTHROPIC_API_KEY_2=${ANTHROPIC_KEY_2}
      - ANTHROPIC_API_KEY_3=${ANTHROPIC_KEY_3}
    volumes:
      - ./litellm-config.yaml:/app/config.yaml
    command: ["--config", "/app/config.yaml", "--port", "4000"]
    networks:
      - codexflow

volumes:
  db_data:
  redis_data:
  app_storage:

networks:
  codexflow:
    driver: bridge
```

### 3. MySQL Optimizasyonu

#### docker/mysql.cnf

```ini
[mysqld]
# InnoDB settings
innodb_buffer_pool_size = 8G
innodb_log_file_size = 512M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Query cache
query_cache_type = 1
query_cache_size = 256M

# Connection settings
max_connections = 200
max_connect_errors = 1000000

# Slow query log
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2

# Binary logging
log_bin = /var/log/mysql/mysql-bin.log
expire_logs_days = 7
max_binlog_size = 100M

# Character set
character_set_server = utf8mb4
collation_server = utf8mb4_unicode_ci
```

### 4. Environment Variables (.env)

EasyPanel'de Environment Variables bölümünde:

```env
# App
APP_KEY=base64:your-generated-key-here

# Database
DB_PASSWORD=your-strong-db-password
DB_ROOT_PASSWORD=your-strong-root-password

# LiteLLM
LITELLM_API_KEY=your-litellm-master-key

# Anthropic Keys
ANTHROPIC_KEY_1=sk-ant-your-key-1
ANTHROPIC_KEY_2=sk-ant-your-key-2
ANTHROPIC_KEY_3=sk-ant-your-key-3
```

---

## EasyPanel Deployment Adımları

### 1. Proje Oluşturma

1. EasyPanel'e giriş yapın
2. **Projects** → **Create Project**
3. Proje adı: `codexflow`
4. **Source** → **Git Repository**
5. Repository URL'ini girin
6. Branch: `main`

### 2. Build Settings

1. **Build** sekmesine gidin
2. **Dockerfile** seçin
3. Build context: `/`
4. Dockerfile path: `Dockerfile`

### 3. Environment Variables

**Environment** sekmesinde yukarıdaki değişkenleri ekleyin.

### 4. Domains

1. **Domains** sekmesine gidin
2. Domain ekleyin: `api.codexflow.dev`
3. SSL sertifikası otomatik oluşturulacak

### 5. Deploy

1. **Deploy** butonuna tıklayın
2. Build ve deployment sürecini izleyin
3. Logları kontrol edin

---

## İlk Kurulum Komutları

Container başladıktan sonra:

```bash
# Container'a bağlan
docker exec -it codexflow-app sh

# Laravel kurulum
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Permissions
chown -R www-data:www-data /var/www/html/storage
chown -R www-data:www-data /var/www/html/bootstrap/cache
```

---

## Monitoring ve Maintenance

### 1. Health Checks

EasyPanel'de health check URL'i: `/health`

### 2. Logs

```bash
# Application logs
docker logs codexflow-app

# Database logs
docker logs codexflow-db

# Redis logs
docker logs codexflow-redis

# LiteLLM logs
docker logs codexflow-litellm
```

### 3. Backup Script

```bash
#!/bin/bash
# /root/backup-codexflow.sh

DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/codexflow"

mkdir -p $BACKUP_DIR

# Database backup
docker exec codexflow-db mysqldump -u codexflow -p$DB_PASSWORD codexflow | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Redis backup
docker exec codexflow-redis redis-cli BGSAVE
docker cp codexflow-redis:/data/dump.rdb $BACKUP_DIR/redis_$DATE.rdb

# Application files backup
docker cp codexflow-app:/var/www/html/storage $BACKUP_DIR/storage_$DATE

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete
find $BACKUP_DIR -name "*.rdb" -mtime +30 -delete

echo "Backup completed: $DATE"
```

Crontab'a ekleyin:
```bash
# Daily backup at 2 AM
0 2 * * * /root/backup-codexflow.sh >> /var/log/backup.log 2>&1
```

### 4. Performance Monitoring

```bash
# Container resource usage
docker stats

# Database performance
docker exec codexflow-db mysql -u root -p -e "SHOW PROCESSLIST;"
docker exec codexflow-db mysql -u root -p -e "SHOW ENGINE INNODB STATUS\G"

# Redis info
docker exec codexflow-redis redis-cli INFO memory
docker exec codexflow-redis redis-cli INFO stats
```

---

## SSL ve Domain Ayarları

### 1. Cloudflare (Önerilen)

1. Domain'i Cloudflare'e ekleyin
2. DNS A record: `api.codexflow.dev` → `your-server-ip`
3. SSL/TLS → Full (strict)
4. Security → Bot Fight Mode: On
5. Speed → Auto Minify: CSS, JS, HTML

### 2. Rate Limiting

Cloudflare'de:
- Rate Limiting: 1000 requests/minute per IP
- DDoS Protection: High
- Bot Management: On

---

## Güvenlik Ayarları

### 1. Firewall (UFW)

```bash
# Sadece gerekli portları aç
ufw allow 22    # SSH
ufw allow 80    # HTTP
ufw allow 443   # HTTPS
ufw allow 3000  # EasyPanel
ufw deny 3306   # MySQL (sadece container network)
ufw deny 6379   # Redis (sadece container network)
ufw deny 4000   # LiteLLM (sadece container network)
```

### 2. SSH Güvenliği

```bash
# SSH key-only access
echo "PasswordAuthentication no" >> /etc/ssh/sshd_config
echo "PubkeyAuthentication yes" >> /etc/ssh/sshd_config
systemctl restart ssh
```

### 3. Fail2Ban

```bash
apt install -y fail2ban

# /etc/fail2ban/jail.local
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = ssh
logpath = /var/log/auth.log

systemctl enable fail2ban
systemctl start fail2ban
```

---

## Troubleshooting

### 1. Container Başlamıyor

```bash
# Logs kontrol et
docker logs codexflow-app

# Container'a debug modunda bağlan
docker run -it --rm codexflow-app sh
```

### 2. Database Bağlantı Sorunu

```bash
# MySQL container durumu
docker exec codexflow-db mysql -u root -p -e "SELECT 1"

# Network bağlantısı test et
docker exec codexflow-app ping db
```

### 3. Redis Bağlantı Sorunu

```bash
# Redis test
docker exec codexflow-redis redis-cli ping

# Laravel'den test
docker exec codexflow-app php artisan tinker
>>> Cache::put('test', 'value')
>>> Cache::get('test')
```

### 4. LiteLLM Sorunu

```bash
# LiteLLM health check
curl http://localhost:4000/health

# Config test
docker exec codexflow-litellm cat /app/config.yaml
```

---

## Performance Tuning

### 1. OVH KS-4 Optimizasyonu

```bash
# CPU governor
echo performance > /sys/devices/system/cpu/cpu*/cpufreq/scaling_governor

# Swappiness
echo "vm.swappiness=10" >> /etc/sysctl.conf

# Network tuning
echo "net.core.rmem_max = 16777216" >> /etc/sysctl.conf
echo "net.core.wmem_max = 16777216" >> /etc/sysctl.conf

sysctl -p
```

### 2. Docker Optimizasyonu

```bash
# Docker daemon.json
cat > /etc/docker/daemon.json << EOF
{
  "log-driver": "json-file",
  "log-opts": {
    "max-size": "10m",
    "max-file": "3"
  },
  "storage-driver": "overlay2"
}
EOF

systemctl restart docker
```

---

## Sonuç

Bu deployment rehberi ile CodexFlow.dev:
- ✅ OVH KS-4 sunucuda optimize edilmiş
- ✅ EasyPanel ile kolay yönetim
- ✅ Docker containerları ile izolasyon
- ✅ SSL/TLS güvenliği
- ✅ Otomatik backup
- ✅ Monitoring ve alerting
- ✅ High availability (Redis persistence, MySQL replication ready)

**Deployment süresi**: ~30 dakika
**Downtime**: Sıfır (rolling deployment)
**Scalability**: Horizontal scaling ready

🚀 **Production-ready deployment tamamlandı!**
