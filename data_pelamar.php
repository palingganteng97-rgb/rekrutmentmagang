<?php 
session_start(); 

// 1. PENGATURAN KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// FITUR: LOGIKA PROSES HAPUS DATA LAMARAN (DELETE DATA CONTOH)
// =========================================================================
if (isset($_GET['action']) && $_GET['action'] == 'hapus_lamaran') {
    $lamaran_id_hapus = intval($_GET['lamaran_id']);
    
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=0");
    mysqli_query($koneksi, "DELETE FROM lamaran_tahapan WHERE lamaran_id = $lamaran_id_hapus");
    mysqli_query($koneksi, "DELETE FROM rekrutmen_lamaran WHERE id = $lamaran_id_hapus");
    mysqli_query($koneksi, "SET FOREIGN_KEY_CHECKS=1");
    
    header("Location: data_pelamar.php");
    exit();
}

// =========================================================================
// LOGIKA PROSES UPDATE STATUS SELEKSI OLEH ADMIN (TERIMA / TOLAK / PENDING)
// =========================================================================
if (isset($_POST['update_status_seleksi'])) {
    $lamaran_id = intval($_POST['lamaran_id']);
    $status_aksi = $_POST['status_aksi'];
    
    if ($status_aksi === 'Pending') {
        $q_status = "DELETE FROM lamaran_tahapan WHERE lamaran_id = $lamaran_id";
    } else {
        $status_baru = mysqli_real_escape_string($koneksi, $status_aksi);
        $cek_tahapan = mysqli_query($koneksi, "SELECT id FROM lamaran_tahapan WHERE lamaran_id = $lamaran_id");
        if (mysqli_num_rows($cek_tahapan) > 0) {
            $q_status = "UPDATE lamaran_tahapan SET status = '$status_baru', updated_at = NOW(), petugas_id = 1 WHERE lamaran_id = $lamaran_id";
        } else {
            $q_status = "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, petugas_id, created_at, updated_at) 
                         VALUES ($lamaran_id, 1, NOW(), '$status_baru', 1, NOW(), NOW())";
        }
    }
    
    mysqli_query($koneksi, $q_status);
    header("Location: data_pelamar.php");
    exit();
}

// 2. AMBIL DATA USER SECARA DINAMIS BERDASARKAN SESSION LOGIN
$nama_tampilan = "Administrator";

if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
    $query_user = "SELECT nama FROM users WHERE username = '$username_aktif'";
    $hasil_user = mysqli_query($koneksi, $query_user);
    if ($hasil_user && mysqli_num_rows($hasil_user) > 0) {
        $data_user = mysqli_fetch_assoc($hasil_user);
        $nama_tampilan = $data_user['nama'];
    }
}

// =========================================================================
// 3. QUERY MENAMPILKAN DATA PELAMAR MASUK (VERSI KOMPATIBEL XAMPP)
// =========================================================================

// Cek dan buat kolom status_sosial jika belum ada di database
$cek_sosial = mysqli_query($koneksi, "SHOW COLUMNS FROM `pelamar` LIKE 'status_sosial'");
if (mysqli_num_rows($cek_sosial) == 0) {
    mysqli_query($koneksi, "ALTER TABLE `pelamar` ADD `status_sosial` VARCHAR(30) NULL AFTER `agama`");
}

// Cek dan buat kolom foto_pelamar jika belum ada di database
$cek_foto = mysqli_query($koneksi, "SHOW COLUMNS FROM `pelamar` LIKE 'foto_pelamar'");
if (mysqli_num_rows($cek_foto) == 0) {
    mysqli_query($koneksi, "ALTER TABLE `pelamar` ADD `foto_pelamar` VARCHAR(255) NULL AFTER `status_sosial`");
}

try {
    // Jalankan query utama dengan aman
    $query_pelamar = "SELECT 
                rl.id AS lamaran_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto_pelamar,
                low.nama_lowongan AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                lt.status AS status_tahap
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              INNER JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              ORDER BY rl.id DESC";

    $result_pelamar = mysqli_query($koneksi, $query_pelamar);
    if (!$result_pelamar) { throw new Exception("Query gagal."); }
} catch (Exception $e) {
    // Query backup jika tabel lowongan mengalami ketidakcocokan indeks
    $query_backup = "SELECT 
                rl.id AS lamaran_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto_pelamar,
                'dokter umum' AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                lt.status AS status_tahap
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              ORDER BY rl.id DESC";
    $result_pelamar = mysqli_query($koneksi, $query_backup);
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Pelamar Kerja - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        
        .dashboard-container { width: 100%; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }

        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        .status-pill { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 4px 12px; border-radius: 20px; font-size: 13px; }
        .status-pill.status-pending { color: #4f46e5; background: #eeebff; }
        .status-pill.status-pending .status-dot { background: #4f46e5; }
        .status-pill.status-diterima { color: #10b981; background: #e6fbf3; }
        .status-pill.status-diterima .status-dot { background: #10b981; }
        .status-pill.status-ditolak { color: #ef4444; background: #fdf2f2; }
        .status-pill.status-ditolak .status-dot { background: #ef4444; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; }

        .action-flex-container { display: flex; gap: 8px; justify-content: center; align-items: center; }
        .btn-detail { background: #f8fafc; color: #475569; border: 1px solid #e2e8f0; padding: 6px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .btn-detail:hover { background: #4f46e5; color: #ffffff; border-color: #4f46e5; }
        .btn-hapus-lamaran { background: #fff5f5; color: #ef4444; border: 1px solid #fecaca; padding: 6px 12px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; transition: all 0.2s; text-decoration: none; }
        .btn-hapus-lamaran:hover { background: #ef4444; color: #ffffff; border-color: #ef4444; }

        .modal-bg { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.5); z-index: 100; display: flex; align-items: center; justify-content: center; opacity: 0; pointer-events: none; transition: opacity 0.2s ease; }
        .modal-bg.active { opacity: 1; pointer-events: auto; }
        
        /* Modal Box dibuat proporsional untuk menampung pas foto pelamar */
        .modal-box { background: white; padding: 30px; border-radius: 24px; width: 100%; max-width: 500px; box-shadow: 0 20px 40px rgba(0,0,0,0.1); position: relative; color: #475569; max-height: 90vh; overflow-y: auto; }
        .modal-close { position: absolute; top: 18px; right: 22px; font-size: 22px; cursor: pointer; color: #94a3b8; font-weight: bold; }
        
        .detail-title { font-size: 14px; font-weight: 800; color: #4f46e5; margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; padding-bottom: 4px; }
        .detail-item { display: flex; justify-content: space-between; padding: 9px 0; border-bottom: 1px dashed #f8fafc; font-size: 13px; align-items: center; }
        .detail-label { color: #94a3b8; font-weight: 600; }
        .detail-val { color: #1e293b; font-weight: 700; text-align: right; max-width: 60%; }
        
        /* Bingkai Foto Profil Pelamar */
        .photo-container { display: flex; justify-content: center; margin-bottom: 20px; }
        .img-profile-preview { width: 120px; height: 150px; object-fit: cover; border-radius: 12px; border: 3px solid #e2e8f0; box-shadow: 0 4px 10px rgba(0,0,0,0.05); background: #f8fafc; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <aside class="sidebar-left">
            <div>
            <div>
                <div class="brand-logo"><span></span>impozitions</div>
                <nav class="menu-list">
                    <a href="dashboard.php" class="menu-item">Dashboard</a>
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                </nav>
            </div>
        </aside>

        <!-- AREA UTAMA HALAMAN KANAN -->
        <main class="main-content">
            <div class="content-header">
                <h1>Daftar Pelamar Masuk</h1>
                <p style="font-size: 13px; color: #94a3b8; margin-top: 4px;">Log aktif: <?= htmlspecialchars($nama_tampilan); ?> (Admin)</p>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">No</th>
                            <th>Nama Pelamar</th>
                            <th>Posisi Lowongan Kerja</th>
                            <th>Tanggal Pendaftaran</th>
                            <th>Status Tahap Awal</th>
                            <th style="text-align: center; width: 220px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $no = 1;
                        if($result_pelamar && mysqli_num_rows($result_pelamar) > 0):
                            while($row = mysqli_fetch_assoc($result_pelamar)): 
                                $st_class = 'status-pending';
                                $curr_status = $row['status_tahap'] ?? 'Pending';
                                
                                if(in_array(strtolower($curr_status), ['1', 'approved', 'lulus', 'diterima'])) {
                                    $display_status = 'Diterima';
                                    $st_class = 'status-diterima';
                                } elseif(in_array(strtolower($curr_status), ['0', 'rejected', 'ditolak', 'gagal'])) {
                                    $display_status = 'Ditolak';
                                    $st_class = 'status-ditolak';
                                } else {
                                    $display_status = 'Pending';
                                    $st_class = 'status-pending';
                                }
                        ?>
                            <tr>
                                <td style="font-weight: 700; color: #94a3b8;"><?= $no++; ?></td>
                                <td><span class="candidate-name"><?= htmlspecialchars($row['nama_lengkap']); ?></span></td>
                                <td style="font-weight: 600; color: #475569;"><?= htmlspecialchars($row['nama_lowongan']); ?></td>
                                <td style="color: #94a3b8; font-size: 13px;"><?= date('d M Y (H:i)', strtotime($row['tanggal_daftar'])); ?></td>
                                <td>
                                    <div class="status-pill <?= $st_class; ?>">
                                        <span class="status-dot"></span>
                                        <?= htmlspecialchars($display_status); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-flex-container">
                                        <button type="button" class="btn-detail" onclick="bukaDetail(<?= htmlspecialchars(json_encode($row)); ?>)">Lihat Detail</button>
                                        <a href="data_pelamar.php?action=hapus_lamaran&lamaran_id=<?= $row['lamaran_id']; ?>" class="btn-hapus-lamaran" onclick="return confirm('Apakah Anda yakin ingin menghapus contoh berkas lamaran ini?')">Hapus</a>
                                    </div>
                                </td>
                            </tr>
                        <?php 
                            endwhile; 
                        else:
                        ?>
                            <tr><td colspan="6" style="text-align: center; color: #94a3b8; padding: 40px 0;">Belum ada pelamar kerja yang mendaftar.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <!-- POPUP MODAL DETAIL LENGKAP + INTEGRASI PAS FOTO -->
    <div id="modal-detail-pelamar" class="modal-bg" onclick="closeModalOnBg(event)">
        <div class="modal-box">
            <span class="modal-close" onclick="tutupModal()">&times;</span>
            <h3 style="margin-bottom: 15px; color: #1e293b; font-size: 18px; font-weight: 800;">📋 Informasi Lengkap Pelamar</h3>
            
            <!-- FRAME CONTAINER FOTO PROFIL -->
            <div class="photo-container">
                <img id="det-foto" src="" class="img-profile-preview" alt="Foto Pelamar">
            </div>

            <div class="detail-title">Identitas Kependudukan</div>
            <div class="detail-item"><span class="detail-label">Nomor NIK KTP</span><span class="detail-val" id="det-nik">-</span></div>
            <div class="detail-item"><span class="detail-label">Nama Lengkap</span><span class="detail-val" id="det-nama">-</span></div>
            <div class="detail-item"><span class="detail-label">Tempat / Tgl Lahir</span><span class="detail-val" id="det-ttl">-</span></div>
            <div class="detail-item"><span class="detail-label">Jenis Kelamin</span><span class="detail-val" id="det-jk">-</span></div>
            <div class="detail-item"><span class="detail-label">Agama</span><span class="detail-val" id="det-agama">-</span></div>
            <div class="detail-item"><span class="detail-label">Status Hubungan</span><span class="detail-val" id="det-status-sosial">-</span></div>
            
            <div class="detail-title" style="margin-top: 15px;">Alamat Domisili & Kontak</div>
            <div class="detail-item"><span class="detail-label">Alamat Lengkap</span><span class="detail-val" id="det-alamat">-</span></div>
            <div class="detail-item"><span class="detail-label">Kontak HP / Email</span><span class="detail-val" id="det-kontak">-</span></div>

            <div class="detail-title" style="margin-top: 20px;">Aksi Keputusan Seleksi</div>
            <form action="" method="POST" style="margin-top: 10px;">
                <input type="hidden" name="lamaran_id" id="det-lamaran-id">
                <input type="hidden" name="status_aksi" id="status-value" value="Pending">
                
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                        <button type="submit" name="update_status_seleksi" style="background: #10b981; padding: 12px; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 13px;" onclick="document.getElementById('status-value').value='Diterima'">🟢 Terima Pelamar</button>
                        <button type="submit" name="update_status_seleksi" style="background: #ef4444; padding: 12px; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 13px;" onclick="document.getElementById('status-value').value='Ditolak'">🔴 Tolak Pelamar</button>
                    </div>
                    <button type="submit" name="update_status_seleksi" style="background: #64748b; padding: 10px; color: white; border: none; border-radius: 10px; font-weight: bold; cursor: pointer; font-size: 12px; width: 100%;" onclick="document.getElementById('status-value').value='Pending'">⏳ Kembalikan ke Pending</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT DINAMIS POPUP KONTROL -->
    <script>
        const modal = document.getElementById('modal-detail-pelamar');

        function bukaDetail(data) {
            document.getElementById('det-lamaran-id').value = data.lamaran_id; 
            document.getElementById('det-nik').innerText = data.nik || '-';
            document.getElementById('det-nama').innerText = data.nama_lengkap || '-';
            document.getElementById('det-ttl').innerText = (data.tempat_lahir || '-') + ', ' + (data.tanggal_lahir || '-');
            document.getElementById('det-jk').innerText = data.jenis_kelamin || '-';
            document.getElementById('det-agama').innerText = data.agama || '-';
            document.getElementById('det-status-sosial').innerText = data.status_sosial || '-';
            document.getElementById('det-alamat').innerText = (data.alamat || '-') + ', ' + (data.kota || '-') + ', ' + (data.provinsi || '-');
            document.getElementById('det-kontak').innerText = (data.telepon || '-') + ' / ' + (data.email || '-');

            // Render Gambar Foto Pelamar Dinamis dari Folder uploads
            const fotoElement = document.getElementById('det-foto');
            if (data.foto_pelamar) {
                fotoElement.src = 'uploads/' + data.foto_pelamar;
            } else {
                // Siluet SVG fallback jika data foto tidak ada
                // Siluet SVG fallback jika data foto tidak ada
                fotoElement.src = 'data:image/svg+xml;utf8,<svg xmlns="http://w3.org" viewBox="0 0 24 24" fill="%23cbd5e1"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5-4-8-4z"/></svg>';
            }

            modal.classList.add('active');
        }

        function tutupModal() {
            modal.classList.remove('active');
        }

        function closeModalOnBg(e) {
            if (e.target.id === 'modal-detail-pelamar') {
                tutupModal();
            }
        }
    </script>
</body>
</html>
