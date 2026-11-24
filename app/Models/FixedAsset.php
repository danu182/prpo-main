<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FixedAsset extends Model
{
    use HasFactory;

    protected $guarded = [];

    // Relasi ke Master Barang
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Relasi ke Penerimaan Barang
    public function goodsReceipt()
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    // Relasi ke User (Pemegang Aset)
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function histories()
    {
        return $this->hasMany(FixedAssetHistory::class)->orderBy('created_at', 'desc');
    }

    public function fixedAsset()
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    // Relasi ke Entitas / PT Pemilik Aset
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    // Relasi ke Master Status
    public function status()
    {
        return $this->belongsTo(\App\Models\Status::class, 'status_id');
    }

    public function importBatch()
    {
        return $this->belongsTo(ImportBatch::class, 'batch_id', 'batch_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'assigned_to');
    }

    // Relasi ke Tabel Gudang
    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    // Relasi ke Tabel Currency (Mata Uang)
    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    // Accessor untuk Nomor BAST Individu
    public function getBastNumberAttribute()
    {
        $tanggal = \Carbon\Carbon::parse($this->created_at)->format('Y/m/d');
        $urutan = substr($this->asset_number, -4);

        return "BAST-AST/{$tanggal}/{$urutan}";
    }

    // Relasi ke Tabel Department
    public function department()
    {
        return $this->belongsTo(\App\Models\Department::class, 'department_id');
    }

    // Relasi ke Kategori Aset
    public function assetCategory()
    {
        return $this->belongsTo(\App\Models\AssetCategory::class, 'asset_category_id');
    }

    // Relasi ke Foto Aset
    public function photos()
    {
        return $this->hasMany(AssetPhoto::class);
    }

    // Relasi ke Komponen Upgrade Aset
    public function upgrades()
    {
        return $this->hasMany(\App\Models\FixedAssetUpgrade::class, 'fixed_asset_id')
                    ->orderBy('upgrade_date', 'asc')
                    ->orderBy('id', 'asc');
    }

    public function getTotalUpgradeCostAttribute(): float
    {
        return (float) $this->upgrades
            ->whereNotIn('status', ['REMOVED_GOOD', 'REMOVED_DEFECTIVE'])
            ->sum('cost');
    }

    // Hitung Penyusutan per Bulan (Prospektif sesuai kondisi terbaru)
    public function getMonthlyDepreciationAttribute()
    {
        return $this->calculateDepreciationSummary()['monthly_depreciation'];
    }

    // Hitung Penyusutan per Tahun
    public function getAnnualDepreciationAttribute()
    {
        return $this->monthly_depreciation * 12;
    }

    // Hitung Nilai Buku Saat Ini (Prospektif termasuk upgrade yang dikapitalisasi)
    public function getNetBookValueAttribute()
    {
        return $this->calculateDepreciationSummary()['net_book_value'];
    }

    /**
     * Menghitung ringkasan nilai aset & penyusutan prospektif (termasuk efek upgrade & pencopotan part)
     */
    public function calculateDepreciationSummary($asOfDate = null): array
    {
        $evalDate = $asOfDate ? \Carbon\Carbon::parse($asOfDate)->startOfDay() : now()->startOfDay();
        $acqDate  = $this->acquisition_date ? \Carbon\Carbon::parse($this->acquisition_date)->startOfDay() : $evalDate;

        $initialPrice = (float) ($this->purchase_price ?? 0);
        $lifeYears    = (int) (optional($this->assetCategory)->useful_life_years ?? 4);
        $totalMonths  = max(1, $lifeYears * 12);

        $currentBasis            = $initialPrice;
        $currentDateCursor       = $acqDate->copy();
        $remainingMonths         = $totalMonths;
        $accumulatedDep          = 0.0;
        $totalCapitalizedUpgrade = 0.0;
        $totalExpenseUpgrade     = 0.0;

        $allUpgrades = $this->upgrades->sortBy(function ($u) {
            return ($u->upgrade_date ? $u->upgrade_date->format('Y-m-d') : '1970-01-01') . '-' . str_pad($u->id, 8, '0', STR_PAD_LEFT);
        });

        // Susun event kapitalisasi & pencopotan secara kronologis
        $capEvents = [];
        foreach ($allUpgrades as $upg) {
            $uDate = $upg->upgrade_date ? $upg->upgrade_date->copy()->startOfDay() : $evalDate;
            if ($uDate->gt($evalDate)) {
                continue;
            }

            $isRemoved       = in_array($upg->status ?? 'ACTIVE', ['REMOVED_GOOD', 'REMOVED_DEFECTIVE']);
            $remDate         = ($isRemoved && $upg->removed_at) ? \Carbon\Carbon::parse($upg->removed_at)->startOfDay() : null;
            $isRemovedByEval = ($isRemoved && $remDate && $remDate->lte($evalDate));

            if ($upg->is_capitalized) {
                if (!$isRemovedByEval) {
                    $totalCapitalizedUpgrade += (float) $upg->cost;
                }

                // Event 1: Penambahan nilai saat pemasangan part
                $addKey = $uDate->format('Y-m-d') . '_ADD';
                if (!isset($capEvents[$addKey])) {
                    $capEvents[$addKey] = [
                        'date'                 => $uDate,
                        'order'                => 1,
                        'added_cost'           => 0.0,
                        'new_remaining_months' => null,
                        'revert_extension'     => false,
                    ];
                }
                $capEvents[$addKey]['added_cost'] += (float) $upg->cost;
                if (!empty($upg->new_remaining_months) && !$isRemovedByEval) {
                    $capEvents[$addKey]['new_remaining_months'] = (int) $upg->new_remaining_months;
                }

                // Event 2: Pengurangan nilai kembali saat part dicopot (jika sudah dicopot <= evalDate)
                if ($isRemovedByEval) {
                    $remKey = $remDate->format('Y-m-d') . '_REM';
                    if (!isset($capEvents[$remKey])) {
                        $capEvents[$remKey] = [
                            'date'                 => $remDate,
                            'order'                => 2,
                            'added_cost'           => 0.0,
                            'new_remaining_months' => null,
                            'revert_extension'     => false,
                        ];
                    }
                    $capEvents[$remKey]['added_cost'] -= (float) $upg->cost;
                    if (!empty($upg->new_remaining_months)) {
                        $capEvents[$remKey]['revert_extension'] = true;
                    }
                }
            } else {
                if (!$isRemovedByEval) {
                    $totalExpenseUpgrade += (float) $upg->cost;
                }
            }
        }

        // Urutkan kronologis berdasarkan tanggal & urutan event
        uasort($capEvents, function ($a, $b) {
            if ($a['date']->timestamp === $b['date']->timestamp) {
                return $a['order'] <=> $b['order'];
            }
            return $a['date']->timestamp <=> $b['date']->timestamp;
        });

        foreach ($capEvents as $event) {
            $eventDate = $event['date'];
            if ($eventDate->gt($currentDateCursor)) {
                $monthsElapsed = max(0, (int) $currentDateCursor->diffInMonths($eventDate));
                if ($remainingMonths > 0 && $currentBasis > 0) {
                    $depMonths  = min($monthsElapsed, $remainingMonths);
                    $monthlyDep = $currentBasis / $remainingMonths;
                    $depAmount  = $monthlyDep * $depMonths;

                    $accumulatedDep  += $depAmount;
                    $currentBasis     = max(0, $currentBasis - $depAmount);
                    $remainingMonths  = max(0, $remainingMonths - $depMonths);
                } else {
                    $remainingMonths = 0;
                }
                $currentDateCursor = $eventDate->copy();
            }

            // Sesuaikan basis nilai buku (positif saat pasang, negatif saat copot)
            $currentBasis = max(0, $currentBasis + $event['added_cost']);

            // Terapkan sisa umur baru jika diperpanjang
            if (!empty($event['new_remaining_months'])) {
                $remainingMonths = (int) $event['new_remaining_months'];
            }

            // Jika perpanjangan umur dibatalkan saat pencopotan dan basis nilai kembali 0, kembalikan sisa umur normal
            if (!empty($event['revert_extension']) && $currentBasis <= 0) {
                $totalElapsedFromAcq = max(0, (int) $acqDate->diffInMonths($eventDate));
                $remainingMonths = max(0, $totalMonths - $totalElapsedFromAcq);
            }
        }

        // Susutkan dari titik terakhir hingga tanggal evaluasi
        if ($evalDate->gt($currentDateCursor)) {
            $monthsElapsed = max(0, (int) $currentDateCursor->diffInMonths($evalDate));
            if ($remainingMonths > 0 && $currentBasis > 0) {
                $depMonths  = min($monthsElapsed, $remainingMonths);
                $monthlyDep = $currentBasis / $remainingMonths;
                $depAmount  = $monthlyDep * $depMonths;

                $accumulatedDep  += $depAmount;
                $currentBasis     = max(0, $currentBasis - $depAmount);
                $remainingMonths  = max(0, $remainingMonths - $depMonths);
            } else {
                $remainingMonths = 0;
            }
        }

        $monthlyDepNow       = ($remainingMonths > 0 && $currentBasis > 0) ? ($currentBasis / $remainingMonths) : 0.0;
        $totalHistoricalCost = $initialPrice + $totalCapitalizedUpgrade;

        return [
            'initial_price'             => round($initialPrice, 2),
            'capitalized_upgrade_total' => round($totalCapitalizedUpgrade, 2),
            'expense_upgrade_total'     => round($totalExpenseUpgrade, 2),
            'total_historical_cost'     => round($totalHistoricalCost, 2),
            'accumulated_depreciation'  => round($accumulatedDep, 2),
            'net_book_value'            => round($currentBasis, 2),
            'remaining_months'          => $remainingMonths,
            'monthly_depreciation'      => round($monthlyDepNow, 2),
            'initial_life_months'       => $totalMonths,
        ];
    }
}