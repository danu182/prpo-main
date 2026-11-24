<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ItemAlias extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'alias_name',
        'source',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    /**
     * Helper aman untuk menyimpan nama alternatif baru tanpa duplikasi
     */
    public static function recordAlias($itemId, ?string $aliasName, string $source = 'MANUAL'): void
    {
        $clean = trim((string) $aliasName);
        if (!$itemId || $clean === '' || $clean === '-') {
            return;
        }

        $item = Item::find($itemId);
        if ($item && strcasecmp(trim($item->name), $clean) === 0) {
            return; // Jangan simpan jika sama persis dengan nama Master Item
        }

        static::firstOrCreate(
            ['item_id' => $itemId, 'alias_name' => $clean],
            ['source' => $source]
        );
    }
}
