<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\HasBranch;
use App\Traits\HasDynamicFields;
use App\Traits\HasJsonAttributes;
use App\Traits\LogsActivity;
use App\Traits\ModuleAware;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

abstract class BaseModel extends Model
{
    use HasBranch;
    use HasDynamicFields;
    use HasFactory;
    use HasJsonAttributes;
    use LogsActivity;
    use ModuleAware;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if ($model->usesUuid() && empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }

            if ($model->usesCode() && empty($model->code)) {
                $model->code = static::generateCode();
            }
        });
    }

    protected static function generateCode(): string
    {
        return match (static::class) {
            User::class => 'USR-'.Str::upper(Str::random(8)),
            Branch::class => 'BR-'.Str::upper(Str::random(8)),
            Customer::class => 'CUST-'.Str::upper(Str::random(8)),
            Supplier::class => 'SUP-'.Str::upper(Str::random(8)),
            Warehouse::class => 'WH-'.Str::upper(Str::random(8)),
            Product::class => 'PRD-'.Str::upper(Str::random(8)),
            Purchase::class => 'PO-'.Str::upper(Str::random(8)),
            PurchaseItem::class => 'POI-'.Str::upper(Str::random(8)),
            Sale::class => 'SO-'.Str::upper(Str::random(8)),
            SaleItem::class => 'SOI-'.Str::upper(Str::random(8)),
            StockMovement::class => 'STM-'.Str::upper(Str::random(8)),
            Adjustment::class => 'ADJ-'.Str::upper(Str::random(8)),
            AdjustmentItem::class => 'ADJI-'.Str::upper(Str::random(8)),
            Transfer::class => 'TRF-'.Str::upper(Str::random(8)),
            TransferItem::class => 'TRFI-'.Str::upper(Str::random(8)),
            ReturnNote::class => 'RTN-'.Str::upper(Str::random(8)),
            Receipt::class => 'RCPT-'.Str::upper(Str::random(8)),
            Delivery::class => 'DLV-'.Str::upper(Str::random(8)),
            Property::class => 'PROP-'.Str::upper(Str::random(8)),
            RentalUnit::class => 'RUNIT-'.Str::upper(Str::random(8)),
            Tenant::class => 'TEN-'.Str::upper(Str::random(8)),
            RentalContract::class => 'RC-'.Str::upper(Str::random(8)),
            RentalInvoice::class => 'RINV-'.Str::upper(Str::random(8)),
            RentalPayment::class => 'RPAY-'.Str::upper(Str::random(8)),
            Vehicle::class => 'VEH-'.Str::upper(Str::random(8)),
            VehicleContract::class => 'VC-'.Str::upper(Str::random(8)),
            VehiclePayment::class => 'VPAY-'.Str::upper(Str::random(8)),
            Warranty::class => 'WAR-'.Str::upper(Str::random(8)),
            HREmployee::class => 'EMP-'.Str::upper(Str::random(8)),
            Attendance::class => 'ATT-'.Str::upper(Str::random(8)),
            LeaveRequest::class => 'LV-'.Str::upper(Str::random(8)),
            Payroll::class => 'PAY-'.Str::upper(Str::random(8)),
            PriceGroup::class => 'PG-'.Str::upper(Str::random(8)),
            Tax::class => 'TAX-'.Str::upper(Str::random(8)),
            default => 'REC-'.Str::upper(Str::random(8)),
        };
    }

    protected function usesUuid(): bool
    {
        return in_array('uuid', $this->getFillable(), true);
    }

    protected function usesCode(): bool
    {
        return in_array('code', $this->getFillable(), true);
    }
}
