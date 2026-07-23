<aside id="sidebar">

    {{-- LOGO BRAND --}}
    <div class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="text-decoration-none d-flex align-items-center">
            <div class="brand-icon">
                <i class="bi bi-box-seam"></i>
            </div>
            <div class="brand-text">
                <span class="fw-bolder text-primary fs-5" style="letter-spacing: -0.5px;">Procure</span><span class="text-dark fw-medium fs-5">App</span>
            </div>
        </a>
    </div>

    {{-- DAFTAR MENU --}}
    <div class="sidebar-nav">

        {{-- 1. OVERVIEW --}}
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Overview">
            <i class="bi bi-grid-1x2-fill menu-icon text-primary"></i>
            <span class="menu-text">Overview</span>
        </a>

        {{-- 2. MASTER DATA --}}
        @canany(['view_companies', 'view_vendors', 'view_inventory', 'manage_items'])
        <div class="sidebar-dropdown">
            <a href="#menuMaster" class="sidebar-link {{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'uoms.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'uoms.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Master Data">
                <i class="bi bi-database-fill menu-icon text-info"></i>
                <span class="menu-text">Master Data</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'uoms.*') ? 'show' : '' }}" id="menuMaster">
                @can('manage_items')
                    <a href="{{ route('items.index') }}" class="sidebar-sublink {{ request()->routeIs('items.*') ? 'active' : '' }}">Master Barang & Jasa</a>
                @endcan
                @can('view_inventory')
                    <a href="{{ route('uoms.index') }}" class="sidebar-sublink {{ request()->routeIs('uoms.*') ? 'active' : '' }}">Master Satuan (UOM)</a>
                @endcan
                @can('view_companies')
                    <a href="{{ route('companies.index') }}" class="sidebar-sublink {{ request()->routeIs('companies.*') ? 'active' : '' }}">Master Perusahaan (PT)</a>
                @endcan
                @can('view_vendors')
                    <a href="{{ route('vendors.index') }}" class="sidebar-sublink {{ request()->routeIs('vendors.*') ? 'active' : '' }}">Master Vendor / Supplier</a>
                @endcan
                @can('view_inventory')
                    <a href="{{ route('warehouses.index') }}" class="sidebar-sublink {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">Master Gudang</a>
                @endcan
            </div>
        </div>
        @endcanany

        <div class="sidebar-heading">Procurement & Logistik</div>

        {{-- 3. PURCHASING --}}
        @canany(['view_pr', 'view_po'])
        <div class="sidebar-dropdown">
            <a href="#menuPurchasing" class="sidebar-link {{ request()->routeIs('pr.*', 'po.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('pr.*', 'po.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Purchasing">
                <i class="bi bi-cart-fill menu-icon text-success"></i>
                <span class="menu-text">Purchasing</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('pr.*', 'po.*') ? 'show' : '' }}" id="menuPurchasing">
                @can('view_pr')
                    <a href="{{ route('pr.index') }}" class="sidebar-sublink {{ request()->routeIs('pr.*') ? 'active' : '' }}">Purchase Requisitions</a>
                @endcan
                @can('view_po')
                    <a href="{{ route('po.index') }}" class="sidebar-sublink {{ request()->routeIs('po.*') ? 'active' : '' }}">Purchase Orders</a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- 4. RECEIVING --}}
        @can('view_gr')
        <a href="{{ route('gr.index') }}" class="sidebar-link {{ request()->routeIs('gr.*') ? 'active' : '' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Receiving (GR)">
            <i class="bi bi-box-seam-fill menu-icon text-primary"></i>
            <span class="menu-text">Receiving (GR)</span>
        </a>
        @endcan

        {{-- 5. WAREHOUSE --}}
        @canany(['view_inventory', 'manage_gi'])
        <div class="sidebar-dropdown">
            <a href="#menuWarehouse" class="sidebar-link {{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.*', 'rtv.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.*', 'rtv.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Warehouse">
                <i class="bi bi-houses-fill menu-icon text-warning"></i>
                <span class="menu-text">Warehouse</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.*', 'rtv.*') ? 'show' : '' }}" id="menuWarehouse">
                @can('view_inventory')
                    <a href="{{ route('inventory.index') }}" class="sidebar-sublink {{ request()->routeIs('inventory.*') ? 'active' : '' }}">Ringkasan Stok Barang</a>
                @endcan
                @can('manage_gi')
                    <a href="{{ route('goods-issues.index') }}" class="sidebar-sublink {{ request()->routeIs('goods-issues.*') ? 'active' : '' }}">Pengeluaran Barang</a>
                    <a href="{{ route('stock-transfers.index') }}" class="sidebar-sublink {{ request()->routeIs('stock-transfers.*') ? 'active' : '' }}">Mutasi Antar Gudang</a>
                    <a href="{{ route('goods-issue-returns.index') }}" class="sidebar-sublink {{ request()->routeIs('goods-issue-returns.*') ? 'active' : '' }}">Retur Barang (Masuk)</a>
                    <a href="{{ route('rtv.index') }}" class="sidebar-sublink {{ request()->routeIs('rtv.*') ? 'active' : '' }}">Retur ke Vendor (RTV)</a>
                    <a href="{{ route('employee-inventories.index') }}" class="sidebar-sublink {{ request()->routeIs('employee-inventories.*') ? 'active' : '' }}">Inventaris Karyawan</a>
                    <a href="{{ route('stock-adjustments.index') }}" class="sidebar-sublink {{ request()->routeIs('stock-adjustments.*') ? 'active' : '' }}">Penyesuaian Stok</a>
                    <a href="{{ route('stock-opnames.index') }}" class="sidebar-sublink {{ request()->routeIs('stock-opnames.*') ? 'active' : '' }}">Audit & Stock Opname</a>
                @endcan
            </div>
        </div>
        @endcanany

        {{-- 6. FIXED ASSETS --}}
        @can('view_assets')
        <div class="sidebar-dropdown">
            <a href="#menuAssets" class="sidebar-link {{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Fixed Assets">
                <i class="bi bi-building-fill-gear menu-icon text-danger"></i>
                <span class="menu-text">Fixed Assets</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'show' : '' }}" id="menuAssets">
                <a href="{{ route('fixed-assets.master_list') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.master_list') ? 'active' : '' }}">Master Data Aset Aktif</a>
                <a href="{{ route('fixed-assets.transactions') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.transactions') ? 'active' : '' }}">Transaksi & Retur Aset</a>
                <a href="{{ route('asset-capitalizations.index') }}" class="sidebar-sublink {{ request()->routeIs('asset-capitalizations.*') ? 'active' : '' }}">Pengakuan Aset</a>
                <a href="{{ route('fixed-assets.index') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.index') ? 'active' : '' }}">Registrasi / Import Aset</a>
                <a href="{{ route('fixed-assets.import_history') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.import_history') ? 'active' : '' }}">Riwayat Import & Label QR</a>
                <a href="{{ route('fixed-assets.hibah_history') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.hibah_history') ? 'active' : '' }}">Riwayat Hibah Aset</a>
            </div>
        </div>
        @endcan

        <div class="sidebar-heading">Keuangan & Sistem</div>

        {{-- 7. FINANCE --}}
        @canany(['view_invoices', 'view_payments', 'view_bills'])
        <div class="sidebar-dropdown">
            <a href="#menuFinance" class="sidebar-link {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Finance">
                <i class="bi bi-wallet-fill menu-icon text-success"></i>
                <span class="menu-text">Finance</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*') ? 'show' : '' }}" id="menuFinance">
                <a href="{{ route('vendor-invoices.index') }}" class="sidebar-sublink {{ request()->routeIs('vendor-invoices.*') ? 'active' : '' }}">Tagihan Vendor (A/P)</a>
                <a href="{{ route('bills.index') }}" class="sidebar-sublink {{ request()->routeIs('bills.*') ? 'active' : '' }}">Pengajuan Opex</a>
                <a href="{{ route('payments.index') }}" class="sidebar-sublink {{ request()->routeIs('payments.*') ? 'active' : '' }}">Proses Pembayaran</a>
            </div>
        </div>
        @endcanany

        {{-- 8. REPORT --}}
        @can('view_reports')
        <div class="sidebar-dropdown">
            <a href="#menuReport" class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Laporan">
                <i class="bi bi-pie-chart-fill menu-icon text-primary"></i>
                <span class="menu-text">Laporan</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="menuReport">
                <a href="{{ route('reports.finance') }}" class="sidebar-sublink {{ request()->routeIs('reports.finance') ? 'active' : '' }}">Laporan Keuangan</a>
                <a href="{{ route('reports.inventory-valuation') }}" class="sidebar-sublink {{ request()->routeIs('reports.inventory-valuation') ? 'active' : '' }}">Valuasi Persediaan</a>
            </div>
        </div>
        @endcan

        {{-- 9. SETTINGS --}}
        @can('manage_roles')
        <div class="sidebar-dropdown">
            <a href="#menuSettings" class="sidebar-link {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'true' : 'false' }}" data-bs-toggle="tooltip" data-bs-placement="right" title="Pengaturan">
                <i class="bi bi-gear-fill menu-icon text-secondary"></i>
                <span class="menu-text">Pengaturan</span>
                <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'show' : '' }}" id="menuSettings">
                <a href="{{ route('departments.index') }}" class="sidebar-sublink {{ request()->routeIs('departments.*') ? 'active' : '' }}">Departments Management</a>
                <a href="{{ route('users.index') }}" class="sidebar-sublink {{ request()->routeIs('users.*') ? 'active' : '' }}">User Management</a>
                <a href="{{ route('roles.index') }}" class="sidebar-sublink {{ request()->routeIs('roles.*') ? 'active' : '' }}">Role & Permissions</a>
                <a href="{{ route('workflows.index') }}" class="sidebar-sublink {{ request()->routeIs('workflows.*') ? 'active' : '' }}">Workflows Approval</a>
                <a href="{{ route('document-types.index') }}" class="sidebar-sublink {{ request()->routeIs('document-types.*') ? 'active' : '' }}">Document Types</a>
            </div>
        </div>
        @endcan

    </div>
</aside>
