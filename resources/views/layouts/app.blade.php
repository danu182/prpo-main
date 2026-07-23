<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Procurement App') }}</title>

    {{-- CSS Frameworks --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        :root {
            --sidebar-w: 260px;
            --sidebar-mini-w: 70px;
            --topbar-h: 64px;
            --bg-body: #f8fafc;
            --primary-accent: #2563eb;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        html, body {
            background-color: var(--bg-body);
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            margin: 0;
            padding: 0;
            overflow-x: hidden !important;
            width: 100%;
        }

        /* --- WRAPPER UTAMA --- */
        #wrapper {
            display: flex;
            width: 100vw;
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }

        /* --- STYLES SIDEBAR --- */
        #sidebar {
            width: var(--sidebar-w);
            min-width: var(--sidebar-w);
            background: #ffffff;
            border-right: 1px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 1040;
            transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                        min-width 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                        transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden !important; /* MENCEGAH TEKS BOCOR */
        }

        /* Header Sidebar */
        .sidebar-header {
            height: var(--topbar-h);
            padding: 0 1.25rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            min-width: 38px;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
            flex-shrink: 0;
        }

        .brand-text {
            margin-left: 0.75rem;
            white-space: nowrap;
            overflow: hidden;
            transition: opacity 0.2s;
        }

        /* Body Menu Navigasi */
        .sidebar-nav {
            padding: 0.75rem 0.65rem;
            overflow-y: auto;
            overflow-x: hidden;
            flex-grow: 1;
        }

        .sidebar-heading {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 700;
            color: #94a3b8;
            padding: 1.2rem 0.75rem 0.4rem;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 0.65rem 0.75rem;
            color: #475569;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 2px;
            transition: all 0.2s ease;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-link i.menu-icon {
            font-size: 1.2rem;
            min-width: 24px;
            width: 24px;
            text-align: center;
            flex-shrink: 0;
            margin-right: 0.75rem;
        }

        .sidebar-link:hover {
            background-color: #f1f5f9;
            color: #0f172a;
        }

        .sidebar-link.active {
            background-color: #eff6ff;
            color: var(--primary-accent);
            font-weight: 700;
        }

        .sidebar-dropdown .collapse {
            padding-left: 0.25rem;
        }

        .sidebar-sublink {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem 0.5rem 2.25rem;
            color: #64748b;
            font-size: 0.825rem;
            font-weight: 500;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 2px;
            transition: all 0.2s;
            white-space: nowrap;
            overflow: hidden;
        }

        .sidebar-sublink:hover {
            color: #0f172a;
            background-color: #f8fafc;
        }

        .sidebar-sublink.active {
            color: var(--primary-accent);
            font-weight: 700;
            background-color: #f0f9ff;
        }

        .dropdown-chevron {
            margin-left: auto;
            font-size: 0.75rem;
            transition: transform 0.2s;
            flex-shrink: 0;
        }

        .sidebar-link[aria-expanded="true"] .dropdown-chevron {
            transform: rotate(180deg);
        }

        /* --- KONTEN UTAMA --- */
        #content-wrapper {
            flex-grow: 1;
            margin-left: var(--sidebar-w);
            width: calc(100% - var(--sidebar-w));
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.25s cubic-bezier(0.4, 0, 0.2, 1),
                        width 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .topbar {
            height: var(--topbar-h);
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 0 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        .content-body {
            padding: 1.5rem;
            width: 100%;
            flex-grow: 1;
        }

        /* ====================================================
           🔥 1. DESKTOP MINI MODE (70px COLLAPSED) 🔥
           ==================================================== */
        @media (min-width: 992px) {
            #wrapper.sidebar-mini #sidebar {
                width: var(--sidebar-mini-w) !important;
                min-width: var(--sidebar-mini-w) !important;
            }

            #wrapper.sidebar-mini #content-wrapper {
                margin-left: var(--sidebar-mini-w) !important;
                width: calc(100% - var(--sidebar-mini-w)) !important;
            }

            /* Hapus Total Teks & Submenu pada Mode Mini */
            #wrapper.sidebar-mini .brand-text,
            #wrapper.sidebar-mini .menu-text,
            #wrapper.sidebar-mini .sidebar-heading,
            #wrapper.sidebar-mini .dropdown-chevron,
            #wrapper.sidebar-mini .sidebar-dropdown .collapse {
                display: none !important;
                visibility: hidden !important;
                opacity: 0 !important;
            }

            #wrapper.sidebar-mini .sidebar-header {
                padding: 0;
                justify-content: center;
            }

            #wrapper.sidebar-mini .sidebar-nav {
                padding: 0.75rem 0.4rem;
            }

            #wrapper.sidebar-mini .sidebar-link {
                justify-content: center;
                padding: 0.75rem 0;
                margin-bottom: 4px;
            }

            #wrapper.sidebar-mini .sidebar-link i.menu-icon {
                margin-right: 0 !important;
                font-size: 1.3rem;
            }
        }

        /* ====================================================
           🔥 2. MOBILE & TABLET RESPONSIVE (OVERLAY DRAWER) 🔥
           ==================================================== */
        @media (max-width: 991.98px) {
            #sidebar {
                transform: translateX(-100%);
                box-shadow: none;
            }

            #content-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
            }

            /* Mobile Active Drawer */
            #wrapper.mobile-active #sidebar {
                transform: translateX(0);
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
            }

            /* Backdrop Gelap saat Mobile Sidebar Terbuka */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(15, 23, 42, 0.4);
                backdrop-filter: blur(2px);
                z-index: 1035;
            }

            #wrapper.mobile-active .sidebar-overlay {
                display: block;
            }
        }
    </style>

    @yield('styles')
    @stack('css')
</head>
<body>

    <div id="wrapper">

        {{-- BACKDROP MOBILE --}}
        <div class="sidebar-overlay" id="sidebarBackdrop"></div>

        {{-- SIDEBAR NAVIGASI --}}
        @include('layouts.sidebar')

        {{-- AREA KONTEN UTAMA --}}
        <div id="content-wrapper">

            {{-- HEADER ATAS (TOPBAR) --}}
            @include('layouts.header')

            {{-- ISI HALAMAN --}}
            <main class="content-body">
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

            {{-- footer star --}}
            @include('layouts.footer')
            {{-- footer end --}}


        </div>

    </div>

    {{-- JS Frameworks --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    {{-- SCRIPT RESPONSIF & TOGGLE SIDEBAR PINTAR --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const wrapper = document.getElementById('wrapper');

            // Inisialisasi Tooltip Bootstrap
            const tooltipList = [...document.querySelectorAll('[data-bs-toggle="tooltip"]')].map(
                el => new bootstrap.Tooltip(el, { trigger: 'hover' })
            );

            function updateTooltips() {
                const isMini = wrapper.classList.contains('sidebar-mini');
                tooltipList.forEach(t => {
                    if (isMini && window.innerWidth >= 992) {
                        t.enable();
                    } else {
                        t.disable();
                    }
                });
            }

            // Cek Memori Lokal
            if (localStorage.getItem('sidebar-mini') === 'true' && window.innerWidth >= 992) {
                wrapper.classList.add('sidebar-mini');
            }
            updateTooltips();

            // Toggle Klik
            sidebarToggle?.addEventListener('click', function(e) {
                e.preventDefault();
                if (window.innerWidth < 992) {
                    wrapper.classList.toggle('mobile-active');
                } else {
                    wrapper.classList.toggle('sidebar-mini');
                    localStorage.setItem('sidebar-mini', wrapper.classList.contains('sidebar-mini'));
                    updateTooltips();
                }
            });

            // Tutup Mobile Sidebar jika Backdrop Diklik
            sidebarBackdrop?.addEventListener('click', function() {
                wrapper.classList.remove('mobile-active');
            });
        });
    </script>

    @stack('scripts')
</body>
</html>
