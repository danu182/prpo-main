<nav class="shadow-sm navbar navbar-expand-lg navbar-floating sticky-top">
    <div class="p-0 container-fluid">

        <a class="brand-logo me-4" href="{{ route('dashboard') }}">
            <div class="brand-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div>
                <span class="fw-bolder text-primary" style="letter-spacing: -0.5px;">Procure</span><span class="text-dark fw-medium">App</span>
            </div>
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

                {{-- 2. PURCHASING DROPDOWN --}}
                @canany(['view_pr', 'view_po'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('pr.*', 'po.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Purchasing
                    </a>
                    <ul class="dropdown-menu">
                        @can('view_pr')
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('pr.*') ? 'active' : '' }}" href="{{ route('pr.index') }}">
                                <i class="bi bi-file-earmark-text me-2 text-primary"></i> Purchase Requisitions
                            </a>
                        </li>
                        @endcan
                        @can('view_po')
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('po.*') ? 'active' : '' }}" href="{{ route('po.index') }}">
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
                    <a class="nav-link {{ request()->routeIs('gr.*') ? 'active' : '' }}" href="{{ route('gr.index') }}">
                        Receiving
                    </a>
                </li>
                @endcan

                {{-- 4. WAREHOUSE DROPDOWN --}}
                @canany(['view_inventory', 'manage_gi', 'view_assets', 'manage_items'])
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('asset-capitalizations.*', 'assets.*', 'goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'fixed-assets.*', 'items.*', 'inventory.*', 'rtv.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Warehouse
                    </a>
                    <ul class="dropdown-menu">
                        @can('view_inventory')
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('inventory.*') ? 'active' : '' }}" href="{{ route('inventory.index') }}">
                                <i class="bi bi-boxes me-2 text-primary"></i> Master Stok Barang
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @endcan

                        @can('manage_gi')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold">Operasional Gudang</h6></li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('goods-issues.*') ? 'active' : '' }}" href="{{ route('goods-issues.index') }}">
                                <i class="bi bi-box-arrow-up text-danger me-2"></i> Pengeluaran Barang
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}" href="{{ route('stock-transfers.index') }}">
                                <i class="bi bi-truck text-primary me-2"></i> Mutasi Antar Gudang
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('goods-issue-returns.*') ? 'active' : '' }}" href="{{ route('goods-issue-returns.index') }}">
                                <i class="bi bi-arrow-return-left text-warning me-2"></i> Retur Barang (Masuk)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('rtv.*') ? 'active' : '' }}" href="{{ route('rtv.index') }}">
                                <i class="bi bi-truck-flatbed text-danger me-2"></i> Retur ke Vendor (RTV)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('employee-inventories.*') ? 'active' : '' }}" href="{{ route('employee-inventories.index') }}">
                                <i class="bi bi-person-badge text-info me-2"></i> Inventaris Karyawan
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}" href="{{ route('stock-adjustments.index') }}">
                                <i class="bi bi-sliders text-success me-2"></i> Stock Opname
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @endcan

                        @can('view_assets')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold">Manajemen Aset</h6></li>

                        {{-- MENU BARU: PENGAKUAN ASET --}}
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('asset-capitalizations.*') ? 'active' : '' }}" href="{{ route('asset-capitalizations.index') }}">
                                <i class="bi bi-magic text-warning me-2"></i> Pengakuan Aset (Capitalization)
                            </a>
                        </li>

                        <li>
                            <a class="dropdown-item {{ request()->routeIs('fixed-assets.*') ? 'active' : '' }}" href="{{ route('fixed-assets.index') }}">
                                <i class="bi bi-pc-display text-info me-2"></i> Daftar Fixed Assets
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        @endcan

                        @can('manage_items')
                        <li><h6 class="opacity-50 dropdown-header text-uppercase small fw-bold">Katalog Data</h6></li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('items.*') ? 'active' : '' }}" href="{{ route('items.index') }}">
                                <i class="bi bi-box text-secondary me-2"></i> Master Barang & Jasa
                            </a>
                        </li>
                        @endcan
                    </ul>
                </li>
                @endcanany

                {{-- 5. FINANCE --}}
                @canany(['view_invoices', 'view_payments', 'view_bills'])
                <li class="nav-item dropdown">
                    @php
                        $debtCount = 0;
                        if(class_exists('\App\Models\BillRequest')) {
                            $debtCount = \App\Models\BillRequest::whereHas('status', function($q) {
                                $q->whereIn('slug', ['approved', 'partial']);
                            })->count();
                        }
                    @endphp
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Finance
                        @if($debtCount > 0)
                            <span class="badge bg-danger rounded-pill ms-1" style="font-size: 0.65rem;">{{ $debtCount }}</span>
                        @endif
                    </a>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('vendor-invoices.*') ? 'active' : '' }}" href="{{ route('vendor-invoices.index') }}">
                                <i class="bi bi-receipt me-2 text-primary"></i> Tagihan Vendor (A/P)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('bills.*') ? 'active' : '' }}" href="{{ route('bills.index') }}">
                                <i class="bi bi-file-earmark-spreadsheet me-2 text-info"></i> Pengajuan Opex
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('payments.*') ? 'active' : '' }}" href="{{ route('payments.index') }}">
                                <i class="bi bi-wallet2 me-2 text-success"></i> Proses Pembayaran
                            </a>
                        </li>
                    </ul>
                </li>
                @endcanany

                {{-- 6. REPORT (Ditambahkan @can pengaman) --}}
                @can('view_reports')
                <li class="nav-item">
                    <a class="nav-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" href="{{ route('reports.finance') }}">
                        Report
                    </a>
                </li>
                @endcan

                {{-- 7. SETTINGS (Ditambahkan Workflow & Doc Types) --}}
                @can('manage_roles')
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'active' : '' }}" href="#" role="button" data-bs-toggle="dropdown">
                        Settings
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                                <i class="bi bi-people me-2 text-primary"></i> User Management
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('roles.*') ? 'active' : '' }}" href="{{ route('roles.index') }}">
                                <i class="bi bi-shield-lock me-2 text-danger"></i> Role & Permissions
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('workflows.*') ? 'active' : '' }}" href="{{ route('workflows.index') }}">
                                <i class="bi bi-diagram-3 me-2 text-warning"></i> Workflows
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item {{ request()->routeIs('document-types.*') ? 'active' : '' }}" href="{{ route('document-types.index') }}">
                                <i class="bi bi-file-earmark-check me-2 text-info"></i> Document Types
                            </a>
                        </li>
                    </ul>
                </li>
                @endcan

            </ul>

            {{-- PROFIL KANAN --}}
            <div class="gap-2 d-flex align-items-center right-actions ms-lg-3">

                {{-- Tombol Pencarian --}}
                <a href="#" class="action-btn" title="Pencarian Global">
                    <i class="bi bi-search"></i>
                </a>

                {{-- LONCENG NOTIFIKASI DENGAN TAB PR & PO --}}
                @auth
                <div class="dropdown me-3">
                    @php
                        $allUnreadCount = auth()->user()->unreadNotifications->count();
                        $allNotifs = auth()->user()->notifications()->limit(10)->get();

                        // Pisahkan Notifikasi berdasarkan kata kunci di judulnya
                        $prNotifs = $allNotifs->filter(function($n) { return str_contains(strtoupper($n->data['title'] ?? ''), 'PR '); });
                        $poNotifs = $allNotifs->filter(function($n) { return str_contains(strtoupper($n->data['title'] ?? ''), 'PO '); });
                    @endphp

                    <a href="#" class="action-btn position-relative" id="notifDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifikasi" data-bs-auto-close="outside">
                        <i class="bi bi-bell-fill fs-5 text-secondary"></i>
                        @if($allUnreadCount > 0)
                            <span class="top-0 p-1 border border-white position-absolute start-75 translate-middle bg-danger rounded-circle"></span>
                        @endif
                    </a>

                    {{-- ISI KOTAK DROPDOWN NOTIFIKASI BER-TAB --}}
                    <div class="p-0 mt-3 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" aria-labelledby="notifDropdown" style="width: 350px; overflow: hidden;">

                        {{-- HEADER --}}
                        <div class="p-3 text-white bg-primary d-flex justify-content-between align-items-center">
                            <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2"></i>Pusat Notifikasi</h6>
                            @if($allUnreadCount > 0)
                                <span class="badge bg-danger rounded-pill">{{ $allUnreadCount }} Baru</span>
                            @endif
                        </div>

                        {{-- NAV TABS --}}
                        <ul class="nav nav-tabs nav-justified bg-light border-bottom" id="notifTab" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="border-0 nav-link active fw-bold text-dark rounded-0" id="pr-tab" data-bs-toggle="tab" data-bs-target="#pr-pane" type="button" role="tab">
                                    <i class="bi bi-file-earmark-text me-1"></i> Request (PR)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="border-0 nav-link fw-bold text-dark rounded-0" id="po-tab" data-bs-toggle="tab" data-bs-target="#po-pane" type="button" role="tab">
                                    <i class="bi bi-cart-check me-1"></i> Order (PO)
                                </button>
                            </li>
                        </ul>

                        {{-- TAB CONTENT --}}
                        <div class="tab-content" id="notifTabContent" style="max-height: 350px; overflow-y: auto;">

                            {{-- TAB 1: PURCHASE REQUEST (PR) --}}
                            <div class="p-2 tab-pane fade show active" id="pr-pane" role="tabpanel" tabindex="0">
                                <ul class="mb-0 list-unstyled">
                                    @forelse($prNotifs as $notification)
                                        @php $isRead = $notification->read_at !== null; @endphp
                                        <li>
                                            <a class="py-2 mb-1 dropdown-item rounded-3 text-wrap {{ $isRead ? 'bg-light opacity-75' : 'bg-white border-start border-primary border-3 shadow-sm' }}" href="{{ route('notif.read', $notification->id) }}">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="small {{ $isRead ? 'text-muted' : 'text-dark fw-bold' }}">{{ $notification->data['title'] ?? 'Notifikasi PR' }}</div>
                                                    @if($isRead) <i class="bi bi-check2-all text-success ms-2"></i> @endif
                                                </div>
                                                <div class="mt-1 {{ $isRead ? 'text-muted' : 'text-secondary' }}" style="font-size: 0.75rem; line-height: 1.3;">{{ $notification->data['message'] ?? '' }}</div>
                                                <div class="mt-2 {{ $isRead ? 'text-muted' : 'text-primary fw-semibold' }}" style="font-size: 0.65rem;"><i class="bi bi-clock me-1"></i> {{ $notification->created_at->diffForHumans() }}</div>
                                            </a>
                                        </li>
                                    @empty
                                        <li>
                                            <div class="py-4 text-center text-muted small rounded-3"><i class="mb-2 bi bi-check2-circle fs-3 d-block text-success"></i>Tidak ada notifikasi PR.</div>
                                        </li>
                                    @endforelse
                                </ul>
                            </div>

                            {{-- TAB 2: PURCHASE ORDER (PO) --}}
                            <div class="p-2 tab-pane fade" id="po-pane" role="tabpanel" tabindex="0">
                                <ul class="mb-0 list-unstyled">
                                    @forelse($poNotifs as $notification)
                                        @php $isRead = $notification->read_at !== null; @endphp
                                        <li>
                                            <a class="py-2 mb-1 dropdown-item rounded-3 text-wrap {{ $isRead ? 'bg-light opacity-75' : 'bg-white border-start border-success border-3 shadow-sm' }}" href="{{ route('notif.read', $notification->id) }}">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="small {{ $isRead ? 'text-muted' : 'text-dark fw-bold' }}">{{ $notification->data['title'] ?? 'Notifikasi PO' }}</div>
                                                    @if($isRead) <i class="bi bi-check2-all text-success ms-2"></i> @endif
                                                </div>
                                                <div class="mt-1 {{ $isRead ? 'text-muted' : 'text-secondary' }}" style="font-size: 0.75rem; line-height: 1.3;">{{ $notification->data['message'] ?? '' }}</div>
                                                <div class="mt-2 {{ $isRead ? 'text-muted' : 'text-success fw-semibold' }}" style="font-size: 0.65rem;"><i class="bi bi-clock me-1"></i> {{ $notification->created_at->diffForHumans() }}</div>
                                            </a>
                                        </li>
                                    @empty
                                        <li>
                                            <div class="py-4 text-center text-muted small rounded-3"><i class="mb-2 bi bi-check2-circle fs-3 d-block text-success"></i>Tidak ada notifikasi PO.</div>
                                        </li>
                                    @endforelse
                                </ul>
                            </div>

                        </div> {{-- End Tab Content --}}
                    </div> {{-- End Dropdown Menu --}}
                </div>
                @endauth

                {{-- MENU PROFIL USER --}}
                @auth
                <div class="dropdown ms-2">
                    <a href="#" class="text-decoration-none d-flex align-items-center" data-bs-toggle="dropdown">
                        @if(Auth::user()->avatar)
                            <img src="{{ asset('storage/' . Auth::user()->avatar) }}"
                                 class="shadow-sm user-avatar-small object-fit-cover" alt="Avatar" style="width: 35px; height: 35px; border-radius: 50%;">
                        @else
                            <div class="shadow-sm user-avatar-small bg-primary text-white d-flex align-items-center justify-content-center" style="width: 35px; height: 35px; border-radius: 50%; font-weight: bold;">
                                {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                            </div>
                        @endif
                    </a>

                    <ul class="p-2 mt-3 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="min-width: 240px;">
                        <li class="px-3 py-2">
                            <div class="text-muted small fw-bold text-uppercase" style="font-size: 0.7rem;">Signed in as</div>
                            <div class="text-dark fw-bold fs-6 text-truncate" title="{{ Auth::user()->name }}">{{ Auth::user()->name }}</div>
                        </li>
                        <li><hr class="mx-2 dropdown-divider"></li>
                        <li>
                            <a class="py-2 dropdown-item d-flex align-items-center rounded-3" href="{{ route('profile.edit') }}">
                                <i class="bi bi-person-circle fs-5 me-3 text-secondary"></i> Profil Saya
                            </a>
                        </li>
                        <li>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="py-2 dropdown-item text-danger d-flex align-items-center fw-bold rounded-3">
                                    <i class="bi bi-box-arrow-right fs-5 me-3"></i> Keluar
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
