# CodexFlow.dev - LLM Gateway Backend

Cost-efficient LLM gateway + usage/billing platform built on Laravel 12.

## Features

- **OpenAI-compatible Gateway**: `/v1/chat/completions` endpoint
- **Smart Routing**: Fast/Deep tier routing based on request complexity
- **Budget & Limits**: Per-project token and cost limits
- **Telemetry**: Comprehensive usage tracking and analytics
- **Caching**: Deterministic request caching (temperature=0)
- **Security**: API key authentication, rate limiting, input validation
- **LiteLLM Integration**: All LLM calls go through LiteLLM proxy

## Requirements

- PHP 8.3+
- MySQL 8.0+
- Redis
- Composer
- LiteLLM Proxy (running separately)

## Installation

```bash
# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database and Redis in .env
# Configure LiteLLM settings

# Run migrations
php artisan migrate

# Start queue worker
php artisan queue:work

# Start scheduler (in production use cron)
php artisan schedule:work
```

## Configuration

### LiteLLM Setup

Configure LiteLLM proxy with multiple Claude API keys for pooling:

```yaml
# config.yaml for LiteLLM
model_list:
  # FAST pool: Claude Haiku 4.5 (3 keys)
  - model_name: fast
    litellm_params:
      model: anthropic/claude-haiku-4-5
      api_key: os.environ/ANTHROPIC_KEY_1
      rpm: 40
  - model_name: fast
    litellm_params:
      model: anthropic/claude-haiku-4-5
      api_key: os.environ/ANTHROPIC_KEY_2
      rpm: 40
  - model_name: fast
    litellm_params:
      model: anthropic/claude-haiku-4-5
      api_key: os.environ/ANTHROPIC_KEY_3
      rpm: 40

  # DEEP pool: Claude Sonnet 4.5 (3 keys)
  - model_name: deep
    litellm_params:
      model: anthropic/claude-sonnet-4-5
      api_key: os.environ/ANTHROPIC_KEY_1
      rpm: 20
  - model_name: deep
    litellm_params:
      model: anthropic/claude-sonnet-4-5
      api_key: os.environ/ANTHROPIC_KEY_2
      rpm: 20
  - model_name: deep
    litellm_params:
      model: anthropic/claude-sonnet-4-5
      api_key: os.environ/ANTHROPIC_KEY_3
      rpm: 20
```

### Environment Variables

```env
# LiteLLM Configuration
LITELLM_BASE_URL=http://localhost:4000
LITELLM_API_KEY=your_litellm_key
LITELLM_TIMEOUT=120
LITELLM_CONNECT_TIMEOUT=10
LITELLM_MAX_RETRIES=2
LITELLM_RETRY_DELAY_MS=500

# Routing
LARGE_REQUEST_THRESHOLD=8000

# Cache
LITELLM_CACHE_ENABLED=true
LITELLM_CACHE_TTL=86400
CACHE_DRIVER=redis

# Logging
LOG_PROMPTS=false
LOG_RESPONSES=false

# Decomposition (optional)
DECOMPOSITION_ENABLED=true
```

## API Usage

### Gateway Endpoint

```bash
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer sk_your_api_key" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "anthropic/claude-haiku-4-5",
    "messages": [
      {"role": "user", "content": "Hello!"}
    ],
    "temperature": 0.7,
    "max_tokens": 1000
  }'
```

### Management Endpoints (Sanctum Auth)

```bash
# Create organization
POST /api/v1/orgs
{
  "name": "My Organization"
}

# Create project
POST /api/v1/projects
{
  "organization_id": 1,
  "name": "My Project",
  "monthly_token_limit": 1000000,
  "monthly_cost_limit": 100.00
}

# Create API key
POST /api/v1/projects/{project}/keys
{
  "name": "Production Key"
}

# Get usage
GET /api/v1/usage/daily?from=2025-01-01&to=2025-01-31&project_id=1
GET /api/v1/usage/summary?month=2025-01&project_id=1
```

## Architecture

### Layers

- **Controllers**: HTTP request handling
- **Middleware**: Authentication, rate limiting, plan enforcement
- **Services**: Business logic (LiteLLM client, router, gateway)
- **Models**: Database entities
- **Jobs**: Background processing (aggregation, pruning)

### Request Flow

1. Request arrives at `/v1/chat/completions`
2. `EnsureRequestId` middleware adds request ID
3. `AuthenticateProjectApiKey` validates API key
4. `EnforcePlanLimits` checks quotas
5. `GatewayService` processes request:
   - Routes to fast/deep tier
   - Checks cache
   - Calls LiteLLM with failover
   - Records telemetry
6. Response returned to client

## Testing

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --filter GatewayTest
```

## Scheduled Jobs

- **Daily 01:00**: Aggregate usage data
- **Weekly Sunday 02:00**: Prune old request logs

## Security

- API keys stored hashed (bcrypt)
- Rate limiting per key and IP
- Input validation on all endpoints
- No PII in logs (unless `LOG_PROMPTS=true`)
- CSRF protection on web routes
- SQL injection protection via Eloquent

## License

Proprietary
