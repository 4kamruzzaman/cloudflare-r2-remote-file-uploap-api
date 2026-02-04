# 📘 Technical Reference

> Detailed specifications for the R2 Upload Service SaaS platform.

---

## Table of Contents
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
- [BYOB Configuration](#byob-configuration)
- [Infrastructure](#infrastructure)
- [Cloudflare Workers](#cloudflare-workers)
- [Security](#security)
- [Pricing Details](#pricing-details)
- [Deployment](#deployment)

---

## Database Schema

### Cloudflare D1 (SQLite)

```sql
-- Users/Tenants
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uuid TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    password TEXT NOT NULL,
    name TEXT,
    company_name TEXT,
    plan_id INTEGER DEFAULT 1,
    status TEXT DEFAULT 'active' CHECK(status IN ('active', 'suspended', 'deleted')),
    email_verified_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
);

-- Storage Connections (BYOB)
CREATE TABLE storage_connections (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    name TEXT NOT NULL DEFAULT 'My Storage',
    provider TEXT NOT NULL DEFAULT 'managed', 
    -- managed, r2, s3, spaces, b2, minio, wasabi, custom
    is_default INTEGER DEFAULT 0,
    endpoint_url TEXT,
    region TEXT DEFAULT 'auto',
    bucket_name TEXT NOT NULL,
    access_key_id_encrypted TEXT NOT NULL,
    secret_access_key_encrypted TEXT NOT NULL,
    custom_domain TEXT,
    path_prefix TEXT DEFAULT '',
    is_validated INTEGER DEFAULT 0,
    validated_at TEXT,
    last_error TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- API Keys
CREATE TABLE api_keys (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    key_hash TEXT NOT NULL,
    key_prefix TEXT NOT NULL, -- First 8 chars for display
    name TEXT,
    permissions TEXT, -- JSON: {"upload": true, "delete": false}
    rate_limit INTEGER DEFAULT 1000,
    last_used_at TEXT,
    expires_at TEXT,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Uploads
CREATE TABLE uploads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    storage_connection_id INTEGER,
    object_key TEXT NOT NULL,
    original_filename TEXT,
    mime_type TEXT,
    size_bytes INTEGER DEFAULT 0,
    status TEXT DEFAULT 'pending' CHECK(status IN ('pending', 'processing', 'completed', 'failed')),
    visibility TEXT DEFAULT 'public' CHECK(visibility IN ('public', 'private', 'signed')),
    access_mode TEXT DEFAULT 'inline' CHECK(access_mode IN ('inline', 'attachment')),
    file_url TEXT,
    message TEXT,
    retries INTEGER DEFAULT 0,
    original_url TEXT, -- For remote URL uploads
    download_time_ms INTEGER,
    upload_time_ms INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (storage_connection_id) REFERENCES storage_connections(id)
);
CREATE UNIQUE INDEX idx_uploads_user_key ON uploads(user_id, object_key);

-- Usage Logs
CREATE TABLE usage_logs (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    api_key_id INTEGER,
    action TEXT CHECK(action IN ('upload', 'download', 'delete', 'list')),
    bytes_transferred INTEGER DEFAULT 0,
    object_key TEXT,
    ip_address TEXT,
    user_agent TEXT,
    response_code INTEGER,
    response_time_ms INTEGER,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_usage_user_date ON usage_logs(user_id, created_at);

-- Plans
CREATE TABLE plans (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    slug TEXT UNIQUE NOT NULL,
    storage_limit_bytes INTEGER NOT NULL, -- -1 = unlimited
    bandwidth_limit_bytes INTEGER NOT NULL,
    requests_per_month INTEGER NOT NULL,
    max_file_size_bytes INTEGER NOT NULL,
    max_byob_connections INTEGER DEFAULT 1,
    max_api_keys INTEGER DEFAULT 1,
    max_team_members INTEGER DEFAULT 1,
    price_monthly_cents INTEGER,
    price_yearly_cents INTEGER,
    stripe_price_id_monthly TEXT,
    stripe_price_id_yearly TEXT,
    features TEXT, -- JSON
    is_active INTEGER DEFAULT 1
);

-- Default Plans (All features enabled, usage-limited)
-- Philosophy: No feature locks, just different limits
INSERT INTO plans (name, slug, storage_limit_bytes, bandwidth_limit_bytes, requests_per_month, max_file_size_bytes, max_byob_connections, max_api_keys, max_team_members, price_monthly_cents, price_yearly_cents) VALUES
('Free', 'free', 524288000, 2147483648, 500, 52428800, 1, 1, 1, 0, 0),
-- 500MB storage, 2GB bandwidth, 500 calls, 50MB max file, 1 BYOB, 1 key, 1 member

('Starter', 'starter', 53687091200, 107374182400, 50000, 524288000, 3, 5, 3, 1900, 19000),
-- 50GB storage, 100GB bandwidth, 50K calls, 500MB max file, 3 BYOB, 5 keys, 3 members

('Pro', 'pro', 536870912000, 1099511627776, 500000, 5368709120, 10, 20, 10, 7900, 79000),
-- 500GB storage, 1TB bandwidth, 500K calls, 5GB max file, 10 BYOB, 20 keys, 10 members

('Enterprise', 'enterprise', -1, -1, -1, -1, -1, -1, -1, NULL, NULL);
-- Unlimited everything, custom pricing

-- Webhooks
CREATE TABLE webhooks (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    url TEXT NOT NULL,
    events TEXT NOT NULL, -- JSON: ["upload.completed", "upload.failed"]
    secret TEXT,
    is_active INTEGER DEFAULT 1,
    last_triggered_at TEXT,
    failure_count INTEGER DEFAULT 0,
    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## API Endpoints

### Authentication
```
POST   /api/v1/auth/register       Register new user
POST   /api/v1/auth/login          Login, get JWT token
POST   /api/v1/auth/logout         Revoke token
POST   /api/v1/auth/refresh        Refresh JWT token
POST   /api/v1/auth/forgot-password
POST   /api/v1/auth/reset-password
```

### API Keys
```
GET    /api/v1/keys                List API keys
POST   /api/v1/keys                Create new key
GET    /api/v1/keys/{id}           Get key details
PATCH  /api/v1/keys/{id}           Update key
DELETE /api/v1/keys/{id}           Delete key
POST   /api/v1/keys/{id}/rotate    Rotate key
```

### Files
```
POST   /api/v1/files/upload        Direct file upload
POST   /api/v1/files/upload-url    Remote URL upload
POST   /api/v1/files/presign       Get presigned upload URL
GET    /api/v1/files               List files (paginated)
GET    /api/v1/files/{key}         File details
GET    /api/v1/files/{key}/url     Get download URL
DELETE /api/v1/files/{key}         Delete file
POST   /api/v1/files/bulk-delete   Delete multiple files
```

### Storage Connections (BYOB)
```
GET    /api/v1/storage             List connections
POST   /api/v1/storage             Add connection
GET    /api/v1/storage/{id}        Get connection
PATCH  /api/v1/storage/{id}        Update connection
DELETE /api/v1/storage/{id}        Remove connection
POST   /api/v1/storage/{id}/test   Test connection
POST   /api/v1/storage/{id}/default Set as default
```

### Webhooks
```
GET    /api/v1/webhooks            List webhooks
POST   /api/v1/webhooks            Create webhook
PATCH  /api/v1/webhooks/{id}       Update webhook
DELETE /api/v1/webhooks/{id}       Delete webhook
POST   /api/v1/webhooks/{id}/test  Test webhook
```

### Account & Usage
```
GET    /api/v1/account             Get account info
PATCH  /api/v1/account             Update account
GET    /api/v1/usage               Usage summary
GET    /api/v1/usage/bandwidth     Bandwidth details
GET    /api/v1/usage/storage       Storage breakdown
```

---

## BYOB Configuration

### Supported Providers

| Provider | Endpoint Template | Region Required |
|----------|-------------------|-----------------|
| Cloudflare R2 | `https://{account_id}.r2.cloudflarestorage.com` | No (auto) |
| AWS S3 | `https://s3.{region}.amazonaws.com` | Yes |
| DigitalOcean Spaces | `https://{region}.digitaloceanspaces.com` | Yes |
| Backblaze B2 | `https://s3.{region}.backblazeb2.com` | Yes |
| Wasabi | `https://s3.{region}.wasabisys.com` | Yes |
| MinIO | Custom endpoint | No |

### Provider Presets
```json
{
  "r2": {
    "name": "Cloudflare R2",
    "endpoint_template": "https://{account_id}.r2.cloudflarestorage.com",
    "requires": ["account_id", "access_key_id", "secret_access_key", "bucket_name"]
  },
  "s3": {
    "name": "Amazon S3",
    "endpoint_template": "https://s3.{region}.amazonaws.com",
    "regions": ["us-east-1", "us-west-2", "eu-west-1", "ap-southeast-1"],
    "requires": ["region", "access_key_id", "secret_access_key", "bucket_name"]
  },
  "spaces": {
    "name": "DigitalOcean Spaces",
    "endpoint_template": "https://{region}.digitaloceanspaces.com",
    "regions": ["nyc3", "sfo3", "ams3", "sgp1", "fra1"],
    "requires": ["region", "access_key_id", "secret_access_key", "bucket_name"]
  },
  "b2": {
    "name": "Backblaze B2",
    "endpoint_template": "https://s3.{region}.backblazeb2.com",
    "requires": ["region", "access_key_id", "secret_access_key", "bucket_name"]
  },
  "wasabi": {
    "name": "Wasabi",
    "endpoint_template": "https://s3.{region}.wasabisys.com",
    "regions": ["us-east-1", "us-east-2", "us-west-1", "eu-central-1"],
    "requires": ["region", "access_key_id", "secret_access_key", "bucket_name"]
  },
  "minio": {
    "name": "MinIO (Self-hosted)",
    "requires": ["endpoint_url", "access_key_id", "secret_access_key", "bucket_name"]
  },
  "custom": {
    "name": "Custom S3-Compatible",
    "requires": ["endpoint_url", "access_key_id", "secret_access_key", "bucket_name"]
  }
}
```

---

## Infrastructure

### Phase 1: Single VPS
```
Cost: ~$25/month

┌─────────────────────────────────────────────┐
│              Cloudflare                      │
│   DNS + CDN + WAF + Tunnel                  │
└──────────────────┬──────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │   Hetzner VPS       │
        │   CX32 ($17/mo)     │
        │                     │
        │  ┌───────────────┐  │
        │  │ Laravel + PHP │  │
        │  │ 8.4 + Octane  │  │
        │  └───────────────┘  │
        │  ┌───────────────┐  │
        │  │    Redis      │  │
        │  │ Queue/Cache   │  │
        │  └───────────────┘  │
        └──────────┬──────────┘
                   │
    ┌──────────────┼──────────────┐
    │              │              │
┌───▼───┐     ┌────▼────┐    ┌────▼────┐
│  D1   │     │   R2    │    │  BYOB   │
│  DB   │     │ Storage │    │ Storage │
└───────┘     └─────────┘    └─────────┘
```

### Phase 2: Multi-VPS (1000+ users)
```
Cost: ~$80/month

- 2× VPS for API (load balanced)
- 1× VPS for queue workers
- Managed Redis (Upstash)
- Cloudflare Load Balancer
```

### Phase 3: Kubernetes (10K+ users)
```
Cost: $300-500/month

Only when:
- Revenue > $10K/month
- Need auto-scaling
- Multi-region required
```

---

## Cloudflare Workers

### Use Cases

1. **Presigned URL Generation**
   - Generate upload/download URLs at edge
   - Works with any S3 provider (BYOB)
   - Sub-10ms latency globally

2. **Rate Limiting**
   - Block abuse before hitting origin
   - Per-IP, per-API-key limits
   - No Redis required

3. **Direct R2 Uploads**
   - Files go directly to R2
   - Bypass VPS bandwidth
   - Faster uploads

4. **Access Control**
   - JWT validation at edge
   - Signed URL verification
   - IP restrictions

### Worker Example (Presigned URL)
```javascript
export default {
  async fetch(request, env) {
    const { user, filename, storageId } = await request.json();
    
    // Fetch user's storage config from D1
    const storage = await env.DB.prepare(
      'SELECT * FROM storage_connections WHERE id = ? AND user_id = ?'
    ).bind(storageId, user.id).first();
    
    // Generate presigned URL (works for any S3 provider)
    const url = await generatePresignedUrl({
      endpoint: storage.endpoint_url,
      bucket: storage.bucket_name,
      region: storage.region,
      accessKeyId: decrypt(storage.access_key_id_encrypted, env.ENCRYPTION_KEY),
      secretAccessKey: decrypt(storage.secret_access_key_encrypted, env.ENCRYPTION_KEY),
      key: `${user.uuid}/${filename}`,
      expiresIn: 3600
    });
    
    return Response.json({ uploadUrl: url });
  }
}
```

---

## Security

### Zero-Vulnerability Pipeline

Every deployment goes through this security gauntlet:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        SECURITY PIPELINE                                    │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                             │
│  1. CODE PUSH                                                               │
│     │                                                                       │
│     ├──► PHPStan Level 9 ──────► Catch PHP bugs before runtime             │
│     ├──► TypeScript strict ────► Catch JS/TS bugs at compile               │
│     ├──► ESLint + Prettier ────► Code quality & consistency                │
│     │                                                                       │
│  2. DEPENDENCY SCAN                                                         │
│     │                                                                       │
│     ├──► composer audit ───────► PHP vulnerability database                │
│     ├──► pnpm audit ───────────► NPM vulnerability database                │
│     ├──► Snyk scan ────────────► Deep dependency analysis                  │
│     │                                                                       │
│  3. SECRET DETECTION                                                        │
│     │                                                                       │
│     ├──► GitLeaks ─────────────► Prevent secrets in commits                │
│     ├──► TruffleHog ───────────► Find leaked credentials                   │
│     │                                                                       │
│  4. CONTAINER SCAN                                                          │
│     │                                                                       │
│     ├──► Trivy ────────────────► Scan Docker images for CVEs               │
│     ├──► Hadolint ─────────────► Dockerfile best practices                 │
│     │                                                                       │
│  5. DEPLOY (only if all pass)                                               │
│     │                                                                       │
│     └──► Kamal ────────────────► Zero-downtime deployment                  │
│                                                                             │
└─────────────────────────────────────────────────────────────────────────────┘
```

### GitHub Actions Security Workflow

```yaml
# .github/workflows/security.yml
name: Security

on:
  push:
    branches: [main, develop]
  pull_request:
  schedule:
    - cron: '0 0 * * *'  # Daily scan

jobs:
  # PHP Security
  php-security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          
      - name: Install dependencies
        run: composer install --no-dev
        
      - name: PHPStan (Level 9)
        run: ./vendor/bin/phpstan analyse --level=9 --memory-limit=1G
        
      - name: Composer Audit
        run: composer audit --format=json
        
      - name: PHP Security Checker
        uses: symfonycorp/security-checker-action@v5

  # JavaScript Security
  js-security:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: ./frontend
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: TypeScript Check
        run: pnpm type-check
        
      - name: ESLint
        run: pnpm lint
        
      - name: pnpm Audit
        run: pnpm audit --audit-level=moderate

  # Dependency Scanning
  snyk:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Snyk PHP
        uses: snyk/actions/php@master
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}
        with:
          args: --severity-threshold=high
          
      - name: Snyk Node
        uses: snyk/actions/node@master
        env:
          SNYK_TOKEN: ${{ secrets.SNYK_TOKEN }}
        with:
          args: --severity-threshold=high

  # Secret Detection
  secrets:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0
          
      - name: GitLeaks
        uses: gitleaks/gitleaks-action@v2
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          
      - name: TruffleHog
        uses: trufflesecurity/trufflehog@main
        with:
          extra_args: --only-verified

  # Container Security
  container:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Build image
        run: docker build -t r2-upload:scan .
        
      - name: Trivy Scan
        uses: aquasecurity/trivy-action@master
        with:
          image-ref: 'r2-upload:scan'
          format: 'sarif'
          output: 'trivy-results.sarif'
          severity: 'CRITICAL,HIGH'
          
      - name: Upload Trivy results
        uses: github/codeql-action/upload-sarif@v3
        with:
          sarif_file: 'trivy-results.sarif'

  # Block deploy if any security job fails
  security-gate:
    needs: [php-security, js-security, snyk, secrets, container]
    runs-on: ubuntu-latest
    steps:
      - name: Security Gate Passed
        run: echo "All security checks passed!"
```

### Dependabot Configuration

```yaml
# .github/dependabot.yml
version: 2
updates:
  # PHP dependencies
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "daily"
    open-pull-requests-limit: 10
    labels:
      - "dependencies"
      - "security"
    
  # NPM dependencies
  - package-ecosystem: "npm"
    directory: "/frontend"
    schedule:
      interval: "daily"
    open-pull-requests-limit: 10
    labels:
      - "dependencies"
      - "security"
      
  # Docker base images
  - package-ecosystem: "docker"
    directory: "/"
    schedule:
      interval: "weekly"
    labels:
      - "dependencies"
      - "security"
      
  # GitHub Actions
  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
```

### Authentication
- **User Auth**: Laravel Sanctum (JWT tokens)
- **API Auth**: API keys with prefix (e.g., `r2u_live_xxxx`)
- **2FA**: TOTP for dashboard access
- **Session**: Encrypted, HTTP-only cookies

### Encryption
- Credentials encrypted at rest (AES-256-GCM)
- HTTPS everywhere (Cloudflare)
- Signed URLs for private files
- Database field encryption for BYOB credentials

### Rate Limiting
```php
// Per-IP limits (everyone)
'ip' => [
    'requests' => 100,
    'per_minutes' => 1,
],

// Per-API-key limits (by plan)
'api_key' => [
    'free' => ['requests' => 500, 'per_month' => true],
    'starter' => ['requests' => 50000, 'per_month' => true],
    'pro' => ['requests' => 500000, 'per_month' => true],
],
```

### File Security
- Magic byte validation (not just extension)
- Blocked extensions: exe, bat, sh, php, phar, etc.
- Max file size per plan
- Filename sanitization (prevent path traversal)
- Virus scanning with ClamAV

### Infrastructure Security
- Cloudflare WAF (OWASP rules)
- DDoS protection (Cloudflare)
- Cloudflare Tunnel (no exposed ports)
- Fail2ban for SSH
- Automatic security updates (unattended-upgrades)

---

## Pricing Details

### All Features for Everyone (Usage-Limited)

**Philosophy**: No feature locks. Free users get everything, just smaller limits.

```
┌─────────────┬──────────┬──────────┬──────────┬────────────┐
│   Feature   │   Free   │  Starter │   Pro    │ Enterprise │
├─────────────┼──────────┼──────────┼──────────┼────────────┤
│ Storage     │  500 MB  │   50 GB  │  500 GB  │ Unlimited  │
│ Bandwidth   │   2 GB   │  100 GB  │    1 TB  │ Unlimited  │
│ Max File    │   50 MB  │  500 MB  │    5 GB  │ Unlimited  │
│ API Calls   │  500/mo  │  50K/mo  │  500K/mo │ Unlimited  │
│ BYOB        │ 1 bucket │ 3 buckets│ 10 bucket│ Unlimited  │
│ Remote URL  │    ✅    │    ✅    │    ✅    │     ✅     │
│ Webhooks    │    ✅    │    ✅    │    ✅    │     ✅     │
│ API Keys    │    1     │    5     │    20    │ Unlimited  │
│ Team        │    1     │    3     │    10    │ Unlimited  │
│ Support     │Community │  Email   │ Priority │ Dedicated  │
│ Price/mo    │   $0     │   $19    │   $79    │  Custom    │
│ Annual      │   $0     │  $190    │  $790    │  Custom    │
└─────────────┴──────────┴──────────┴──────────┴────────────┘
✅ = Available for ALL plans (with usage limits)
```

### Soft Limits (User-Friendly)
```
At 80%:  Warning notification
At 90%:  Urgent warning + upgrade prompt
At 100%: Pause new uploads (never delete user data)
         Option: Pay overage ($0.02/GB) or upgrade
```

### Overage Pricing
| Resource | Overage Rate |
|----------|--------------|
| Storage | $0.02/GB/month |
| Bandwidth | $0.01/GB |
| API Calls | $0.50/10K calls |
| Image transforms | $1/1000 |

### Why Free Users Won't Cause Losses
| Limit | Cost to Us | Notes |
|-------|------------|-------|
| 500MB storage | ~$0.01/user | Negligible |
| 2GB bandwidth | $0 | R2 = free egress |
| 500 API calls | ~$0.001 | Minimal compute |
| 1 BYOB bucket | $0 | Their storage, not ours |

---

## Deployment

### Zero-Downtime with Kamal

Kamal provides Docker-based blue-green deployments with automatic rollback.

#### Kamal Configuration
```yaml
# config/deploy.yml
service: r2-upload
image: your-registry/r2-upload

servers:
  web:
    hosts:
      - 167.235.1.1      # VPS #1
      # - 167.235.1.2    # Add more for scaling
    options:
      memory: 512m
    labels:
      traefik.http.routers.r2-upload.rule: Host(`api.yourdomain.com`)

  worker:
    hosts:
      - 167.235.1.1      # Same or separate VPS
    cmd: php artisan horizon
    options:
      memory: 256m

registry:
  server: ghcr.io
  username: 
    - KAMAL_REGISTRY_USERNAME
  password:
    - KAMAL_REGISTRY_PASSWORD

env:
  clear:
    APP_ENV: production
    LOG_LEVEL: warning
  secret:
    - APP_KEY
    - DB_PASSWORD
    - R2_ACCESS_KEY
    - R2_SECRET_KEY

traefik:
  options:
    publish:
      - "443:443"
    volume:
      - "/letsencrypt:/letsencrypt"
  args:
    entryPoints.websecure.address: ":443"
    certificatesResolvers.letsencrypt.acme.email: "ssl@yourdomain.com"
    certificatesResolvers.letsencrypt.acme.storage: "/letsencrypt/acme.json"
    certificatesResolvers.letsencrypt.acme.httpchallenge: true

healthcheck:
  path: /health
  port: 8000
  interval: 10s
```

#### Scaling (Add More Servers)
```yaml
# Just add hosts for horizontal scaling
servers:
  web:
    hosts:
      - 167.235.1.1    # VPS #1
      - 167.235.1.2    # VPS #2 (new)
      - 167.235.1.3    # VPS #3 (new)
  worker:
    hosts:
      - 167.235.2.1    # Dedicated worker VPS
      - 167.235.2.2    # More workers
```

### CI/CD Pipeline (GitHub Actions)

```yaml
# .github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [main]

env:
  KAMAL_REGISTRY_USERNAME: ${{ github.actor }}
  KAMAL_REGISTRY_PASSWORD: ${{ secrets.GITHUB_TOKEN }}

jobs:
  # 1. Backend Tests
  test-backend:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: redis, pdo_sqlite
          
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
        
      - name: Run PHPStan
        run: ./vendor/bin/phpstan analyse --memory-limit=512M
        
      - name: Run Pest tests
        run: php artisan test --parallel
        env:
          DB_CONNECTION: sqlite
          DB_DATABASE: ":memory:"

  # 2. Frontend Tests  
  test-frontend:
    runs-on: ubuntu-latest
    defaults:
      run:
        working-directory: ./frontend
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Node
        uses: actions/setup-node@v4
        with:
          node-version: '22'
          cache: 'pnpm'
          
      - name: Install dependencies
        run: pnpm install --frozen-lockfile
        
      - name: Type check
        run: pnpm type-check
        
      - name: Lint
        run: pnpm lint
        
      - name: Build
        run: pnpm build

  # 3. Deploy (only if tests pass)
  deploy:
    needs: [test-backend, test-frontend]
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Setup Ruby (for Kamal)
        uses: ruby/setup-ruby@v1
        with:
          ruby-version: '3.3'
          bundler-cache: true
          
      - name: Install Kamal
        run: gem install kamal
        
      - name: Setup SSH
        uses: webfactory/ssh-agent@v0.9.0
        with:
          ssh-private-key: ${{ secrets.SSH_PRIVATE_KEY }}
          
      - name: Deploy with Kamal
        run: kamal deploy
        env:
          APP_KEY: ${{ secrets.APP_KEY }}
          R2_ACCESS_KEY: ${{ secrets.R2_ACCESS_KEY }}
          R2_SECRET_KEY: ${{ secrets.R2_SECRET_KEY }}

  # 4. Post-deploy verification
  smoke-test:
    needs: deploy
    runs-on: ubuntu-latest
    steps:
      - name: Health check
        run: |
          for i in {1..10}; do
            if curl -sf https://api.yourdomain.com/health; then
              echo "Health check passed"
              exit 0
            fi
            sleep 5
          done
          echo "Health check failed"
          exit 1
          
      - name: Notify Sentry
        run: |
          curl -sL https://sentry.io/api/hooks/release/builtin/... \
            -X POST \
            -H "Content-Type: application/json" \
            -d '{"version": "${{ github.sha }}"}'
```

### Frontend Deployment (Vercel)

Next.js dashboard deploys separately to Vercel:

```yaml
# .github/workflows/frontend.yml
name: Frontend

on:
  push:
    branches: [main]
    paths:
      - 'frontend/**'

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      
      - name: Deploy to Vercel
        uses: amondnet/vercel-action@v25
        with:
          vercel-token: ${{ secrets.VERCEL_TOKEN }}
          vercel-org-id: ${{ secrets.VERCEL_ORG_ID }}
          vercel-project-id: ${{ secrets.VERCEL_PROJECT_ID }}
          working-directory: ./frontend
```

### Rollback

```bash
# Instant rollback to previous version
kamal rollback

# Rollback to specific version
kamal rollback v1.2.3
```

### Monitoring & Alerts

```yaml
# docker-compose.monitoring.yml (optional)
services:
  sentry:
    # Error tracking - use Sentry.io SaaS instead
    
  checkly:
    # Synthetic monitoring - use Checkly SaaS
    # Monitors: API health, upload flow, dashboard
```

### Scaling Architecture

```
Phase 1: Single VPS (~$17/mo)
┌─────────────────────────────────────────────┐
│              Cloudflare                      │
└──────────────────┬──────────────────────────┘
                   │
        ┌──────────▼──────────┐
        │     VPS ($17/mo)    │
        │  - Laravel API      │
        │  - Horizon (queues) │
        │  - Redis            │
        └─────────────────────┘

Phase 2: Multi-VPS (~$60/mo)
┌─────────────────────────────────────────────┐
│         Cloudflare Load Balancer            │
└──────────────────┬──────────────────────────┘
                   │
        ┌──────────┼──────────┐
        ▼          ▼          ▼
    ┌───────┐  ┌───────┐  ┌───────┐
    │ VPS 1 │  │ VPS 2 │  │ VPS 3 │
    │ (API) │  │ (API) │  │(Worker)│
    └───────┘  └───────┘  └───────┘
                   │
            ┌──────▼──────┐
            │   Upstash   │
            │   (Redis)   │
            └─────────────┘

Phase 3: High Scale (~$100+/mo)
- 5+ API servers
- Dedicated worker fleet  
- Multiple regions (if needed)
- Still no Kubernetes needed!
```

---

## Competitive Analysis

| Competitor | Pricing | BYOB | Our Advantage |
|------------|---------|------|---------------|
| Uploadcare | $25-399/mo | ❌ | 50% cheaper + BYOB |
| Cloudinary | $99-249/mo | ❌ | Way cheaper, no lock-in |
| Filestack | $49-499/mo | Partial | Full BYOB, simpler |
| ImageKit | $49-349/mo | ❌ | Any file type |
| upload.io | $19-99/mo | ❌ | BYOB differentiator |

**Key Differentiators:**
1. True BYOB support (unique)
2. Free egress (R2)
3. Self-hostable option
4. Edge-first architecture

---

*Last Updated: February 2026*
