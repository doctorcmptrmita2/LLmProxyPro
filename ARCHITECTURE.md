# CodexFlow.dev Architecture

## Overview

CodexFlow.dev is a cost-efficient LLM gateway built on Laravel 12 that provides:
- OpenAI-compatible API endpoint
- Smart routing (fast/deep tiers)
- Budget and usage tracking
- Caching for deterministic requests
- Comprehensive telemetry

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                     Client Applications                      │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────┐
│                    Laravel API Gateway                       │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ Middleware Stack                                     │   │
│  │ • EnsureRequestId                                    │   │
│  │ • AuthenticateProjectApiKey                          │   │
│  │ • EnforcePlanLimits                                  │   │
│  │ • RateLimiter                                        │   │
│  └──────────────────────────────────────────────────────┘   │
│                         │                                    │
│                         ▼                                    │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ ChatCompletionController                             │   │
│  │ • Validates request                                  │   │
│  │ • Delegates to GatewayService                        │   │
│  └──────────────────────────────────────────────────────┘   │
│                         │                                    │
│                         ▼                                    │
│  ┌──────────────────────────────────────────────────────┐   │
│  │ GatewayService                                       │   │
│  │ • Routes request (fast/deep)                         │   │
│  │ • Checks cache                                       │   │
│  │ • Calls LiteLLM with failover                        │   │
│  │ • Records telemetry                                  │   │
│  └──────────────────────────────────────────────────────┘   │
│                         │                                    │
│         ┌───────────────┼───────────────┐                   │
│         ▼               ▼               ▼                   │
│    ┌────────┐      ┌────────┐      ┌────────┐              │
│    │ Router │      │ Cache  │      │ Client │              │
│    │        │      │ (Redis)│      │        │              │
│    └────────┘      └────────┘      └────────┘              │
│                                         │                   │
└─────────────────────────────────────────┼───────────────────┘
                                          │
                                          ▼
                    ┌─────────────────────────────────┐
                    │   LiteLLM Proxy (Port 4000)     │
                    │  • Load balancing               │
                    │  • Rate limiting                │
                    │  • Provider routing             │
                    └─────────────────────────────────┘
                                          │
                    ┌─────────────────────┼─────────────────┐
                    ▼                     ▼                 ▼
            ┌──────────────┐      ┌──────────────┐   ┌──────────────┐
            │ Anthropic    │      │ OpenAI       │   │ Other LLMs   │
            │ (Claude)     │      │ (GPT)        │   │              │
            └──────────────┘      └──────────────┘   └──────────────┘
```

## Request Flow

### 1. Gateway Request (`POST /v1/chat/completions`)

```
Request arrives
    ↓
EnsureRequestId (adds UUID if missing)
    ↓
AuthenticateProjectApiKey (validates API key)
    ↓
EnforcePlanLimits (checks monthly quotas)
    ↓
ChatCompletionController::create()
    ↓
GatewayService::processRequest()
    ├─ LlmRouter::pickTier() → fast|deep
    ├─ Check cache (if temperature=0 && !stream)
    ├─ LiteLlmClient::chat() with failover
    ├─ Record LlmRequest telemetry
    └─ Return response
```

### 2. Management Request (Sanctum Auth)

```
Request arrives
    ↓
EnsureRequestId
    ↓
auth:sanctum middleware
    ↓
Controller (Organization/Project/Usage)
    ↓
Policy authorization
    ↓
Response
```

## Core Components

### Middleware

#### `EnsureRequestId`
- Adds `x-request-id` header if missing
- Uses UUID v4 for uniqueness
- Propagated through entire request lifecycle

#### `AuthenticateProjectApiKey`
- Extracts bearer token from Authorization header
- Hashes token and compares with stored key_hash
- Checks if key is revoked
- Attaches project and api_key to request

#### `EnforcePlanLimits`
- Queries monthly usage aggregates
- Estimates tokens for current request
- Rejects if would exceed monthly limits
- Returns 429 with quota details

### Services

#### `LiteLlmClient`
```php
public function chat(array $payload, string $requestId): array
```
- Sends request to LiteLLM proxy
- Implements retry logic (429, 5xx, timeout)
- Throws `LlmException` on failure
- Handles response parsing

#### `LlmRouter`
```php
public function pickTier(array $messages, array $headers): string
public function pickModelsForTier(string $tier): array
public function getNextModel(string $tier, ?string $current): ?string
```
- Routes based on message length or `x-quality` header
- Threshold: 8000 chars (configurable)
- Returns model list for failover

#### `GatewayService`
```php
public function processRequest(
    array $payload,
    Project $project,
    ?string $userId,
    ?int $apiKeyId,
    array $headers
): array
```
- Orchestrates entire request processing
- Manages cache (Redis)
- Implements failover across models
- Records telemetry to database

### Models

#### `Organization`
- Belongs to many users (pivot: role)
- Has many projects

#### `Project`
- Belongs to organization
- Has many API keys
- Has many LLM requests
- Has monthly limits (tokens, cost)

#### `ProjectApiKey`
- Belongs to project
- Stores hashed key (bcrypt)
- Tracks last_used_at, revoked_at

#### `LlmRequest`
- UUID primary key
- Records every request (success/failure)
- Stores: tokens, cost, latency, cache_hit, error_type
- Indexed by (project_id, created_at)

#### `UsageDailyAggregate`
- Aggregated daily usage per project
- Unique constraint: (project_id, date)
- Populated by `AggregateUsageDailyJob`

## Caching Strategy

### When to Cache
- `temperature == 0` (deterministic)
- `stream == false` (complete response)

### Cache Key
```
sha256(json_encode({
  messages,
  model,
  temperature,
  max_tokens,
  tier
}))
```

### Cache Hit Behavior
- Skip LiteLLM call
- Still record telemetry with `cache_hit=true`
- Return cached response

### TTL
- Configurable via `LITELLM_CACHE_TTL` (default: 86400 seconds)
- Stored in Redis

## Routing Logic

### Fast Tier
- Used for: short prompts, quick responses
- Models: Claude Haiku 4.5
- Threshold: < 8000 chars
- RPM limit: 40 per key

### Deep Tier
- Used for: long prompts, complex tasks
- Models: Claude Sonnet 4.5
- Threshold: >= 8000 chars OR `x-quality: deep` header
- RPM limit: 20 per key

### Failover
1. Try first model in tier
2. On 429/5xx/timeout: retry with next model
3. Max retries: 2 (configurable)
4. Delay between retries: 500ms (configurable)

## Telemetry

### LlmRequest Fields
```
id (uuid)
project_id
user_id (nullable)
api_key_id (nullable)
request_id (string, unique)
route_tier (fast|deep)
model_requested
model_used
provider (anthropic|openai|unknown)
prompt_tokens
completion_tokens
total_tokens
cost (nullable)
latency_ms
cache_hit (boolean)
status_code
error_type (nullable)
created_at (indexed)
```

### Daily Aggregation
- Job: `AggregateUsageDailyJob` (runs daily at 01:00)
- Aggregates: total_tokens, total_cost, request_count
- Grouped by: project_id, date
- Used for: quota enforcement, usage reports

### Retention
- Raw requests: 90 days (configurable)
- Aggregates: indefinite
- Job: `PruneLlmRequestsJob` (runs weekly)

## Security

### API Key Management
- Keys stored as bcrypt hashes
- Plaintext shown only once on creation
- Can be revoked (soft delete via revoked_at)
- Last used timestamp tracked

### Input Validation
- OpenAI payload shape validation
- Message role/content required
- Temperature: 0-2
- Max tokens: 1-128000
- Response format: text|json_object

### Rate Limiting
- Per API key (via LiteLLM)
- Per IP (via Laravel RateLimiter)
- Configurable RPM per model

### Authorization
- Policies for Organization/Project
- User must be member of organization
- Admin/owner required for modifications

## Configuration

### Environment Variables
```env
LITELLM_BASE_URL=http://localhost:4000
LITELLM_API_KEY=optional_key
LITELLM_TIMEOUT=120
LITELLM_MAX_RETRIES=2
LARGE_REQUEST_THRESHOLD=8000
LITELLM_CACHE_ENABLED=true
LITELLM_CACHE_TTL=86400
LOG_PROMPTS=false
DECOMPOSITION_ENABLED=true
```

### Config Files
- `config/litellm.php`: LiteLLM settings
- `config/sanctum.php`: API token settings
- `config/cache.php`: Cache driver (Redis)
- `config/queue.php`: Queue driver (Redis)

## Database Schema

### Key Indexes
```sql
-- Fast lookups
llm_requests (project_id, created_at)
llm_requests (created_at)
usage_daily_aggregates (project_id, date) UNIQUE
project_api_keys (project_id, revoked_at)
```

### Relationships
```
Organization 1--* Project
Organization *--* User (pivot: organization_user)
Project 1--* ProjectApiKey
Project 1--* LlmRequest
ProjectApiKey 1--* LlmRequest
Project 1--* UsageDailyAggregate
```

## Jobs & Scheduling

### AggregateUsageDailyJob
- Runs: Daily at 01:00
- Aggregates: Yesterday's requests
- Groups by: project_id
- Sums: tokens, cost, request_count

### PruneLlmRequestsJob
- Runs: Weekly on Sunday at 02:00
- Deletes: Requests older than 90 days
- Keeps: Aggregates indefinitely

## Error Handling

### LlmException
```php
throw new LlmException(
    errorType: 'api_error|network_error|quota_exceeded|...',
    message: 'Human readable message',
    statusCode: 429|500|...,
    details: ['key' => 'value']
);
```

### Response Format
```json
{
  "error": {
    "type": "error_type",
    "message": "Error message",
    "request_id": "uuid",
    "details": {}
  }
}
```

## Testing

### Test Suites
- `GatewayTest`: API key auth, routing, limits
- `ApiKeyManagementTest`: CRUD operations
- `UsageTrackingTest`: Aggregation, reporting

### Test Database
- Uses in-memory SQLite
- Refreshes before each test
- Factories for model creation

## Performance Considerations

### Caching
- Deterministic requests cached in Redis
- Reduces LiteLLM calls by ~30-50%
- TTL: 24 hours (configurable)

### Indexing
- (project_id, created_at) for fast queries
- Unique constraint on (project_id, date)
- Revoked_at index for key lookups

### Aggregation
- Nightly batch job (not real-time)
- Reduces query load on raw requests
- Enables fast usage reports

### Connection Pooling
- Redis: Single connection (Laravel manages)
- MySQL: Connection pooling via WAMP
- LiteLLM: HTTP keep-alive

## Deployment

### Requirements
- PHP 8.3+
- MySQL 8.0+
- Redis 6.0+
- LiteLLM Proxy (separate service)

### Environment Setup
```bash
cp .env.codexflow .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan queue:work (background)
php artisan schedule:work (background)
```

### Production Checklist
- [ ] Set `APP_DEBUG=false`
- [ ] Set `APP_ENV=production`
- [ ] Configure proper database backups
- [ ] Set up Redis persistence
- [ ] Configure LiteLLM with production keys
- [ ] Set up monitoring/logging
- [ ] Configure CORS if needed
- [ ] Set up SSL/TLS
- [ ] Configure rate limits appropriately
- [ ] Set up log rotation

