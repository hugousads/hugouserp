{{-- resources/views/layouts/app.blade.php --}}
@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $userTheme = auth()->check() ? (auth()->user()->preferences->theme ?? 'light') : 'light';
    $isDark = $userTheme === 'dark' || ($userTheme === 'system' && request()->cookie('theme') === 'dark');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $dir }}" class="h-full antialiased {{ $isDark ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', config('app.name', 'Ghanem ERP'))</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    {{-- PWA Meta Tags --}}
    <meta name="theme-color" content="#10b981">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'HugousERP') }}">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon-192x192.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    {{-- Turbo.js is now loaded via Vite in app.js for proper SPA-like navigation --}}

    <style>
        * { font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif !important; }

        html { scroll-behavior: smooth; }
        body {
            min-height: 100vh;
            background-color: #f8fafc;
            overflow-x: hidden;
            padding: env(safe-area-inset-top) 0 env(safe-area-inset-bottom);
        }

        img, svg, video, canvas { max-width: 100%; height: auto; object-fit: contain; }
        main, .content-container, .erp-card { width: 100%; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1.25rem; }
        .toolbar-wrap { flex-wrap: wrap; row-gap: 0.5rem; }
        button, input, select, textarea { max-width: 100%; }

        /* Performance optimizations */
        .erp-card, .sidebar-link, table {
            contain: content;
        }
        
        /* Hardware acceleration for animations */
        .sidebar-link, .erp-card, button, a {
            transform: translateZ(0);
            will-change: transform, opacity;
        }
        
        /* Smooth transitions */
        * {
            transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Loading indicator */
        .turbo-progress-bar {
            height: 3px;
            background: linear-gradient(to right, #10b981, #3b82f6);
        }
        
        /* Responsive improvements */
        @media (max-width: 768px) {
            .responsive-table {
                overflow-x-auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>

    <script>
        // Theme initialization
        (function() {
            const theme = localStorage.getItem('theme') || '{{ $userTheme }}';
            if (theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            }
        })();
        
        window.Laravel = {
            @if(auth()->check())
                userId: {{ auth()->id() }},
            @else
                userId: null,
            @endif
        };
    </script>

    @livewireStyles
</head>
<body class="h-full text-[15px] sm:text-base"
      x-data="{ sidebarOpen: false }"
      @keydown.escape.window="sidebarOpen = false">

{{-- Main Layout Container with new sidebar --}}
<div class="erp-layout">
    {{-- New Sidebar (includes overlay) --}}
    @includeIf('layouts.sidebar-new')

    {{-- Main Content Wrapper --}}
    <div class="erp-main-wrapper">

        {{-- Navbar --}}
        @includeIf('layouts.navbar')

        <main class="flex-1 w-full overflow-x-hidden">
            <div class="content-container mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-4">

                @hasSection('page-header')
                    @php
                        $routeName = \Illuminate\Support\Facades\Route::currentRouteName();
                        $routePermissions = [
                            'dashboard'                 => config('screen_permissions.dashboard', 'dashboard.view'),
                            'pos.terminal'              => config('screen_permissions.pos.terminal', 'pos.use'),
                            'pos.offline.report'        => 'pos.offline.report.view',
                            'admin.users.index'         => config('screen_permissions.admin.users.index', 'users.manage'),
                            'admin.users.create'        => config('screen_permissions.admin.users.index', 'users.manage'),
                            'admin.users.edit'          => config('screen_permissions.admin.users.index', 'users.manage'),
                            'admin.branches.index'      => config('screen_permissions.admin.branches.index', 'branches.view'),
                            'admin.branches.create'     => config('screen_permissions.admin.branches.index', 'branches.view'),
                            'admin.branches.edit'       => config('screen_permissions.admin.branches.index', 'branches.view'),
                            'admin.settings.system'     => config('screen_permissions.admin.settings.system', 'settings.view'),
                            'admin.settings.branch'     => config('screen_permissions.admin.settings.branch', 'settings.branch'),
                            'notifications.center'      => config('screen_permissions.notifications.center', 'system.view-notifications'),
                            'inventory.products.index'  => config('screen_permissions.inventory.products.index', 'inventory.products.view'),
                            'inventory.products.create' => config('screen_permissions.inventory.products.index', 'inventory.products.view'),
                            'inventory.products.edit'   => config('screen_permissions.inventory.products.index', 'inventory.products.view'),
                            'hrm.reports.dashboard'     => config('screen_permissions.hrm.reports.dashboard', 'hr.view-reports'),
                            'rental.reports.dashboard'  => config('screen_permissions.rental.reports.dashboard', 'rental.view-reports'),
                            'admin.logs.audit'          => config('screen_permissions.logs.audit', 'logs.audit.view'),
                        ];
                        $requiredPermission = $routePermissions[$routeName] ?? null;
                    @endphp
                    <div class="flex items-center justify-between gap-3 toolbar-wrap w-full">
                        <div class="flex flex-col gap-1 w-full sm:w-auto">
                            @yield('page-header')
                        </div>
                        <div class="flex items-center gap-2 toolbar-wrap justify-end">
                            @if ($requiredPermission)
                                <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-[11px] font-medium text-slate-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    <span>can:{{ $requiredPermission }}</span>
                                </span>
                            @endif
                            @yield('page-actions')
                        </div>
                    </div>
                @else

                    <div class="flex items-center justify-between gap-3 toolbar-wrap w-full">
                        <div class="flex flex-col gap-1 w-full sm:w-auto">
                            @yield('page-header')
                        </div>
                        @yield('page-actions')
                    </div>
                @endif

                @if (session('status'))
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 shadow-sm shadow-emerald-500/20">
                        {{ session('status') }}
                    </div>
                @endif

                @if (session('success'))
                    <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-sm text-emerald-800 shadow-sm shadow-emerald-500/20">
                        {{ session('success') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-sm text-red-800 shadow-sm">
                        <ul class="list-disc ms-4 space-y-1">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="erp-card p-4 sm:p-6">
                    {{ $slot ?? '' }}
                    @yield('content')
                </div>
            </div>
        </main>

        <footer class="border-t border-emerald-100/60 bg-white/80 backdrop-blur py-3 text-xs text-slate-500">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-between gap-2 flex-wrap w-full">
                <span>&copy; {{ date('Y') }} {{ config('app.name', 'Ghanem ERP') }}</span>
                <span class="hidden sm:inline">
                    {{ __('Powered by Laravel & Livewire') }}
                </span>
            </div>
        </footer>
    </div>
</div>

@livewireScripts
@stack('scripts')

<script>
    // CSRF Token Refresh - Prevents 419 Session Expired Errors
    // Refreshes the CSRF token every 30 minutes to keep sessions alive
    (function() {
        let livewireHookRegistered = false;
        
        const updateCsrfToken = (token) => {
            // Update meta tag
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                metaTag.setAttribute('content', token);
            }
            
            // Update axios default header if available
            if (window.axios) {
                window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
            }
            
            // Register Livewire hook once to update CSRF token on requests
            if (window.Livewire && !livewireHookRegistered) {
                window.Livewire.hook('request', ({ options }) => {
                    const currentToken = document.querySelector('meta[name="csrf-token"]')?.content;
                    if (currentToken) {
                        options.headers = options.headers || {};
                        options.headers['X-CSRF-TOKEN'] = currentToken;
                    }
                });
                livewireHookRegistered = true;
            }
        };
        
        const refreshCsrfToken = async () => {
            try {
                const response = await fetch('/csrf-token', {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin'
                });
                
                if (response.ok) {
                    const data = await response.json();
                    if (data.csrf_token) {
                        updateCsrfToken(data.csrf_token);
                        @if(config('app.debug'))
                        console.log('[CSRF] Token refreshed successfully');
                        @endif
                    }
                } else if (response.status === 401) {
                    // User is no longer authenticated, redirect to login
                    window.location.href = '/login';
                }
            } catch (error) {
                @if(config('app.debug'))
                console.error('[CSRF] Error refreshing token:', error);
                @endif
            }
        };
        
        // Refresh token every 30 minutes (1800000 ms)
        // This ensures the token is always fresh even during long sessions
        setInterval(refreshCsrfToken, 30 * 60 * 1000);
        
        // Also refresh on page visibility change (user comes back to tab)
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                refreshCsrfToken();
            }
        });
        
        // Handle 419 errors silently - auto-refresh instead of showing error
        if (window.axios) {
            window.axios.interceptors.response.use(
                response => response,
                error => {
                    if (error.response && error.response.status === 419) {
                        // Silently refresh the page on 419 error
                        window.location.reload();
                        return Promise.reject(error);
                    }
                    return Promise.reject(error);
                }
            );
        }
        
        // Handle Livewire 419 errors silently
        if (window.Livewire) {
            document.addEventListener('livewire:init', () => {
                Livewire.hook('request', ({ fail }) => {
                    fail(({ status, preventDefault }) => {
                        if (status === 419) {
                            preventDefault();
                            // Silently refresh the page
                            window.location.reload();
                        }
                    });
                });
            });
        }
    })();
    
    // Handle export downloads - triggered from Livewire components
    document.addEventListener('livewire:init', () => {
        Livewire.on('trigger-download', (params) => {
            console.log('Export download event received:', params);
            
            // Extract URL from various possible formats
            // Livewire v3 sends named parameters as object properties
            let url = null;
            if (typeof params === 'string') {
                url = params;
            } else if (params && typeof params === 'object') {
                // Try different possible formats
                url = params.url || params[0]?.url || params[0];
            }
            
            console.log('Extracted URL:', url);
            
            if (url) {
                // Create a temporary anchor element to trigger download
                // This method is more reliable than iframe for downloads
                const link = document.createElement('a');
                link.href = url;
                link.style.display = 'none';
                // Browser will use the filename from the Content-Disposition header
                document.body.appendChild(link);
                
                // Trigger the download
                link.click();
                
                // Clean up after a short delay
                setTimeout(() => {
                    if (document.body.contains(link)) {
                        document.body.removeChild(link);
                    }
                }, 100);
                
                console.log('Export download triggered successfully');
            } else {
                console.error('No URL found in export download event:', params);
            }
        });
    });
    
    // Handle theme changes from UserPreferences
    document.addEventListener('livewire:init', () => {
        Livewire.on('theme-changed', (event) => {
            const theme = event.theme || event[0]?.theme || event[0];
            if (theme) {
                localStorage.setItem('theme', theme);
                
                if (theme === 'dark') {
                    document.documentElement.classList.add('dark');
                } else if (theme === 'light') {
                    document.documentElement.classList.remove('dark');
                } else if (theme === 'system') {
                    if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                }
            }
        });
    });

    // Handle session/page expiration (419 errors)
    // This automatically refreshes the page when the CSRF token expires
    document.addEventListener('livewire:init', () => {
        Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status === 419) {
                    // Session expired - show a friendly message and refresh
                    if (confirm('{{ __("Your session has expired. Click OK to refresh the page.") }}')) {
                        window.location.reload();
                    }
                    preventDefault();
                }
            });
        });
    });
</script>

    <div id="erp-toast-root" class="toast-container flex flex-col items-end justify-start px-4 py-6 space-y-2 inset-inline-end-0 inset-block-start-0 inset-inline-start-auto inset-block-end-auto"></div>

{{-- Loading indicator --}}
<div id="page-loading" class="loading-overlay h-1 inset-block-end-auto bg-gradient-to-r from-emerald-500 via-blue-500 to-emerald-500 transform -translate-x-full transition-transform duration-300" style="display:none;"></div>

<script>
    // Intelligent prefetching - preload links on hover
    // Uses a Set to track prefetched URLs to avoid duplicates
    document.addEventListener('DOMContentLoaded', function() {
        const prefetchedUrls = new Set();
        const MAX_PREFETCHES = 20; // Limit to prevent memory issues
        
        document.querySelectorAll('a[href^="/"]').forEach(link => {
            link.addEventListener('mouseenter', function() {
                const href = this.getAttribute('href');
                if (href && !prefetchedUrls.has(href) && !href.includes('#') && prefetchedUrls.size < MAX_PREFETCHES) {
                    prefetchedUrls.add(href);
                    const prefetch = document.createElement('link');
                    prefetch.rel = 'prefetch';
                    prefetch.href = href;
                    document.head.appendChild(prefetch);
                    
                    // Remove prefetch link after 30 seconds to free memory
                    setTimeout(() => {
                        prefetch.remove();
                    }, 30000);
                }
            }, { once: true, passive: true });
        });
    });
    
    // Show loading indicator during Turbo navigation (only if Turbo is loaded)
    if (window.TurboLoaded !== false) {
        document.addEventListener('turbo:before-fetch-request', () => {
            const loader = document.getElementById('page-loading');
            if (loader) {
                loader.style.display = 'block';
                loader.style.transform = 'translateX(-50%)';
            }
        });
        
        document.addEventListener('turbo:before-fetch-response', () => {
            const loader = document.getElementById('page-loading');
            if (loader) {
                loader.style.transform = 'translateX(0)';
                setTimeout(() => {
                    loader.style.display = 'none';
                    loader.style.transform = 'translateX(-100%)';
            }, 300);
        }
    });
    }
</script>
    
</body>
</html>
