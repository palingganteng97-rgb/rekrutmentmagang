<?php
session_start();

// =========================================================================
// 1. KONFIGURASI WAKTU & KONEKSI DATABASE SERVER
// =========================================================================
date_default_timezone_set('Asia/Jakarta');

$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. LOGIKA CRUD BACKEND - [UPDATE / INSERT] STATUS DARI POP-UP MODAL
// =========================================================================
if (isset($_POST['action']) && $_POST['action'] == 'update_status') {
    $id_tahapan = mysqli_real_escape_string($koneksi, $_POST['id_tahapan']);
    $id_lamaran = mysqli_real_escape_string($koneksi, $_POST['id_lamaran']);
    $status_baru = mysqli_real_escape_string($koneksi, $_POST['status_tahap']);
    $tanggal_sekarang = date('Y-m-d H:i:s');
    $tahapan_id_default = 1; // Solusi Error: 'tahapan_id' doesn't have a default value

    if (!empty($id_tahapan)) {
        // C (Update) - Jika data tahapan sudah ada, lakukan UPDATE
        $stmt = mysqli_prepare($koneksi, "UPDATE lamaran_tahapan SET status = ?, tanggal_mulai = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "sss", $status_baru, $tanggal_sekarang, $id_tahapan);
    } else {
        // C (Create) - Jika belum ada tahapan (pelamar baru), lakukan INSERT dengan menyertakan tahapan_id
        $stmt = mysqli_prepare($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, status, tanggal_mulai, tahapan_id) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "issi", $id_lamaran, $status_baru, $tanggal_sekarang, $tahapan_id_default);
    }
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    header("Location: lamaran_tahapan.php");
    exit();
}

// =========================================================================
// [CRUD - DELETE] SINKRONISASI TOTAL: HAPUS DI TAHAPAN & DATA PELAMAR
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'hapus_tahapan') {
    $id_lamaran_hapus = mysqli_real_escape_string($koneksi, $_GET['id_lamaran']);

    // 1. Hapus terlebih dahulu riwayat tahapan pelamar agar tidak melanggar Foreign Key Constraint
    $stmt1 = mysqli_prepare($koneksi, "DELETE FROM lamaran_tahapan WHERE lamaran_id = ?");
    mysqli_stmt_bind_param($stmt1, "i", $id_lamaran_hapus);
    mysqli_stmt_execute($stmt1);
    mysqli_stmt_close($stmt1);

    // 2. Hapus berkas pendaftaran utama di rekrutmen_lamaran agar data di kedua halaman hilang bersamaan
    $stmt2 = mysqli_prepare($koneksi, "DELETE FROM rekrutmen_lamaran WHERE id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $id_lamaran_hapus);
    mysqli_stmt_execute($stmt2);
    mysqli_stmt_close($stmt2);

    // Refresh halaman untuk melihat hasil perubahan
    header("Location: lamaran_tahapan.php");
    exit();
}


// =========================================================================
// 4. QUERY READ - PEMBACA KARTU LOWONGAN AKTIF
// =========================================================================
$lowongan_kerja = [];
$q_lwn = mysqli_query($koneksi, "SELECT judul_lowongan AS posisi, deskripsi FROM rekrutmen_lowongan ORDER BY id DESC LIMIT 2");
if ($q_lwn) {
    while ($r_lwn = mysqli_fetch_assoc($q_lwn)) {
        $lowongan_kerja[] = $r_lwn;
    }
}

// =========================================================================
// [CRUD - READ] PERBAIKAN LOGIKA: FILTER INTERAKTIF BERDASARKAN KARTU KLIK
// =========================================================================
$filter_lowongan = isset($_GET['lowongan']) ? mysqli_real_escape_string($koneksi, $_GET['lowongan']) : '';

// Query dasar membaca seluruh data lamaran
$sql_progress = "SELECT 
                    lt.id AS id_tahapan,
                    rl.id AS id_lamaran,
                    COALESCE(p.nama_lengkap, 'Pelamar Otomatis') AS nama_pendaftar, 
                    COALESCE(p.nik, '-') AS nik, 
                    COALESCE(low.judul_lowongan, 'dokter umum') AS nama_lowongan,
                    COALESCE(lt.status, 'Pending') AS status_tahap, 
                    COALESCE(lt.tanggal_mulai, rl.created_at) AS tanggal_update
                 FROM rekrutmen_lamaran rl
                 LEFT JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
                 LEFT JOIN pelamar p ON rl.pelamar_id = p.id
                 LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id";

// Jika admin mengklik kartu lowongan atas, tambahkan kondisi WHERE penyaring database
if (!empty($filter_lowongan)) {
    $sql_progress .= " WHERE low.judul_lowongan = '$filter_lowongan'";
}

$sql_progress .= " ORDER BY rl.id DESC";
$query_progress = mysqli_query($koneksi, $sql_progress);

if (!$query_progress) {
    die("Gagal memuat data progress: " . mysqli_error($koneksi));
}

?>

<!-- =========================================================================
     TAHAP 2: STRUKTUR DOKUMEN FRONTEND (DOCTYPE & HEAD STYLE)
     ========================================================================= -->
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lamaran Tahapan Seleksi</title>

    <style>

        .btn-score:hover {
    background-color: #4f46e5 !important;
    color: white !important;
    border-color: #4f46e5 !important;
}

        /* =========================================================================
           1. RESET GLOBAL & BASE STYLE
           ========================================================================= */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        }
        body { 
            background-color: #f0f2f5; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
            color: #475569; 
        }
        
        /* Container Pembungkus Utama Layout Flexbox */
        .dashboard-container { 
            width: 100%; 
            max-width: 1440px; 
            background: #ffffff; 
            border-radius: 32px; 
            box-shadow: 0 20px 40px rgba(0,0,0,0.04); 
            display: flex; 
            min-height: 850px; 
            overflow: hidden; 
        }
        
        /* =========================================================================
           2. NAVIGATION SIDEBAR STYLING (KIRI)
           ========================================================================= */
        .sidebar-left { 
            width: 280px; 
            background: #ffffff; 
            border-right: 1px solid #f1f5f9; 
            padding: 35px; 
            display: flex; 
            flex-direction: column; 
            justify-content: space-between; 
            flex-shrink: 0; 
        }
        .brand-logo { 
            font-size: 22px; 
            font-weight: 800; 
            color: #1e293b; 
            margin-bottom: 45px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .brand-logo span { 
            width: 10px; 
            height: 20px; 
            background: #4f46e5; 
            border-radius: 4px; 
            display: inline-block; 
        }
        .menu-list { 
            display: flex; 
            flex-direction: column; 
            gap: 6px; 
        }
        .menu-item { 
            display: block; 
            padding: 14px 18px; 
            color: #94a3b8; 
            text-decoration: none; 
            border-radius: 16px; 
            font-size: 14px; 
            font-weight: 600; 
            transition: all 0.2s; 
        }
        .menu-item.active { 
            background: #f5f3ff; 
            color: #4f46e5; 
            border-right: 4px solid #4f46e5; 
            font-weight: 700; 
        }
        .menu-item:hover:not(.active) { 
            background: #f8fafc; 
            color: #1e293b; 
        }
        
        /* =========================================================================
           3. MAIN AREA & MASTER CARD COMPONENT
           ========================================================================= */
        .main-content { 
            flex: 1; 
            background: #fbfbfd; 
            padding: 40px 50px; 
            display: flex; 
            flex-direction: column; 
            gap: 32px; 
            overflow-y: auto; 
        }
        .content-header h1 { 
            font-size: 26px; 
            font-weight: 800; 
            color: #1e293b; 
            letter-spacing: -0.5px; 
        }
        
        .section-header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 15px; 
        }
        .section-title { 
            font-size: 16px; 
            font-weight: 800; 
            color: #1e293b; 
        }
        .cards-grid { 
            display: grid; 
            grid-template-columns: repeat(2, 1fr); 
            gap: 20px; 
        }
        .job-card { 
            background: #ffffff; 
            border: 1px solid #f1f5f9; 
            padding: 22px; 
            border-radius: 20px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            box-shadow: 0 4px 10px rgba(0,0,0,0.01); 
        }
        .job-card .qty { 
            font-size: 32px; 
            font-weight: 900; 
            color: #4f46e5; 
            line-height: 1; 
        }
        .job-card .title { 
            font-size: 14px; 
            font-weight: 700; 
            color: #1e293b; 
            margin-top: 6px; 
        }
        .job-card .desc { 
            font-size: 12px; 
            color: #94a3b8; 
            margin-top: 2px; 
        }
        .percentage-ring { 
            width: 48px; 
            height: 48px; 
            border-radius: 50%; 
            border: 4px solid #f1f5f9; 
            border-top-color: #4f46e5; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 11px; 
            font-weight: 800; 
            color: #4f46e5; 
        }

        /* =========================================================================
           4. TABLE DATA & BADGES STYLING (PERBAIKAN WARNA STATUS BARU)
           ========================================================================= */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); 
        }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; 
        }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; 
        }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; 
        }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; 
        }
        
        /* Style Badge Status Kapsul Pastel */
        .badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-align: center; text-transform: uppercase;
        }
        
        /* Pending -> Kuning */
        .badge-pending   { background: #fef3c7 !important; color: #d97706 !important; }
        
        /* Lulus / Diterima -> Hijau */
        .badge-diterima  { background: #dcfce7 !important; color: #15803d !important; }
        
        /* Tidak Lulus / Ditolak -> Merah */
        .badge-ditolak   { background: #fee2e2 !important; color: #b91c1c !important; }

        /* Proses -> Diubah Menjadi Warna Biru */
        .badge-proses    { background: #e0f2fe !important; color: #0369a1 !important; }

        
        /* Skip -> Hitam */
        .badge-skip      { background: #1e293b !important; color: #ffffff !important; }

        .text-empty      { text-align: center; color: #64748b; font-style: italic; padding: 40px 0; }


        /* =========================================================================
           6. FLOATING MODAL POP-UP LAYER
           ========================================================================= */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); 
                   backdrop-filter: blur(5px); justify-content: center; align-items: center; z-index: 999999; 
        }
        .modal-box { background: #ffffff; width: 90%; max-width: 440px; padding: 35px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); animation: modalEfekMasuk 0.25s ease-out; 
        }
        @keyframes modalEfekMasuk { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } 
        }
        .modal-box h2 { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 20px; letter-spacing: -0.5px; 
        }
        .form-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 24px; text-align: left; 
        }
        .form-group label { font-size: 11px; font-weight: 700; color: #94a3b8; 
        }
        .form-control { width: 100%; padding: 12px 16px; border: 1px solid #e2e8f0; border-radius: 12px; font-size: 14px; color: #1e293b; font-weight: 600; background-color: #f8fafc; outline: none; transition: border-color 0.2s; }
        .form-control:focus { border-color: #4f46e5; }
        .modal-buttons { display: flex; justify-content: flex-end; gap: 12px; }
        .btn-primary, .btn-secondary { padding: 12px 22px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: background 0.2s; }
        .btn-primary { background: #4f46e5; color: #ffffff; }.btn-primary:hover { background: #4338ca; }.btn-secondary { background: #f1f5f9; color: #64748b; }
        .btn-secondary:hover { background: #e2e8f0; }
    </style>
</head>
<body>

    <div class="dashboard-container">

       <!-- SIDEBAR MENU KIRI DENGAN CELAH & TOMBOL LOG OUT MERAH PRESISI -->
<aside class="sidebar-left" style="display: flex; flex-direction: column; justify-content: space-between; min-height: 100vh; padding: 35px; background: #ffffff; border-right: 1px solid #f1f5f9; flex-shrink: 0; width: 280px;">
    
    <!-- GRUP ATAS: Navigasi Utama sampai Lowongan Tahapan -->
    <div style="display: flex; flex-direction: column; gap: 6px;">
        <div class="brand-logo" style="font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px;"><span style="width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block;"></span>impozitions</div>
        <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="data_pelamar.php" class="menu-item">Data Pelamar</a>
                    <a href="lamaran_tahapan.php" class="menu-item active">Lamaran Tahapan</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>
        </nav>
    </div>

    <!-- GRUP BAWAH: Menyisakan Celah Kosong di Tengah, Memuat Profil & Tombol Log Out Merah -->
    <div style="margin-top: auto; display: flex; flex-direction: column; gap: 20px; padding-top: 40px;">
        <nav class="menu-list">
        </nav>               
        <!-- TOMBOL LOG OUT DENGAN STYLE KOTAK MERAH ABSOLUT -->
        <a href="logout.php" style="display: block; width: 100%; padding: 14px; background: #ef4444; color: #ffffff !important; text-align: center; border-radius: 16px; font-weight: 700; font-size: 14px; text-decoration: none; border: none; transition: background 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem Admin?')">Log Out</a>
    </div>            
</aside>

        <!-- AREA KONTEN UTAMA MID -->
        <main class="main-content">
            <div class="content-header">
                <h1>Lamaran Tahapan</h1>
            </div>

            <!-- Bagian Lowongan Kerja (Kotak Kuota Master) -->
            <section>
                <div class="section-header">
                    <div class="section-title">Kuota Data Master Lowongan (Klik kartu untuk memfilter tabel)</div>
                    <?php if (!empty($filter_lowongan)) : ?>
                        <!-- Tombol untuk mengembalikan/menampilkan semua data lagi -->
                        <a href="lamaran_tahapan.php" style="font-size: 13px; font-weight: 700; color: #4f46e5; text-decoration: none;">🔄 Tampilkan Semua</a>
                    <?php endif; ?>
                </div>
                <div class="cards-grid">
                    <?php if (!empty($lowongan_kerja)) : ?>
                        <?php foreach ($lowongan_kerja as $lk) : ?>
                            <?php 
                                // Membuat link filter dinamis berdasarkan nama posisi lowongan
                                $nama_posisi = $lk['posisi'] ?? ''; 
                                $is_active_card = ($filter_lowongan === $nama_posisi) ? 'style="border-color: #4f46e5; background: #f5f3ff;"' : '';
                            ?>
                            <a href="lamaran_tahapan.php?lowongan=<?php echo urlencode($nama_posisi); ?>" style="text-decoration: none; color: inherit;">
                                <div class="job-card" <?php echo $is_active_card; ?>>
                                    <div>
                                        <div class="qty">NEW</div>
                                        <div class="title">Posisi: <?php echo htmlspecialchars($nama_posisi); ?></div>
                                        <div class="desc"><?php echo htmlspecialchars($lk['deskripsi'] ?? '-'); ?></div>
                                    </div>
                                    <div class="percentage-ring">NEW</div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p style="color:#94a3b8; font-style:italic; font-size: 14px; width: 100%;">Belum ada kuota data master lowongan aktif.</p>
                    <?php endif; ?>
                </div>
            </section>

            <!-- TABEL PROGRESS SELEKSI -->
            <section>
                <div class="section-header">
                    <!-- Judul dinamis yang mengabarkan status filter aktif saat ini -->
                    <div class="section-title">
                        Progress Rekrutmen Terbaru 
                        <?php echo !empty($filter_lowongan) ? " - Formasi " . htmlspecialchars($filter_lowongan) : ""; ?>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table>
                            <thead>
                                <tr>
                                    <th>NAMA PELAMAR</th>
                                    <th>FORMASI LOWONGAN</th>
                                    <th>TANGGAL UPDATE</th>
                                    <th>STATUS TAHAP</th>
                                    <th style="text-align: center; width: 100px;">AKSI</th> <!-- TAMBAHKAN INI -->
                                </tr>
                            </thead>

<tbody>
<?php // KONDISIONAL PEMBUKA YANG DIKEMBALIKAN AGAR TIDAK SYNTAX ERROR
if ($query_progress && mysqli_num_rows($query_progress) > 0) : ?>
    <?php while ($row = mysqli_fetch_assoc($query_progress)) : ?>
        <?php 
            // 1. Ambil status dari database secara fleksibel dari kolom status_tahap atau status global
            $status_badge = !empty($row['status_tahap']) ? $row['status_tahap'] : ($row['status'] ?? 'Pending'); 
            
            // Standardisasi ke huruf besar untuk akurasi pengecekan class CSS
            $status_cek = strtoupper($status_badge);

            // Jika isi database adalah 'Tolak', paksa tampilan menjadi 'Tidak Lulus'
            if ($status_cek == 'TOLAK' || $status_cek == 'DITOLAK') {
                $status_badge = 'Tidak Lulus';
                $status_cek   = 'TIDAK LULUS';
            }

            // 2. Tentukan kelas warna badge berdasarkan status masing-masing
            $class_badge = 'badge-pending'; // Default: Kuning
            
            if ($status_cek == 'PROSES') { 
                $class_badge = 'badge-proses';     // Abu-abu
            } elseif ($status_cek == 'LULUS' || $status_cek == 'TERIMA' || $status_cek == 'DITERIMA') { 
                $class_badge = 'badge-diterima';   // Hijau
            } elseif ($status_cek == 'TIDAK LULUS') { 
                $class_badge = 'badge-ditolak';    // Merah
            } elseif ($status_cek == 'SKIP' || $status_cek == 'DILEWATI') { 
                $class_badge = 'badge-skip';       // Mengaktifkan kelas CSS khusus tombol lewati Anda
                $status_badge = 'SKIP';            // Memastikan teks tercetak huruf kapital sempurna
            }
            
            $id_lamaran_tahapan = $row['id_tahapan'] ?? ''; 
        ?>
        <tr>
            <td>
                <div class="candidate-name"><?php echo htmlspecialchars($row['nama_pendaftar']); ?></div>
                <div style="font-size: 11px; color: #94a3b8; margin-top: 2px;">NIK: <?php echo htmlspecialchars($row['nik'] ?? '-'); ?></div>
            </td>
            <td><span style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['nama_lowongan']); ?></span></td>
            <td><?php echo !empty($row['tanggal_update']) ? date('d M Y - H:i', strtotime($row['tanggal_update'])) : date('d M Y - H:i'); ?> WIB</td>
            <td>
                <span class="badge <?php echo $class_badge; ?>"><?php echo htmlspecialchars($status_badge); ?></span>
            </td>
            <!-- KOLOM AKSI: TOMBOL NILAI -->
            <td style="text-align: center; white-space: nowrap;">
                <a href="penilaian_tahapan.php?id=<?php echo urlencode($id_lamaran_tahapan); ?>" class="btn-score" title="Beri Nilai Pelamar" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 6px 14px; background-color: #eef2ff; color: #4f46e5; border: 1px solid #c7d2fe; border-radius: 8px; font-weight: 700; font-size: 13px; text-decoration: none; transition: all 0.2s; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                    <svg xmlns="http://w3.org" width="14" height="14" fill="currentColor" class="bi bi-bookmark-star" viewBox="0 0 16 16" style="display: inline-block; vertical-align: middle;">
                        <path d="M7.84 4.1a.5.5 0 0 1 .32 0l1.353.362-.124.484L8 4.584l-1.39.362-.122-.484zM6.6 6.3a.5.5 0 0 0 .117-.168l1-2a.5.5 0 0 0-.834-.464l-1 2A.5.5 0 0 0 6.6 6.3"/>
                        <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v13.5a.5.5 0 0 1-.777.416L8 13.101l-5.223 2.815A.5.5 0 0 1 2 15.5zm2-1a1 1 0 0 0-1 1v12.566l4.723-2.543a.5.5 0 0 1 .554 0L13 14.566V2a1 1 0 0 0-1-1z"/>
                    </svg>
                    <span style="line-height: 1;">Nilai</span>
                </a>
            </td>
        </tr>
    <?php endwhile; ?>
<?php else : ?>
    <tr>
        <td colspan="5" class="text-empty" style="text-align: center; padding: 24px; color: #64748b;">
            <?php echo !empty($filter_lowongan) ? "Belum ada pelamar yang mendaftar pada formasi lowongan " . htmlspecialchars($filter_lowongan) . "." : "Belum ada progress tahapan rekrutmen terbaru saat ini."; ?>
        </td>
    </tr>
<?php endif; ?>
</tbody>

                    </table>
                </div>
            </section>
        </main>
    </div> <!-- Penutup dashboard-container -->

    <!-- STRUKTUR ELEMEN HTML MODAL BOX -->
    <div class="modal-overlay" id="modalEditStatus">
        <div class="modal-box">
            <h2>Ubah Status Tahap</h2>
            <form action="lamaran_tahapan.php" method="POST">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="id_tahapan" id="modal_id_tahapan">
                <input type="hidden" name="id_lamaran" id="modal_id_lamaran">
                
                <div class="form-group">
                    <label>Pilih Status Baru</label>
                    <select name="status_tahap" id="modal_select_status" class="form-control">
                        <option value="Pending">Pending</option>
                        <option value="Proses">Proses</option>
                        <option value="Lulus">Lulus</option>
                        <option value="Tidak Lulus">Tidak Lulus</option>
                        <option value="Skip">Skip</option>
                    </select>
                </div>
                
                <div class="modal-buttons">
                    <button type="button" class="btn-secondary" onclick="tutupModalEdit()">Batal</button>
                    <button type="submit" class="btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LOGIKA PENGENDALI JAVASCRIPT GLOBAL -->
    <script>
        var g_modal = document.getElementById('modalEditStatus');
        var g_inputId = document.getElementById('modal_id_tahapan');
        var g_idLamaran = document.getElementById('modal_id_lamaran');
        var g_selectStatus = document.getElementById('modal_select_status');

        function bukaModalEdit(idTahapan, idLamaran, statusSaatIni) {
            if (g_modal && g_inputId && g_idLamaran && g_selectStatus) {
                g_inputId.value = idTahapan;
                g_idLamaran.value = idLamaran;
                g_selectStatus.value = statusSaatIni;
                g_modal.style.display = 'flex';
            }
        }

        function tutupModalEdit() {
            if (g_modal) { g_modal.style.display = 'none'; }
        }

        window.onclick = function(event) {
            if (event.target == g_modal) { tutupModalEdit(); }
        }
    </script>
</body>
</html>
