<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierQuotationItem extends BaseModel
{
    protected ?string $moduleKey = 'purchases';

    protected $table = 'supplier_quotation_items';

    protected $fillable = [
        'quotation_id', 'product_id', 'quantity', 'uom',
        'unit_cost', 'discount', 'tax_rate', 'line_total',
        'specifications', 'notes', 'extra_attributes',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'discount' => 'decimal:4',
        'tax_rate' => 'decimal:4',
        'line_total' => 'decimal:4',
        'extra_attributes' => 'array',
    ];

    // Relationships
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(SupplierQuotation::class, 'quotation_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Backward compatibility accessors for qty
    public function getQtyAttribute()
    {
        return $this->quantity;
    }

    public function setQtyAttribute($value): void
    {
        $this->attributes['quantity'] = $value;
    }
}
