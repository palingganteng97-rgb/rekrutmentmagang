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
// 3. QUERY MENAMPILKAN DATA PELAMAR MASUK (VERSI DISINKRONKAN DENGAN p.foto)
// =========================================================================
try {
    $query_pelamar = "SELECT 
                rl.id AS lamaran_id,
                p.id AS pelamar_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto AS foto_pelamar,
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
    $query_backup = "SELECT 
                rl.id AS lamaran_id,
                p.id AS pelamar_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto AS foto_pelamar,
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
        .btn-logout { display: block; width: 100%; background: #dc2626; color: white; text-decoration: none; text-align: center; font-weight: 700; font-size: 14px; padding: 14px 0; border-radius: 16px; box-shadow: 0 4px 12px rgba(220, 38, 38, 0.15); transition: background 0.2s; margin-top: auto; }
        .btn-logout:hover { background: #b91c1c; }
        
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        /* PILIS STATUS */
        .status-pill { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; padding: 4px 12px; border-radius: 20px; font-size: 13px; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-terima { background: #dcfce7; color: #15803d; }
        .status-tolak { background: #fee2e2; color: #b91c1c; }
        
        .btn-action { padding: 8px 14px; font-size: 13px; font-weight: 600; border-radius: 10px; cursor: pointer; border: none; transition: background 0.2s; text-decoration: none; display: inline-block; }
        .btn-detail { background: #f1f5f9; color: #334155; margin-right: 5px; }
        .btn-detail:hover { background: #e2e8f0; }
        .btn-delete { background: #fff5f5; color: #e11d48; }
        .btn-delete:hover { background: #ffe4e6; }

        /* MODAL DETAIL ADMIN STYLING */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); justify-content: center; align-items: center; z-index: 1000; }
        .modal-box { background: white; padding: 35px; border-radius: 28px; width: 100%; max-width: 580px; max-height: 85vh; overflow-y: auto; position: relative; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.1); text-align: left; }
        .modal-close { position: absolute; top: 20px; right: 25px; background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
        .modal-box h3 { font-size: 18px; font-weight: 800; color: #1e293b; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; }
        .modal-box h4 { font-size: 12px; font-weight: 800; color: #4f46e5; margin: 25px 0 12px 0; border-bottom: 2px solid #f1f5f9; padding-bottom: 6px; letter-spacing: 0.5px; }
        
        .detail-item { display: flex; justify-content: space-between; padding: 8px 0; font-size: 14px; border-bottom: 1px solid #f8fafc; }
        .detail-label { color: #94a3b8; font-weight: 500; }
        .detail-val { color: #1e293b; font-weight: 700; text-align: right; }
        
        .form-select-status { width: 100%; padding: 10px 14px; border-radius: 12px; border: 1px solid #cbd5e1; font-weight: 600; color: #334155; outline: none; margin-top: 15px; background-color: #f8fafc; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        <!-- SIDEBAR KIRI -->
        <div class="sidebar-left">
            <div>
                <div class="brand-logo"><span></span>REKRUTMEN RS</div>
                <div class="menu-list">
    <a href="master_user.php" class="menu-item">Master User</a>
    <a href="master_unit.php" class="menu-item">Master Unit</a>
    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
    <a href="user.php" class="menu-item">Profil Pengguna</a>
</div>

            </div>
            <a href="logout_admin.php" class="btn-logout"> Log Out</a>
        </div>

        <!-- KONTEN UTAMA -->
        <div class="main-content">
            <div class="content-header">
                <h1>Daftar Pelamar Masuk</h1>
                <p style="font-size: 14px; color: #94a3b8; margin-top: 4px;">Halo, <?= htmlspecialchars($nama_tampilan); ?> • Kelola berkas pelamar baru</p>
            </div>

            <!-- TABEL DATA PELAMAR -->
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Nama Pelamar</th>
                            <th>Formasi Lowongan</th>
                            <th>Tanggal Masuk</th>
                            <th>Tahap Seleksi</th>
                            <th style="text-align: center;">Aksi Kontrol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($result_pelamar) == 0): ?>
                            <tr><td colspan="5" style="text-align: center; color: #94a3b8; padding: 40px 0;">Belum ada lamaran masuk untuk saat ini.</td></tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($result_pelamar)): 
                                $status_seleksi = $row['status_tahap'] ?? 'Pending';
                                $pill_class = 'status-pending';
                                if($status_seleksi === 'Lolos') $pill_class = 'status-terima';
                                if($status_seleksi === 'Gagal' || $status_seleksi === 'Tolak') $pill_class = 'status-tolak';
                            ?>
                                <tr>
                                    <td>
                                        <div class="candidate-name"><?= htmlspecialchars($row['nama_lengkap']); ?></div>
                                        <div style="font-size: 12px; color: #94a3b8; margin-top: 2px;">NIK: <?= htmlspecialchars($row['nik']); ?></div>
                                    </td>
                                    <td style="font-weight: 600; color: #334155; text-transform: uppercase; font-size: 13px;"><?= htmlspecialchars($row['nama_lowongan']); ?></td>
                                    <td style="color: #64748b;"><?= date('d M Y', strtotime($row['tanggal_daftar'])); ?></td>
                                    <td>
                                        <span class="status-pill <?= $pill_class; ?>">
                                            <span style="width: 6px; height: 6px; background: currentColor; border-radius: 50%;"></span>
                                            <?= htmlspecialchars($status_seleksi); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn-action btn-detail" onclick='bukaDetailPelamar(<?= json_encode($row); ?>)'>Lihat Detail</button>
                                        <a href="?action=hapus_lamaran&lamaran_id=<?= $row['lamaran_id']; ?>" class="btn-action btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus data lamaran ini?')">Hapus</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- MODAL POP-UP INFORMASI LENGKAP PELAMAR -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-box">
            <button class="modal-close" onclick="tutupDetailPelamar()">&times;</button>
            <h3>📋 Informasi Lengkap Pelamar</h3>
            
            <div style="text-align: center; margin-bottom: 25px;">
                <img id="modalFoto" src="" style="width: 120px; height: 155px; object-fit: cover; border-radius: 12px; border: 2px solid #cbd5e1; background-color: #f8fafc; display: inline-block;" alt="Foto Profil">
                <div id="modalNoFoto" style="width: 120px; height: 155px; background: #f1f5f9; border-radius: 12px; display: none; align-items: center; justify-content: center; font-size: 12px; color: #94a3b8; border: 2px solid #cbd5e1; margin: 0 auto;">Tanpa Foto</div>
            </div>

            <h4>IDENTITAS KEPENDUDUKAN</h4>
            <div class="detail-item"><div class="detail-label">Nomor NIK KTP</div><div class="detail-val" id="m_nik">-</div></div>
            <div class="detail-item"><div class="detail-label">Nama Lengkap</div><div class="detail-val" id="m_nama">-</div></div>
            <div class="detail-item"><div class="detail-label">Tempat / Tgl Lahir</div><div class="detail-val" id="m_ttl">-</div></div>
            <div class="detail-item"><div class="detail-label">Jenis Kelamin</div><div class="detail-val" id="m_jk">-</div></div>
            <div class="detail-item"><div class="detail-label">Agama</div><div class="detail-val" id="m_agama">-</div></div>
            <div class="detail-item"><div class="detail-label">Status Hubungan</div><div class="detail-val" id="m_status">-</div></div>

            <h4>ALAMAT DOMISILI & KONTAK</h4>
            <div class="detail-item"><div class="detail-label">Alamat Email</div><div class="detail-val" id="m_email">-</div></div>
            <div class="detail-item"><div class="detail-label">Nomor Telepon / WA</div><div class="detail-val" id="m_telepon">-</div></div>
            <div class="detail-item"><div class="detail-label">Kota / Provinsi</div><div class="detail-val" id="m_lokasi">-</div></div>
            <div class="detail-item" style="flex-direction: column; align-items: flex-start; gap: 4px;"><div class="detail-label">Alamat Rumah Lengkap</div><div class="detail-val" id="m_alamat" style="text-align: left; font-weight: 600; color: #334155; width: 100%; padding-top: 2px;">-</div></div>

            <!-- AREA MULTI-PENDIDIKAN DINAMIS -->
            <div id="areaPendidikanAdmin"></div>

            <h4>PEMROSESAN STATUS KELULUSAN</h4>
            <form method="POST" action="">
                <input type="hidden" name="lamaran_id" id="m_submit_id">
                <select name="status_aksi" id="m_select_status" class="form-select-status" onchange="this.form.submit()">
                    <option value="Pending">Pending (Belum Diproses)</option>
                    <option value="Lolos">Lolos Seleksi Berkas</option>
                    <option value="Tolak">Tolak Lamaran</option>
                </select>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIKA KONTROL POP-UP DAN FETCH API PENDIDIKAN -->
    <script>
        const modal = document.getElementById('detailModal');

        function bukaDetailPelamar(data) {
            document.getElementById('m_nik').innerText = data.nik || '-';
            document.getElementById('m_nama').innerText = data.nama_lengkap || '-';
            document.getElementById('m_ttl').innerText = (data.tempat_lahir || '-') + ', ' + (data.tanggal_lahir || '-');
            document.getElementById('m_jk').innerText = data.jenis_kelamin || '-';
            document.getElementById('m_agama').innerText = data.agama || '-';
            document.getElementById('m_status').innerText = data.status_sosial || '-';
            document.getElementById('m_email').innerText = data.email || '-';
            document.getElementById('m_telepon').innerText = data.telepon || '-';
            document.getElementById('m_lokasi').innerText = (data.kota || '-') + ', ' + (data.provinsi || '-');
            document.getElementById('m_alamat').innerText = data.alamat || '-';
            
            document.getElementById('m_submit_id').value = data.lamaran_id;
            document.getElementById('m_select_status').value = data.status_tahap || 'Pending';

            // Logika Validasi Tampilan Foto Profil
            const imgEl = document.getElementById('modalFoto');
            const noImgEl = document.getElementById('modalNoFoto');
            
            if (data.foto_pelamar && data.foto_pelamar.trim() !== '') {
                imgEl.src = 'uploads/' + data.foto_pelamar;
                imgEl.style.display = 'inline-block';
                noImgEl.style.display = 'none';
            } else {
                imgEl.style.display = 'none';
                noImgEl.style.display = 'flex';
            }

            // Ambil data multi-pendidikan via AJAX Fetch API secara instan
            const areaPend = document.getElementById('areaPendidikanAdmin');
            areaPend.innerHTML = '<h4>RIWAYAT PENDIDIKAN</h4><p style="font-size: 13px; color: #94a3b8; font-style: italic;">Memuat data pendidikan...</p>';

            fetch('get_pendidikan_admin.php?pelamar_id=' + data.pelamar_id)
                .then(response => response.text())
                .then(html => {
                    areaPend.innerHTML = html;
                })
                .catch(err => {
                    areaPend.innerHTML = '<h4>RIWAYAT PENDIDIKAN</h4><p style="font-size: 13px; color: #e11d48;">Gagal memuat riwayat pendidikan.</p>';
                });

            modal.style.display = 'flex';
        }

        function tutupDetailPelamar() { modal.style.display = 'none'; }

        window.onclick = function(event) {
            if (event.target == modal) tutupDetailPelamar();
        }
    </script>
</body>
</html>
