<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Procurement App') }}</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            background-color: #f8f9fc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        /* --- STYLES KHUSUS NAVBAR --- */
        .navbar-floating {
            background: #ffffff;
            border-radius: 50px;
            padding: 0.75rem 1.5rem;
            margin-top: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 24px rgba(149, 157, 165, 0.08);
            border: 1px solid rgba(0,0,0,0.04);
        }

        .brand-logo {
            font-weight: 700;
            font-size: 1.2rem;
            color: #1a1a1a;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .brand-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6f42c1 0%, #4a2394 100%);
            color: white;
            border-radius: 10px; /* Sedikit mengotak agar lebih modern */
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-size: 1.1rem;
            box-shadow: 0 4px 10px rgba(111, 66, 193, 0.3);
        }

        .nav-pills-custom .nav-link {
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.6rem 1.2rem;
            border-radius: 30px;
            margin: 0 4px;
            transition: all 0.2s ease;
            background-color: transparent;
        }

        .nav-pills-custom .nav-link:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .nav-pills-custom .nav-link.active {
            background-color: #0f172a;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.15);
        }

        /* --- DROPDOWN MEWAH --- */
        .nav-pills-custom .dropdown-menu {
            margin-top: 12px;
            border: none;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 0.8rem;
            min-width: 220px;
        }

        .nav-pills-custom .dropdown-item {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            font-weight: 500;
            color: #475569;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-bottom: 2px;
        }

        .nav-pills-custom .dropdown-item:hover {
            background-color: #f8fafc;
            color: #0f172a;
            transform: translateX(4px); /* Efek geser kecil saat di-hover */
        }

        .nav-pills-custom .dropdown-item.active {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: 700;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s;
            background-color: #f8fafc;
        }

        .action-btn:hover {
            background-color: #e2e8f0;
            color: #0f172a;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            border: 2px solid #fff;
            box-shadow: 0 2px 8px rgba(13, 110, 253, 0.2);
        }

        @media (max-width: 991px) {
            .navbar-floating { border-radius: 16px; margin-top: 10px; padding: 1rem; }
            .nav-pills-custom { margin-top: 1rem; flex-direction: column; width: 100%; }
            .nav-pills-custom .nav-link { margin-bottom: 8px; text-align: center; }
            .nav-pills-custom .dropdown-menu { text-align: center; box-shadow: none; border: 1px solid #eee; }
            .right-actions { margin-top: 1rem; justify-content: center; width: 100%; }
        }
    </style>

    @yield('styles')
    @stack('css')
</head>
<body>

    <div class="container">
        {{-- navbar start --}}
        @include('layouts.navbar')
        {{-- navbar end --}}



        <main class="py-2">

            {{-- ALERT GLOBAL --}}
            @if(session('success'))
                <div class="mb-4 border-0 shadow-sm alert alert-success alert-dismissible fade show rounded-4 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                    <div>{{ session('success') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-4 d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                    <div>{{ session('error') }}</div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @yield('content')

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    @stack('scripts')

</body>
</html>
