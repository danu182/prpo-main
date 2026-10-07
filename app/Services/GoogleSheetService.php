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
    public function deleteRowsByBillNumber(string $range, string $billNumber)
    {
        // 1. Ambil semua data baris yang ada di tab
        $response = $this->service->spreadsheets_values->get($this->spreadsheetId, $range);
        $rows = $response->getValues();

        if (empty($rows)) {
            return;
        }

        // 2. 🔥 PERBAIKAN: Cari Sheet ID secara otomatis berdasarkan nama tab 🔥
        $spreadsheet = $this->service->spreadsheets->get($this->spreadsheetId);
        $sheetId = null;
        foreach ($spreadsheet->getSheets() as $sheet) {
            if ($sheet->getProperties()->getTitle() == $range) {
                $sheetId = $sheet->getProperties()->getSheetId();
                break;
            }
        }

        if ($sheetId === null) {
            throw new \Exception("Sheet dengan nama '$range' tidak ditemukan.");
        }

        $requests = [];

        // 3. Loop dari bawah ke atas agar indeks baris tidak bergeser saat ada yang dihapus
        for ($i = count($rows) - 1; $i >= 0; $i--) {
            $row = $rows[$i];

            $foundMatch = false;

            // Cek Kolom K (Indeks 10) - Biasanya di sinilah No BPR/PO berada
            if (isset($row[10]) && trim($row[10]) === $billNumber) {
                $foundMatch = true;
            }
            // Cek Kolom L (Indeks 11) - Berjaga-jaga jika letaknya bergeser
            elseif (isset($row[11]) && trim($row[11]) === $billNumber) {
                $foundMatch = true;
            }
            // Cek Kolom J (Indeks 9) - Berjaga-jaga
            elseif (isset($row[9]) && trim($row[9]) === $billNumber) {
                $foundMatch = true;
            }

            // Jika ada yang cocok, masukkan ke dalam antrean perintah penghapusan
            if ($foundMatch) {
                $requests[] = new \Google_Service_Sheets_Request([
                    'deleteDimension' => [
                        'range' => [
                            'sheetId' => $sheetId,
                            'dimension' => 'ROWS',
                            'startIndex' => $i,
                            'endIndex' => $i + 1
                        ]
                    ]
                ]);
            }
        }

        // 4. Eksekusi penghapusan massal sekaligus ke Google Sheet
        if (!empty($requests)) {
            $batchUpdateRequest = new \Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            $this->service->spreadsheets->batchUpdate($this->spreadsheetId, $batchUpdateRequest);
        }
    }


}
