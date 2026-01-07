<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRequisitionItem extends BaseModel
{
    protected ?string $moduleKey = 'purchases';

    protected $table = 'purchase_requisition_items';

    protected $fillable = [
        'requisition_id', 'product_id', 'quantity', 'unit_id',
        'estimated_price', 'specifications', 'preferred_supplier_id',
        'extra_attributes', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'estimated_price' => 'decimal:4',
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

    // Backward compatibility for uom -> unit_id
    public function getUomAttribute()
    {
        return $this->unit_id;
    }

    public function setUomAttribute($value): void
    {
        $this->attributes['unit_id'] = $value;
    }

    // Backward compatibility for estimated_unit_cost -> estimated_price
    public function getEstimatedUnitCostAttribute()
    {
        return $this->estimated_price;
    }

    public function setEstimatedUnitCostAttribute($value): void
    {
        $this->attributes['estimated_price'] = $value;
    }

    // Calculated field for backward compatibility
    public function getEstimatedTotalAttribute()
    {
        return ($this->quantity ?? 0) * ($this->estimated_price ?? 0);
    }
}
