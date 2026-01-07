<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionItem extends BaseModel
{
    protected ?string $moduleKey = 'purchases';

    protected $table = 'purchase_requisition_items';

    protected $fillable = [
        'requisition_id', 'product_id', 'quantity', 'uom',
        'estimated_unit_cost', 'estimated_total', 'specifications', 'notes',
        'extra_attributes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'estimated_unit_cost' => 'decimal:4',
        'estimated_total' => 'decimal:4',
        'extra_attributes' => 'array',
    ];

    // Relationships
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequisition::class, 'requisition_id');
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
