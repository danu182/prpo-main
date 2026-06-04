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
            font-size: 1.1rem;
            color: #1a1a1a;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .brand-icon {
            width: 32px;
            height: 32px;
            background-color: #6f42c1;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 14px;
        }

        .nav-pills-custom .nav-link {
            color: #6c757d;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.5rem 1.2rem;
            border-radius: 30px;
            margin: 0 4px;
            transition: all 0.2s ease;
            background-color: transparent;
        }

        .nav-pills-custom .nav-link:hover {
            background-color: #f1f3f5;
            color: #212529;
        }

        .nav-pills-custom .nav-link.active {
            background-color: #0f172a;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.2);
        }

        /* Styling tambahan untuk Dropdown agar senada */
        .nav-pills-custom .dropdown-menu {
            margin-top: 10px;
            border: 1px solid rgba(0,0,0,0.05);
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            padding: 0.5rem;
        }
        .nav-pills-custom .dropdown-item {
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            color: #495057;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .nav-pills-custom .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0f172a;
        }
        .nav-pills-custom .dropdown-item.active {
            background-color: #e2e8f0;
            color: #0f172a;
            font-weight: 600;
        }

        .action-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            color: #495057;
            text-decoration: none;
            transition: background 0.2s;
        }

        .action-btn:hover {
            background-color: #f1f3f5;
            color: #000;
        }

        .user-avatar-small {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: #e9ecef;
            color: #495057;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85rem;
            border: 2px solid #fff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        @media (max-width: 991px) {
            .navbar-floating {
                border-radius: 15px;
                margin-top: 10px;
                padding: 1rem;
            }
            .nav-pills-custom {
                margin-top: 1rem;
                flex-direction: column;
                width: 100%;
            }
            .nav-pills-custom .nav-link {
                margin-bottom: 5px;
                text-align: center;
            }
            .right-actions {
                margin-top: 1rem;
                justify-content: center;
                width: 100%;
            }
        }
    </style>

    @stack('css')
</head>
<body>

    <div class="container">
        <nav class="shadow-sm navbar navbar-expand-lg navbar-floating sticky-top">
            <div class="p-0 container-fluid">

                <a class="brand-logo me-4" href="{{ route('dashboard') }}">
                    <div class="brand-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <span class="fw-bold text-primary">Procure</span><span style="font-weight: 400;" class="text-dark">App</span>
                </a>

                <button class="p-2 border-0 navbar-toggler bg-light rounded-circle" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="navbarContent">

                    <ul class="mx-auto nav nav-pills nav-pills-custom align-items-center">

                        {{-- 1. OVERVIEW --}}
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                                Overview
                            </a>
                        </li>

                        {{-- 2. PURCHASING DROPDOWN (PR, PO, Bills) --}}
                        @canany(['view_pr', 'view_po', 'view_bills'])
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('pr.*', 'po.*', 'bills.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Purchasing
                            </a>
                            <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4">
                                @can('view_pr')
                                <li><a class="dropdown-item {{ request()->routeIs('pr.*') ? 'active' : '' }}" href="{{ route('pr.index') }}"><i class="bi bi-file-earmark-text me-2 text-primary"></i> Requests (PR)</a></li>
                                @endcan

                                @can('view_po')
                                <li><a class="dropdown-item {{ request()->routeIs('po.*') ? 'active' : '' }}" href="{{ route('po.index') }}"><i class="bi bi-cart2 me-2 text-success"></i> Orders (PO)</a></li>
                                @endcan

                                @can('view_bills')
                                <li><a class="dropdown-item {{ request()->routeIs('bills.*') ? 'active' : '' }}" href="{{ route('bills.index') }}"><i class="bi bi-receipt-cutoff me-2 text-warning"></i> Bills (PRQ)</a></li>
                                @endcan
                            </ul>
                        </li>
                        @endcanany

                        {{-- 3. RECEIVING --}}
                        @can('view_gr')
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('gr.*') ? 'active' : '' }}" href="{{ route('gr.index') }}">
                                Receiving
                            </a>
                        </li>
                        @endcan

                        {{-- 4. WAREHOUSE (ASSETS, GI, ITEMS) --}}
                        {{-- PERBAIKAN: Gunakan canany untuk semua kemungkinan isi gudang --}}
                        @canany(['view_inventory', 'manage_gi', 'view_assets', 'manage_items'])
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('assets.*', 'goods-issues.*', 'stock-adjustments.*', 'fixed-assets.*', 'items.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Warehouse
                            </a>
                            <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4">

                                @can('view_inventory')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('assets.index', 'assets.show') ? 'active' : '' }}" href="{{ route('assets.index') }}">
                                        <i class="bi bi-boxes me-2 text-primary"></i> Master Stok Barang
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @endcan

                                @can('manage_gi')
                                <li>
                                    <h6 class="dropdown-header text-uppercase small fw-bold">Operasional Gudang</h6>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('goods-issues.create') ? 'active' : '' }}" href="{{ route('goods-issues.create') }}">
                                        <i class="bi bi-box-arrow-up text-danger me-2"></i> Pengeluaran Barang (GI)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('stock-adjustments.create') ? 'active' : '' }}" href="{{ route('stock-adjustments.create') }}">
                                        <i class="bi bi-sliders text-warning me-2"></i> Penyesuaian Stok (Opname)
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @endcan

                                @can('view_inventory')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('inventory.index', 'inventory.show') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                                        <i class="bi bi-boxes me-2 text-primary"></i> Master Stok Barang
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @endcan

                                @can('view_assets')
                                <li>
                                    <h6 class="dropdown-header text-uppercase small fw-bold">Manajemen Aset</h6>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('fixed-assets.*') ? 'active' : '' }}" href="{{ route('fixed-assets.index') }}">
                                        <i class="bi bi-pc-display text-info me-2"></i> Register Aset (Fixed Asset)
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                @endcan

                                @can('manage_items')
                                <li>
                                    <h6 class="dropdown-header text-uppercase small fw-bold">Katalog Data</h6>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}">
                                        <i class="bi bi-box text-secondary me-2"></i> Master Barang & Jasa
                                    </a>
                                </li>
                                @endcan

                            </ul>
                        </li>
                        @endcanany

                        {{-- 5. FINANCE DROPDOWN --}}
                        @canany(['view_invoices', 'view_payments'])
                        <li class="nav-item dropdown">
                            @php
                                $debtCount = class_exists('\App\Models\BillRequest') ? \App\Models\BillRequest::whereIn('status', ['APPROVED', 'PARTIAL'])->count() : 0;
                            @endphp
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Finance
                                @if($debtCount > 0)
                                    <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.6rem; padding: 0.25em 0.5em;">{{ $debtCount }}</span>
                                @endif
                            </a>
                            <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4">
                                @can('view_invoices')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('vendor-invoices.*') ? 'active' : '' }}" href="{{ route('vendor-invoices.index') }}">
                                        <i class="bi bi-receipt me-2 text-primary"></i> Tagihan Vendor (A/P)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('bills.index') ? 'active' : '' }}" href="{{ route('bills.index') }}">
                                        <i class="bi bi-receipt-cutoff me-2 text-info"></i> Tagihan Bill
                                    </a>
                                </li>
                                @endcan

                                @can('view_payments')
                                <li>
                                    <a class="dropdown-item {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}">
                                        <i class="bi bi-wallet2 me-2 text-success"></i> Pembayaran (Hutang)
                                        @if($debtCount > 0)
                                            <span class="badge bg-danger rounded-pill float-end">{{ $debtCount }}</span>
                                        @endif
                                    </a>
                                </li>
                                @endcan
                            </ul>
                        </li>
                        @endcanany

                        {{-- 6. SETTINGS DROPDOWN --}}
                        @can('manage_roles')
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*', 'roles.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                Settings
                            </a>
                            <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4 dropdown-menu-end">
                                <li><a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people me-2 text-primary"></i> User Management</a></li>
                                <li><a class="dropdown-item {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock me-2 text-danger"></i> Role Management</a></li>
                            </ul>
                        </li>
                        @endcan

                    </ul>

                    {{-- PROFIL KANAN (TIDAK ADA PERUBAHAN, SUDAH SEMPURNA) --}}
                    <div class="gap-2 d-flex align-items-center right-actions">
                        <a href="#" class="action-btn text-secondary" title="Search">
                            <i class="bi bi-search"></i>
                        </a>
                        <a href="#" class="action-btn text-secondary" title="Settings">
                            <i class="bi bi-gear"></i>
                        </a>
                        <a href="#" class="action-btn position-relative text-secondary" title="Notifications">
                            <i class="bi bi-bell"></i>
                            <span class="p-1 border position-absolute top-25 start-75 translate-middle bg-danger border-light rounded-circle" style="width: 8px; height: 8px;"></span>
                        </a>

                        @auth
                        <div class="dropdown ms-2">
                            <a href="#" class="text-decoration-none d-flex align-items-center" data-bs-toggle="dropdown">
                                @if(Auth::user()->avatar)
                                    <img src="{{ asset('storage/' . Auth::user()->avatar) }}"
                                        class="border border-white shadow-sm rounded-circle object-fit-cover"
                                        width="40" height="40" alt="Avatar">
                                @else
                                    <div class="text-white shadow-sm user-avatar-small bg-primary d-flex align-items-center justify-content-center rounded-circle" style="width: 40px; height: 40px; font-size: 1rem; font-weight:bold;">
                                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                    </div>
                                @endif
                            </a>

                            <ul class="p-2 mt-3 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4">
                                <li>
                                    <h6 class="dropdown-header text-uppercase small font-weight-bold">
                                        Signed in as <br>
                                        <span class="text-dark fw-bold" style="font-size: 0.9rem;">{{ Auth::user()->name }}</span>
                                    </h6>
                                </li>
                                <li>
                                    <h6 class="dropdown-header text-uppercase small font-weight-bold">
                                        Role / Divisi: <br>
                                        <span class="text-primary fw-bold">{{ auth()->user()->getRoleNames()->implode(', ') ?: 'No Role' }}</span>
                                    </h6>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="gap-2 py-2 dropdown-item rounded-3 d-flex align-items-center" href="{{ route('profile.edit') }}">
                                        <i class="bi bi-person-circle text-secondary"></i> Profile
                                    </a>
                                </li>
                                <li>
                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <button type="submit" class="gap-2 py-2 dropdown-item rounded-3 text-danger d-flex align-items-center">
                                            <i class="bi bi-box-arrow-right"></i> Sign out
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                        @else
                        <a href="{{ route('login') }}" class="px-4 btn btn-dark rounded-pill btn-sm ms-2">Login</a>
                        @endauth
                    </div>

                </div>
            </div>
        </nav>

        <main class="py-2">

            {{-- ALERT GLOBAL --}}
            @if(session('success'))
                <div class="mb-4 border-0 shadow-sm alert alert-success alert-dismissible fade show rounded-3">
                    <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 border-0 shadow-sm alert alert-danger alert-dismissible fade show rounded-3">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('error') }}
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
