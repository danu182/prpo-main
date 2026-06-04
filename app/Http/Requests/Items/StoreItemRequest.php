<?php

namespace App\Http\Requests\Items;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan melakukan request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi (Pindahan dari Controller).
     */
    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'uom_id'         => 'required|exists:uoms,id',
            // 🔥 PERBAIKAN 1: Pastikan kode tipe barang benar-benar ada di database 🔥
            'item_type_code' => 'required|string|exists:item_types,code',
            'is_asset'       => 'required|in:0,1',
            'is_trackable'   => 'required|in:0,1',
            'min_stock'      => 'nullable|numeric|min:0',
            'max_stock'      => 'nullable|numeric|gt:min_stock',
            'specification'  => 'nullable|string',

            // Validasi Array Kemasan Alternatif (Multi-UOM)
            'uoms'                  => 'nullable|array',
            'uoms.*.uom_name'       => 'nullable|string',
            // 🔥 PERBAIKAN 2: Jika 'uom_name' diisi, maka 'conversion_qty' WAJIB diisi 🔥
            'uoms.*.conversion_qty' => 'required_with:uoms.*.uom_name|nullable|numeric|min:1',
            'uoms.*.barcode'        => 'nullable|string|max:255',
        ];
    }

    /**
     * Custom pesan error (Opsional tapi sangat disarankan untuk UX yang baik)
     */
    public function messages(): array
    {
        return [
            'name.required'             => 'Nama barang wajib diisi!',
            'category_id.required'      => 'Kategori barang belum dipilih!',
            'uom_id.required'           => 'Satuan dasar terkecil wajib dipilih!',
            'item_type_code.exists'     => 'Tipe barang yang dipilih tidak valid atau tidak terdaftar!',
            'max_stock.gt'              => 'Angka Batas Overstock (Max) harus lebih besar dari Batas Bahaya (Min)!',
            'uoms.*.conversion_qty.required_with' => 'Isi (Konversi) wajib diisi jika Nama Kemasan dipilih!',
            'uoms.*.conversion_qty.min' => 'Nilai konversi kemasan tidak boleh kurang dari 1.',
        ];
    }
}
