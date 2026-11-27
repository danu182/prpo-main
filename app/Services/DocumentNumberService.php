<?php

namespace App\Services;

use App\Models\Company;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    /**
     * =========================================================================
     * KONFIGURASI TERPUSAT PENOMORAN DOKUMEN (UBAH DI SINI SAJA KE DEPANNYA)
     * =========================================================================
     */
    public const SEPARATOR       = '-';     // Gunakan '-' (aman di URL/PDF) atau '/'
    public const DATE_FORMAT     = 'Ymd';   // 'Ymd' (20261126) atau 'Ym' (202611)
    public const SEQUENCE_DIGITS = 4;       // Panjang nomor urut (0001)
    public const RESET_PER_MONTH = false;   // true = nomor urut lanjut sebulan (202611%), false = reset per hari (20261126%)

    /**
     * Generator Inti (Thread-Safe dengan lockForUpdate)
     *
     * @param string      $docPrefix  Prefix jenis dokumen (misal: 'PR', 'PO', 'GR', 'RTV', 'BILL-OPX', 'AST', 'UPG', 'UNUPG', 'BAST')
     * @param string      $table      Nama tabel database
     * @param string      $column     Nama kolom nomor dokumen di tabel tersebut
     * @param int|null    $companyId  ID perusahaan (opsional, untuk menyisipkan kode PT)
     * @param mixed       $date       Tanggal transaksi dokumen (default: hari ini)
     * @param bool        $useCompany Apakah menyertakan kode PT di tengah nomor
     */
    public static function generate(
        string $docPrefix,
        string $table,
        string $column,
        ?int $companyId = null,
        $date = null,
        bool $useCompany = true
    ): string {
        $sep       = self::SEPARATOR;
        $carbon    = $date ? Carbon::parse($date) : now();
        $datePart  = $carbon->format(self::DATE_FORMAT);
        $resetPart = self::RESET_PER_MONTH ? $carbon->format('Ym') : $datePart;

        // Susun bagian awal sebelum tanggal
        $basePrefix = trim($docPrefix, '-/');
        if ($useCompany) {
            $companyCode = self::resolveCompanyCode($companyId);
            $basePrefix .= $sep . $companyCode;
        }

        // Prefix lengkap untuk dokumen baru
        $fullPrefix = "{$basePrefix}{$sep}{$datePart}{$sep}";

        // Pola pencarian nomor urut terakhir
        $searchLike = "{$basePrefix}{$sep}{$resetPart}%";

        $lastRecord = DB::table($table)
            ->where($column, 'like', $searchLike)
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($lastRecord && !empty($lastRecord->{$column})) {
            $lastNumberStr = (string) $lastRecord->{$column};
            if (preg_match('/(\d+)$/', $lastNumberStr, $matches)) {
                $nextSeq = ((int) $matches[1]) + 1;
            }
        }

        return $fullPrefix . str_pad((string) $nextSeq, self::SEQUENCE_DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Ambil Kode Perusahaan (PT) secara aman
     */
    private static function resolveCompanyCode(?int $companyId): string
    {
        if (!$companyId) {
            return 'HO';
        }
        $company = Company::find($companyId);
        if ($company && !empty($company->code)) {
            return strtoupper(trim($company->code));
        }
        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', $company->name ?? 'GEN');
        return strtoupper(substr($cleanName, 0, 3)) ?: 'GEN';
    }

    // =========================================================================
    // SHORTCUT PER MODUL (SIAP PANGGIL DARI CONTROLLER MANAPUN)
    // =========================================================================

    public static function pr(?int $companyId, $date = null): string
    {
        return self::generate('PR', 'purchase_requests', 'pr_number', $companyId, $date, true);
    }

    public static function po(?int $companyId, $date = null): string
    {
        return self::generate('PO', 'purchase_orders', 'po_number', $companyId, $date, true);
    }

    public static function gr(?int $companyId, $date = null): string
    {
        return self::generate('GR', 'goods_receipts', 'gr_number', $companyId, $date, true);
    }

    public static function rtv(?int $companyId, $date = null): string
    {
        return self::generate('RTV', 'return_to_vendors', 'rtv_number', $companyId, $date, true);
    }

    public static function billOpex(?int $companyId, $date = null): string
    {
        return self::generate('BILL' . self::SEPARATOR . 'OPX', 'bill_requests', 'bill_number', $companyId, $date, true);
    }

    public static function fixedAsset($date = null, ?int $companyId = null): string
    {
        return self::generate('AST', 'fixed_assets', 'asset_number', $companyId, $date, false);
    }

    public static function assetUpgrade($date = null): string
    {
        return self::generate('UPG', 'fixed_asset_upgrades', 'upgrade_number', null, $date, false);
    }

    public static function assetUpgradeRemoval($date = null): string
    {
        return self::generate('UNUPG', 'fixed_asset_upgrades', 'removal_number', null, $date, false);
    }

    public static function assetTransaction(string $prefix, $date = null): string
    {
        return self::generate($prefix, 'fixed_asset_transactions', 'transaction_number', null, $date, false);
    }

    // =========================================================================
    // SHORTCUT MODUL MASTER ITEM & BATCH IMPORT
    // =========================================================================

    /**
     * Generate Kode SKU Master Barang (Contoh: SKU-CNS-00001)
     */
    public static function itemSku(?string $categoryCode): string
    {
        $sep  = self::SEPARATOR;
        $cat  = !empty($categoryCode) ? strtoupper(trim($categoryCode)) : 'UMM';
        $pref = "SKU{$sep}{$cat}{$sep}";

        $lastItem = DB::table('items')
            ->where('code', 'like', $pref . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($lastItem && !empty($lastItem->code)) {
            if (preg_match('/(\d+)$/', (string) $lastItem->code, $matches)) {
                $nextSeq = ((int) $matches[1]) + 1;
            }
        }

        return $pref . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Generate Nomor Batch Import Master Item (Contoh: IMP-20261127-001)
     */
    public static function itemImportBatch($date = null): string
    {
        $sep      = self::SEPARATOR;
        $datePart = $date ? Carbon::parse($date)->format('Ymd') : now()->format('Ymd');
        $pref     = "ITM{$sep}IMP{$sep}{$datePart}{$sep}";

        $lastBatch = DB::table('item_import_batches')
            ->where('batch_number', 'like', $pref . '%')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        $nextSeq = 1;
        if ($lastBatch && !empty($lastBatch->batch_number)) {
            if (preg_match('/(\d+)$/', (string) $lastBatch->batch_number, $matches)) {
                $nextSeq = ((int) $matches[1]) + 1;
            }
        }

        return $pref . str_pad((string) $nextSeq, 3, '0', STR_PAD_LEFT);
    }
}
