<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Product extends BaseModel
{
    protected ?string $moduleKey = 'inventory';

    protected $table = 'products';

    protected $with = ['branch', 'module'];

    protected $fillable = [
        'uuid', 'code', 'name', 'sku', 'barcode',
        'thumbnail', 'image', 'gallery',
        'module_id',
        'category_id',
        'product_type',
        'type',
        'has_variations',
        'has_variants',
        'parent_product_id',
        'variation_attributes',
        'custom_fields',
        'uom', 'uom_factor', 'unit_id',
        'cost_method', 'cost_currency', 'standard_cost', 'cost',
        'tax_id',
        'price_list_id', 'default_price', 'price_currency',
        'min_stock', 'reorder_point', 'reorder_qty',
        'is_serialized', 'is_batch_tracked',
        'track_stock_alerts',
        'hourly_rate', 'service_duration', 'duration_unit',
        'status', 'notes',
        'extra_attributes',
        'branch_id', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'standard_cost' => 'decimal:4',
        'cost' => 'decimal:4',
        'default_price' => 'decimal:4',
        'min_stock' => 'decimal:4',
        'reorder_point' => 'decimal:4',
        'reorder_qty' => 'decimal:4',
        'hourly_rate' => 'decimal:2',
        'service_duration' => 'integer',
        'is_serialized' => 'boolean',
        'is_batch_tracked' => 'boolean',
        'has_variations' => 'boolean',
        'has_variants' => 'boolean',
        'track_stock_alerts' => 'boolean',
        'extra_attributes' => 'array',
        'variation_attributes' => 'array',
        'custom_fields' => 'array',
        'gallery' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model): void {
            $model->uuid ??= (string) Str::uuid();
            $model->code ??= 'PRD-'.Str::upper(Str::random(8));
            $model->type ??= 'product';
            $model->product_type ??= 'physical';
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function parentProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'parent_product_id');
    }

    public function tax(): BelongsTo
    {
        return $this->belongsTo(Tax::class, 'tax_id');
    }

    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(PriceGroup::class, 'price_list_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function transferItems(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }

    public function adjustmentItems(): HasMany
    {
        return $this->hasMany(AdjustmentItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function variations(): HasMany
    {
        return $this->hasMany(ProductVariation::class);
    }

    public function childProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'parent_product_id');
    }

    public function fieldValues(): HasMany
    {
        return $this->hasMany(ProductFieldValue::class);
    }

    public function priceTiers(): HasMany
    {
        return $this->hasMany(ProductPriceTier::class);
    }

    public function compatibilities(): HasMany
    {
        return $this->hasMany(ProductCompatibility::class);
    }

    public function compatibleVehicles(): BelongsToMany
    {
        return $this->belongsToMany(VehicleModel::class, 'product_compatibilities')
            ->withPivot(['oem_number', 'position', 'notes', 'is_verified'])
            ->withTimestamps();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeServices($query)
    {
        return $query->where('type', 'service');
    }

    public function scopeForModule($query, $moduleId)
    {
        return $query->where('module_id', $moduleId);
    }

    public function scopeParentsOnly($query)
    {
        return $query->whereNull('parent_product_id');
    }

    public function scopeVariationsOnly($query)
    {
        return $query->whereNotNull('parent_product_id');
    }

    public function scopeWithVariations($query)
    {
        return $query->where('has_variations', true);
    }

    public function uomLabel(): string
    {
        return $this->uom ?: 'unit';
    }

    public function getFieldValue(string $fieldKey)
    {
        $value = $this->fieldValues()
            ->whereHas('field', fn ($q) => $q->where('field_key', $fieldKey))
            ->first();

        return $value?->typed_value;
    }

    public function setFieldValue(string $fieldKey, $value): ?ProductFieldValue
    {
        if (! $this->module_id) {
            return null;
        }

        $field = ModuleProductField::where('module_id', $this->module_id)
            ->where('field_key', $fieldKey)
            ->first();

        if (! $field) {
            return null;
        }

        return ProductFieldValue::updateOrCreate(
            [
                'product_id' => $this->id,
                'module_product_field_id' => $field->id,
            ],
            ['value' => is_array($value) ? json_encode($value) : (string) $value]
        );
    }

    public function getAllFieldValues(): array
    {
        return $this->fieldValues()
            ->with('field')
            ->get()
            ->mapWithKeys(fn ($v) => [$v->field->field_key => $v->typed_value])
            ->toArray();
    }

    public function getPriceForQuantity(float $quantity, ?int $branchId = null): ?float
    {
        $tier = $this->priceTiers()
            ->active()
            ->forBranch($branchId)
            ->forQuantity($quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        return $tier?->selling_price ?? $this->default_price;
    }

    public function getCostForQuantity(float $quantity, ?int $branchId = null): ?float
    {
        $tier = $this->priceTiers()
            ->active()
            ->forBranch($branchId)
            ->forQuantity($quantity)
            ->orderBy('min_quantity', 'desc')
            ->first();

        return $tier?->cost_price ?? $this->standard_cost;
    }

    public function isRental(): bool
    {
        return $this->product_type === 'rental' || $this->module?->is_rental;
    }

    public function isService(): bool
    {
        return $this->product_type === 'service' || $this->type === 'service' || $this->module?->is_service;
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return $this->thumbnail ? Storage::url($this->thumbnail) : null;
    }
}
