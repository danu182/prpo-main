{{-- HEADER ATAS (TOPBAR) --}}
            <header class="topbar">
                <div class="d-flex align-items-center">
                    <button class="btn btn-light border-0 rounded-circle me-2 d-flex align-items-center justify-content-center text-secondary" id="sidebarToggle" style="width: 38px; height: 38px;" title="Sembunyikan/Tampilkan Menu">
                        <i class="bi bi-list fs-4"></i>
                    </button>
                    <h5 class="mb-0 fw-bold text-dark fs-6 d-none d-sm-block">Procurement System</h5>
                </div>

                <div class="gap-2 d-flex align-items-center">

                    {{-- TOMBOL CARI --}}
                    <a href="#" class="btn btn-light border-0 rounded-circle d-flex align-items-center justify-content-center text-secondary" style="width: 38px; height: 38px;" title="Cari...">
                        <i class="bi bi-search"></i>
                    </a>

                    {{-- 🔥 PUSAT NOTIFIKASI (WORKFLOW APPROVAL) 🔥 --}}
                    @php
                        $totalNotif = 0;
                        $pendingPRs = collect();
                        $pendingPOs = collect();
                        $pendingSOs = collect();

                        if (auth()->check()) {
                            $user = auth()->user();
                            $userRoles = $user->roles->pluck('id')->toArray();
                            $isSuperAdmin = $user->hasAnyRole(['Super Administrator', 'Super Admin']) || $user->id === 1;

                            // 1. Cek Antrean Purchase Request (PR)
                            if (class_exists('\App\Models\PurchaseRequest')) {
                                $prQuery = \App\Models\PurchaseRequest::whereHas('approvals', function($q) use ($userRoles, $isSuperAdmin) {
                                    $q->whereIn('status', ['PENDING', 'pending']);
                                    if (!$isSuperAdmin) { $q->whereIn('role_id', $userRoles); }
                                });
                                $totalNotif += $prQuery->count();
                                $pendingPRs = $prQuery->latest()->take(3)->get();
                            }

                            // 2. Cek Antrean Purchase Order (PO)
                            if (class_exists('\App\Models\PurchaseOrder')) {
                                $poQuery = \App\Models\PurchaseOrder::whereHas('approvals', function($q) use ($userRoles, $isSuperAdmin) {
                                    $q->whereIn('status', ['PENDING', 'pending']);
                                    if (!$isSuperAdmin) { $q->whereIn('role_id', $userRoles); }
                                });
                                $totalNotif += $poQuery->count();
                                $pendingPOs = $poQuery->latest()->take(3)->get();
                            }

                            // 3. Cek Antrean Stock Opname (SO)
                            if (class_exists('\App\Models\StockOpname')) {
                                $soQuery = \App\Models\StockOpname::whereHas('approvals', function($q) use ($userRoles, $isSuperAdmin) {
                                    $q->whereIn('status', ['PENDING', 'pending']);
                                    if (!$isSuperAdmin) { $q->whereIn('role_id', $userRoles); }
                                });
                                $totalNotif += $soQuery->count();
                                $pendingSOs = $soQuery->latest()->take(3)->get();
                            }
                        }
                    @endphp

                    <div class="dropdown">
                        <a href="#" class="btn btn-light border-0 rounded-circle d-flex align-items-center justify-content-center text-secondary position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" style="width: 38px; height: 38px;">
                            <i class="bi bi-bell"></i>
                            @if($totalNotif > 0)
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.6rem;">
                                    {{ $totalNotif > 99 ? '99+' : $totalNotif }}
                                </span>
                            @endif
                        </a>

                        <div class="dropdown-menu dropdown-menu-end shadow-lg border-0 rounded-4 p-0 mt-2" style="width: 320px; z-index: 1050;">
                            <div class="bg-primary text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold"><i class="bi bi-bell-fill me-2"></i> Notifikasi Approval</h6>
                                <span class="badge bg-white text-primary rounded-pill">{{ $totalNotif }} Antrean</span>
                            </div>

                            <div class="p-0 overflow-auto" style="max-height: 350px;">
                                @if($totalNotif > 0)
                                    <div class="list-group list-group-flush">

                                        {{-- List Notif PR --}}
                                        @foreach($pendingPRs as $pr)
                                            <a href="{{ route('pr.show', $pr->id) }}" class="list-group-item list-group-item-action py-3 border-bottom">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-primary-subtle text-primary border-primary-subtle small px-2">PR Request</span>
                                                    <small class="text-muted" style="font-size: 0.65rem;">{{ \Carbon\Carbon::parse($pr->created_at)->diffForHumans() }}</small>
                                                </div>
                                                <div class="small fw-bold text-dark text-truncate">{{ $pr->pr_number }}</div>
                                                <div class="text-muted small text-truncate" style="font-size: 0.75rem;">Butuh persetujuan Anda.</div>
                                            </a>
                                        @endforeach

                                        {{-- List Notif PO --}}
                                        @foreach($pendingPOs as $po)
                                            <a href="{{ route('po.show', $po->id) }}" class="list-group-item list-group-item-action py-3 border-bottom">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-success-subtle text-success border-success-subtle small px-2">Purchase Order</span>
                                                    <small class="text-muted" style="font-size: 0.65rem;">{{ \Carbon\Carbon::parse($po->created_at)->diffForHumans() }}</small>
                                                </div>
                                                <div class="small fw-bold text-dark text-truncate">{{ $po->po_number }}</div>
                                                <div class="text-muted small text-truncate" style="font-size: 0.75rem;">Total: Rp {{ number_format($po->grand_total ?? 0, 0, ',', '.') }}</div>
                                            </a>
                                        @endforeach

                                        {{-- List Notif Stock Opname --}}
                                        @foreach($pendingSOs as $so)
                                            <a href="{{ route('stock-opnames.show', $so->id) }}" class="list-group-item list-group-item-action py-3 border-bottom">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="badge bg-warning-subtle text-warning border-warning-subtle small px-2">Stock Opname</span>
                                                    <small class="text-muted" style="font-size: 0.65rem;">{{ \Carbon\Carbon::parse($so->created_at)->diffForHumans() }}</small>
                                                </div>
                                                <div class="small fw-bold text-dark text-truncate">{{ $so->document_number }}</div>
                                                <div class="text-muted small text-truncate" style="font-size: 0.75rem;">Validasi selisih stok gudang.</div>
                                            </a>
                                        @endforeach

                                    </div>
                                @else
                                    <div class="text-center py-5">
                                        <i class="bi bi-check2-circle text-success opacity-50 d-block mb-2" style="font-size: 2.5rem;"></i>
                                        <span class="text-muted small fw-medium">Yeay! Tidak ada dokumen yang menunggu persetujuan Anda.</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    {{-- 🔥 END PUSAT NOTIFIKASI 🔥 --}}

                    {{-- PROFIL USER --}}
                    @auth
                    <div class="dropdown">
                        <a href="#" class="p-1 bg-white border border-light-subtle shadow-sm text-decoration-none d-flex align-items-center rounded-pill" data-bs-toggle="dropdown">
                            @if(Auth::user()->avatar)
                                <img src="{{ asset('storage/' . Auth::user()->avatar) }}" class="rounded-circle object-fit-cover" style="width: 32px; height: 32px;">
                            @else
                                <div class="text-white bg-primary rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                    {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                                </div>
                            @endif
                            <span class="ms-2 me-2 fw-bold text-dark small d-none d-md-block">{{ explode(' ', Auth::user()->name)[0] }}</span>
                            <i class="bi bi-chevron-down text-muted me-1 small d-none d-md-block" style="font-size: 0.65rem;"></i>
                        </a>

                        <ul class="p-2 mt-2 border-0 shadow-lg dropdown-menu dropdown-menu-end rounded-4" style="min-width: 220px;">
                            <li class="px-3 py-2 mb-2 bg-light rounded-3">
                                <div class="small text-muted fw-bold text-uppercase" style="font-size: 0.6rem;">Signed in as</div>
                                <div class="fw-bold text-dark text-truncate">{{ Auth::user()->name }}</div>
                                <div class="small text-primary fw-semibold">{{ Auth::user()->roles->pluck('name')->first() ?? 'Staff' }}</div>
                            </li>
                            <li>
                                <a class="py-2 dropdown-item rounded-3 text-secondary small" href="{{ route('profile.edit') }}">
                                    <i class="bi bi-person-circle me-2 text-info"></i> Profil Saya
                                </a>
                            </li>
                            <li><hr class="my-2 dropdown-divider"></li>
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="py-2 dropdown-item text-danger small fw-bold rounded-3 bg-danger-subtle">
                                        <i class="bi bi-box-arrow-right me-2"></i> Keluar / Sign Out
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                    @endauth
                </div>
            </header>
