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

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    {{-- Turbo.js for SPA-like navigation (optional enhancement) --}}
    <script type="module">
        // Turbo.js loaded via CDN as optional enhancement
        // If CDN fails, navigation falls back to standard page loads
        try {
            const turbo = await import('https://cdn.skypack.dev/@hotwired/turbo');
            window.TurboLoaded = true;
        } catch (e) {
            console.info('Turbo.js not loaded, using standard navigation');
            window.TurboLoaded = false;
        }
    </script>

    <style>
        * { font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif !important; }
        
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

{{-- Mobile sidebar overlay --}}
<div x-show="sidebarOpen" 
     x-transition:enter="transition-opacity ease-out duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="sidebarOpen = false"
     class="fixed inset-0 bg-slate-900/50 backdrop-blur-sm z-40 md:hidden"></div>

<div class="min-h-screen flex {{ $dir === 'rtl' ? 'flex-row-reverse' : 'flex-row' }}">

    {{-- Sidebar --}}
    @includeIf('layouts.sidebar-improved')

    {{-- Main --}}
    <div class="flex-1 flex flex-col min-h-screen">

        {{-- Navbar --}}
        @includeIf('layouts.navbar')

        <main class="flex-1">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-4 space-y-4">

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
                    <div class="flex items-center justify-between gap-3">
                        <div class="flex flex-col gap-1">
                            @yield('page-header')
                        </div>
                        <div class="flex items-center gap-2">
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

                    <div class="flex items-center justify-between gap-3">
                        <div class="flex flex-col gap-1">
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
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 flex items-center justify-between">
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
    document.addEventListener('livewire:initialized', () => {
        Livewire.on('trigger-download', (event) => {
            const url = event.url || event[0]?.url || event[0];
            if (url) {
                // Create hidden iframe to trigger download without page navigation
                const iframe = document.createElement('iframe');
                iframe.style.display = 'none';
                iframe.src = url;
                document.body.appendChild(iframe);
                
                // Remove iframe after download starts (3 seconds for larger files)
                const IFRAME_CLEANUP_DELAY = 3000;
                setTimeout(() => {
                    if (document.body.contains(iframe)) {
                        document.body.removeChild(iframe);
                    }
                }, IFRAME_CLEANUP_DELAY);
            }
        });
    });
    
    // Handle theme changes from UserPreferences
    document.addEventListener('livewire:initialized', () => {
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

    <div id="erp-toast-root" class="fixed inset-0 pointer-events-none flex flex-col items-end justify-start px-4 py-6 space-y-2 z-[9999]"></div>

{{-- Loading indicator --}}
<div id="page-loading" class="fixed top-0 left-0 right-0 h-1 bg-gradient-to-r from-emerald-500 via-blue-500 to-emerald-500 transform -translate-x-full transition-transform duration-300 z-[10000]" style="display:none;"></div>

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
        
        // Auto-scroll to active menu item in sidebar
        const activeItem = document.querySelector('.sidebar-link.active, .sidebar-link-secondary.active');
        if (activeItem) {
            setTimeout(() => {
                activeItem.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
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
