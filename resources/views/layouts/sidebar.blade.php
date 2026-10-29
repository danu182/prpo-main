<aside id="sidebar">
    <div class="sidebar-header">
        <a href="{{ route('dashboard') }}" class="text-decoration-none d-flex align-items-center">
            <div class="brand-icon"><i class="bi bi-box-seam"></i></div>
            <div class="brand-text">
                <span class="fw-bolder text-primary fs-5" style="letter-spacing: -0.5px;">Procure</span><span class="text-dark fw-medium fs-5">App</span>
            </div>
        </a>
    </div>

    <div class="sidebar-nav">
        {{-- 1. OVERVIEW --}}
        <a href="{{ route('dashboard') }}" class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-grid-1x2-fill menu-icon text-primary"></i> <span class="menu-text">Overview</span>
        </a>

        {{-- 2. MASTER DATA --}}
        @can('menu_master_data')
        <div class="sidebar-dropdown">
            <a href="#menuMaster" class="sidebar-link {{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'categories.*', 'uoms.*', 'asset-categories.*', 'departments.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'categories.*', 'uoms.*', 'asset-categories.*', 'departments.*') ? 'true' : 'false' }}">
                <i class="bi bi-database-fill menu-icon text-info"></i> <span class="menu-text">Master Data</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('companies.*', 'vendors.*', 'warehouses.*', 'items.*', 'categories.*', 'uoms.*', 'asset-categories.*', 'departments.*') ? 'show' : '' }}" id="menuMaster">

                @can('view_items')
                    <a href="{{ route('items.index') }}" class="sidebar-sublink {{ request()->routeIs('items.*') ? 'active' : '' }}">Master Barang & Jasa</a>
                    <a href="{{ url('/categories') }}" class="sidebar-sublink {{ request()->is('categories*') ? 'active' : '' }}">Master Kategori</a>
                @endcan

                @can('view_inventory')
                    <a href="{{ route('uoms.index') }}" class="sidebar-sublink {{ request()->routeIs('uoms.*') ? 'active' : '' }}">Master Satuan (UOM)</a>
                    <a href="{{ route('warehouses.index') }}" class="sidebar-sublink {{ request()->routeIs('warehouses.*') ? 'active' : '' }}">Master Gudang</a>
                @endcan

                @can('view_assets_master')
                    <a href="{{ route('asset-categories.index') }}" class="sidebar-sublink {{ request()->routeIs('asset-categories.*') ? 'active' : '' }}">Master Kategori Aset</a>
                @endcan

                @can('view_companies')
                    <a href="{{ route('companies.index') }}" class="sidebar-sublink {{ request()->routeIs('companies.*') ? 'active' : '' }}">Master Perusahaan (PT)</a>
                    <a href="{{ route('departments.index') }}" class="sidebar-sublink {{ request()->routeIs('departments.*') ? 'active' : '' }}">Master Departemen</a>
                @endcan

                @can('view_vendors')
                    <a href="{{ route('vendors.index') }}" class="sidebar-sublink {{ request()->routeIs('vendors.*') ? 'active' : '' }}">Master Vendor / Supplier</a>
                @endcan
            </div>
        </div>
        @endcan

        <div class="sidebar-heading">Procurement & Logistik</div>

        {{-- 3. PURCHASING --}}
        @can('menu_purchasing')
        <div class="sidebar-dropdown">
            <a href="#menuPurchasing" class="sidebar-link {{ request()->routeIs('pr.*', 'po.*', 'inventory.purchase-history', 'inventory.price-analysis') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('pr.*', 'po.*', 'inventory.purchase-history', 'inventory.price-analysis') ? 'true' : 'false' }}">
                <i class="bi bi-cart-fill menu-icon text-success"></i> <span class="menu-text">Purchasing</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('pr.*', 'po.*', 'inventory.purchase-history', 'inventory.price-analysis') ? 'show' : '' }}" id="menuPurchasing">
                @can('view_pr')
                    <a href="{{ route('pr.index') }}" class="sidebar-sublink {{ request()->routeIs('pr.*') ? 'active' : '' }}">Purchase Requisitions</a>
                @endcan
                @can('view_po')
                    <a href="{{ route('po.index') }}" class="sidebar-sublink {{ request()->routeIs('po.*') ? 'active' : '' }}">Purchase Orders</a>
                @endcan
                @can('view_purchasing_reports')
                    <a href="{{ route('inventory.price-analysis') }}" class="sidebar-sublink {{ request()->routeIs('inventory.price-analysis') ? 'active' : '' }}">Analisis Harga Pembelian</a>
                    <a href="{{ route('inventory.purchase-history') }}" class="sidebar-sublink {{ request()->routeIs('inventory.purchase-history') ? 'active' : '' }}">Riwayat Harga Beli (PO)</a>
                @endcan
            </div>
        </div>
        @endcan

        {{-- 4. RECEIVING --}}
        @can('menu_receiving')
        <a href="{{ route('gr.index') }}" class="sidebar-link {{ request()->routeIs('gr.*') ? 'active' : '' }}">
            <i class="bi bi-box-seam-fill menu-icon text-primary"></i> <span class="menu-text">Receiving (GR)</span>
        </a>
        @endcan

        {{-- 5. WAREHOUSE & INVENTORY --}}
        @can('menu_warehouse')
        <div class="sidebar-dropdown">
            <a href="#menuWarehouse" class="sidebar-link {{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.index', 'inventory.stock-as-of', 'rtv.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.index', 'inventory.stock-as-of', 'rtv.*') ? 'true' : 'false' }}">
                <i class="bi bi-houses-fill menu-icon text-warning"></i> <span class="menu-text">Warehouse</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('goods-issues.*', 'goods-issue-returns.*', 'stock-transfers.*', 'employee-inventories.*', 'stock-adjustments.*', 'stock-opnames.*', 'inventory.index', 'inventory.stock-as-of', 'rtv.*') ? 'show' : '' }}" id="menuWarehouse">
                @can('view_inventory')
                    <a href="{{ route('inventory.index') }}" class="sidebar-sublink {{ request()->routeIs('inventory.index') ? 'active' : '' }}">Ringkasan Stok Barang</a>
                    <a href="{{ route('inventory.stock-as-of') }}" class="sidebar-sublink {{ request()->routeIs('inventory.stock-as-of') ? 'active' : '' }}">Laporan Mutasi Stok (Qty)</a>
                @endcan
                @can('manage_inventory')
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
        @endcan

        {{-- 6. FIXED ASSETS --}}
        @can('menu_assets')
        <div class="sidebar-dropdown">
            <a href="#menuAssets" class="sidebar-link {{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'true' : 'false' }}">
                <i class="bi bi-building-fill-gear menu-icon text-danger"></i> <span class="menu-text">Fixed Assets</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('fixed-assets.*', 'asset-capitalizations.*', 'assets.*') ? 'show' : '' }}" id="menuAssets">
                @can('view_assets')
                    <a href="{{ route('fixed-assets.master_list') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.master_list') ? 'active' : '' }}">Master Data Aset Aktif</a>
                    <a href="{{ route('fixed-assets.transactions') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.transactions') ? 'active' : '' }}">Transaksi & Retur Aset</a>
                    <a href="{{ route('asset-capitalizations.index') }}" class="sidebar-sublink {{ request()->routeIs('asset-capitalizations.*') ? 'active' : '' }}">Pengakuan Aset</a>
                    <a href="{{ route('fixed-assets.index') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.index') ? 'active' : '' }}">Registrasi / Import Aset</a>
                    <a href="{{ route('fixed-assets.import_history') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.import_history') ? 'active' : '' }}">Riwayat Import & Label QR</a>
                    <a href="{{ route('fixed-assets.hibah_history') }}" class="sidebar-sublink {{ request()->routeIs('fixed-assets.hibah_history') ? 'active' : '' }}">Riwayat Hibah Aset</a>
                @endcan
            </div>
        </div>
        @endcan

        <div class="sidebar-heading">Keuangan & Sistem</div>

        {{-- 7. FINANCE --}}
        @can('menu_finance')
        <div class="sidebar-dropdown">
            <a href="#menuFinance" class="sidebar-link {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*', 'inventory.valuation') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*', 'inventory.valuation') ? 'true' : 'false' }}">
                <i class="bi bi-wallet-fill menu-icon text-success"></i> <span class="menu-text">Finance</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('payments.*', 'vendor-invoices.*', 'bills.*', 'inventory.valuation') ? 'show' : '' }}" id="menuFinance">
                @can('view_invoices')
                    <a href="{{ route('vendor-invoices.index') }}" class="sidebar-sublink {{ request()->routeIs('vendor-invoices.*') ? 'active' : '' }}">Tagihan Vendor (A/P)</a>
                @endcan
                @can('view_bills')
                    <a href="{{ route('bills.index') }}" class="sidebar-sublink {{ request()->routeIs('bills.*') ? 'active' : '' }}">Pengajuan Opex</a>
                @endcan
                @can('view_payments')
                    <a href="{{ route('payments.index') }}" class="sidebar-sublink {{ request()->routeIs('payments.*') ? 'active' : '' }}">Proses Pembayaran</a>
                @endcan
                @can('view_inventory_valuation')
                    <a href="{{ route('inventory.valuation') }}" class="sidebar-sublink {{ request()->routeIs('inventory.valuation') ? 'active' : '' }}">Valuasi Stok (Qty & Rp)</a>
                @endcan
            </div>
        </div>
        @endcan

        {{-- 8. REPORT CENTER --}}
        @can('menu_reports')
        <div class="sidebar-dropdown">
            <a href="#menuReport" class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('reports.*') ? 'true' : 'false' }}">
                <i class="bi bi-pie-chart-fill menu-icon text-primary"></i> <span class="menu-text">Laporan</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('reports.*') ? 'show' : '' }}" id="menuReport">
                @can('view_reports')
                    <a href="{{ route('reports.finance') }}" class="sidebar-sublink {{ request()->routeIs('reports.finance') ? 'active' : '' }}">Laporan Keuangan</a>
                    <a href="{{ route('reports.inventory-valuation') }}" class="sidebar-sublink {{ request()->routeIs('reports.inventory-valuation') ? 'active' : '' }}">Valuasi Rata-Rata (WACC)</a>
                    <a href="{{ route('reports.3way') }}" class="sidebar-sublink {{ request()->routeIs('reports.3way') ? 'active' : '' }}">Laporan 3-Way Matching</a>
                @endcan
            </div>
        </div>
        @endcan

        {{-- 9. SETTINGS --}}
        @can('menu_settings')
        <div class="sidebar-dropdown">
            <a href="#menuSettings" class="sidebar-link {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'active' : '' }}" data-bs-toggle="collapse" aria-expanded="{{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'true' : 'false' }}">
                <i class="bi bi-gear-fill menu-icon text-secondary"></i> <span class="menu-text">Pengaturan</span> <i class="bi bi-chevron-down dropdown-chevron"></i>
            </a>
            <div class="collapse {{ request()->routeIs('users.*', 'roles.*', 'workflows.*', 'document-types.*') ? 'show' : '' }}" id="menuSettings">
                @can('manage_roles')
                    <a href="{{ route('users.index') }}" class="sidebar-sublink {{ request()->routeIs('users.*') ? 'active' : '' }}">User Management</a>
                    <a href="{{ route('roles.index') }}" class="sidebar-sublink {{ request()->routeIs('roles.*') ? 'active' : '' }}">Role & Permissions</a>
                    <a href="{{ route('workflows.index') }}" class="sidebar-sublink {{ request()->routeIs('workflows.*') ? 'active' : '' }}">Workflows Approval</a>
                    <a href="{{ route('document-types.index') }}" class="sidebar-sublink {{ request()->routeIs('document-types.*') ? 'active' : '' }}">Document Types</a>
                @endcan
            </div>
        </div>
        @endcan
    </div>
</aside>
