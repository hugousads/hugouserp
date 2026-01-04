<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;

/**
 * Trait for Livewire 4 enhanced form features
 * 
 * Provides:
 * - Auto-save draft functionality
 * - Dirty state tracking
 * - Form reset confirmations
 * - Computed validation messages
 * - Loading state management
 */
trait WithLivewire4Forms
{
    /**
     * Default auto-save interval in seconds
     */
    protected const DEFAULT_AUTO_SAVE_INTERVAL = 30;

    /**
     * Default auto-save enabled state
     */
    protected const DEFAULT_AUTO_SAVE_ENABLED = true;

    /**
     * Whether the form has unsaved changes
     */
    public bool $isDirty = false;

    /**
     * Whether auto-save draft is enabled
     */
    public bool $autoSaveDraft = false;

    /**
     * Auto-save interval in seconds
     */
    public int $autoSaveInterval = self::DEFAULT_AUTO_SAVE_INTERVAL;

    /**
     * Last saved draft timestamp
     */
    public ?string $lastDraftSaved = null;

    /**
     * Initialize form state
     */
    public function initializeWithLivewire4Forms(): void
    {
        // Load auto-save preference from settings
        $this->autoSaveDraft = (bool) config('settings.advanced.auto_save_forms', self::DEFAULT_AUTO_SAVE_ENABLED);
        $this->autoSaveInterval = (int) config('settings.advanced.auto_save_interval', self::DEFAULT_AUTO_SAVE_INTERVAL);
    }

    /**
     * Mark form as dirty when any property changes
     */
    public function updatedFormProperty(): void
    {
        $this->isDirty = true;
    }

    /**
     * Save draft to session
     */
    public function saveDraft(): void
    {
        if (!$this->autoSaveDraft) {
            return;
        }

        $draftKey = $this->getDraftKey();
        $formData = $this->getFormData();

        session()->put($draftKey, [
            'data' => $formData,
            'saved_at' => now()->toIso8601String(),
        ]);

        $this->lastDraftSaved = now()->format('H:i:s');
        $this->dispatch('draft-saved');
    }

    /**
     * Load draft from session
     */
    public function loadDraft(): bool
    {
        $draftKey = $this->getDraftKey();
        $draft = session()->get($draftKey);

        if (!$draft || !isset($draft['data'])) {
            return false;
        }

        $this->setFormData($draft['data']);
        $this->isDirty = false;

        return true;
    }

    /**
     * Clear draft from session
     */
    public function clearDraft(): void
    {
        session()->forget($this->getDraftKey());
        $this->lastDraftSaved = null;
    }

    /**
     * Check if draft exists
     */
    public function hasDraft(): bool
    {
        return session()->has($this->getDraftKey());
    }

    /**
     * Get unique draft key for this form
     */
    protected function getDraftKey(): string
    {
        $className = class_basename(static::class);
        $userId = auth()->id() ?? 'guest';
        $recordId = $this->getRecordId() ?? 'new';

        return "form_draft_{$className}_{$userId}_{$recordId}";
    }

    /**
     * Get form data for draft - override in component
     */
    protected function getFormData(): array
    {
        // Default implementation looks for common form property names
        if (property_exists($this, 'form')) {
            return $this->form;
        }

        return [];
    }

    /**
     * Set form data from draft - override in component
     */
    protected function setFormData(array $data): void
    {
        if (property_exists($this, 'form') && is_array($this->form)) {
            foreach ($data as $key => $value) {
                if (array_key_exists($key, $this->form)) {
                    $this->form[$key] = $value;
                }
            }
        }
    }

    /**
     * Get record ID for draft key - override in component
     */
    protected function getRecordId(): ?string
    {
        // Look for common ID property names
        $idProperties = ['id', 'branchId', 'employeeId', 'productId', 'customerId'];

        foreach ($idProperties as $prop) {
            if (property_exists($this, $prop) && $this->$prop !== null) {
                return (string) $this->$prop;
            }
        }

        return null;
    }

    /**
     * Confirm before leaving with unsaved changes
     */
    public function confirmLeave(): bool
    {
        if ($this->isDirty) {
            $this->dispatch('confirm-leave');
            return false;
        }

        return true;
    }

    /**
     * Reset form to initial state
     */
    public function resetFormState(): void
    {
        $this->isDirty = false;
        $this->clearDraft();
        $this->dispatch('form-reset');
    }

    /**
     * Get validation message for a field
     */
    public function getValidationMessage(string $field): ?string
    {
        $errors = $this->getErrorBag();
        return $errors->first($field) ?: null;
    }

    /**
     * Check if a field has validation error
     */
    public function hasValidationError(string $field): bool
    {
        return $this->getErrorBag()->has($field);
    }

    /**
     * Get CSS class for input based on validation state
     */
    public function getInputClass(string $field, string $baseClass = 'erp-input'): string
    {
        if ($this->hasValidationError($field)) {
            return $baseClass . ' border-red-500 focus:border-red-500 focus:ring-red-500';
        }

        return $baseClass;
    }
}
