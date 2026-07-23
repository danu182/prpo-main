<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class UsersImport implements ToArray, WithHeadingRow
{
    /**
     * Mengubah Excel menjadi Array asosiatif berdasarkan Header Baris ke-1
     */
    public function array(array $array)
    {
        return $array;
    }
}
