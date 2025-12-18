<?php

declare(strict_types=1);

namespace App\Traits;

trait HasDualModeCrud
{
    public bool $isModal = false;
    public bool $showModal = false;
    public ?int $editingId = null;

    /**
     * Initialize dual mode CRUD
     * Call this from mount() method
     */
    public function initializeDualMode(): void
    {
        // Check if we're in modal mode from URL parameter
        $this->isModal = request()->has('modal') || request()->input('mode') === 'modal';
        
        // If editing and ID is provided, load the record
        if ($editingId = request()->input('id') ?? request()->input('edit')) {
            $this->editingId = (int) $editingId;
            if (method_exists($this, 'loadRecord')) {
                $this->loadRecord($this->editingId);
            }
        }
    }

    /**
     * Open modal for creating new record
     */
    public function openCreateModal(): void
    {
        $this->reset();
        $this->editingId = null;
        $this->showModal = true;
        
        if (method_exists($this, 'resetForm')) {
            $this->resetForm();
        }
    }

    /**
     * Open modal for editing existing record
     */
    public function openEditModal(int $id): void
    {
        $this->editingId = $id;
        $this->showModal = true;
        
        if (method_exists($this, 'loadRecord')) {
            $this->loadRecord($id);
        }
    }

    /**
     * Close modal
     */
    public function closeModal(): void
    {
        $this->showModal = false;
        $this->editingId = null;
        $this->reset();
        
        if (method_exists($this, 'resetForm')) {
            $this->resetForm();
        }
    }

    /**
     * Navigate to dedicated create page
     */
    public function goToCreatePage(): void
    {
        $currentRoute = request()->route()->getName();
        $createRoute = str_replace('.index', '.create', $currentRoute);
        
        if (\Route::has($createRoute)) {
            return redirect()->route($createRoute);
        }
    }

    /**
     * Navigate to dedicated edit page
     */
    public function goToEditPage(int $id): void
    {
        $currentRoute = request()->route()->getName();
        $editRoute = str_replace('.index', '.edit', $currentRoute);
        
        if (\Route::has($editRoute)) {
            return redirect()->route($editRoute, ['id' => $id]);
        }
    }

    /**
     * Return to index/list page
     */
    public function goToIndex(): void
    {
        $currentRoute = request()->route()->getName();
        
        // Try to find index route
        $patterns = ['.create', '.edit', '.form'];
        foreach ($patterns as $pattern) {
            if (str_contains($currentRoute, $pattern)) {
                $indexRoute = str_replace($pattern, '.index', $currentRoute);
                if (\Route::has($indexRoute)) {
                    return redirect()->route($indexRoute);
                }
            }
        }
        
        // Fallback to previous page
        return redirect()->back();
    }

    /**
     * Handle save action based on mode
     */
    public function handleSave(): void
    {
        // Validate first
        $this->validate();
        
        // Call the actual save method
        if (method_exists($this, 'performSave')) {
            $result = $this->performSave();
        } else {
            $result = $this->save();
        }
        
        // Handle post-save based on mode
        if ($this->isModal || $this->showModal) {
            $this->closeModal();
            $this->dispatch('refreshList');
        } else {
            $this->goToIndex();
        }
    }

    /**
     * Check if we're in create mode
     */
    public function isCreating(): bool
    {
        return empty($this->editingId);
    }

    /**
     * Check if we're in edit mode
     */
    public function isEditing(): bool
    {
        return !empty($this->editingId);
    }

    /**
     * Get page title based on mode
     */
    public function getPageTitle(string $entityName): string
    {
        if ($this->isCreating()) {
            return __('Create :entity', ['entity' => $entityName]);
        }
        
        return __('Edit :entity', ['entity' => $entityName]);
    }

    /**
     * Get save button text based on mode
     */
    public function getSaveButtonText(): string
    {
        if ($this->isCreating()) {
            return __('Create');
        }
        
        return __('Update');
    }
}
