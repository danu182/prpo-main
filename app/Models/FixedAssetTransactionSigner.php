<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAssetTransactionSigner extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_asset_transaction_id',
        'user_id',
        'role_label',
        'sequence',
        'signature_mode',
    ];

    public function transaction()
    {
        return $this->belongsTo(FixedAssetTransaction::class, 'fixed_asset_transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
