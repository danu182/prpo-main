<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use GuzzleHttp\Client;

class GoogleSheetService
{
    protected $client;
    protected $service;
    protected $spreadsheetId;

    public function __construct()
    {
        $this->client = new Google_Client();
        $this->client->setApplicationName('ERP ProcureApp');
        $this->client->setScopes([Google_Service_Sheets::SPREADSHEETS]);
        $this->client->setAuthConfig(storage_path('app/google-service-account.json'));
        $this->client->setAccessType('offline');

        $guzzleClient = new Client(['verify' => false]);
        $this->client->setHttpClient($guzzleClient);

        $this->service = new Google_Service_Sheets($this->client);
        $this->spreadsheetId = env('GOOGLE_SHEET_OPEX_ID');
    }

    public function appendRow($tabName, $rowData)
    {
        try {
            $body = new \Google_Service_Sheets_ValueRange([
                'values' => [$rowData]
            ]);

            $params = [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS'
            ];

            // 🔥 KUNCI PERBAIKANNYA DI SINI 🔥
            // Tambahkan '!A:A' di belakang nama tab.
            // Ini memaksa Google Sheet untuk selalu memulai sisipan dari Kolom A.
            $range = $tabName . '!A:A';

            $this->service->spreadsheets_values->append(
                $this->spreadsheetId,
                $range,
                $body,
                $params
            );

        } catch (\Exception $e) {
            \Log::error("Gagal Append ke Google Sheet: " . $e->getMessage());
        }
    }

    // 🔥 FITUR BARU: PENGHAPUS BARIS OTOMATIS 🔥
    public function deleteRowsByBillNumber($tabName, $billNumber)
    {
        try {
            $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $tabName);
            $values = $response->getValues();

            if (empty($values)) return;

            $rowsToDelete = [];

            // PENCARIAN SUPER DINAMIS: Cari nomor PO di seluruh kolom!
            foreach ($values as $index => $row) {
                foreach ($row as $colValue) {
                    if (trim((string)$colValue) === (string)$billNumber) {
                        $rowsToDelete[] = $index;
                        break; // Jika ketemu, hentikan pencarian di baris ini, lanjut baris bawahnya
                    }
                }
            }

            // Jika nomor PO tidak ditemukan di Sheet, tidak usah proses hapus
            if (empty($rowsToDelete)) return;

            // WAJIB DIURUTKAN DARI BAWAH KE ATAS (DESCENDING) AGAR BARIS TIDAK BERGESER SAAT DIHAPUS
            rsort($rowsToDelete);

            // Cari ID Sheet (Berupa Angka)
            $sheetId = 0;
            $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
            foreach ($spreadsheet->getSheets() as $sheet) {
                if ($sheet->getProperties()->getTitle() == $tabName) {
                    $sheetId = $sheet->getProperties()->getSheetId();
                    break;
                }
            }

            $requests = [];
            foreach ($rowsToDelete as $rowIndex) {
                $requests[] = new \Google_Service_Sheets_Request([
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $rowIndex,
                            'endIndex' => $rowIndex + 1
                        ]
                    ]
                ]);
            }

            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            // Eksekusi Hapus!
            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);

        } catch (\Exception $e) {
            \Log::error("Gagal Hapus Baris Google Sheet untuk {$billNumber}: " . $e->getMessage());
        }
    }


}
