<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAssetUpgrade extends Model
{
    use HasFactory;

    protected $fillable = [
        'upgrade_number',
        'fixed_asset_id',
        'item_id',
        'warehouse_id',
        'purchase_order_id',
        'goods_receipt_id',
        'upgrade_date',
        'component_name',
        'qty',
        'unit_cost',
        'cost',
        'accounting_treatment', // EXPENSE, CAPITALIZE_VALUE, CAPITALIZE_EXTEND
        'is_capitalized',
        'book_value_before',
        'book_value_after',
        'new_remaining_months',
        'currency_id',
        'vendor_or_executor',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'upgrade_date'   => 'date',
        'qty'            => 'decimal:2',
        'unit_cost'      => 'decimal:2',
        'cost'           => 'decimal:2',
        'is_capitalized' => 'boolean',
        'book_value_before' => 'decimal:2',
        'book_value_after'  => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getTreatmentLabelAttribute(): string
    {
        return match ($this->accounting_treatment) {
            'CAPITALIZE_VALUE'  => 'Kapitalisasi Nilai (Sisa Umur Tetap)',
            'CAPITALIZE_EXTEND' => 'Kapitalisasi Nilai + Perpanjang Umur (' . ($this->new_remaining_months ?? 0) . ' Bln)',
            default             => 'Beban Pemeliharaan (Tidak Mengubah Nilai/Umur)',
        };
    }
}
