<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Onboarding Guide Component
 * 
 * Provides an interactive guide for new users to learn the ERP system.
 * Shows contextual help based on which page the user is on.
 * Tracks completed onboarding steps in user preferences.
 */
class OnboardingGuide extends Component
{
    public bool $showGuide = false;
    public int $currentStep = 0;
    public array $completedSteps = [];
    public string $context = 'dashboard';

    public function mount(string $context = 'dashboard'): void
    {
        $this->context = $context;
        
        $user = Auth::user();
        if (!$user) {
            return;
        }

        // Get completed steps from user preferences
        $preferences = $user->preferences ?? [];
        $this->completedSteps = $preferences['onboarding_completed'] ?? [];
        
        // Check if we should show the guide automatically for new users
        $hasSeenOnboarding = in_array('welcome', $this->completedSteps);
        
        if (!$hasSeenOnboarding && $this->isNewUser()) {
            $this->showGuide = true;
        }
    }

    /**
     * Check if user is new (registered in last 7 days)
     */
    protected function isNewUser(): bool
    {
        $user = Auth::user();
        
        if (!$user || !$user->created_at) {
            return false;
        }
        
        return $user->created_at->gt(now()->subDays(7));
    }

    /**
     * Get onboarding steps based on context
     */
    public function getStepsProperty(): array
    {
        $allSteps = [
            'dashboard' => [
                [
                    'id' => 'welcome',
                    'title' => __('Welcome to Your ERP System!'),
                    'description' => __('This system helps you manage your business. Let us show you around.'),
                    'icon' => '👋',
                    'target' => null,
                ],
                [
                    'id' => 'sidebar',
                    'title' => __('Navigation Menu'),
                    'description' => __('Use the sidebar to navigate between modules. Click on any menu item to explore.'),
                    'icon' => '📍',
                    'target' => '#sidebar',
                ],
                [
                    'id' => 'quick_actions',
                    'title' => __('Quick Actions'),
                    'description' => __('Use quick action buttons to perform common tasks like creating sales, adding products, etc.'),
                    'icon' => '⚡',
                    'target' => '[data-quick-actions]',
                ],
                [
                    'id' => 'notifications',
                    'title' => __('Notifications'),
                    'description' => __('Check the notification bell for important alerts about stock, invoices, and system updates.'),
                    'icon' => '🔔',
                    'target' => '[data-notifications]',
                ],
                [
                    'id' => 'user_menu',
                    'title' => __('Your Profile'),
                    'description' => __('Click your name to access profile settings, change password, or logout.'),
                    'icon' => '👤',
                    'target' => '[data-user-menu]',
                ],
            ],
            'sales' => [
                [
                    'id' => 'sales_intro',
                    'title' => __('Sales Module'),
                    'description' => __('Create invoices, manage orders, and track customer payments here.'),
                    'icon' => '💰',
                    'target' => null,
                ],
                [
                    'id' => 'create_sale',
                    'title' => __('Create a Sale'),
                    'description' => __('Click "New Sale" to create a sales invoice. Select customer, add products, and save.'),
                    'icon' => '➕',
                    'target' => '[data-create-button]',
                ],
            ],
            'inventory' => [
                [
                    'id' => 'inventory_intro',
                    'title' => __('Inventory Module'),
                    'description' => __('Manage your products, track stock levels, and receive alerts for low stock.'),
                    'icon' => '📦',
                    'target' => null,
                ],
                [
                    'id' => 'add_product',
                    'title' => __('Add Products'),
                    'description' => __('Add your products with prices, descriptions, and stock quantities.'),
                    'icon' => '➕',
                    'target' => '[data-create-button]',
                ],
            ],
            'settings' => [
                [
                    'id' => 'settings_intro',
                    'title' => __('System Settings'),
                    'description' => __('Configure your company information, preferences, and system behavior here.'),
                    'icon' => '⚙️',
                    'target' => null,
                ],
                [
                    'id' => 'settings_tabs',
                    'title' => __('Settings Categories'),
                    'description' => __('Use the tabs to navigate between different settings categories.'),
                    'icon' => '📑',
                    'target' => '[data-settings-tabs]',
                ],
            ],
        ];

        return $allSteps[$this->context] ?? $allSteps['dashboard'];
    }

    /**
     * Show the onboarding guide
     */
    public function openGuide(): void
    {
        $this->showGuide = true;
        $this->currentStep = 0;
    }

    /**
     * Hide the onboarding guide
     */
    public function closeGuide(): void
    {
        $this->showGuide = false;
    }

    /**
     * Go to next step
     */
    public function nextStep(): void
    {
        $steps = $this->steps;
        
        if ($this->currentStep < count($steps) - 1) {
            $this->markStepComplete($steps[$this->currentStep]['id']);
            $this->currentStep++;
        } else {
            // Last step - mark as complete and close
            $this->markStepComplete($steps[$this->currentStep]['id']);
            $this->finishOnboarding();
        }
    }

    /**
     * Go to previous step
     */
    public function previousStep(): void
    {
        if ($this->currentStep > 0) {
            $this->currentStep--;
        }
    }

    /**
     * Skip to a specific step
     */
    public function goToStep(int $step): void
    {
        if ($step >= 0 && $step < count($this->steps)) {
            $this->currentStep = $step;
        }
    }

    /**
     * Mark a step as complete
     */
    protected function markStepComplete(string $stepId): void
    {
        if (!in_array($stepId, $this->completedSteps)) {
            $this->completedSteps[] = $stepId;
            $this->saveProgress();
        }
    }

    /**
     * Save onboarding progress to user preferences
     */
    protected function saveProgress(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $preferences = $user->preferences ?? [];
        $preferences['onboarding_completed'] = $this->completedSteps;
        $user->update(['preferences' => $preferences]);
    }

    /**
     * Finish onboarding
     */
    public function finishOnboarding(): void
    {
        $this->saveProgress();
        $this->showGuide = false;
        
        session()->flash('success', __('Onboarding complete! You can access help anytime from the menu.'));
    }

    /**
     * Skip all onboarding
     */
    public function skipOnboarding(): void
    {
        // Mark welcome as seen so it doesn't show again
        if (!in_array('welcome', $this->completedSteps)) {
            $this->completedSteps[] = 'welcome';
            $this->saveProgress();
        }
        
        $this->showGuide = false;
    }

    /**
     * Reset onboarding (for testing)
     */
    public function resetOnboarding(): void
    {
        $user = Auth::user();
        if (!$user) {
            return;
        }

        $preferences = $user->preferences ?? [];
        $preferences['onboarding_completed'] = [];
        $user->update(['preferences' => $preferences]);
        
        $this->completedSteps = [];
        $this->currentStep = 0;
        $this->showGuide = true;
    }

    /**
     * Get progress percentage
     */
    public function getProgressProperty(): int
    {
        $steps = $this->steps;
        if (empty($steps)) {
            return 100;
        }

        $completed = count(array_filter($steps, fn($step) => in_array($step['id'], $this->completedSteps)));
        
        return (int) round(($completed / count($steps)) * 100);
    }

    #[On('start-onboarding')]
    public function handleStartOnboarding(): void
    {
        $this->openGuide();
    }

    public function render(): View
    {
        return view('livewire.shared.onboarding-guide', [
            'steps' => $this->steps,
            'progress' => $this->progress,
        ]);
    }
}
