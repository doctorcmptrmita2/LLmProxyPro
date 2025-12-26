# EasyPanel Deployment Hatası - Çözüm

## 🔴 Hata Mesajı

```
Top-level object must be a mapping
Command failed with exit code 15: docker compose -f /etc/easypanel/projects/codexflow/codexflow/code/Dockerfile ...
```

## 🔍 Sorun

EasyPanel, Dockerfile'ı docker-compose dosyası gibi okumaya çalışıyor. Bu yüzden "Top-level object must be a mapping" hatası alıyorsunuz.

## ✅ Çözüm 1: Docker Compose Build Type Kullan (Önerilen)

### ⚠️ Önemli Notlar

`docker-compose.easypanel.yml` dosyası EasyPanel için optimize edilmiştir:
- ✅ `version` satırı kaldırıldı (obsolete)
- ✅ `container_name` kaldırıldı (EasyPanel otomatik yönetiyor)
- ✅ `ports` yerine `expose` kullanıldı (port mapping EasyPanel UI'dan yapılıyor)

### Adımlar:

1. **EasyPanel'de projeye git**
   - Projects → codexflow → Settings

2. **Build sekmesine git**
   - Build Type: **"Docker Compose"** seç
   - Docker Compose File: `docker-compose.easypanel.yml`
   - Build Context: `/`

3. **Port Mapping (EasyPanel UI'dan)**
   - **app** servisi için: Container Port `80` → Host Port `80` (veya istediğiniz port)
   - **db** servisi için: Container Port `3306` → Host Port `3306` (opsiyonel, sadece dış erişim için)
   - **redis** servisi için: Container Port `6379` → Host Port `6379` (opsiyonel, sadece dış erişim için)
   - **litellm** servisi için: Container Port `4000` → Host Port `4000` (opsiyonel, sadece dış erişim için)
   
   **Not**: `app` servisi dışındaki portlar genellikle sadece internal network'te kullanılır, dışarıya açmaya gerek yok.

3. **Environment Variables ekle** (Environment sekmesi):
   ```
   APP_KEY=base64:your-generated-key-here
   DB_PASSWORD=your-strong-db-password
   DB_ROOT_PASSWORD=your-strong-root-password
   REDIS_PASSWORD=your-redis-password
   LITELLM_API_KEY=your-litellm-master-key
   ANTHROPIC_KEY_1=sk-ant-your-key-1
   ANTHROPIC_KEY_2=sk-ant-your-key-2
   ANTHROPIC_KEY_3=sk-ant-your-key-3
   APP_URL=https://api.codexflow.dev
   ```

4. **Deploy butonuna tıkla**

---

## ✅ Çözüm 2: Sadece Dockerfile Kullan (Tek Container)

Eğer docker-compose kullanmak istemiyorsanız:

### Adımlar:

1. **Build sekmesinde:**
   - Build Type: **"Dockerfile"** seç
   - Dockerfile Path: `Dockerfile`
   - Build Context: `/`

2. **Port Mapping:**
   - Container Port: `80`
   - Host Port: `80`

3. **Environment Variables ekle:**
   ```
   APP_KEY=base64:your-generated-key-here
   DB_HOST=your-external-db-host
   DB_PASSWORD=your-db-password
   REDIS_HOST=your-external-redis-host
   REDIS_PASSWORD=your-redis-password
   LITELLM_BASE_URL=http://your-litellm-host:4000
   ```

4. **Database ve Redis'i ayrı servisler olarak oluştur:**
   - EasyPanel'de yeni servis: MySQL
   - EasyPanel'de yeni servis: Redis
   - LiteLLM'i ayrı bir servis olarak deploy et

---

## 🔧 Çözüm 3: Manuel Docker Compose (Sunucuda)

Eğer EasyPanel'de sorun yaşamaya devam ediyorsanız:

```bash
# Sunucuya SSH
ssh root@your-server-ip

# Proje dizinine git
cd /opt/codexflow

# docker-compose ile deploy
docker-compose -f docker-compose.easypanel.yml up -d --build

# Logları kontrol et
docker-compose -f docker-compose.easypanel.yml logs -f
```

---

## 📝 Önemli Notlar

### 1. Docker Compose File İsmi

EasyPanel'de `docker-compose.easypanel.yml` dosyasını kullanın çünkü:
- EasyPanel'in override dosyalarıyla çakışmaz
- Daha temiz yapılandırma
- Environment variable'ları daha iyi handle eder

### 2. Environment Variables

Tüm environment variable'ları EasyPanel'in **Environment** sekmesinden ekleyin. `.env` dosyası kullanmayın çünkü EasyPanel bunu otomatik olarak handle eder.

### 3. Health Checks

Tüm servislerde health check'ler var. EasyPanel bunları otomatik olarak kullanır.

### 4. Volumes

Volumes otomatik olarak oluşturulur. EasyPanel bunları yönetir.

---

## 🧪 Test

Deployment başarılı olduktan sonra:

```bash
# Health check
curl https://api.codexflow.dev/health

# Container durumları
docker ps

# Loglar
docker logs codexflow-app
docker logs codexflow-db
docker logs codexflow-redis
docker logs codexflow-litellm
```

---

## 🆘 Hala Sorun Varsa

### 1. Logları Kontrol Et

EasyPanel'de:
- Projects → codexflow → Logs

Veya sunucuda:
```bash
docker logs codexflow-app
docker-compose -f docker-compose.easypanel.yml logs
```

### 2. Docker Compose Syntax Kontrolü

```bash
# Syntax kontrolü
docker-compose -f docker-compose.easypanel.yml config
```

### 3. Environment Variables Kontrolü

```bash
# Container içinde environment variables
docker exec codexflow-app env | grep -E "APP_|DB_|REDIS_|LITELLM_"
```

### 4. Network Kontrolü

```bash
# Network'leri listele
docker network ls

# Network detayları
docker network inspect codexflow_codexflow
```

---

## ✅ Başarı Kriterleri

Deployment başarılı olduğunda:

- ✅ Tüm container'lar çalışıyor (`docker ps`)
- ✅ Health check'ler geçiyor (`/health` endpoint)
- ✅ Database bağlantısı çalışıyor
- ✅ Redis bağlantısı çalışıyor
- ✅ LiteLLM proxy çalışıyor
- ✅ Gateway endpoint çalışıyor (`/api/v1/chat/completions`)

---

## 📞 Destek

Sorun devam ederse:

1. EasyPanel loglarını kontrol et
2. Docker compose config'i kontrol et: `docker-compose config`
3. Container loglarını kontrol et
4. Network bağlantılarını kontrol et

**En önemli nokta**: Build Type'ı **"Docker Compose"** olarak ayarlayın ve `docker-compose.easypanel.yml` dosyasını kullanın!
