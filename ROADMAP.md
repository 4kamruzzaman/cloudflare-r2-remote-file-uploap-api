# 🚀 R2 Upload Service — Enterprise Roadmap

## Vision
Transform this Cloudflare R2 upload service into a **production-ready, enterprise-grade SaaS platform** with **BYOB (Bring Your Own Bucket)** support.

---

## 📊 Current State → Target State

| Aspect | Current | Target |
|--------|---------|--------|
| Framework | Vanilla PHP | **Laravel 12.x** |
| Database | MySQL | **Cloudflare D1** |
| Auth | Session-based | **JWT + API Keys** |
| Storage | Single R2 bucket | **Multi-storage (BYOB)** |
| Users | Admin only | **Multi-tenant SaaS** |
| Deployment | Docker local | **VPS + CF Workers** |

---

## 🎯 Key Features

### Core (MVP)
- [x] Local file upload to R2
- [x] Remote URL upload
- [x] Admin dashboard
- [ ] **Laravel migration**
- [ ] Multi-tenant user system
- [ ] JWT API authentication
- [ ] API key management

### BYOB (Bring Your Own Bucket)
- [ ] Connect any S3-compatible storage
- [ ] Support: R2, AWS S3, Spaces, B2, Wasabi, MinIO
- [ ] Encrypted credential storage
- [ ] Connection validation

### File Management
- [ ] View vs Download control (Content-Disposition)
- [ ] Signed URLs for private files
- [ ] Image transformations
- [ ] Chunked/resumable uploads
- [ ] **Hash-based deduplication** — Detect duplicates before upload
- [ ] **Bulk URL import** — Paste list of URLs to fetch

### Developer Experience
- [ ] **Interactive API playground** — Try endpoints in browser
- [ ] **Official SDKs** — JS, Python, PHP, Go
- [ ] **CLI tool** — `r2u upload ./file.pdf`
- [ ] **GitHub Action** — CI/CD integration
- [ ] **Embeddable widget** — Drop-in upload component

### Monetization
- [ ] Stripe subscription billing
- [ ] Usage-based metering
- [ ] Two pricing models (Managed + BYOB)
- [ ] **Pay-per-use credits** — No subscription option

### Growth Features (Post-MVP)
- [ ] **Magic Link uploads** — Clients upload without account
- [ ] **Upload zones** — Project-specific upload URLs
- [ ] **Webhook templates** — One-click Slack/Discord setup
- [ ] **Custom domains** — `cdn.yoursite.com` for files

### UI/UX & Design
- [ ] **Design system** — Shadcn/UI + Tailwind CSS 4.x
- [ ] **Dark/Light mode** — System sync + manual toggle
- [ ] **Responsive design** — Mobile-first, all breakpoints
- [ ] **Accessibility** — WCAG 2.1 AA compliant
- [ ] **Cookie consent** — GDPR/CCPA compliant banner
- [ ] **SEO optimization** — Meta tags, structured data, sitemap
- [ ] **Page speed** — Lighthouse 90+ all categories
- [ ] **Loading states** — Skeleton loaders, error boundaries

---

## 📅 Phase Timeline

| Phase | Weeks | Focus |
|-------|-------|-------|
| 1 | 1-3 | Laravel migration + Auth + Multi-tenant |
| 2 | 4-5 | BYOB implementation |
| 3 | 6-7 | API enhancement + Documentation |
| 4 | 8-9 | Dashboard + Billing |
| 5 | 10-12 | Testing + Security + Launch |

**Total: ~12 weeks (3 months)**

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Cloudflare (DNS + CDN + WAF)             │
└─────────────────────────┬───────────────────────────────────┘
                          │
         ┌────────────────┼────────────────┐
         │                │                │
    ┌────▼────┐    ┌──────▼──────┐   ┌─────▼─────┐
    │ Vercel  │    │   Workers   │   │    VPS    │
    │ Next.js │    │ (Presign)   │   │  Laravel  │
    │Dashboard│    └──────┬──────┘   │  API      │
    └─────────┘           │          └─────┬─────┘
                          │                │
         ┌────────────────┼────────────────┤
         │                │                │
    ┌────▼────┐      ┌────▼────┐      ┌────▼────┐
    │   D1    │      │   R2    │      │  BYOB   │
    │ Database│      │ Storage │      │ Storage │
    └─────────┘      └─────────┘      └─────────┘
```

---

## 🚀 Zero-Downtime Deployment

```
Push → GitHub Actions → Tests Pass → Kamal Deploy → Health Check → Live
                                          │
                                     (Blue-Green)
                                          │
                              ┌───────────┴───────────┐
                              │                       │
                         [New Container]    [Old Container]
                              │                  (rollback ready)
                         Health OK?
                              │
                         Switch Traffic
```

### Scaling Path
```
Phase 1: Single VPS ($17/mo) → 500 users
Phase 2: 3× VPS + LB ($60/mo) → 5,000 users  
Phase 3: 5× VPS + Workers ($100/mo) → 10,000+ users
```

---

## 💰 Pricing Model

### All Features for Everyone (Usage-Limited)
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

**Philosophy**: No feature locks. Free users get everything, just smaller limits.

---

## 🏆 Competitive Advantages

| Advantage | Details |
|-----------|---------|
| **BYOB** | Only platform with true bring-your-own-bucket |
| **Free Egress** | R2 = $0/TB vs S3 = $90/TB |
| **Remote URL** | Fetch files from any URL |
| **Self-hostable** | Docker image for on-premise |
| **Edge-first** | Cloudflare Workers = global fast |
| **All Features Free** | No feature locks, only usage limits |

---

## 🔒 Zero-Vulnerability Security

| Layer | Tool | Purpose |
|-------|------|--------|
| Code (PHP) | PHPStan Level 9 | Static analysis |
| Code (TS) | TypeScript strict | Type safety |
| Dependencies | Composer audit + pnpm audit | Vulnerability scan |
| Dependencies | Dependabot + Snyk | Auto-updates |
| Secrets | GitLeaks | Prevent leaks |
| Container | Trivy | Docker image scan |
| Runtime | Sentry | Error tracking |
| Infrastructure | Cloudflare WAF | Attack blocking |
| Files | ClamAV | Virus scanning |

---

## 🛠️ Tech Stack

### Backend
| Layer | Technology |
|-------|------------|
| **Framework** | Laravel 12.x (PHP 8.4) |
| **Database** | Cloudflare D1 (SQLite edge) |
| **Cache/Queue** | Redis 8.x / Laravel Horizon |
| **Storage** | Cloudflare R2 + BYOB |
| **Edge** | Cloudflare Workers |
| **Payments** | Stripe (Laravel Cashier) |

### Frontend
| Layer | Technology |
|-------|------------|
| **Framework** | Next.js 16.x (App Router) |
| **UI Library** | React 20.x (React Compiler) |
| **Styling** | Tailwind CSS 4.x |
| **Language** | TypeScript 5.x |
| **State** | TanStack Query + Zustand |

### DevOps (Zero Downtime)
| Tool | Purpose |
|------|--------|
| **Kamal** | Zero-downtime Docker deploys |
| **GitHub Actions** | CI/CD pipeline |
| **PHPStan + Pest** | PHP testing & static analysis |
| **TypeScript + ESLint** | Frontend type checking |
| **Sentry** | Error tracking (PHP + JS) |
| **Checkly** | Synthetic monitoring |

---

## 🎨 UI/UX & Design Requirements

### Design Philosophy
```
Enterprise-grade • User-friendly • Responsive • Accessible • Fast
```

### Core UI/UX Principles
| Principle | Implementation |
|-----------|----------------|
| **Mobile-First** | Design for mobile, scale up to desktop |
| **Consistent** | Unified design system across all pages |
| **Intuitive** | Zero learning curve, obvious actions |
| **Fast** | Skeleton loaders, optimistic UI, instant feedback |
| **Accessible** | WCAG 2.1 AA compliant minimum |

### Theme System (Day/Night Mode)
| Feature | Details |
|---------|--------|
| **Light Mode** | Clean, bright, professional (default) |
| **Dark Mode** | Eye-friendly, OLED-optimized blacks |
| **System Sync** | Auto-detect OS preference |
| **Manual Toggle** | User can override system preference |
| **Persistence** | Remember choice across sessions |
| **Smooth Transition** | CSS transitions, no flash |
| **Both Apps** | Frontend dashboard + Admin backend |

### Component Library
```
Framework: Shadcn/UI (Radix primitives)
Styling: Tailwind CSS 4.x
Icons: Lucide React
Animations: Framer Motion
Charts: Recharts / Tremor
Forms: React Hook Form + Zod
```

### Responsive Breakpoints
```css
mobile:    320px - 639px   (1 column)
tablet:    640px - 1023px  (2 columns)
desktop:   1024px - 1279px (3 columns)
wide:      1280px+         (4+ columns)
```

### Page Speed Requirements
| Metric | Target | Tool |
|--------|--------|------|
| **LCP** (Largest Contentful Paint) | < 2.5s | Core Web Vitals |
| **FID** (First Input Delay) | < 100ms | Core Web Vitals |
| **CLS** (Cumulative Layout Shift) | < 0.1 | Core Web Vitals |
| **TTFB** (Time to First Byte) | < 200ms | WebPageTest |
| **Lighthouse Performance** | > 90 | Chrome DevTools |
| **Lighthouse Accessibility** | 100 | Chrome DevTools |
| **Lighthouse SEO** | 100 | Chrome DevTools |
| **Lighthouse Best Practices** | 100 | Chrome DevTools |

### Performance Optimizations
- [ ] **Image optimization** — WebP/AVIF with fallbacks, lazy loading
- [ ] **Code splitting** — Route-based chunks, dynamic imports
- [ ] **Tree shaking** — Remove unused code
- [ ] **Bundle analysis** — Monitor bundle size < 200KB initial
- [ ] **Font optimization** — `font-display: swap`, subset fonts
- [ ] **Preloading** — Critical resources, prefetch routes
- [ ] **Service Worker** — Offline support, cache strategies
- [ ] **CDN delivery** — Static assets via Cloudflare CDN
- [ ] **Compression** — Brotli/Gzip for all responses

### SEO Requirements
| Feature | Implementation |
|---------|---------------|
| **Meta Tags** | Dynamic title, description, keywords per page |
| **Open Graph** | og:title, og:description, og:image for social sharing |
| **Twitter Cards** | Summary card with large image |
| **Canonical URLs** | Prevent duplicate content |
| **Structured Data** | JSON-LD schema (Organization, SoftwareApplication) |
| **Sitemap** | Auto-generated XML sitemap |
| **Robots.txt** | Proper crawl directives |
| **Alt Text** | All images have descriptive alt text |
| **Semantic HTML** | Proper heading hierarchy (h1-h6) |
| **URL Structure** | Clean, descriptive, lowercase URLs |

### Accessibility (WCAG 2.1 AA+)
| Requirement | Implementation |
|-------------|---------------|
| **Keyboard Navigation** | Full tab navigation, focus indicators |
| **Screen Reader** | ARIA labels, roles, live regions |
| **Color Contrast** | 4.5:1 minimum (7:1 for AAA) |
| **Focus Management** | Visible focus rings, skip links |
| **Form Labels** | All inputs have associated labels |
| **Error Messages** | Clear, descriptive, associated with fields |
| **Reduced Motion** | Respect `prefers-reduced-motion` |
| **Text Scaling** | Support 200% zoom without breaking |
| **Touch Targets** | Minimum 44x44px tap targets |
| **Language** | Proper `lang` attribute on HTML |

### Cookie Consent & Privacy
| Feature | Details |
|---------|--------|
| **Cookie Banner** | GDPR/CCPA compliant consent popup |
| **Granular Control** | Accept all / Reject all / Customize |
| **Categories** | Essential, Analytics, Marketing, Preferences |
| **Persistence** | Remember consent for 12 months |
| **Re-consent** | Easy access to change preferences |
| **No Pre-checked** | Optional cookies unchecked by default |
| **Documentation** | Link to Privacy Policy & Cookie Policy |

### Cookie Categories
```
✅ Essential (always on)
   - Session cookies
   - Authentication tokens
   - CSRF protection
   - User preferences (theme)

⬜ Analytics (opt-in)
   - Plausible Analytics (privacy-friendly)
   - Error tracking (Sentry)

⬜ Marketing (opt-in)
   - None planned (privacy-first approach)
```

### Dashboard Layout
```
┌─────────────────────────────────────────────────────────┐
│  Logo    Search...    [🔔] [🌙/☀️] [Avatar ▼]          │
├──────────┬──────────────────────────────────────────────┤
│          │                                              │
│  📊 Home │   Main Content Area                         │
│  📁 Files│   - Cards, Tables, Charts                   │
│  🪣 BYOB │   - Responsive grid                         │
│  🔑 API  │   - Loading skeletons                       │
│  🔗 Hooks│   - Empty states                            │
│  ⚙️ Set  │   - Error boundaries                        │
│          │                                              │
├──────────┴──────────────────────────────────────────────┤
│  © 2026 R2 Upload · Privacy · Terms · Status           │
└─────────────────────────────────────────────────────────┘
```

### Admin Backend Design
- Same design system as frontend (consistency)
- Collapsible sidebar navigation
- Breadcrumb navigation
- Data tables with sorting, filtering, pagination
- Batch actions (select multiple, bulk delete)
- Real-time updates (WebSocket/polling)
- Toast notifications for actions
- Confirmation modals for destructive actions

### Loading & Error States
| State | Design |
|-------|--------|
| **Loading** | Skeleton placeholders (not spinners) |
| **Empty** | Friendly illustration + CTA |
| **Error** | Clear message + retry button |
| **Success** | Toast notification + next action |
| **Offline** | Banner with reconnection status |

### Animation Guidelines
```css
/* Subtle, purposeful animations */
--transition-fast: 150ms ease;
--transition-normal: 200ms ease;
--transition-slow: 300ms ease;

/* Respect user preferences */
@media (prefers-reduced-motion: reduce) {
  * { animation: none !important; transition: none !important; }
}
```

---

## 💵 Cost Estimates

### Bootstrapping (~$25/mo)
- Hetzner VPS: $17
- R2 (10GB): $1
- D1: Free tier
- Domain: $1

### Growth - 500 users (~$150/mo)
- VPS × 2: $34
- R2 (500GB): $8
- Cloudflare Pro: $25
- Services: ~$80

### Scale - 1000+ users (~$300/mo)
- VPS × 3: $51
- R2 (2TB): $30
- Managed services: ~$200

---

## ✅ Immediate Next Steps

1. **Create new Laravel 12 project**
2. **Set up Cloudflare D1 database**
3. **Implement user authentication (Sanctum)**
4. **Build BYOB storage abstraction**
5. **Deploy to VPS with Cloudflare Tunnel**

---

## 📚 Related Documents

| Document | Description |
|----------|-------------|
| [docs/TECHNICAL_REFERENCE.md](./docs/TECHNICAL_REFERENCE.md) | Database schemas, API specs, deployment |
| [docs/FUTURE_FEATURES.md](./docs/FUTURE_FEATURES.md) | Post-launch features (v2.0+) |
| [.github/instructions/copilot.instructions.md](./.github/instructions/copilot.instructions.md) | AI coding guidelines |

---

*Last Updated: February 2026 · Version 3.0*
