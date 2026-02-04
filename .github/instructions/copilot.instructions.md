---
applyTo: '**'
---

# R2 Upload Service — AI Coding Instructions

## 🎯 Project Overview

**R2 Upload Service** is a SaaS file upload platform built with **Laravel 12.x** that supports:
- Direct file uploads to Cloudflare R2
- Remote URL uploads (fetch from any URL)
- **BYOB (Bring Your Own Bucket)** — connect any S3-compatible storage
- Multi-tenant architecture with API key authentication
- Usage-based billing via Stripe

## 🏗️ Architecture

```
User Request → Cloudflare CDN → Workers (optional) → Laravel API → R2/BYOB Storage
                                                          ↓
                                                    Cloudflare D1 (SQLite)
```

## 🛠️ Tech Stack

### Backend
| Layer | Technology | Version |
|-------|------------|---------|
| Framework | Laravel | 12.x |
| PHP | PHP | 8.4 |
| Database | Cloudflare D1 | SQLite |
| Cache/Queue | Redis | 8.x |
| Storage | Cloudflare R2 | S3-compatible |
| Edge | Cloudflare Workers | V8 |
| Payments | Stripe (Laravel Cashier) | Latest |

### Frontend
| Layer | Technology | Version |
|-------|------------|---------|
| Framework | Next.js | 16.x |
| UI Library | React | 20.x |
| Styling | Tailwind CSS | 4.x |
| Language | TypeScript | 5.x |
| State | TanStack Query + Zustand | Latest |
| Package Manager | pnpm | 10.x |

### DevOps (Zero Downtime)
| Tool | Purpose |
|------|---------|
| Kamal | Docker-based zero-downtime deploys |
| GitHub Actions | CI/CD pipeline |
| PHPStan + Pest | PHP testing & static analysis |
| TypeScript + ESLint | Frontend type checking |
| Sentry | Error tracking (PHP + JS) |
| Checkly | Synthetic monitoring |

## 📁 Project Structure (Laravel)

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   ├── AuthController.php
│   │   │   ├── FileController.php
│   │   │   ├── StorageConnectionController.php
│   │   │   ├── ApiKeyController.php
│   │   │   └── WebhookController.php
│   │   └── Admin/
│   │       └── DashboardController.php
│   └── Middleware/
│       ├── ValidateApiKey.php
│       └── RateLimitByPlan.php
├── Models/
│   ├── User.php
│   ├── Upload.php
│   ├── StorageConnection.php
│   ├── ApiKey.php
│   ├── Plan.php
│   └── Webhook.php
├── Services/
│   ├── StorageService.php         # Abstract storage layer
│   ├── S3CompatibleStorage.php    # Works with any S3 provider
│   ├── PresignedUrlService.php
│   └── UsageTrackingService.php
├── Jobs/
│   ├── ProcessRemoteUpload.php
│   └── TriggerWebhook.php
└── Events/
    ├── UploadCompleted.php
    └── UploadFailed.php
```

## 📁 Project Structure (Next.js Frontend)

```
frontend/
├── app/                          # Next.js App Router
│   ├── (auth)/
│   │   ├── login/page.tsx
│   │   └── register/page.tsx
│   ├── (dashboard)/
│   │   ├── layout.tsx
│   │   ├── page.tsx              # Dashboard home
│   │   ├── files/page.tsx
│   │   ├── storage/page.tsx      # BYOB connections
│   │   ├── api-keys/page.tsx
│   │   ├── webhooks/page.tsx
│   │   └── settings/page.tsx
│   ├── (marketing)/              # Public pages
│   │   ├── page.tsx              # Landing page
│   │   ├── pricing/page.tsx
│   │   ├── docs/page.tsx
│   │   └── blog/page.tsx
│   ├── layout.tsx                # Root layout (theme provider)
│   ├── globals.css               # Tailwind + CSS variables
│   ├── not-found.tsx             # 404 page
│   └── error.tsx                 # Error boundary
├── components/
│   ├── ui/                       # Shadcn/ui components
│   │   ├── button.tsx
│   │   ├── input.tsx
│   │   ├── dialog.tsx
│   │   ├── toast.tsx
│   │   ├── skeleton.tsx
│   │   └── ...
│   ├── theme/
│   │   ├── ThemeProvider.tsx     # next-themes provider
│   │   └── ThemeToggle.tsx       # Dark/light switch
│   ├── cookies/
│   │   └── CookieConsent.tsx     # GDPR cookie banner
│   ├── files/
│   │   ├── FileUploader.tsx
│   │   ├── FileList.tsx
│   │   └── FilePreview.tsx
│   ├── storage/
│   │   └── StorageConnectionForm.tsx
│   └── layout/
│       ├── Sidebar.tsx
│       ├── Header.tsx
│       ├── Footer.tsx
│       └── SkipLink.tsx          # Accessibility skip link
├── lib/
│   ├── api.ts                    # API client
│   ├── auth.ts                   # Auth utilities
│   ├── utils.ts                  # cn() helper, etc.
│   └── seo.ts                    # SEO metadata helpers
├── hooks/
│   ├── useFiles.ts
│   ├── useStorage.ts
│   ├── useUpload.ts
│   ├── useTheme.ts               # Theme hook
│   └── useMediaQuery.ts          # Responsive hook
├── stores/                       # Zustand stores
│   ├── uploadStore.ts
│   └── uiStore.ts                # Sidebar, modals state
├── types/
│   └── index.ts
├── styles/
│   └── themes.css                # CSS variables for themes
├── public/
│   ├── robots.txt
│   ├── sitemap.xml
│   └── manifest.json             # PWA manifest
├── package.json
├── tsconfig.json
├── tailwind.config.ts
├── next.config.ts
└── components.json               # Shadcn/ui config
```

## 🎨 UI/UX Requirements

### Design System
- **Component Library**: Shadcn/UI (Radix primitives)
- **Styling**: Tailwind CSS 4.x with CSS variables for theming
- **Icons**: Lucide React (consistent icon set)
- **Animations**: Framer Motion (subtle, purposeful)
- **Charts**: Recharts or Tremor
- **Forms**: React Hook Form + Zod validation

### Theme System (Dark/Light Mode)
```tsx
// Required: Support both themes everywhere
// Implementation: next-themes + CSS variables

// Theme toggle component required in:
// - Dashboard header (user-facing)
// - Admin backend header
// - Landing page header

// Theme persistence:
// 1. Check localStorage for saved preference
// 2. Fall back to system preference (prefers-color-scheme)
// 3. Default to light mode
```

### Responsive Design
```css
/* Mobile-first breakpoints */
sm: 640px    /* Tablet portrait */
md: 768px    /* Tablet landscape */
lg: 1024px   /* Desktop */
xl: 1280px   /* Wide desktop */
2xl: 1536px  /* Ultra-wide */

/* Always test on: iPhone SE, iPad, MacBook, 27" monitor */
```

### Accessibility (WCAG 2.1 AA)
- **Keyboard navigation**: All interactive elements focusable via Tab
- **Focus indicators**: Visible focus rings (not just outline: none)
- **Screen readers**: ARIA labels, roles, live regions
- **Color contrast**: Minimum 4.5:1 for text, 3:1 for UI
- **Skip links**: "Skip to main content" link
- **Form labels**: All inputs have associated `<label>`
- **Error messages**: Connected via `aria-describedby`
- **Reduced motion**: Respect `prefers-reduced-motion`

### Page Speed Targets
| Metric | Target |
|--------|--------|
| Lighthouse Performance | > 90 |
| Lighthouse Accessibility | 100 |
| Lighthouse SEO | 100 |
| LCP | < 2.5s |
| FID | < 100ms |
| CLS | < 0.1 |

### SEO Requirements
- Dynamic meta tags per page (title, description)
- Open Graph tags for social sharing
- Twitter Card meta tags
- Canonical URLs
- JSON-LD structured data
- Auto-generated sitemap.xml
- Proper robots.txt

### Cookie Consent
```tsx
// GDPR/CCPA compliant cookie banner required
// Categories:
// - Essential (always on, no consent needed)
// - Analytics (opt-in, Plausible/Sentry)
// - Marketing (opt-in, none planned)

// Implementation: Cookie consent library (e.g., react-cookie-consent)
// Store consent in localStorage + send to backend
```

### Loading & Error States
```tsx
// Every async operation needs:
// 1. Loading state → Skeleton placeholder (NOT spinner)
// 2. Error state → Clear message + retry button
// 3. Empty state → Friendly illustration + CTA
// 4. Success state → Toast notification

// Use React Suspense + Error Boundaries
```

### Component Patterns
```tsx
// Button variants: default, secondary, destructive, outline, ghost
// Form inputs: with label, error message, helper text
// Cards: with header, content, footer sections
// Tables: sortable, filterable, paginated
// Modals: with proper focus trap, escape to close
// Toasts: success, error, warning, info variants
```

## 📐 Coding Standards

### General
- Follow PSR-12 coding standard
- Use strict types: `declare(strict_types=1);`
- Prefer composition over inheritance
- Use dependency injection
- Write tests for new features (Pest preferred)

### Laravel Specific
- Use Form Requests for validation
- Use API Resources for JSON responses
- Use Eloquent relationships, avoid raw queries
- Use Laravel's built-in features (queues, events, etc.)
- Use `php artisan make:*` for generating classes

### Naming Conventions
```php
// Controllers: singular, PascalCase
FileController.php
StorageConnectionController.php

// Models: singular, PascalCase
User.php
StorageConnection.php

// Tables: plural, snake_case
users
storage_connections

// API routes: plural, kebab-case
/api/v1/files
/api/v1/storage-connections

// Methods: camelCase, descriptive
public function uploadFile(Request $request)
public function validateStorageConnection(StorageConnection $connection)
```

### Database
- Use migrations for schema changes
- Use seeders for test data
- Index frequently queried columns
- Use foreign key constraints
- Soft deletes for user data

### API Responses
```php
// Success
return response()->json([
    'success' => true,
    'data' => $resource,
    'meta' => ['page' => 1, 'total' => 100]
]);

// Error
return response()->json([
    'success' => false,
    'error' => [
        'code' => 'VALIDATION_ERROR',
        'message' => 'The given data was invalid.',
        'details' => $errors
    ]
], 422);
```

## 🔌 BYOB (Bring Your Own Bucket)

The system must support ANY S3-compatible storage. Key implementation:

```php
// StorageService should be provider-agnostic
interface StorageServiceInterface
{
    public function upload(string $path, $contents, array $options = []): string;
    public function download(string $path): StreamInterface;
    public function delete(string $path): bool;
    public function getPresignedUrl(string $path, int $expiry = 3600): string;
    public function exists(string $path): bool;
}

// S3CompatibleStorage works with any provider
class S3CompatibleStorage implements StorageServiceInterface
{
    public function __construct(
        private string $endpoint,
        private string $bucket,
        private string $region,
        private string $accessKey,
        private string $secretKey
    ) {}
    
    // Uses AWS SDK with custom endpoint
}
```

### Supported Providers
- Cloudflare R2
- AWS S3
- DigitalOcean Spaces
- Backblaze B2
- Wasabi
- MinIO
- Any S3-compatible endpoint

## 🔐 Security Requirements

### Zero-Vulnerability Pipeline
All code must pass through:
1. **PHPStan Level 9** — PHP static analysis
2. **TypeScript strict** — Frontend type safety
3. **Composer audit** — PHP dependency vulnerabilities
4. **pnpm audit** — JS dependency vulnerabilities
5. **Snyk** — Deep dependency scanning
6. **GitLeaks** — Secret detection
7. **Trivy** — Docker image scanning

### Authentication
- Use Laravel Sanctum for API tokens
- API keys format: `r2u_live_` + random 32 chars
- Store hashed keys only
- Support key expiration and rotation

### Credentials Encryption
```php
// Always encrypt storage credentials
$encryptedKey = Crypt::encryptString($accessKey);
$decryptedKey = Crypt::decryptString($encryptedKey);
```

### File Validation
- Check magic bytes, not just extension
- Block dangerous extensions: php, exe, sh, bat, phar
- Enforce file size limits per plan
- Sanitize filenames (prevent path traversal)
- Virus scanning with ClamAV

### Rate Limiting
- Per-IP: 100 requests/minute (default)
- Per-API-key: Based on user's plan
- Use Redis for distributed rate limiting
- Cloudflare WAF for edge protection

## 💰 Pricing Philosophy

**All features for everyone, just different limits.**

| Feature | Free | Starter | Pro |
|---------|------|---------|-----|
| Storage | 500MB | 50GB | 500GB |
| BYOB | 1 bucket | 3 buckets | 10 buckets |
| API Calls | 500/mo | 50K/mo | 500K/mo |
| All features | ✅ | ✅ | ✅ |

**Never block features for free users** — only limit usage.

## 📊 Database Schema

Key tables (D1/SQLite):
- `users` — Multi-tenant users
- `storage_connections` — BYOB credentials (encrypted)
- `api_keys` — API authentication
- `uploads` — File metadata
- `usage_logs` — Billing/analytics
- `plans` — Subscription tiers (usage limits, not feature flags)
- `webhooks` — Event notifications

See `docs/TECHNICAL_REFERENCE.md` for full schema.

## 🧪 Testing

```bash
# Run all tests
php artisan test

# Run specific test
php artisan test --filter=FileUploadTest

# With coverage
php artisan test --coverage
```

### Test Structure
```
tests/
├── Feature/
│   ├── Api/
│   │   ├── FileUploadTest.php
│   │   ├── StorageConnectionTest.php
│   │   └── AuthenticationTest.php
│   └── Admin/
│       └── DashboardTest.php
└── Unit/
    ├── Services/
    │   └── StorageServiceTest.php
    └── Models/
        └── UploadTest.php
```

## 🚀 Key Commands

```bash
# Development
php artisan serve
php artisan horizon           # Queue worker
php artisan octane:start      # Production server

# Database
php artisan migrate
php artisan db:seed

# Generate
php artisan make:model Upload -mfc
php artisan make:controller Api/FileController --api
php artisan make:request StoreUploadRequest

# Cache
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## ⚠️ Common Pitfalls

1. **Don't hardcode R2 as storage** — Always use StorageService abstraction
2. **Don't store plain credentials** — Always encrypt with `Crypt::encryptString()`
3. **Don't skip file validation** — Check magic bytes, not extension
4. **Don't forget rate limiting** — Every API endpoint needs it
5. **Don't expose internal errors** — Use proper error handling
6. **Don't skip usage tracking** — Log every API call for billing

## 📋 Implementation Priorities

When implementing features, follow this order:
1. **Authentication** — Sanctum + API keys
2. **BYOB** — Storage abstraction layer
3. **File uploads** — Direct + remote URL
4. **Usage tracking** — For billing
5. **Webhooks** — Event notifications
6. **Billing** — Stripe integration

## 🔗 Related Documents

- [ROADMAP.md](../../../ROADMAP.md) — Project roadmap
- [docs/TECHNICAL_REFERENCE.md](../../../docs/TECHNICAL_REFERENCE.md) — Full technical specs

## 💡 AI Assistant Tips

When helping with this project:

1. **Always consider BYOB** — Code should work with any S3 provider
2. **Use Laravel conventions** — Don't reinvent the wheel
3. **Check existing patterns** — Follow established code style
4. **Suggest tests** — Every feature needs tests
5. **Think multi-tenant** — Every query needs user scope
6. **Consider edge cases** — Large files, slow connections, failures

### Example Prompts That Work Well
- "Create a controller for managing storage connections"
- "Add a job for processing remote URL uploads"
- "Implement presigned URL generation that works with BYOB"
- "Add rate limiting middleware based on user's plan"
