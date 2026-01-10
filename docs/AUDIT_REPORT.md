# Project Audit Report
## Date: 2026-01-10

### Executive Summary
This audit covers security, dead code, Turbo/Livewire migration issues, performance patterns, and Alpine.js usage in the HugousERP Laravel 12 + Livewire 4 application.

---

## 1. Security Issues

### 1.1 Committed Secrets & .env Files
- **Status**: ✅ Safe
- `.env` is properly listed in `.gitignore`
- `.env.backup` is properly listed in `.gitignore`
- `.env.production` is properly listed in `.gitignore`
- `.env.example` exists and contains safe placeholder values

### 1.2 Public Logs
- **Status**: ✅ Safe
- No `public/error_log` or similar files found in the public directory

### 1.3 Trusted Proxies Configuration
- **Status**: ✅ Properly Configured
- `bootstrap/app.php` already handles:
  - Wildcard (`*`) proxy configuration
  - Comma-separated proxy list parsing
  - Production warning when using wildcard proxies

---

## 2. Turbo References (MUST REMOVE)

### 2.1 Package Dependencies
- **package.json**: No `@hotwired/turbo` found ✅
- **package-lock.json**: Contains orphaned `@hotwired/turbo` reference ⚠️
  - Line: `"@hotwired/turbo": "^8.0.20"`
  - **Action**: Regenerate package-lock.json after npm install

### 2.2 JavaScript/Blade Turbo References
Files with Turbo references to remove:
1. `resources/views/layouts/sidebar-new.blade.php`:
   - `this._turboHandler` listener registration
   - `turbo:render` event listener
   - Turbo undefined check: `if (typeof Turbo !== 'undefined')`

2. `resources/js/app.js`:
   - Comment reference: "No need for Turbo.js - Livewire handles navigation natively" (safe comment, can keep)

---

## 3. Alpine.js Usage

### 3.1 x-collapse Directive (REQUIRES @alpinejs/collapse plugin)
Files using `x-collapse`:
1. `resources/views/livewire/admin/modules/form.blade.php`
2. `resources/views/livewire/reports/scheduled-reports/form.blade.php`
3. `resources/views/layouts/sidebar-new.blade.php`
4. `resources/views/components/sidebar/menu-item.blade.php`

**Recommendation**: Replace `x-collapse` with `x-show` + `x-transition` unless @alpinejs/collapse is installed.

### 3.2 General Alpine Usage
- Alpine.js is bundled via `package.json` (alpinejs: ^3.15.2) ✅
- x-data, x-show, x-transition are used extensively (safe)

---

## 4. Livewire v3 Hooks (MUST MIGRATE TO v4)

### 4.1 Deprecated Hook Usage
File: `resources/views/layouts/app.blade.php`
```javascript
Livewire.hook('request', ({ options }) => { ... })
Livewire.hook('request', ({ fail }) => { ... })
```

**Action Required**: Update to Livewire 4 interceptor API.

---

## 5. Performance Issues

### 5.1 Dashboard Chart.js CDN
File: `resources/views/livewire/dashboard/index.blade.php` (Line 399)
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
```

**Issue**: Chart.js is already bundled in `resources/js/app.js` via npm.
**Action**: Remove CDN script tag and use bundled version.

### 5.2 Heavy Queries in render()
- **LoadsDashboardData trait**: ✅ Uses caching properly
- **Dashboard widgets**: Load data in mount(), not render() ✅
- **GlobalSearch**: Uses debounce on input ✅

### 5.3 N+1 Patterns
- **ModuleService**: Uses eager loading with `with()` ✅
- **ModuleNavigationService**: Uses eager loading ✅
- **Sidebar**: Inline N+1 pattern in Blade - needs refactor to Livewire component ⚠️

---

## 6. Role String Mismatches

### 6.1 Inconsistent Role Names
Files with potential mismatches:
- Some files use `'Super Admin'` (Title Case - from seeder)
- Some files use `'super-admin'` (kebab-case)

**Files affected**:
- `app/Livewire/Concerns/LoadsDashboardData.php` - Uses both formats ✅ (already handles both)
- Various policy and service files

**Recommendation**: Use permission-based checks (`$user->can()`) instead of role checks where possible.

---

## 7. Dead Code & Unused Files

### 7.1 Sidebar Files
- `resources/views/layouts/sidebar-new.blade.php` - Currently in use
- `resources/views/layouts/sidebar-organized.blade.php` - Status unclear, may be unused
- `resources/views/components/sidebar/menu-item.blade.php` - Uses x-collapse

### 7.2 Unused Middleware
- All registered middleware in `bootstrap/app.php` appear to be in use

---

## 8. wire:navigate Usage

### 8.1 Current Implementation
Files using `wire:navigate`:
- Auth views (login, forgot-password, reset-password)
- Various index/list views
- Command palette
- Dashboard
- Sidebar links

**Status**: Partial implementation. Key navigation areas need `wire:navigate` attribute.

---

## 9. Recommended Actions (Priority Order)

### High Priority
1. Remove Turbo references from sidebar-new.blade.php
2. Regenerate package-lock.json to remove orphaned Turbo
3. Replace x-collapse with x-show + x-transition
4. Update Livewire v3 hooks to v4 API in app.blade.php
5. Remove CDN Chart.js from dashboard

### Medium Priority
1. Create Sidebar Livewire component to replace inline Blade logic
2. Add @persist('sidebar') to layout
3. Update GlobalSearch to use #[Json] attribute

### Lower Priority
1. Standardize role name usage
2. Remove unused sidebar blade files
3. Add ModuleObserver for cache invalidation

---

## 10. Files to Modify

| File | Action |
|------|--------|
| `resources/views/layouts/sidebar-new.blade.php` | Remove Turbo, replace x-collapse |
| `resources/views/layouts/app.blade.php` | Update Livewire hooks, add @persist |
| `resources/views/livewire/dashboard/index.blade.php` | Remove CDN Chart.js |
| `resources/views/livewire/admin/modules/form.blade.php` | Replace x-collapse |
| `resources/views/livewire/reports/scheduled-reports/form.blade.php` | Replace x-collapse |
| `resources/views/components/sidebar/menu-item.blade.php` | Replace x-collapse |
| `package-lock.json` | Regenerate via npm install |

---

*Report generated as part of Livewire 4 migration project.*
