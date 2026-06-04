<?php

namespace App\Http\Requests\Items;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    /**
     * Tentukan apakah user diizinkan melakukan request ini.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Aturan validasi.
     */
    public function rules(): array
    {
        return [
            // Code & Slug tidak divalidasi karena tidak kita izinkan diubah di form
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'uom_id'         => 'required|exists:uoms,id',

            // 🔥 PERBAIKAN 1: Ganti is_stockable menjadi item_type_code 🔥
            'item_type_code' => 'required|string|exists:item_types,code',

            'is_asset'       => 'required|in:0,1',
            'is_trackable'   => 'required|in:0,1',

            // 🔥 PERBAIKAN 2: Min & Max dibuat 100% Fleksibel 🔥
            // Boleh diisi salah satu, boleh dikosongkan dua-duanya, yang penting tidak minus.
            'min_stock'      => 'nullable|numeric|min:0',
            'max_stock'      => 'nullable|numeric|min:0',

            'specification'  => 'nullable|string',

            // 🔥 PERBAIKAN 3: Validasi Array Kemasan Alternatif (Sama dengan Store) 🔥
            'uoms'                  => 'nullable|array',
            'uoms.*.uom_name'       => 'nullable|string',
            'uoms.*.conversion_qty' => 'required_with:uoms.*.uom_name|nullable|numeric|min:1',
            'uoms.*.barcode'        => 'nullable|string|max:255',
        ];
    }

    /**
     * Custom pesan error
     */
    public function messages(): array
    {
        return [
            'name.required'             => 'Nama barang wajib diisi!',
            'category_id.required'      => 'Kategori barang belum dipilih!',
            'uom_id.required'           => 'Satuan dasar terkecil wajib dipilih!',
            'item_type_code.exists'     => 'Tipe barang yang dipilih tidak valid!',
            'item_type_code.required'   => 'Tipe barang wajib dipilih!',

            // Pesan error khusus untuk array UOM
            'uoms.*.conversion_qty.required_with' => 'Isi (Konversi) wajib diisi jika Nama Kemasan dipilih!',
            'uoms.*.conversion_qty.min' => 'Nilai konversi kemasan tidak boleh kurang dari 1.',
        ];
    }
}
