<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Livewire\Attributes\Computed;
use Livewire\Attributes\On;

/**
 * Mobile UX Optimization Trait
 * 
 * Provides:
 * - Responsive breakpoint detection
 * - Touch-friendly interactions
 * - Swipe gesture support
 * - Bottom sheet modals for mobile
 * - Optimized mobile pagination
 */
trait WithMobileOptimization
{
    public string $viewMode = 'auto'; // auto, list, grid, card
    public bool $isMobileView = false;
    public bool $showMobileFilters = false;
    public int $mobilePerPage = 10;
    public int $desktopPerPage = 25;

    /**
     * Initialize mobile optimization
     */
    public function bootWithMobileOptimization(): void
    {
        // Default view mode based on settings
        $this->viewMode = config('settings.ui.default_view_mode', 'auto');
    }

    /**
     * Set mobile view state (called from JS)
     */
    #[On('set-mobile-view')]
    public function setMobileView(bool $isMobile): void
    {
        $this->isMobileView = $isMobile;
        
        // Adjust per page for mobile
        if (property_exists($this, 'perPage')) {
            $this->perPage = $isMobile ? $this->mobilePerPage : $this->desktopPerPage;
        }
    }

    /**
     * Toggle mobile filters visibility
     */
    public function toggleMobileFilters(): void
    {
        $this->showMobileFilters = !$this->showMobileFilters;
    }

    /**
     * Close mobile filters
     */
    public function closeMobileFilters(): void
    {
        $this->showMobileFilters = false;
    }

    /**
     * Set view mode
     */
    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['auto', 'list', 'grid', 'card'])) {
            $this->viewMode = $mode;
        }
    }

    /**
     * Get current view mode based on settings and mobile state
     */
    #[Computed]
    public function currentViewMode(): string
    {
        if ($this->viewMode !== 'auto') {
            return $this->viewMode;
        }

        return $this->isMobileView ? 'card' : 'list';
    }

    /**
     * Get responsive grid classes
     */
    #[Computed]
    public function gridClasses(): string
    {
        return match($this->currentViewMode) {
            'grid' => 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4',
            'card' => 'grid grid-cols-1 gap-4',
            default => 'divide-y divide-slate-200 dark:divide-slate-700',
        };
    }

    /**
     * Handle swipe action (for mobile list items)
     * @param string $direction The swipe direction ('left' or 'right')
     * @param int|string $itemId The ID of the item being swiped
     */
    #[On('swipe-action')]
    public function handleSwipeAction(string $direction, int|string $itemId): void
    {
        // Override in component to handle swipe actions
        // e.g., swipe left to delete, swipe right to edit
    }

    /**
     * Get touch-friendly action button classes
     */
    #[Computed]
    public function touchButtonClasses(): string
    {
        return $this->isMobileView
            ? 'min-h-[44px] min-w-[44px] p-3' // iOS minimum touch target
            : 'p-2';
    }

    /**
     * Get mobile-optimized modal size
     */
    #[Computed]
    public function modalSize(): string
    {
        return $this->isMobileView ? 'full' : 'lg';
    }
}
