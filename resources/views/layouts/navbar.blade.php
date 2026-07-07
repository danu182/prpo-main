<nav class="bg-white shadow-sm navbar navbar-expand-lg navbar-floating sticky-top">
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

                {{-- 2. PURCHASING DROPDOWN --}}
                @canany(['view_pr', 'view_po'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('pr.*', 'po.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Purchasing
                    </a>
                    <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4">
                        @can('view_pr')
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('pr.*') ? 'active' : '' }}" href="{{ route('pr.index') }}">
                                <i class="bi bi-file-earmark-text me-2 text-primary"></i> Purchase Requisitions
                            </a>
                        </li>
                        @endcan
                        @can('view_po')
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('po.*') ? 'active' : '' }}" href="{{ route('po.index') }}">
                                <i class="bi bi-cart2 me-2 text-success"></i> Purchase Orders
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- 3. RECEIVING --}}
                @can('view_gr')
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('gr.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="{{ route('gr.index') }}">
                        Receiving
                    </a>
                </li>
                @endcan

                {{-- 4. WAREHOUSE DROPDOWN --}}
                @canany(['view_inventory', 'manage_gi', 'view_assets', 'manage_items'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('asset-capitalizations.*', 'assets.*', 'goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'fixed-assets.*', 'items.*', 'inventory.*', 'rtv.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Warehouse
                    </a>
                    <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4" style="min-width: 250px;">
                        @can('view_inventory')
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                                <i class="bi bi-boxes me-2 text-primary"></i> Master Stok Barang
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @endcan

                        @can('manage_gi')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold">Operasional Gudang</h6></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('goods-issues.*') ? 'active' : '' }}" href="{{ route('goods-issues.index') }}"><i class="bi bi-box-arrow-up text-danger me-2"></i> Pengeluaran Barang</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}" href="{{ route('stock-transfers.index') }}"><i class="bi bi-truck text-primary me-2"></i> Mutasi Antar Gudang</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('goods-issue-returns.*') ? 'active' : '' }}" href="{{ route('goods-issue-returns.index') }}"><i class="bi bi-arrow-return-left text-warning me-2"></i> Retur Barang (Masuk)</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('rtv.*') ? 'active' : '' }}" href="{{ route('rtv.index') }}"><i class="bi bi-truck-flatbed text-danger me-2"></i> Retur ke Vendor (RTV)</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('employee-inventories.*') ? 'active' : '' }}" href="{{ route('employee-inventories.index') }}"><i class="bi bi-person-badge text-info me-2"></i> Inventaris Karyawan</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}" href="{{ route('stock-adjustments.index') }}"><i class="bi bi-sliders text-success me-2"></i> Stock Opname</a></li>
                        <li><hr class="dropdown-divider"></li>
                        @endcan

                        @can('view_assets')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold">Manajemen Aset</h6></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('asset-capitalizations.*') ? 'active' : '' }}" href="{{ route('asset-capitalizations.index') }}"><i class="bi bi-magic text-warning me-2"></i> Pengakuan Aset (Capitalization)</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('fixed-assets.*') ? 'active' : '' }}" href="{{ route('fixed-assets.index') }}"><i class="bi bi-pc-display text-info me-2"></i> Daftar Fixed Assets</a></li>
                        <li><hr class="dropdown-divider"></li>
                        @endcan

                        @can('manage_items')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold">Katalog Data</h6></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}"><i class="bi bi-box text-secondary me-2"></i> Master Barang & Jasa</a></li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- 5. FINANCE --}}
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

                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Finance
                        @if($totalFinanceNotif > 0)
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;">{{ $totalFinanceNotif }}</span>
                        @endif
                    </a>
                    <ul class="mt-2 border-0 shadow-sm dropdown-menu rounded-4">
                        <li>
                            <a class="dropdown-item py-2 d-flex justify-content-between align-items-center {{ request()->routeIs('vendor-invoices.*') ? 'active' : '' }}" href="{{ route('vendor-invoices.index') }}">
                                <span><i class="bi bi-receipt me-2 text-primary"></i> Tagihan Vendor (A/P)</span>
                                @if($apDebtCount > 0)<span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">{{ $apDebtCount }}</span>@endif
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 d-flex justify-content-between align-items-center {{ request()->routeIs('bills.*') ? 'active' : '' }}" href="{{ route('bills.index') }}">
                                <span><i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i> Pengajuan Opex</span>
                                @if($opexPendingCount > 0)<span class="badge bg-danger rounded-pill" style="font-size: 0.65rem;">{{ $opexPendingCount }}</span>@endif
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item py-2 {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}">
                                <i class="bi bi-wallet2 me-2 text-success"></i> Proses Pembayaran
                            </a>
                        </li>
                    </ul>
                </li>
                @endcanany

                {{-- 6. REPORT --}}
                @can('view_reports')
                <li class="nav-item">
                    <a class="nav-link px-3 {{ request()->routeIs('reports.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="{{ route('reports.finance') }}">
                        Report
                    </a>
                </li>
                @endcan

                {{-- 7. SETTINGS --}}
                @can('manage_roles')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle px-3 {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'active bg-primary-subtle text-primary rounded-pill' : 'text-secondary' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Settings
                    </a>
                    <ul class="mt-2 border-0 shadow-sm dropdown-menu dropdown-menu-end rounded-4">
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}"><i class="bi bi-people me-2 text-primary"></i> User Management</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}"><i class="bi bi-shield-lock me-2 text-danger"></i> Role & Permissions</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('workflows.*') ? 'active' : '' }}" href="{{ route('workflows.index') }}"><i class="bi bi-diagram-3 me-2 text-warning"></i> Workflows</a></li>
                        <li><a class="dropdown-item py-2 {{ request()->routeIs('document-types.*') ? 'active' : '' }}" href="{{ route('document-types.index') }}"><i class="bi bi-file-earmark-check me-2 text-info"></i> Document Types</a></li>
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

                {{-- 🔥 PUSAT NOTIFIKASI 🔥 --}}
                @php
                    $user = auth()->user();
                    $pendingPRs = collect();
                    $countPendingPRs = 0;

                    if ($user) {
                        $userRoleIds = $user->roles->pluck('id')->toArray();
                        $pendingPRs = \App\Models\PurchaseRequest::whereHas('approvals', function($q) use ($userRoleIds) {
                            $q->where('status', 'PENDING')->whereIn('role_id', $userRoleIds);
                        })->latest()->take(5)->get();

                        $countPendingPRs = \App\Models\PurchaseRequest::whereHas('approvals', function($q) use ($userRoleIds) {
                            $q->where('status', 'PENDING')->whereIn('role_id', $userRoleIds);
                        })->count();
                    }
                @endphp

                <div class="dropdown">
                    <a class="shadow-sm btn btn-light rounded-circle d-flex align-items-center justify-content-center position-relative text-secondary" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 40px; height: 40px;">
                        <i class="bi bi-bell fs-5"></i>
                        @if($countPendingPRs > 0)
                            <span class="top-0 p-1 border position-absolute start-100 translate-middle badge border-light rounded-circle bg-danger" style="width: 12px; height: 12px;"><span class="visually-hidden">unread messages</span></span>
                        @endif
                    </a>

                    <div class="p-0 mt-2 overflow-hidden border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="width: 320px;">
                        <div class="px-3 py-3 text-white bg-primary d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2"></i> Pusat Notifikasi</h6>
                            @if($countPendingPRs > 0)<span class="bg-white badge text-primary rounded-pill">{{ $countPendingPRs }} Baru</span>@endif
                        </div>

                        {{-- TABS NOTIFIKASI --}}
                        <ul class="nav nav-tabs nav-fill border-bottom-0 bg-light" id="notifTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="py-2 border-0 border-2 nav-link active fw-bold small rounded-0 border-bottom border-primary text-primary" id="pr-tab" data-bs-toggle="tab" data-bs-target="#notif-pr" type="button" role="tab">Request (PR)</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="py-2 border-0 nav-link fw-bold small rounded-0 text-muted" id="po-tab" data-bs-toggle="tab" data-bs-target="#notif-po" type="button" role="tab">Order (PO)</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="notifTabContent">
                            {{-- TAB PR --}}
                            <div class="tab-pane fade show active" id="notif-pr" role="tabpanel">
                                @if($countPendingPRs > 0)
                                    <div class="list-group list-group-flush" style="max-height: 280px; overflow-y: auto;">
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
                                    <div class="p-2 text-center bg-light">
                                        <a href="{{ route('pr.index') }}" class="text-decoration-none small fw-bold text-primary">Lihat Semua PR <i class="bi bi-arrow-right"></i></a>
                                    </div>
                                @else
                                    <div class="py-5 text-center bg-white text-muted">
                                        <i class="mb-2 bi bi-check2-circle text-success" style="font-size: 2.5rem; display: block;"></i>
                                        <span class="small fw-bold">Yeay! Tidak ada PR mengantre.</span>
                                    </div>
                                @endif
                            </div>

                            {{-- TAB PO (Placeholder) --}}
                            <div class="tab-pane fade" id="notif-po" role="tabpanel">
                                <div class="py-5 text-center bg-white text-muted">
                                    <i class="mb-2 bi bi-inbox text-secondary" style="font-size: 2.5rem; display: block;"></i>
                                    <span class="small fw-bold">Belum ada fitur notifikasi PO.</span>
                                </div>
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

                    <ul class="p-2 mt-3 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="min-width: 240px;">
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
