# CodexFlow.dev - EasyPanel Quick Start Guide

## 📋 Ön Koşullar

- OVH KS-4 sunucu (veya benzer)
- Ubuntu 22.04 LTS
- Root erişimi
- Domain adı (örn: api.codexflow.dev)

---

## 🚀 Adım 1: Sunucu Hazırlığı (5 dakika)

```bash
# SSH ile sunucuya bağlan
ssh root@your-server-ip

# Sistem güncellemesi
apt update && apt upgrade -y

# Gerekli paketler
apt install -y curl wget git htop nano ufw

# Firewall ayarları
ufw allow ssh
ufw allow 80
ufw allow 443
ufw allow 3000
ufw --force enable

# Swap ayarla (32GB RAM olsa da iyi olur)
fallocate -l 4G /swapfile
chmod 600 /swapfile
mkswap /swapfile
swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

---

## 🐳 Adım 2: Docker & EasyPanel Kurulumu (10 dakika)

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

# EasyPanel kurulumu
curl -sSL https://get.easypanel.io | sh

# EasyPanel başlatma
systemctl enable easypanel
systemctl start easypanel

# EasyPanel'e erişim
echo "EasyPanel URL: https://$(hostname -I | awk '{print $1}'):3000"
```

---

## 📦 Adım 3: Proje Dosyalarını Hazırla (5 dakika)

```bash
# Proje dizini oluştur
mkdir -p /opt/codexflow
cd /opt/codexflow

# Git'ten klonla (veya dosyaları kopyala)
git clone https://github.com/yourusername/codexflow.git .

# Veya manuel olarak dosyaları kopyala
# SCP ile: scp -r ./LLmProxyPro/* root@server:/opt/codexflow/
```

---

## ⚙️ Adım 4: Environment Dosyasını Oluştur (5 dakika)

```bash
cd /opt/codexflow

# .env dosyasını oluştur
cat > .env << 'EOF'
APP_NAME=CodexFlow
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.codexflow.dev
APP_KEY=base64:your-generated-key-here

DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=codexflow
DB_USERNAME=codexflow
DB_PASSWORD=your-strong-db-password-here
DB_ROOT_PASSWORD=your-strong-root-password-here

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=your-redis-password-here

LITELLM_BASE_URL=http://litellm:4000
LITELLM_API_KEY=your-litellm-master-key-here
LITELLM_TIMEOUT=120
LITELLM_MAX_RETRIES=2

LARGE_REQUEST_THRESHOLD=8000
LITELLM_CACHE_ENABLED=true
LITELLM_CACHE_TTL=86400

LOG_CHANNEL=stderr
LOG_LEVEL=warning
LOG_PROMPTS=false

ANTHROPIC_KEY_1=sk-ant-your-key-1-here
ANTHROPIC_KEY_2=sk-ant-your-key-2-here
ANTHROPIC_KEY_3=sk-ant-your-key-3-here
EOF

# Güvenli izinler
chmod 600 .env
```

**⚠️ Önemli**: Yukarıdaki değerleri gerçek değerlerle değiştir!

---

## 🎯 Adım 5: EasyPanel'de Proje Oluştur (10 dakika)

### 5.1 EasyPanel'e Giriş

1. Tarayıcıda açın: `https://your-server-ip:3000`
2. Admin kullanıcısı oluşturun
3. Giriş yapın

### 5.2 Yeni Proje Oluştur

1. **Projects** → **Create Project**
2. Proje adı: `codexflow`
3. **Source** seçin:
   - **Git Repository** (önerilen)
   - Repository URL: `https://github.com/yourusername/codexflow.git`
   - Branch: `main`
   - Veya **Upload Files** ile dosyaları yükle

### 5.3 Build Ayarları

1. **Build** sekmesine gidin
2. **Dockerfile** seçin
3. Build context: `/`
4. Dockerfile path: `Dockerfile`

### 5.4 Environment Variables

1. **Environment** sekmesine gidin
2. Aşağıdaki değişkenleri ekle:

```
APP_KEY=base64:your-generated-key-here
DB_PASSWORD=your-strong-db-password-here
DB_ROOT_PASSWORD=your-strong-root-password-here
REDIS_PASSWORD=your-redis-password-here
LITELLM_API_KEY=your-litellm-master-key-here
ANTHROPIC_KEY_1=sk-ant-your-key-1-here
ANTHROPIC_KEY_2=sk-ant-your-key-2-here
ANTHROPIC_KEY_3=sk-ant-your-key-3-here
```

### 5.5 Domain Ayarları

1. **Domains** sekmesine gidin
2. Domain ekle: `api.codexflow.dev`
3. SSL sertifikası otomatik oluşturulacak

### 5.6 Deploy

1. **Deploy** butonuna tıkla
2. Build ve deployment sürecini izle
3. Logları kontrol et

---

## 🔧 Adım 6: İlk Kurulum (5 dakika)

Deployment başarılı olduktan sonra:

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

# Container'dan çık
exit
```

---

## ✅ Adım 7: Test Et (5 dakika)

### 7.1 Health Check

```bash
curl https://api.codexflow.dev/health
# Çıktı: healthy
```

### 7.2 Database Bağlantısı

```bash
docker exec codexflow-app php artisan tinker
>>> DB::connection()->getPdo()
# Çıktı: PDOConnection object
```

### 7.3 Redis Bağlantısı

```bash
docker exec codexflow-redis redis-cli ping
# Çıktı: PONG
```

### 7.4 LiteLLM Bağlantısı

```bash
curl http://localhost:4000/health
# Çıktı: {"status": "ok"}
```

### 7.5 Gateway Endpoint

```bash
# Test API key'i veritabanından al
docker exec codexflow-db mysql -u codexflow -p -e "SELECT * FROM project_api_keys LIMIT 1;"

# Gateway'i test et
curl -X POST https://api.codexflow.dev/api/v1/chat/completions \
  -H "Authorization: Bearer sk_test_your-key-here" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "anthropic/claude-haiku-4-5",
    "messages": [{"role": "user", "content": "Hello!"}],
    "max_tokens": 100
  }'
```

---

## 📊 Monitoring (Devam Eden)

### EasyPanel Dashboard

1. **Projects** → **codexflow**
2. **Logs** sekmesinde tüm logları görebilirsin
3. **Stats** sekmesinde CPU, RAM, Network kullanımını görebilirsin

### Container Logları

```bash
# Application logs
docker logs -f codexflow-app

# Database logs
docker logs -f codexflow-db

# Redis logs
docker logs -f codexflow-redis

# LiteLLM logs
docker logs -f codexflow-litellm
```

### Performance Monitoring

```bash
# Container resource usage
docker stats

# Database performance
docker exec codexflow-db mysql -u root -p -e "SHOW PROCESSLIST;"

# Redis info
docker exec codexflow-redis redis-cli INFO memory
```

---

## 🔐 Güvenlik Ayarları

### 1. Firewall

```bash
# Sadece gerekli portları aç
ufw allow 22    # SSH
ufw allow 80    # HTTP
ufw allow 443   # HTTPS
ufw allow 3000  # EasyPanel (sadece admin IP'den)
ufw deny 3306   # MySQL
ufw deny 6379   # Redis
ufw deny 4000   # LiteLLM
```

### 2. SSH Güvenliği

```bash
# SSH key-only access
sed -i 's/#PasswordAuthentication yes/PasswordAuthentication no/' /etc/ssh/sshd_config
sed -i 's/#PubkeyAuthentication yes/PubkeyAuthentication yes/' /etc/ssh/sshd_config
systemctl restart ssh
```

### 3. Fail2Ban

```bash
apt install -y fail2ban

cat > /etc/fail2ban/jail.local << 'EOF'
[DEFAULT]
bantime = 3600
findtime = 600
maxretry = 5

[sshd]
enabled = true
port = ssh
logfile = /var/log/auth.log
EOF

systemctl enable fail2ban
systemctl start fail2ban
```

---

## 💾 Backup Ayarları

### Otomatik Backup Script

```bash
cat > /root/backup-codexflow.sh << 'EOF'
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/backups/codexflow"

mkdir -p $BACKUP_DIR

# Database backup
docker exec codexflow-db mysqldump -u codexflow -p$DB_PASSWORD codexflow | gzip > $BACKUP_DIR/db_$DATE.sql.gz

# Redis backup
docker exec codexflow-redis redis-cli BGSAVE
docker cp codexflow-redis:/data/dump.rdb $BACKUP_DIR/redis_$DATE.rdb

# Cleanup old backups (keep 30 days)
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete
find $BACKUP_DIR -name "*.rdb" -mtime +30 -delete

echo "Backup completed: $DATE"
EOF

chmod +x /root/backup-codexflow.sh

# Crontab'a ekle
echo "0 2 * * * /root/backup-codexflow.sh >> /var/log/backup.log 2>&1" | crontab -
```

---

## 🆘 Troubleshooting

### Container Başlamıyor

```bash
# Logs kontrol et
docker logs codexflow-app

# Container'ı debug modunda başlat
docker run -it --rm codexflow-app sh
```

### Database Bağlantı Sorunu

```bash
# MySQL container durumu
docker exec codexflow-db mysql -u root -p -e "SELECT 1"

# Network bağlantısı test et
docker exec codexflow-app ping db
```

### Redis Bağlantı Sorunu

```bash
# Redis test
docker exec codexflow-redis redis-cli ping

# Laravel'den test
docker exec codexflow-app php artisan tinker
>>> Cache::put('test', 'value')
>>> Cache::get('test')
```

### LiteLLM Sorunu

```bash
# LiteLLM health check
curl http://localhost:4000/health

# Config test
docker exec codexflow-litellm cat /app/config.yaml
```

---

## 📈 Performance Tuning

### OVH KS-4 Optimizasyonu

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

### Docker Optimizasyonu

```bash
cat > /etc/docker/daemon.json << 'EOF'
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

## 📞 Sonraki Adımlar

1. ✅ Sunucu hazırlandı
2. ✅ Docker kuruldu
3. ✅ EasyPanel kuruldu
4. ✅ Proje deploy edildi
5. ✅ Test edildi
6. ✅ Backup ayarlandı

### Opsiyonel:
- [ ] Cloudflare DNS ayarla
- [ ] SSL sertifikasını yenile
- [ ] Monitoring dashboard kur
- [ ] Log aggregation ayarla
- [ ] CDN konfigürasyonu

---

## 🎉 Tamamlandı!

CodexFlow.dev artık production'da çalışıyor! 🚀

**Önemli Bilgiler:**
- API URL: `https://api.codexflow.dev`
- EasyPanel: `https://your-server-ip:3000`
- Database: `db` container'ında
- Redis: `redis` container'ında
- LiteLLM: `litellm` container'ında

**Destek:**
- Logları kontrol et: `docker logs -f codexflow-app`
- EasyPanel dashboard'u kullan
- Monitoring yapı: `docker stats`

Sorularınız varsa DEPLOYMENT_EASYPANEL.md dosyasını kontrol edin!
