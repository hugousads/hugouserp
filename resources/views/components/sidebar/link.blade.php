@props([
    'route',
    'label',
    'labelAr' => null,
    'icon' => '•',
    'permission' => null,
    'badge' => null,
    'external' => false
])

@php
    $locale = app()->getLocale();
    $displayLabel = $locale === 'ar' && $labelAr ? $labelAr : $label;
    $currentRoute = request()->route()?->getName() ?? '';
    $isActive = str_starts_with($currentRoute, $route);
    
    // Check permission
    $user = auth()->user();
    $canView = true;
    if ($permission) {
        $canView = $user && ($user->hasRole('Super Admin') || $user->can($permission));
    }
    
    // Generate URL
    $url = $external ? $route : route($route);
@endphp

@if($canView)
<a 
    href="{{ $url }}" 
    class="sidebar-link-secondary {{ $isActive ? 'active bg-white/10 border-white/20' : '' }}"
    {{ $external ? 'target="_blank" rel="noopener noreferrer"' : '' }}
>
    <span class="text-base">{{ $icon }}</span>
    <span class="text-sm flex-1">{{ $displayLabel }}</span>
    @if($badge)
        <span class="px-2 py-0.5 text-xs rounded-full bg-white/20 text-white">{{ $badge }}</span>
    @endif
    @if($isActive)
        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
    @endif
</a>
@endif
