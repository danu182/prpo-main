<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>419 - Sesi Halaman Berakhir</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8fafc;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
        }
    </style>
</head>
<body>
    <div class="container px-3" style="max-width: 520px;">
        <div class="p-4 p-md-5 text-center bg-white border-0 shadow-lg card rounded-4">
            <div class="mx-auto mb-3 d-flex align-items-center justify-content-center rounded-circle bg-warning-subtle text-warning" style="width: 80px; height: 80px;">
                <i class="bi bi-clock-history fs-1"></i>
            </div>

            <span class="px-3 py-1 mb-2 badge bg-warning text-dark rounded-pill fw-bold">KODE 419 • PAGE EXPIRED</span>
            <h4 class="mb-2 fw-bold text-dark">Sesi Formulir Telah Berakhir</h4>
            <p class="mb-4 text-muted small">
                Halaman ini didiamkan terlalu lama sehingga token keamanan kedaluwarsa. Silakan muat ulang halaman sebelumnya dan coba kirim ulang data Anda.
            </p>

            <div class="gap-2 d-grid d-sm-flex justify-content-sm-center">
                <a href="{{ url()->previous() }}" class="px-4 btn btn-primary rounded-pill fw-bold">
                    <i class="bi bi-arrow-clockwise me-1"></i> Muat Ulang Halaman
                </a>
                <a href="{{ route('login') }}" class="px-4 btn btn-outline-secondary rounded-pill fw-bold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Halaman Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
