<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

class Edit extends Component
{
    use WithFileUploads;

    #[Layout('layouts.app')]
    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public $avatar;

    public ?string $currentAvatar = null;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(403);
        }

        $this->name = $user->name ?? '';
        $this->email = $user->email ?? '';
        $this->phone = $user->phone ?? '';
        $this->currentAvatar = $user->avatar ?? null;
    }

    public function updateProfile(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update($validated);

        session()->flash('success', __('Profile updated successfully'));
    }

    public function updatePassword(): void
    {
        $user = Auth::user();

        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        session()->flash('success', __('Password updated successfully'));
    }

    #[On('file-uploaded')]
    public function handleFileUploaded(string $fieldId, string $path, array $fileInfo): void
    {
        if ($fieldId === 'profile-avatar') {
            $user = Auth::user();
            
            // Delete old avatar if exists
            if ($this->currentAvatar) {
                Storage::disk('public')->delete($this->currentAvatar);
            }
            
            // Update user with new avatar path
            $user->update([
                'avatar' => $path,
            ]);
            
            $this->currentAvatar = $path;
            session()->flash('success', __('Avatar updated successfully'));
        }
    }

    #[On('file-cleared')]
    public function handleFileCleared(string $fieldId): void
    {
        if ($fieldId === 'profile-avatar') {
            $this->removeAvatar();
        }
    }

    public function updateAvatar(): void
    {
        $user = Auth::user();

        $this->validate([
            'avatar' => ['required', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ]);

        if ($this->currentAvatar) {
            Storage::disk('public')->delete($this->currentAvatar);
        }

        $path = $this->avatar->store('avatars', 'public');

        $user->update([
            'avatar' => $path,
        ]);

        $this->currentAvatar = $path;
        $this->reset('avatar');

        session()->flash('success', __('Avatar updated successfully'));
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($this->currentAvatar) {
            Storage::disk('public')->delete($this->currentAvatar);
        }

        $user->update([
            'avatar' => null,
        ]);

        $this->currentAvatar = null;

        session()->flash('success', __('Avatar removed successfully'));
    }

    public function render()
    {
        return view('livewire.profile.edit');
    }
}
