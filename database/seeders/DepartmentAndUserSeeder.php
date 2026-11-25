<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Models\JobTitle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DepartmentAndUserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $defaultPassword = Hash::make('123456');

        // =========================================================================
        // 1. PASTIKAN ROLE DASAR TERSEDIA
        // =========================================================================
        $roles = [
            'Super Admin',
            'Direktur',
            'General Manager',
            'Manager',
            'Supervisor',
            'Staff',
            'Finance',
            'Purchasing',
            'Gudang',
            'Opex & Asset',
        ];
        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // =========================================================================
        // 2. MASTER DATA JABATAN (JOB TITLES) DARI EXCEL
        // =========================================================================
        $standardTitles = [
            'Director',
            'General Manager',
            'Senior Manager',
            'Manager',
            'Assistant Manager',
            'Supervisor',
            'Senior Staff',
            'Staff',
            'Non-Staff',
        ];
        foreach ($standardTitles as $st) {
            JobTitle::firstOrCreate(['name' => $st], ['is_active' => true]);
        }

        // =========================================================================
        // 3. MASTER DATA DEPARTEMEN & SUB DEPARTEMEN
        // =========================================================================
        $deptData = [
            // Departemen Utama dari Excel HR terbaru
            ['name' => 'HR & GA',                     'code' => 'HR'],
            ['name' => 'IT & WEB',                    'code' => 'IT'],
            ['name' => 'ACCOUNTING',                  'code' => 'ACC'],
            ['name' => 'EDITORIAL',                   'code' => 'ED'],
            ['name' => 'MARKETING',                   'code' => 'MA'],
            ['name' => 'SALES',                       'code' => 'SLS'],
            ['name' => 'PRODUCTION',                  'code' => 'PR'],
            ['name' => 'PHOTOGRAPHER & VIDEOGRAPHER', 'code' => 'PH'],

            // Sub-Departemen & Departemen Spesifik Eksisting (tetap dipertahankan agar relasi lama aman)
            ['name' => 'CIRCULATION & DISTRIBUTION',  'code' => 'DI'],
            ['name' => 'ART DIRECTOR',                'code' => 'ARD'],
            ['name' => 'GRAPHIC DESIGNER',            'code' => 'GRD'],
            ['name' => 'Art. Dir. Prestige',          'code' => 'ARP'],
            ['name' => 'Accounting & Finance',        'code' => 'FA'],
            ['name' => 'Bintaro',                     'code' => 'BIN'],
            ['name' => 'Daman',                       'code' => 'DA'],
            ['name' => 'Destinasian',                 'code' => 'DE'],
            ['name' => 'Digital Imaging',             'code' => 'DIM'],
            ['name' => 'Editorial Fashion',           'code' => 'EDF'],
            ['name' => 'Editor videographer',         'code' => 'EDV'],
            ['name' => 'F&A Sr Manager',              'code' => 'FAM'],
            ['name' => 'Fashion Stylist',             'code' => 'FS'],
            ['name' => 'Online Editor DAI',           'code' => 'ONEDAI'],
            ['name' => 'Online Writer DAI',           'code' => 'ONWDAI'],
            ['name' => 'Online Writer',               'code' => 'ONW'],
            ['name' => 'Personal Assistant',          'code' => 'PA'],
            ['name' => 'Senior Editor',               'code' => 'SE'],
            ['name' => 'Social Media',                'code' => 'SOSMED'],
            ['name' => 'Tax',                         'code' => 'TAX'],
            ['name' => 'Videographer',                'code' => 'VI'],
            ['name' => 'Writer',                      'code' => 'WR'],
        ];

        $departmentsByCode = [];
        $departmentsByName = [];
        foreach ($deptData as $d) {
            $deptModel = Department::updateOrCreate(
                ['code' => $d['code']],
                ['name' => $d['name'], 'is_active' => true]
            );
            $departmentsByCode[$d['code']] = $deptModel;
            $departmentsByName[strtoupper($d['name'])] = $deptModel;
        }

        // =========================================================================
        // 4. AMBIL DATA COMPANY RELEVAN
        // =========================================================================
        $destinAsian = Company::where('code', 'DM')
            ->orWhere('name', 'like', '%DAMA%')
            ->orWhere('is_head_office', true)
            ->first();
        $defaultCompanyId = $destinAsian ? $destinAsian->id : 1;

        $userCols = Schema::getColumnListing('users');

        // =========================================================================
        // 5. DATA LENGKAP 79 KARYAWAN DARI EXCEL TERBARU
        // Format: [pin, no_induk, nama, departemen, sub_departemen, jabatan, grup]
        // =========================================================================
        $employees = [
            ['1',    '1',          'Ronald Liem',                        '-',                           '-',                          'Director',          'NORMAL 2'],
            ['10',   '606010039',  'Darsono',                            'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['100',  '107020005',  'Atiet Soeharto',                     '-',                           '-',                          'Manager',           'NORMAL 2'],
            ['101',  '307090008',  'Sunaryo',                            'PHOTOGRAPHER & VIDEOGRAPHER', '-',                          'Staff',             'NORMAL 2'],
            ['102',  '407190016',  'Syarifudin',                         'MARKETING',                   'CIRCULATION & DISTRIBUTION', 'Staff',             'NORMAL 2'],
            ['1020', '1504060402', 'R. Diana Purnama Putri',             'ACCOUNTING',                  '-',                          'Non-Staff',         'NORMAL 2'],
            ['118',  '1311071336', 'Sibli Azis',                         'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['128',  '1403120348', 'Liana Phiong',                       'ACCOUNTING',                  '-',                          'Senior Staff',      'NORMAL 2'],
            ['14',   '707020070',  'Jatmiko',                            'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['15',   '710013078',  'Kusdiana bn Suryana',                'PRODUCTION',                  '-',                          'Senior Manager',    'NORMAL 2'],
            ['153',  '1408047375', 'Muhammad Rafli Hasani',              'EDITORIAL',                   'ART DIRECTOR',               'Manager',           'NORMAL 2'],
            ['16',   '712030081',  'Resliana Yosephine',                 'ACCOUNTING',                  '-',                          'Manager',           'NORMAL 2'],
            ['173',  '1501057393', 'Darwin Chang',                       'MARKETING',                   '-',                          'Manager',           'NORMAL 2'],
            ['190',  '1505251412', 'Dervina',                            'SALES',                       '-',                          'Senior Staff',      'NORMAL 2'],
            ['191',  '1505257413', 'Yopi Hadi',                          'SALES',                       '-',                          'Manager',           'NORMAL 2'],
            ['192',  '1506037414', 'Dony Haryadi',                       'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['2',    '2',          'Fransisca Liem',                     '-',                           '-',                          'Director',          'NORMAL 2'],
            ['2002', '2201010002', 'Meutia Mahardhika (Khika)',          'SALES',                       '-',                          'Director',          'NORMAL 2'],
            ['22',   '1005031148', 'Lord Raditya Basuki (Radit)',        'SALES',                       '-',                          'Manager',           'NORMAL 2'],
            ['227',  '1605230449', 'James Jia Chang Louie',              'EDITORIAL',                   '-',                          'Manager',           'NORMAL 2'],
            ['25',   '1008161163', 'Muhamad Ridwan',                     'ACCOUNTING',                  '-',                          'Manager',           'NORMAL 2'],
            ['256',  '1708077476', 'Riga Adithya Ramadhan',              'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['258',  '1709180478', 'Imam Subahtiar',                     'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['26',   '1011013165', 'Dwi Hartanto',                       'SALES',                       '-',                          'Senior Manager',    'NORMAL 2'],
            ['290',  '1805025504', 'Dikri Ilhami',                       'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['293',  '1809030293', 'Aditya Wisnu Yaniarso',              'PHOTOGRAPHER & VIDEOGRAPHER', '-',                          'Staff',             'NORMAL 2'],
            ['3',    '3',          'Robin Liem',                         '-',                           '-',                          'Director',          'GROUP NORMAL'],
            ['309',  '1902187524', 'Anindya Rizky Devy',                 'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['319',  '1906101532', 'Irfana Thahirah Putri',              'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['32',   '1104253190', 'Arlen S. Adam',                      'EDITORIAL',                   'GRAPHIC DESIGNER',           'Senior Staff',      'NORMAL 2'],
            ['351',  '2102161555', 'Geoffrey Mohammad Ihsan',            'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['355',  '2106027559', 'Hanifan',                            'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['360',  '2201101564', 'Agus Pramono',                       'EDITORIAL',                   'GRAPHIC DESIGNER',           'Senior Staff',      'NORMAL 2'],
            ['364',  '2203141568', 'Deswita Reghita Cahyani',            'SALES',                       '-',                          'Staff',             'NORMAL 2'],
            ['368',  '2206201571', 'Arie Kusumastuti',                   'HR & GA',                     '-',                          'Manager',           'NORMAL 2'],
            ['374',  '2301091577', 'Jessica Estherina',                  'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['375',  '2301181578', 'Refael Louis Elnathan Koetin',       'ACCOUNTING',                  '-',                          'Manager',           'NORMAL 2'],
            ['377',  '2303017580', 'Joezer Methusael Mandagi',           'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['378',  '2303067581', 'Febry Ramadhan',                     'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['38',   '1105184203', 'Joe Sabarto',                        'PHOTOGRAPHER & VIDEOGRAPHER', '-',                          'Staff',             'NORMAL 2'],
            ['382',  '2305011585', 'Salvia Irani',                       'SALES',                       '-',                          'General Manager',   'NORMAL 2'],
            ['383',  '2305227586', 'Elsabella Sohilait',                 'ACCOUNTING',                  '-',                          'Supervisor',        'NORMAL 2'],
            ['384',  '2306011587', 'Brad Michael Homes',                 'EDITORIAL',                   '-',                          'General Manager',   'NORMAL 2'],
            ['387',  '2308147590', 'Diar Putra Matahari',                'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['392',  '2312010594', 'Eqqi Syahputra',                     'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['396',  '2404160596', 'Rinawati',                           'HR & GA',                     '-',                          'Staff',             'NORMAL 2'],
            ['397',  '2404220597', 'Elfrida Chintya Puri',               'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['398',  '2404220598', 'Myranda Fae',                        'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['403',  '2407010603', 'Gabriel Putra Advent',               'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['404',  '2408261604', 'Rizal Kurniawan',                    'HR & GA',                     '-',                          'Staff',             'NORMAL 2'],
            ['405',  '2410010605', 'Fataya Niken Larassati',             'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['409',  '2411188032', 'Dykie Madhianto Khairi',             'SALES',                       '-',                          'Assistant Manager', 'NORMAL 2'],
            ['411',  '2501030608', 'Idwan',                              'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['412',  '2502037609', 'Muhamad Nur Wahid',                  'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['413',  '2503110610', 'Budi Utomo',                         'PRODUCTION',                  '-',                          'Supervisor',        'NORMAL 2'],
            ['414',  '2504077611', 'Guspariaman Warasi',                 'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['415',  '2505138034', 'Wafiq Giotama',                      'SALES',                       '-',                          'Manager',           'NORMAL 2'],
            ['416',  '2505191612', 'Danu Marwanto',                      'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['418',  '2507141614', 'Sudiksha Ajit Lachman',              'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['419',  '2509080615', 'Shahnaz Tsaniyah',                   'MARKETING',                   '-',                          'Staff',             'NORMAL 2'],
            ['421',  '2509098037', 'Akhmad Zahran Nurrafi',              'SALES',                       '-',                          'Manager',           'NORMAL 2'],
            ['422',  '2509157617', 'Muklas Adi Saputra',                 'ACCOUNTING',                  '-',                          'Manager',           'NORMAL 2'],
            ['423',  '2509228038', 'Angela Gina Rizky Utami',            'MARKETING',                   '-',                          'Assistant Manager', 'NORMAL 2'],
            ['424',  '2511171618', 'Nabila Nadazera',                    'ACCOUNTING',                  '-',                          'Supervisor',        'NORMAL 2'],
            ['425',  '2512018039', 'Triyasa Panji Bagaskoro',            'MARKETING',                   '-',                          'Staff',             'NORMAL 2'],
            ['426',  '2512101619', 'Putri Lathifah',                     'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['428',  '2601061621', 'Ahmad Jarkasyi',                     'HR & GA',                     '-',                          'Non-Staff',         'NORMAL 2'],
            ['430',  '2602090623', 'Vitalis Kusumo Radityo',             'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['431',  '2606020624', 'Hanindhira',                         'EDITORIAL',                   '-',                          'Manager',           'NORMAL 2'],
            ['432',  '2404014029', 'Ivonunda Nurmahadita Kusuma Nindya', 'SALES',                       '-',                          'Supervisor',        'NORMAL 2'],
            ['433',  '2608030625', 'Haikal Afifsyah',                    'IT & WEB',                    '-',                          'Staff',             'NORMAL 2'],
            ['434',  '2609011627', 'Petralea Mabelle Adila Masandhi',    'ACCOUNTING',                  '-',                          'Staff',             'NORMAL 2'],
            ['435',  '2610017628', 'Daniya Sekarning Ayu',               'EDITORIAL',                   '-',                          'Staff',             'NORMAL 2'],
            ['436',  '2610197630', 'Albert Ginting',                     'MARKETING',                   '-',                          'Assistant Manager', 'NORMAL 2'],
            ['437',  '2611021631', 'Peter Zewet',                        'ACCOUNTING',                  '-',                          'Manager',           'NORMAL 2'],
            ['46',   '1110034228', 'Anwar Musadad',                      'MARKETING',                   'CIRCULATION & DISTRIBUTION', 'Staff',             'NORMAL 2'],
            ['507',  '1807027507', 'Raden Haryo Suryadi',                'PHOTOGRAPHER & VIDEOGRAPHER', '-',                          'Staff',             'NORMAL 2'],
            ['620',  '2512150620', 'Fidelis Ilham Cesardianto',          'PHOTOGRAPHER & VIDEOGRAPHER', '-',                          'Staff',             'NORMAL 2'],
            ['7',    '411200019',  'Elvida Nataya',                      'SALES',                       '-',                          'Senior Manager',    'NORMAL 2'],
            ['77',   '1303040299', 'Wahyudi',                            'MARKETING',                   'CIRCULATION & DISTRIBUTION', 'Staff',             'NORMAL 2'],
            ['99',   '105050004',  'Christopher Hill',                   'EDITORIAL',                   '-',                          'General Manager',   'GROUP NORMAL'],
        ];

        $usedEmails = ['admin@app.com'];

        foreach ($employees as $emp) {
            [$pin, $noInduk, $fullName, $deptName, $subDept, $jabatan, $grup] = $emp;

            // 1. Cari ID Departemen (utama dari kolom DEPARTEMEN)
            $deptUpper = strtoupper(trim($deptName));
            $deptModel = ($deptUpper !== '-' && isset($departmentsByName[$deptUpper]))
                ? $departmentsByName[$deptUpper]
                : null;
            $deptId = $deptModel ? $deptModel->id : null;
            $deptCode = $deptModel ? strtolower($deptModel->code) : '';

            // 2. Buat email unik (format bersih berbasis nama + kode dept jika perlu)
            $cleanForEmail = trim(preg_replace('/\([^)]*\)/', '', $fullName));
            $baseSlug      = strtolower(Str::slug($cleanForEmail, '.'));
            if ($baseSlug === '') {
                $baseSlug = 'user.' . $pin;
            }
            $email = $baseSlug . '@app.com';
            if (in_array($email, $usedEmails)) {
                $suffix = $deptCode !== '' ? $deptCode : $pin;
                $email = $baseSlug . '.' . $suffix . '@app.com';
            }
            $usedEmails[] = $email;

            // 3. Daftarkan Jabatan ke Master JobTitle (sertakan Sub Departemen jika ada)
            $generatedTitleName = ($subDept !== '-' && $subDept !== '')
                ? "{$jabatan} - {$subDept}"
                : $jabatan;
            JobTitle::firstOrCreate(['name' => $generatedTitleName], ['is_active' => true]);

            // 4. Siapkan payload kolom user secara dinamis sesuai skema DB
            $userData = [
                'name'          => $fullName,
                'password'      => $defaultPassword,
                'company_id'    => $defaultCompanyId,
                'department_id' => $deptId,
                'job_title'     => $generatedTitleName,
                'is_active'     => true,
            ];

            if (in_array('pin', $userCols))            $userData['pin']            = $pin;
            if (in_array('nik', $userCols))            $userData['nik']            = $noInduk;
            if (in_array('employee_id', $userCols))    $userData['employee_id']    = $noInduk;
            if (in_array('employee_code', $userCols))  $userData['employee_code']  = $noInduk;
            if (in_array('sub_department', $userCols)) $userData['sub_department'] = ($subDept !== '-') ? $subDept : null;
            if (in_array('work_group', $userCols))     $userData['work_group']     = $grup;
            if (in_array('position', $userCols))       $userData['position']       = $generatedTitleName;

            // 5. Cari apakah user sudah pernah ada (agar tidak duplikat dan relasi aset tetap utuh)
            $existingUser = null;
            if (in_array('nik', $userCols)) {
                $existingUser = User::where('nik', $noInduk)->first();
            } elseif (in_array('employee_id', $userCols)) {
                $existingUser = User::where('employee_id', $noInduk)->first();
            }
            if (!$existingUser) {
                $existingUser = User::where('name', $fullName)->orWhere('email', $email)->first();
            }

            if ($existingUser) {
                $existingUser->update($userData);
                $user = $existingUser;
            } else {
                $userData['email'] = $email;
                $user = User::create($userData);
            }

            // 6. Penetapan Role Spatie otomatis berdasarkan Jabatan & Departemen
            $assignedRoles = [];
            $jabatanUpper  = strtoupper(trim($jabatan));

            if ($jabatanUpper === 'DIRECTOR') {
                $assignedRoles[] = 'Direktur';
            } elseif (in_array($jabatanUpper, ['GENERAL MANAGER', 'SENIOR MANAGER', 'MANAGER', 'ASSISTANT MANAGER'])) {
                $assignedRoles[] = 'Manager';
            } elseif ($jabatanUpper === 'SUPERVISOR') {
                $assignedRoles[] = 'Supervisor';
            } else {
                $assignedRoles[] = 'Staff';
            }

            if ($deptUpper === 'ACCOUNTING') {
                $assignedRoles[] = 'Finance';
            }
            if (in_array($deptUpper, ['HR & GA', 'IT & WEB'])) {
                $assignedRoles[] = 'Opex & Asset';
            }

            $user->syncRoles(array_unique($assignedRoles));
        }
    }
}
