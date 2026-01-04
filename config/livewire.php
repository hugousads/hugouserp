<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Component Namespaces
    |---------------------------------------------------------------------------
    | These namespaces are used to discover Livewire components (SFC/MFC).
    | They're also referenced when creating new components via make command.
    |
    */
    'component_namespaces' => [
        'layouts' => resource_path('views/layouts'),
        'pages' => resource_path('views/pages'),
    ],

    /*
    |---------------------------------------------------------------------------
    | Component Layout
    |---------------------------------------------------------------------------
    | The view that will be used as the layout when rendering a single component
    | as an entire page via `Route::livewire(...)`.
    |
    */
    'component_layout' => 'layouts::app',

    /*
    |---------------------------------------------------------------------------
    | Lazy Loading Placeholder
    |---------------------------------------------------------------------------
    | Default placeholder view for lazy-loaded components.
    |
    */
    'component_placeholder' => null,

    /*
    |---------------------------------------------------------------------------
    | Make Command
    |---------------------------------------------------------------------------
    | Default configuration for the artisan make command.
    | type: 'sfc' (single-file), 'mfc' (multi-file), 'class' (traditional)
    | emoji: whether to use ⚡ emoji prefix in sfc/mfc component names
    |
    */
    'make_command' => [
        'type' => 'class', // Keep using traditional class components for existing project
        'emoji' => false,
    ],

    /*
    |---------------------------------------------------------------------------
    | Class Namespace
    |---------------------------------------------------------------------------
    | Root class namespace for Livewire component classes.
    |
    */
    'class_namespace' => 'App\\Livewire',

    /*
    |---------------------------------------------------------------------------
    | Class Path
    |---------------------------------------------------------------------------
    | Path where Livewire component class files are created.
    |
    */
    'class_path' => app_path('Livewire'),

    /*
    |---------------------------------------------------------------------------
    | View Path
    |---------------------------------------------------------------------------
    | Path where Livewire component Blade templates are stored.
    |
    */
    'view_path' => resource_path('views/livewire'),

    /*
    |---------------------------------------------------------------------------
    | Temporary File Uploads
    |---------------------------------------------------------------------------
    | Configuration for handling file uploads in Livewire components.
    |
    */
    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

    /*
    |---------------------------------------------------------------------------
    | Render On Redirect
    |---------------------------------------------------------------------------
    | Whether to run render() after a redirect has been triggered.
    |
    */
    'render_on_redirect' => false,

    /*
    |---------------------------------------------------------------------------
    | Legacy Model Binding
    |---------------------------------------------------------------------------
    | Enable legacy behavior of binding directly to eloquent model properties.
    | Disabled by default in Livewire 4 as it's considered too "magical".
    |
    */
    'legacy_model_binding' => false,

    /*
    |---------------------------------------------------------------------------
    | Auto-inject Frontend Assets
    |---------------------------------------------------------------------------
    | Automatically inject JavaScript and CSS into pages with Livewire components.
    | When false, you need to use @livewireStyles and @livewireScripts.
    |
    */
    'inject_assets' => true,

    /*
    |---------------------------------------------------------------------------
    | Navigate (SPA mode)
    |---------------------------------------------------------------------------
    | Configure wire:navigate behavior for SPA-like navigation.
    |
    */
    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#22c55e', // Green to match brand
    ],

    /*
    |---------------------------------------------------------------------------
    | HTML Morph Markers
    |---------------------------------------------------------------------------
    | Inject markers for more reliable DOM morphing with @if, @class, @foreach.
    |
    */
    'inject_morph_markers' => true,

    /*
    |---------------------------------------------------------------------------
    | Smart Wire Keys
    |---------------------------------------------------------------------------
    | Automatically generate smart keys for nested components in loops.
    |
    */
    'smart_wire_keys' => true,

    /*
    |---------------------------------------------------------------------------
    | Pagination Theme
    |---------------------------------------------------------------------------
    | Theme for pagination: 'tailwind' or 'bootstrap'.
    |
    */
    'pagination_theme' => 'tailwind',

    /*
    |---------------------------------------------------------------------------
    | Release Token
    |---------------------------------------------------------------------------
    | Token for detecting stale sessions after deployment.
    | Change this value when deploying breaking changes.
    |
    */
    'release_token' => 'hugouserp-v4',

    /*
    |---------------------------------------------------------------------------
    | CSP Safe
    |---------------------------------------------------------------------------
    | Use CSP-safe version of Alpine for strict Content Security Policy.
    |
    */
    'csp_safe' => false,

    /*
    |---------------------------------------------------------------------------
    | Payload Guards
    |---------------------------------------------------------------------------
    | Protection against malicious or oversized payloads.
    |
    */
    'payload' => [
        'max_size' => 1024 * 1024 * 2,  // 2MB - increased for ERP data operations
        'max_nesting_depth' => 15,       // Increased for complex nested forms
        'max_calls' => 100,              // Increased for bulk operations
        'max_components' => 50,          // Increased for dashboard with many widgets
    ],
];
