<nav class="bg-white shadow-sm navbar navbar-expand-lg navbar-floating sticky-top" style="z-index: 1050;">
    <div class="p-0 px-3 container-fluid">

        {{-- LOGO BRAND --}}
        <a class="brand-logo me-4 d-flex align-items-center text-decoration-none" href="{{ route('dashboard') }}">
            <div class="text-white shadow-sm brand-icon bg-primary d-flex align-items-center justify-content-center rounded-3 me-2" style="width: 38px; height: 38px; font-size: 1.2rem;">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <span class="fw-bolder text-primary fs-5" style="letter-spacing: -0.5px;">Procure</span><span class="text-dark fw-medium fs-5">App</span>
            </div>
        </a>

        {{-- TOGGLER UNTUK MOBILE --}}
        <button class="p-2 border-0 shadow-sm navbar-toggler bg-light rounded-circle" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">

            {{-- MENU TENGAH --}}
            <ul class="mx-auto nav nav-pills nav-pills-custom align-items-center fw-semibold">

                {{-- 1. OVERVIEW --}}
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('dashboard') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="{{ route('dashboard') }}">
                        Overview
                    </a>
                </li>

                {{-- 2. MASTER DATA --}}
                @canany(['view_companies', 'view_vendors', 'view_inventory', 'manage_items'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'uoms.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Master Data
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu rounded-4" style="min-width: 260px;">
                        @can('manage_items')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('items.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('items.index') }}">
                                <i class="bi bi-box-seam fs-6 me-3 text-primary"></i>
                                <span>Master Barang & Jasa</span>
                            </a>
                        </li>
                        @endcan

                        @can('view_inventory')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('uoms.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('uoms.index') }}">
                                <i class="bi bi-aspect-ratio fs-6 me-3 text-warning"></i>
                                <span>Master Satuan (UOM)</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-2"></li>
                        @endcan

                        @can('view_companies')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('companies.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('companies.index') }}">
                                <i class="bi bi-buildings fs-6 me-3 text-info"></i>
                                <span>Master Perusahaan (PT)</span>
                            </a>
                        </li>
                        @endcan

                        @can('view_vendors')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('vendors.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('vendors.index') }}">
                                <i class="bi bi-shop fs-6 me-3 text-success"></i>
                                <span>Master Vendor / Supplier</span>
                            </a>
                        </li>
                        @endcan

                        @can('view_inventory')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('warehouses.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('warehouses.index') }}">
                                <i class="bi bi-house-door fs-6 me-3 text-secondary"></i>
                                <span>Master Gudang</span>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- 3. PURCHASING --}}
                @canany(['view_pr', 'view_po'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('pr.*', 'po.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Purchasing
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu rounded-4" style="min-width: 240px;">
                        @can('view_pr')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('pr.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('pr.index') }}">
                                <i class="bi bi-file-earmark-text fs-6 me-3 text-primary"></i>
                                <span>Purchase Requisitions</span>
                            </a>
                        </li>
                        @endcan
                        @can('view_po')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('po.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('po.index') }}">
                                <i class="bi bi-cart2 fs-6 me-3 text-success"></i>
                                <span>Purchase Orders</span>
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- 4. RECEIVING --}}
                @can('view_gr')
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('gr.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="{{ route('gr.index') }}">
                        Receiving
                    </a>
                </li>
                @endcan

                {{-- 5. WAREHOUSE & LOGISTIK (MURNI STOK & PERGERAKAN LOGISTIK) --}}
                @canany(['view_inventory', 'manage_gi'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.*', 'rtv.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Warehouse
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu rounded-4" style="min-width: 260px;">
                        @can('view_inventory')
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('inventory.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('inventory.index') }}">
                                <i class="bi bi-boxes fs-6 me-3 text-primary"></i>
                                <span>Ringkasan Stok Barang</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-2"></li>
                        @endcan

                        @can('manage_gi')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold px-3 pt-1">Sirkulasi Barang</h6></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('goods-issues.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('goods-issues.index') }}"><i class="bi bi-box-arrow-up fs-6 me-3 text-danger"></i> Pengeluaran Barang</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('stock-transfers.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('stock-transfers.index') }}"><i class="bi bi-truck fs-6 me-3 text-primary"></i> Mutasi Antar Gudang</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('goods-issue-returns.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('goods-issue-returns.index') }}"><i class="bi bi-arrow-return-left fs-6 me-3 text-warning"></i> Retur Barang (Masuk)</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('rtv.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('rtv.index') }}"><i class="bi bi-truck-flatbed fs-6 me-3 text-danger"></i> Retur ke Vendor (RTV)</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('employee-inventories.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('employee-inventories.index') }}"><i class="bi bi-person-badge fs-6 me-3 text-info"></i> Inventaris Karyawan</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('stock-adjustments.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('stock-adjustments.index') }}"><i class="bi bi-sliders fs-6 me-3 text-success"></i> Penyesuaian Stok</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('stock-opnames.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('stock-opnames.index') }}"><i class="bi bi-ui-checks-grid fs-6 me-3 text-warning"></i> Audit & Stock Opname</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- 6. FIXED ASSETS (TAB MENU TERPISAH KHUSUS MANAJEMEN ASET) --}}
                @can('view_assets')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Fixed Assets
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu rounded-4" style="min-width: 270px;">
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('fixed-assets.master_list') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('fixed-assets.master_list') }}"><i class="bi bi-server fs-6 me-3 text-primary"></i> Master Data Aset Aktif</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('fixed-assets.transactions') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('fixed-assets.transactions') }}"><i class="bi bi-arrow-left-right fs-6 me-3 text-danger"></i> Transaksi & Retur Aset</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('asset-capitalizations.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('asset-capitalizations.index') }}"><i class="bi bi-magic fs-6 me-3 text-warning"></i> Pengakuan Aset (Capitalization)</a></li>
                        <li><hr class="dropdown-divider my-2"></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('fixed-assets.index') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('fixed-assets.index') }}"><i class="bi bi-cloud-arrow-up fs-6 me-3 text-info"></i> Registrasi / Import Aset</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('fixed-assets.import_history') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('fixed-assets.import_history') }}"><i class="bi bi-clock-history fs-6 me-3 text-secondary"></i> Riwayat Import & Label QR</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('fixed-assets.hibah_history') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('fixed-assets.hibah_history') }}"><i class="bi bi-gift fs-6 me-3 text-success"></i> Riwayat Hibah Aset</a></li>
                    </ul>
                </li>
                @endcan

                {{-- 7. FINANCE --}}
                @canany(['view_invoices', 'view_payments', 'view_bills'])
                <li class="nav-item dropdown">
                    @php
                        $apDebtCount = class_exists('\App\Models\PurchaseOrder') ? \App\Models\PurchaseOrder::whereHas('status', function($q) { $q->whereIn('slug', ['approved', 'partial']); })->count() : 0;

                        $opexPendingCount = 0;
                        if(class_exists('\App\Models\DocumentApproval') && auth()->check()) {
                            $userRoles = auth()->user()->roles->pluck('id')->toArray();
                            $opexQuery = \App\Models\DocumentApproval::where('status', 'PENDING')->whereIn('document_type', ['OPEX', 'App\Models\BillRequest', 'BillRequest']);
                            if (!auth()->user()->hasRole(['Super Administrator', 'Super Admin'])) {
                                $opexQuery->whereIn('role_id', $userRoles);
                            }
                            $opexPendingCount = $opexQuery->count();
                        }
                        $totalFinanceNotif = $apDebtCount + $opexPendingCount;
                    @endphp

                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Finance
                        @if($totalFinanceNotif > 0)
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;">{{ $totalFinanceNotif }}</span>
                        @endif
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu rounded-4" style="min-width: 250px;">
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex justify-content-between align-items-center {{ request()->routeIs('vendor-invoices.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('vendor-invoices.index') }}">
                                <span><i class="bi bi-receipt fs-6 me-3 text-primary"></i> Tagihan Vendor (A/P)</span>
                                @if($apDebtCount > 0)<span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">{{ $apDebtCount }}</span>@endif
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex justify-content-between align-items-center {{ request()->routeIs('bills.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('bills.index') }}">
                                <span><i class="bi bi-file-earmark-spreadsheet fs-6 me-3 text-info"></i> Pengajuan Opex</span>
                                @if($opexPendingCount > 0)<span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">{{ $opexPendingCount }}</span>@endif
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-2"></li>
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('payments.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('payments.index') }}">
                                <i class="bi bi-wallet2 fs-6 me-3 text-success"></i>
                                <span>Proses Pembayaran</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endcanany

                {{-- 8. REPORT --}}
                @can('view_reports')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('reports.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Report
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu rounded-4" style="min-width: 240px;">
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('reports.finance') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('reports.finance') }}">
                                <i class="bi bi-file-earmark-bar-graph fs-6 me-3 text-primary"></i>
                                <span>Laporan Keuangan</span>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('reports.inventory-valuation') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('reports.inventory-valuation') }}">
                                <i class="bi bi-boxes fs-6 me-3 text-success"></i>
                                <span>Valuasi Persediaan</span>
                            </a>
                        </li>
                    </ul>
                </li>
                @endcan

                {{-- 9. SETTINGS --}}
                @can('manage_roles')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Settings
                    </a>
                    <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="min-width: 230px;">
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('users.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('users.index') }}"><i class="bi bi-people fs-6 me-3 text-primary"></i> User Management</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('roles.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock fs-6 me-3 text-danger"></i> Role & Permissions</a></li>
                        <li><hr class="dropdown-divider my-2"></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('workflows.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('workflows.index') }}"><i class="bi bi-diagram-3 fs-6 me-3 text-warning"></i> Workflows</a></li>
                        <li><a class="dropdown-item py-2 px-3 rounded-3 d-flex align-items-center {{ request()->routeIs('document-types.*') ? 'active bg-primary-subtle text-primary fw-bold' : 'text-secondary' }}" href="{{ route('document-types.index') }}"><i class="bi bi-file-earmark-check fs-6 me-3 text-info"></i> Document Types</a></li>
                    </ul>
                </li>
                @endcan

            </ul>

            {{-- PROFIL KANAN & PUSAT NOTIFIKASI --}}
            <div class="gap-3 d-flex align-items-center right-actions ms-lg-3">

                {{-- Tombol Pencarian Global --}}
                <a href="#" class="shadow-sm btn btn-light rounded-circle d-flex align-items-center justify-content-center text-secondary" style="width: 40px; height: 40px;" title="Pencarian Global">
                    <i class="bi bi-search"></i>
                </a>

                {{-- PUSAT NOTIFIKASI --}}
                @php
                    $pendingPRs = collect();
                    $pendingPOs = collect();
                    $countPendingPRs = 0;
                    $countPendingPOs = 0;
                    $totalNotif = 0;

                    if (auth()->check()) {
                        $userRoles = auth()->user()->roles->pluck('id')->toArray();

                        if(class_exists('\App\Models\PurchaseRequest')) {
                            $countPendingPRs = \App\Models\PurchaseRequest::whereHas('approvals', function($q) use ($userRoles) {
                                $q->where('status', 'PENDING')->whereIn('role_id', $userRoles);
                            })->count();

                            if ($countPendingPRs > 0) {
                                $pendingPRs = \App\Models\PurchaseRequest::whereHas('approvals', function($q) use ($userRoles) {
                                    $q->where('status', 'PENDING')->whereIn('role_id', $userRoles);
                                })->latest()->take(5)->get();
                            }
                        }

                        if(class_exists('\App\Models\PurchaseOrder')) {
                            $countPendingPOs = \App\Models\PurchaseOrder::whereHas('approvals', function($q) use ($userRoles) {
                                $q->where('status', 'PENDING')->whereIn('role_id', $userRoles);
                            })->count();

                            if ($countPendingPOs > 0) {
                                $pendingPOs = \App\Models\PurchaseOrder::with(['vendor', 'company'])->whereHas('approvals', function($q) use ($userRoles) {
                                    $q->where('status', 'PENDING')->whereIn('role_id', $userRoles);
                                })->latest()->take(5)->get();
                            }
                        }

                        $totalNotif = $countPendingPRs + $countPendingPOs;
                    }
                @endphp

                <div class="dropdown">
                    <a class="shadow-sm btn btn-light rounded-circle d-flex align-items-center justify-content-center position-relative text-secondary" href="#" role="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" style="width: 40px; height: 40px;">
                        <i class="bi bi-bell fs-5"></i>
                        @if($totalNotif > 0)
                            <span class="top-0 p-1 border position-absolute start-100 translate-middle badge border-light rounded-circle bg-danger" style="width: 12px; height: 12px;"><span class="visually-hidden">unread</span></span>
                        @endif
                    </a>

                    <div class="p-0 mt-2 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="width: 350px; z-index: 1060;">
                        <div class="px-3 py-3 text-white bg-primary d-flex justify-content-between align-items-center" style="border-top-left-radius: var(--bs-border-radius-xl); border-top-right-radius: var(--bs-border-radius-xl);">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2"></i> Pusat Notifikasi</h6>
                            @if($totalNotif > 0)<span class="bg-white shadow-sm badge text-primary rounded-pill">{{ $totalNotif }} Baru</span>@endif
                        </div>

                        {{-- TABS NOTIFIKASI --}}
                        <ul class="nav nav-tabs nav-fill border-bottom-0 bg-light" id="notifTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="py-2 border-0 border-2 nav-link active fw-bold small rounded-0 border-bottom border-primary text-primary" id="pr-tab" data-bs-toggle="tab" data-bs-target="#notif-pr" type="button" role="tab">
                                    Request (PR) @if($countPendingPRs > 0)<span class="ms-1 badge bg-danger rounded-pill" style="font-size: 0.6rem;">{{ $countPendingPRs }}</span>@endif
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="py-2 border-0 nav-link fw-bold small rounded-0 text-muted" id="po-tab" data-bs-toggle="tab" data-bs-target="#notif-po" type="button" role="tab" onclick="this.classList.add('active', 'border-bottom', 'border-success', 'border-2', 'text-success'); this.classList.remove('text-muted'); document.getElementById('pr-tab').classList.remove('active', 'border-bottom', 'border-primary', 'border-2', 'text-primary'); document.getElementById('pr-tab').classList.add('text-muted');">
                                    Order (PO) @if($countPendingPOs > 0)<span class="ms-1 badge bg-danger rounded-pill" style="font-size: 0.6rem;">{{ $countPendingPOs }}</span>@endif
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="notifTabContent" style="max-height: 350px; overflow-y: auto;">
                            <div class="tab-pane fade show active" id="notif-pr" role="tabpanel">
                                @if($countPendingPRs > 0)
                                    <div class="list-group list-group-flush">
                                        @foreach($pendingPRs as $notifPr)
                                            <a href="{{ route('pr.show', $notifPr->pr_number) }}" class="py-3 list-group-item list-group-item-action border-bottom">
                                                <div class="mb-1 d-flex w-100 justify-content-between align-items-center">
                                                    <strong class="text-primary small">{{ $notifPr->pr_number }}</strong>
                                                    <small class="text-muted" style="font-size: 0.65rem;">{{ \Carbon\Carbon::parse($notifPr->request_date)->diffForHumans() }}</small>
                                                </div>
                                                <div class="mb-1 text-dark small fw-semibold">
                                                    <img src="https://ui-avatars.com/api/?name={{ urlencode(optional($notifPr->user)->name ?? 'U') }}&background=e0f2fe&color=0284c7&size=20" class="rounded-circle me-1">
                                                    {{ optional($notifPr->user)->name ?? 'Unknown' }}
                                                </div>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <i class="bi bi-building me-1"></i> {{ optional($notifPr->department)->name ?? 'Head Office' }}
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="p-2 text-center bg-light border-top">
                                        <a href="{{ route('pr.index') }}" class="text-decoration-none small fw-bold text-primary">Lihat Semua PR <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                @else
                                    <div class="py-5 text-center bg-white text-muted">
                                        <i class="mb-2 bi bi-check2-circle text-success" style="font-size: 2.5rem; display: block;"></i>
                                        <span class="small fw-bold">Yeay! Tidak ada PR mengantre.</span>
                                    </div>
                                @endif
                            </div>

                            <div class="tab-pane fade" id="notif-po" role="tabpanel">
                                @if($countPendingPOs > 0)
                                    <div class="list-group list-group-flush">
                                        @foreach($pendingPOs as $notifPo)
                                            <a href="{{ route('po.show', $notifPo->po_number) }}" class="py-3 list-group-item list-group-item-action border-bottom">
                                                <div class="mb-1 d-flex w-100 justify-content-between align-items-center">
                                                    <strong class="text-success small">{{ $notifPo->po_number }}</strong>
                                                    <small class="text-muted" style="font-size: 0.65rem;">{{ \Carbon\Carbon::parse($notifPo->po_date)->diffForHumans() }}</small>
                                                </div>
                                                <div class="mb-1 text-dark small fw-semibold text-truncate">
                                                    <i class="bi bi-shop me-1 text-secondary"></i> {{ optional($notifPo->vendor)->name ?? 'Vendor Tidak Diketahui' }}
                                                </div>
                                                <div class="mt-2 text-muted d-flex justify-content-between align-items-center" style="font-size: 0.75rem;">
                                                    <span class="text-truncate" style="max-width: 150px;"><i class="bi bi-building me-1"></i> {{ optional($notifPo->company)->name ?? 'Head Office' }}</span>
                                                    <span class="fw-bold text-dark">{{ $notifPo->currency ?? 'IDR' }} {{ number_format($notifPo->grand_total, 0, ',', '.') }}</span>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                    <div class="p-2 text-center bg-light border-top">
                                        <a href="{{ route('po.index') }}" class="text-decoration-none small fw-bold text-success">Lihat Semua PO <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                @else
                                    <div class="py-5 text-center bg-white text-muted">
                                        <i class="mb-2 bi bi-check2-circle text-success" style="font-size: 2.5rem; display: block;"></i>
                                        <span class="small fw-bold">Semua Order telah disetujui.</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                {{-- MENU PROFIL USER --}}
                @auth
                <div class="dropdown ms-1">
                    <a href="#" class="p-1 bg-white border border-2 border-white shadow-sm text-decoration-none d-flex align-items-center rounded-pill" data-bs-toggle="dropdown" aria-expanded="false">
                        @if(Auth::user()->avatar)
                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="object-fit-cover rounded-circle" alt="Avatar" style="width: 36px; height: 36px;">
                        @else
                            <div class="text-white bg-primary d-flex align-items-center justify-content-center rounded-circle fw-bold" style="width: 36px; height: 36px; font-size: 0.9rem;">
                                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                            </div>
                        @endif
                        <span class="ms-2 me-2 fw-bold text-dark small d-none d-xl-block" style="max-width: 100px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">{{ explode(' ', Auth::user()->name)[0] }}</span>
                        <i class="bi bi-chevron-down text-muted me-1 d-none d-xl-block" style="font-size: 0.7rem;"></i>
                    </a>

                    <ul class="p-2 mt-3 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="min-width: 240px; z-index: 1060;">
                        <li class="px-3 py-2 mb-2 border bg-light rounded-3">
                            <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.65rem; letter-spacing: 0.5px;">Signed in as</div>
                            <div class="text-dark fw-bolder fs-6 text-truncate" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</div>
                            <div class="text-primary small fw-semibold text-truncate">{{ Auth::user()->roles->pluck('name')->first() ?? 'Staff' }}</div>
                        </li>
                        <li>
                            <a class="py-2 dropdown-item d-flex align-items-center rounded-3 fw-semibold text-secondary" href="{{ route('profile.edit') }}">
                                <i class="bi bi-person-circle fs-5 me-3 text-info"></i> Profil Saya
                            </a>
                        </li>
                        <li><hr class="mx-2 my-2 dropdown-divider"></li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="py-2 border dropdown-item text-danger d-flex align-items-center fw-bold rounded-3 bg-danger-subtle border-danger-subtle">
                                    <i class="bi bi-box-arrow-right fs-5 me-3"></i> Keluar / Sign Out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
                @else
                    <a href="{{ route('login') }}" class="px-4 shadow-sm btn btn-primary rounded-pill fw-bold ms-2">Sign In</a>
                @endauth
            </div>

        </div>
    </div>
</nav>

{{-- Script Khusus Tab Navbar --}}
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var prTab = document.getElementById('pr-tab');
        var poTab = document.getElementById('po-tab');

        if(prTab && poTab) {
            prTab.addEventListener('click', function() {
                this.classList.add('active', 'border-bottom', 'border-primary', 'border-2', 'text-primary');
                this.classList.remove('text-muted');
                poTab.classList.remove('active', 'border-bottom', 'border-success', 'border-2', 'text-success');
                poTab.classList.add('text-muted');
            });
        }
    });
</script>
