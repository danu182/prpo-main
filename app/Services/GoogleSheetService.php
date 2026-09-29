<?php

namespace App\Services;

use Google_Client;
use Google_Service_Sheets;
use Google_Service_Sheets_ValueRange;
use GuzzleHttp\Client; // Tambahkan ini

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

        // 🔥 INI ADALAH OBAT UNTUK ERROR "cURL error 60 SSL" DI LOCALHOST 🔥
        // Kita mematikan verifikasi SSL sementara agar PHP lokal bisa ngobrol dengan Google
        $guzzleClient = new Client([
            'verify' => false,
        ]);
        $this->client->setHttpClient($guzzleClient);
        // ====================================================================

        $this->service = new Google_Service_Sheets($this->client);
        $this->spreadsheetId = env('GOOGLE_SHEET_OPEX_ID');
    }

    public function appendRow($tabName, $valuesArray)
    {
        $range = $tabName;
        $body = new Google_Service_Sheets_ValueRange([
            'values' => [$valuesArray]
        ]);
        $params = [
            'valueInputOption' => 'USER_ENTERED'
        ];

        try {
            return $this->service->spreadsheets_values->append($this->spreadsheetId, $range, $body, $params);
        } catch (\Exception $e) {
            \Log::error("Gagal Append Google Sheet: " . $e->getMessage());
            return false;
        }
    }
}
