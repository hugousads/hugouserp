<?php

return [

    'class_namespace' => 'App\\Livewire',

    'view_path' => resource_path('views/livewire'),

    'layout' => 'layouts.app',

    'render_on_redirect' => false,

    'inject_assets' => false,

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#22c55e',
    ],

    'inject_morph_markers' => true,

    'pagination_theme' => 'tailwind',

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => ['file', 'max:12288'],
        'directory' => 'livewire-tmp',
        'middleware' => 'throttle:60,1',
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'svg+xml',
            'jpeg', 'webp', 'mp4', 'mov', 'avi',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
