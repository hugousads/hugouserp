<?php

declare(strict_types=1);

namespace App\Livewire\Rental\Properties;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Property;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;
    use HandlesErrors;

    public ?int $propertyId = null;

    public string $name = '';

    public string $address = '';

    public string $notes = '';

    public function mount(?int $property = null): void
    {
        if ($property) {
            $this->authorize('rental.properties.update');
            $this->propertyId = $property;
            $this->loadProperty();
        } else {
            $this->authorize('rental.properties.create');
        }
    }

    protected function loadProperty(): void
    {
        $property = Property::findOrFail($this->propertyId);

        $this->name = $property->name;
        $this->address = $property->address ?? '';
        $this->notes = $property->notes ?? '';
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ];
    }

    public function save(): mixed
    {
        if ($this->propertyId) {
            $this->authorize('rental.properties.update');
        } else {
            $this->authorize('rental.properties.create');
        }

        $validated = $this->validate();

        $user = auth()->user();
        $data = array_merge($validated, [
            'branch_id' => $user->branch_id ?? 1,
        ]);

        return $this->handleOperation(
            operation: function () use ($data, $user) {
                if ($this->propertyId) {
                    Property::findOrFail($this->propertyId)->update($data);
                } else {
                    Property::create($data);
                }
                Cache::forget('properties_stats_'.($user->branch_id ?? 'all'));
            },
            successMessage: $this->propertyId 
                ? __('Property updated successfully') 
                : __('Property created successfully'),
            redirectRoute: 'app.rental.properties.index'
        );
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.rental.properties.form');
    }
}
