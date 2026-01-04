<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

/**
 * Trait for user-friendly form handling in Livewire components
 * 
 * Provides:
 * - Toast notifications with contextual messages
 * - Form progress indicators
 * - Confirmation dialogs
 * - Helpful validation messages
 * - Keyboard shortcuts support
 */
trait WithUserFriendlyForms
{
    /**
     * Show a success toast notification
     */
    protected function showSuccess(string $message, ?string $action = null, ?string $actionUrl = null): void
    {
        $this->dispatch('toast', [
            'type' => 'success',
            'message' => $message,
            'action' => $action,
            'actionUrl' => $actionUrl,
            'duration' => 5000,
        ]);
    }

    /**
     * Show an error toast notification
     */
    protected function showError(string $message, ?string $details = null): void
    {
        $this->dispatch('toast', [
            'type' => 'error',
            'message' => $message,
            'details' => $details,
            'duration' => 8000,
        ]);
    }

    /**
     * Show a warning toast notification
     */
    protected function showWarning(string $message): void
    {
        $this->dispatch('toast', [
            'type' => 'warning',
            'message' => $message,
            'duration' => 6000,
        ]);
    }

    /**
     * Show an info toast notification
     */
    protected function showInfo(string $message): void
    {
        $this->dispatch('toast', [
            'type' => 'info',
            'message' => $message,
            'duration' => 4000,
        ]);
    }

    /**
     * Show a confirmation dialog
     */
    protected function confirmAction(string $title, string $message, string $confirmMethod, array $params = []): void
    {
        $this->dispatch('confirm', [
            'title' => $title,
            'message' => $message,
            'confirmMethod' => $confirmMethod,
            'params' => $params,
        ]);
    }

    /**
     * Show delete confirmation with contextual warning
     */
    protected function confirmDelete(string $itemName, string $deleteMethod, int|string $itemId): void
    {
        $this->confirmAction(
            __('Confirm Delete'),
            __('Are you sure you want to delete :name? This action cannot be undone.', ['name' => $itemName]),
            $deleteMethod,
            ['id' => $itemId]
        );
    }

    /**
     * Get user-friendly validation message for a field
     */
    protected function getFriendlyValidationMessage(string $field): string
    {
        $messages = [
            'name' => __('Please enter a valid name'),
            'email' => __('Please enter a valid email address'),
            'phone' => __('Please enter a valid phone number'),
            'password' => __('Password must be at least 8 characters'),
            'code' => __('Please enter a unique code'),
            'amount' => __('Please enter a valid amount'),
            'date' => __('Please select a valid date'),
            'branch_id' => __('Please select a branch'),
            'customer_id' => __('Please select a customer'),
            'supplier_id' => __('Please select a supplier'),
            'product_id' => __('Please select a product'),
            'quantity' => __('Please enter a valid quantity'),
            'price' => __('Please enter a valid price'),
        ];

        return $messages[$field] ?? __('This field is required');
    }

    /**
     * Show form progress indicator
     */
    protected function showProgress(string $message): void
    {
        $this->dispatch('form-progress', [
            'show' => true,
            'message' => $message,
        ]);
    }

    /**
     * Hide form progress indicator
     */
    protected function hideProgress(): void
    {
        $this->dispatch('form-progress', [
            'show' => false,
        ]);
    }

    /**
     * Show helpful tip for current context
     */
    protected function showTip(string $title, string $content): void
    {
        $this->dispatch('show-tip', [
            'title' => $title,
            'content' => $content,
        ]);
    }

    /**
     * Handle successful save with redirect and toast
     */
    protected function handleSuccessfulSave(string $message, string $route, array $params = []): void
    {
        session()->flash('success', $message);
        $this->redirectRoute($route, $params, navigate: true);
    }

    /**
     * Handle successful update with in-place notification
     */
    protected function handleSuccessfulUpdate(string $message): void
    {
        $this->showSuccess($message);
    }

    /**
     * Format number for display with locale support
     */
    protected function formatNumber(float|int $number, int $decimals = 2): string
    {
        $locale = app()->getLocale();
        
        if ($locale === 'ar') {
            // Use Arabic numerals
            $formatted = number_format($number, $decimals, '٫', '٬');
            return $this->convertToArabicNumerals($formatted);
        }
        
        return number_format($number, $decimals);
    }

    /**
     * Convert English numerals to Arabic
     */
    private function convertToArabicNumerals(string $number): string
    {
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        return str_replace($english, $arabic, $number);
    }

    /**
     * Format date for display with locale support
     */
    protected function formatDate(mixed $date, string $format = 'd M Y'): string
    {
        if (!$date) {
            return '-';
        }

        $carbonDate = $date instanceof \Carbon\Carbon 
            ? $date 
            : \Carbon\Carbon::parse($date);

        $locale = app()->getLocale();
        
        if ($locale === 'ar') {
            return $carbonDate->locale('ar')->translatedFormat($format);
        }
        
        return $carbonDate->format($format);
    }

    /**
     * Format currency for display
     */
    protected function formatCurrency(float|int $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('settings.general.default_currency', 'EGP');
        $formatted = $this->formatNumber($amount);
        
        $locale = app()->getLocale();
        
        if ($locale === 'ar') {
            return $formatted . ' ' . $currency;
        }
        
        return $currency . ' ' . $formatted;
    }

    /**
     * Get contextual help text for a form field
     */
    protected function getFieldHelp(string $field): ?string
    {
        $helpTexts = [
            'name' => __('Enter the full name as it should appear in reports'),
            'code' => __('Unique identifier - will be auto-generated if left empty'),
            'email' => __('Used for system notifications and password recovery'),
            'phone' => __('Include country code for international numbers'),
            'address' => __('Complete address including city and postal code'),
            'salary' => __('Monthly gross salary before deductions'),
            'min_stock' => __('System will alert when stock falls below this level'),
            'max_stock' => __('Maximum quantity to keep in inventory'),
            'price' => __('Selling price excluding taxes'),
            'cost' => __('Purchase cost for profit calculations'),
        ];

        return $helpTexts[$field] ?? null;
    }

    /**
     * Check if keyboard shortcuts are enabled
     */
    protected function keyboardShortcutsEnabled(): bool
    {
        return (bool) config('settings.advanced.keyboard_shortcuts', true);
    }

    /**
     * Get keyboard shortcut for an action
     */
    protected function getShortcut(string $action): ?string
    {
        if (!$this->keyboardShortcutsEnabled()) {
            return null;
        }

        $shortcuts = [
            'save' => 'Ctrl+S',
            'cancel' => 'Escape',
            'delete' => 'Delete',
            'new' => 'Ctrl+N',
            'search' => 'Ctrl+K',
            'refresh' => 'F5',
        ];

        return $shortcuts[$action] ?? null;
    }
}
