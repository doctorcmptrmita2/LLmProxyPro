# CodexFlow.dev API Documentation

## Base URL
```
http://localhost:8000/api
```

## Authentication

### Gateway Endpoint (API Key)
```
Authorization: Bearer sk_your_api_key
```

### Management Endpoints (Sanctum)
```
Authorization: Bearer your_sanctum_token
```

---

## Gateway Endpoint

### POST /v1/chat/completions

OpenAI-compatible chat completion endpoint.

**Authentication**: API Key (Bearer token)

**Headers**:
```
Authorization: Bearer sk_...
X-Request-Id: optional-uuid (auto-generated if missing)
X-Quality: optional (fast|deep, defaults to auto-routing)
```

**Request Body**:
```json
{
  "model": "anthropic/claude-haiku-4-5",
  "messages": [
    {
      "role": "user",
      "content": "Hello, how are you?"
    }
  ],
  "temperature": 0.7,
  "max_tokens": 1000,
  "stream": false,
  "response_format": {
    "type": "text"
  }
}
```

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| model | string | Yes | Model identifier (e.g., `anthropic/claude-haiku-4-5`) |
| messages | array | Yes | Array of message objects with `role` and `content` |
| temperature | number | No | Sampling temperature (0-2, default: 1) |
| max_tokens | integer | No | Max tokens in response (1-128000) |
| stream | boolean | No | Enable streaming (default: false) |
| response_format | object | No | Response format (type: text\|json_object) |

**Response (Success)**:
```json
{
  "id": "chatcmpl-...",
  "object": "chat.completion",
  "created": 1234567890,
  "model": "anthropic/claude-sonnet-4-5",
  "choices": [
    {
      "index": 0,
      "message": {
        "role": "assistant",
        "content": "Hello! I'm doing well, thank you for asking."
      },
      "finish_reason": "stop"
    }
  ],
  "usage": {
    "prompt_tokens": 10,
    "completion_tokens": 15,
    "total_tokens": 25
  },
  "cost": 0.000123
}
```

**Response (Error)**:
```json
{
  "error": {
    "type": "quota_exceeded",
    "message": "Monthly token limit exceeded",
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "details": {
      "used_tokens": 950000,
      "limit": 1000000
    }
  }
}
```

**Status Codes**:
| Code | Meaning |
|------|---------|
| 200 | Success |
| 400 | Invalid request (validation error) |
| 401 | Authentication failed (invalid/missing API key) |
| 429 | Rate limited or quota exceeded |
| 500 | Server error |

**Examples**:

```bash
# Basic request
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer sk_test_hiEhiAeivZTAHh3xSJgrjUQTvULPijuO" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "anthropic/claude-haiku-4-5",
    "messages": [
      {"role": "user", "content": "Hello!"}
    ],
    "max_tokens": 100
  }'

# With quality hint (force deep tier)
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer sk_..." \
  -H "X-Quality: deep" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "anthropic/claude-haiku-4-5",
    "messages": [
      {"role": "user", "content": "Complex task..."}
    ]
  }'

# With custom request ID
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer sk_..." \
  -H "X-Request-Id: my-custom-id-123" \
  -H "Content-Type: application/json" \
  -d '{...}'
```

---

## Management Endpoints

All management endpoints require Sanctum authentication.

### Organizations

#### POST /v1/orgs

Create a new organization.

**Request**:
```json
{
  "name": "My Company"
}
```

**Response**:
```json
{
  "id": 1,
  "name": "My Company",
  "created_at": "2025-01-01T00:00:00Z",
  "updated_at": "2025-01-01T00:00:00Z"
}
```

#### GET /v1/orgs/{id}

Get organization details.

**Response**: Same as POST response

---

### Projects

#### POST /v1/projects

Create a new project.

**Request**:
```json
{
  "organization_id": 1,
  "name": "Production API",
  "plan_code": "pro",
  "monthly_token_limit": 10000000,
  "monthly_cost_limit": 1000.00
}
```

**Response**:
```json
{
  "id": 1,
  "organization_id": 1,
  "name": "Production API",
  "plan_code": "pro",
  "monthly_token_limit": 10000000,
  "monthly_cost_limit": "1000.000000",
  "created_at": "2025-01-01T00:00:00Z",
  "updated_at": "2025-01-01T00:00:00Z"
}
```

#### GET /v1/projects/{id}

Get project details.

**Response**: Same as POST response

---

### API Keys

#### POST /v1/projects/{project_id}/keys

Create a new API key.

**Request**:
```json
{
  "name": "Production Key"
}
```

**Response** (⚠️ Plaintext key shown only once):
```json
{
  "id": 1,
  "name": "Production Key",
  "key": "sk_test_hiEhiAeivZTAHh3xSJgrjUQTvULPijuO",
  "created_at": "2025-01-01T00:00:00Z"
}
```

#### GET /v1/projects/{project_id}/keys

List all API keys for a project.

**Response**:
```json
[
  {
    "id": 1,
    "name": "Production Key",
    "last_used_at": "2025-01-01T12:30:00Z",
    "revoked_at": null,
    "created_at": "2025-01-01T00:00:00Z"
  },
  {
    "id": 2,
    "name": "Staging Key",
    "last_used_at": null,
    "revoked_at": "2025-01-02T00:00:00Z",
    "created_at": "2025-01-01T00:00:00Z"
  }
]
```

#### DELETE /v1/projects/{project_id}/keys/{key_id}

Revoke an API key.

**Response**: 204 No Content

---

### Usage & Analytics

#### GET /v1/usage/daily

Get daily usage breakdown.

**Query Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| from | date | Yes | Start date (YYYY-MM-DD) |
| to | date | Yes | End date (YYYY-MM-DD) |
| project_id | integer | Yes | Project ID |

**Response**:
```json
{
  "project_id": 1,
  "from": "2025-01-01",
  "to": "2025-01-31",
  "data": [
    {
      "id": 1,
      "project_id": 1,
      "date": "2025-01-01",
      "total_tokens": 50000,
      "total_cost": "0.500000",
      "request_count": 125,
      "created_at": "2025-01-02T01:00:00Z",
      "updated_at": "2025-01-02T01:00:00Z"
    },
    {
      "id": 2,
      "project_id": 1,
      "date": "2025-01-02",
      "total_tokens": 75000,
      "total_cost": "0.750000",
      "request_count": 200,
      "created_at": "2025-01-03T01:00:00Z",
      "updated_at": "2025-01-03T01:00:00Z"
    }
  ]
}
```

#### GET /v1/usage/summary

Get monthly usage summary.

**Query Parameters**:
| Parameter | Type | Required | Description |
| month | string | Yes | Month (YYYY-MM) |
| project_id | integer | Yes | Project ID |

**Response**:
```json
{
  "project_id": 1,
  "month": "2025-01",
  "total_tokens": 2500000,
  "total_cost": "25.000000",
  "request_count": 5000
}
```

---

## Error Codes

### Authentication Errors

**401 Unauthorized**
```json
{
  "error": {
    "type": "authentication_error",
    "message": "Invalid API key",
    "request_id": "uuid"
  }
}
```

### Validation Errors

**422 Unprocessable Entity**
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "model": ["The model field is required."],
    "messages": ["The messages field is required."]
  }
}
```

### Rate Limiting

**429 Too Many Requests**
```json
{
  "error": {
    "type": "rate_limit_exceeded",
    "message": "Too many requests",
    "request_id": "uuid"
  }
}
```

### Quota Exceeded

**429 Too Many Requests**
```json
{
  "error": {
    "type": "quota_exceeded",
    "message": "Monthly token limit exceeded",
    "request_id": "uuid",
    "details": {
      "used_tokens": 950000,
      "limit": 1000000
    }
  }
}
```

### Server Errors

**500 Internal Server Error**
```json
{
  "error": {
    "type": "internal_error",
    "message": "An unexpected error occurred",
    "request_id": "uuid"
  }
}
```

---

## Rate Limiting

- **Per API Key**: Configured in LiteLLM (default: 40 RPM for fast, 20 RPM for deep)
- **Per IP**: Laravel RateLimiter (configurable)
- **Monthly Quota**: Per-project limits (tokens and/or cost)

---

## Caching

Responses are cached when:
- `temperature == 0` (deterministic)
- `stream == false` (complete response)

Cache key is based on:
- Messages
- Model
- Temperature
- Max tokens
- Routing tier

Cache TTL: 24 hours (configurable)

---

## Webhooks (Future)

Not implemented in MVP. Planned for Phase 2.

---

## SDKs

### Python
```python
import requests

response = requests.post(
    "http://localhost:8000/api/v1/chat/completions",
    headers={"Authorization": "Bearer sk_..."},
    json={
        "model": "anthropic/claude-haiku-4-5",
        "messages": [{"role": "user", "content": "Hello!"}],
        "max_tokens": 100
    }
)
print(response.json())
```

### JavaScript/Node.js
```javascript
const response = await fetch("http://localhost:8000/api/v1/chat/completions", {
  method: "POST",
  headers: {
    "Authorization": "Bearer sk_...",
    "Content-Type": "application/json"
  },
  body: JSON.stringify({
    model: "anthropic/claude-haiku-4-5",
    messages: [{ role: "user", content: "Hello!" }],
    max_tokens: 100
  })
});
const data = await response.json();
console.log(data);
```

### cURL
```bash
curl -X POST http://localhost:8000/api/v1/chat/completions \
  -H "Authorization: Bearer sk_..." \
  -H "Content-Type: application/json" \
  -d '{
    "model": "anthropic/claude-haiku-4-5",
    "messages": [{"role": "user", "content": "Hello!"}],
    "max_tokens": 100
  }'
```

---

## Changelog

### v1.0.0 (2025-01-01)
- Initial release
- OpenAI-compatible gateway
- Smart routing (fast/deep)
- Budget enforcement
- Usage tracking
- Caching support

