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
                lt.status AS status_tahap,
                pd.jenjang, pd.institusi, pd.jurusan, pd.ipk
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              INNER JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              LEFT JOIN pelamar_pendidikan pd ON pd.pelamar_id = p.id
              ORDER BY rl.id DESC";

    $result_pelamar = mysqli_query($koneksi, $query_pelamar);
    if (!$result_pelamar) { throw new Exception("Query gagal."); }
} catch (Exception $e) {
    $query_backup = "SELECT 
                rl.id AS lamaran_id,
                p.id AS pelamar_id,
                p.nama_lengkap, p.nik, p.tempat_lahir, p.tanggal_lahir, p.jenis_kelamin, p.agama, p.alamat, p.kota, p.provinsi, p.telepon, p.email,
                p.status_sosial, p.foto AS foto_pelamar,
                'DOKTER UMUM' AS nama_lowongan,
                rl.created_at AS tanggal_daftar,
                lt.status AS status_tahap,
                '-' AS jenjang, '-' AS institusi, '-' AS jurusan, '-' AS ipk
              FROM rekrutmen_lamaran rl
              INNER JOIN pelamar p ON rl.pelamar_id = p.id
              LEFT JOIN lamaran_tahapan lt ON lt.lamaran_id = rl.id
              LEFT JOIN pelamar_pendidikan pd ON pd.pelamar_id = p.id
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
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .menu-item:hover:not(.active) { background: #f8fafc; color: #1e293b; }
        
        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        .content-header p { font-size: 14px; color: #94a3b8; margin-top: 4px; }
        
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 25px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f1f5f9; }
        td { padding: 18px 0; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        .candidate-name { font-weight: 700; color: #1e293b; font-size: 14px; }
        
        /* STATUS PILL */
        .status-pill { display: inline-flex; align-items: center; gap: 6px; font-weight: 700; padding: 6px 14px; border-radius: 20px; font-size: 12px; text-transform: uppercase; }
        .status-pending { background: #fef3c7; color: #d97706; }
        .status-terima { background: #dcfce7; color: #15803d; }
        .status-tolak { background: #fee2e2; color: #b91c1c; }
        
        .btn-action { padding: 8px 14px; font-size: 13px; font-weight: 600; border-radius: 10px; cursor: pointer; border: none; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-detail { background: #f1f5f9; color: #334155; margin-right: 5px; }
        .btn-detail:hover { background: #e2e8f0; }
        .btn-delete { background: #fff5f5; color: #e11d48; }
        .btn-delete:hover { background: #ffe4e6; }

        /* MODAL POP-UP STYLE */
        .modal-overlay { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center; padding: 20px; }
        .modal-box { background-color: white; padding: 30px; border-radius: 20px; width: 100%; max-width: 600px; max-height: 85vh; overflow-y: auto; position: relative; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); scrollbar-width: thin; }
        .modal-close { position: absolute; right: 20px; top: 20px; background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
        .modal-close:hover { color: #475569; }
        
        .detail-section-title { font-size: 12px; font-weight: 800; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.5px; margin: 20px 0 10px 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 5px; }
        .detail-item { display: flex; padding: 8px 0; font-size: 14px; border-bottom: 1px solid #fafafa; }
        .detail-label { width: 150px; color: #94a3b8; font-weight: 600; flex-shrink: 0; }
        .detail-val { color: #1e293b; font-weight: 500; }
    </style>
</head>
<body>

    <div class="dashboard-container">
        
            <!-- SIDEBAR MENU KIRI DENGAN CELAH & TOMBOL LOG OUT MERAH PRESISI -->
        <aside class="sidebar-left" style="display: flex; flex-direction: column; justify-content: space-between; padding: 35px; background: #ffffff; border-right: 1px solid #f1f5f9; flex-shrink: 0; width: 280px;">
            
            <!-- GRUP ATAS: Navigasi Utama sampai Profil Pengguna -->
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <div class="brand-logo" style="font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px;">
                    <span style="width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block;"></span>Rekrutmen RS
                </div>
                <nav class="menu-list">
                    <a href="master_user.php" class="menu-item">Master User</a>
                    <a href="master_unit.php" class="menu-item">Master Unit</a>
                    <a href="master_jabatan.php" class="menu-item">Master Jabatan</a>
                    <a href="master_pendidikan.php" class="menu-item">Master Pendidikan</a>
                    <a href="master_lowongan.php" class="menu-item">Master Lowongan</a>
                    <a href="master_tahapan_seleksi.php" class="menu-item">Master Tahapan Seleksi</a>
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                    <!-- Menu ini diberikan class 'active' agar menyala ungu di halaman ini -->
                    <a href="data_pelamar.php" class="menu-item active">Data Pelamar</a>
                    <a href="user.php" class="menu-item">Profil Pengguna</a>
                </nav>
            </div>

            <!-- GRUP BAWAH: Menyisakan Celah Kosong di Tengah, Memuat Tombol Log Out Merah -->
            <div style="margin-top: auto; display: flex; flex-direction: column; gap: 20px; padding-top: 40px;">
                <nav class="menu-list">
                    <!-- Area kosong jika ingin ditambahkan menu bawah di masa depan -->
                </nav>
                
                <!-- TOMBOL LOG OUT DENGAN STYLE KOTAK MERAH ABSOLUT -->
                <a href="logout.php" style="display: block; width: 100%; padding: 14px; background: #ef4444; color: #ffffff !important; text-align: center; border-radius: 16px; font-weight: 700; font-size: 14px; text-decoration: none; border: none; transition: background 0.2s;" onmouseover="this.style.background='#dc2626'" onmouseout="this.style.background='#ef4444'" onclick="return confirm('Apakah Anda yakin ingin keluar dari sistem Admin?')">Log Out</a>
            </div>
            
        </aside>

        <!-- AREA UTAMA CONTENT -->
        <div class="main-content">
            <div class="content-header">
                <h1>Daftar Pelamar Masuk</h1>
                <p>Halo, <?php echo htmlspecialchars($nama_tampilan); ?> • Kelola berkas pelamar baru</p>
            </div>

            <!-- TABEL DATA -->
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
                        <?php while ($row = mysqli_fetch_assoc($result_pelamar)): 
                            $status_clean = $row['status_tahap'] ?? 'Pending';
                            $pill_class = 'status-pending';
                            if ($status_clean == 'Diterima') $pill_class = 'status-terima';
                            if ($status_clean == 'Ditolak') $pill_class = 'status-tolak';
                        ?>
                        <tr>
                            <td>
                                <div class="candidate-name"><?= htmlspecialchars($row['nama_lengkap']) ?></div>
                                <div style="font-size:11px; color:#94a3b8; margin-top:2px;">NIK: <?= htmlspecialchars($row['nik']) ?></div>
                            </td>
                            <td style="font-weight: 600; color: #1e293b; text-transform: uppercase; font-size:13px;"><?= htmlspecialchars($row['nama_lowongan']) ?></td>
                            <td><?= date('d M Y', strtotime($row['tanggal_daftar'])) ?></td>
                            <td>
                                <span class="status-pill <?= $pill_class ?>">
                                    <?= $status_clean == 'Pending' ? '• Pending' : ($status_clean == 'Diterima' ? '• Diterima' : '• Ditolak') ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <button type="button" class="btn-action btn-detail" onclick="bukaDetailModal(
                                    '<?= $row['lamaran_id'] ?>',
                                    '<?= htmlspecialchars($row['nama_lengkap']) ?>',
                                    '<?= htmlspecialchars($row['nik']) ?>',
                                    '<?= htmlspecialchars($row['foto_pelamar'] ?? '') ?>',
                                    '<?= htmlspecialchars($row['tempat_lahir'] . ', ' . $row['tanggal_lahir']) ?>',
                                    '<?= htmlspecialchars($row['jenis_kelamin']) ?>',
                                    '<?= htmlspecialchars($row['agama']) ?>',
                                    '<?= htmlspecialchars($row['status_sosial']) ?>',
                                    '<?= htmlspecialchars($row['email']) ?>',
                                    '<?= htmlspecialchars($row['telepon']) ?>',
                                    '<?= htmlspecialchars($row['kota'] . ', ' . $row['provinsi']) ?>',
                                    '<?= htmlspecialchars($row['alamat']) ?>',
                                    '<?= htmlspecialchars($row['jenjang'] . ' - ' . $row['institusi']) ?>',
                                    '<?= htmlspecialchars($row['jurusan']) ?>',
                                    '<?= htmlspecialchars($row['ipk']) ?>',
                                    '<?= $status_clean ?>'
                                )">Lihat Detail</button>
                                <a href="?action=hapus_lamaran&lamaran_id=<?= $row['lamaran_id'] ?>" class="btn-action btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus berkas lamaran ini?');">Hapus</a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ================= MODAL POP-UP INFORMASI LENGKAP PELAMAR (BISA SCROLL) ================= -->
    <div class="modal-overlay" id="detailModal">
        <div class="modal-box">
            <button class="modal-close" onclick="tutupDetailPelamar()">&times;</button>
            <h3 style="color:#1e293b; font-size:18px; font-weight:800; margin-bottom:20px; border-bottom:2px solid #f1f5f9; padding-bottom:10px;">Informasi Lengkap Pelamar</h3>
            
            <!-- FOTO PREVIEW -->
            <div style="text-align: center; margin-bottom: 25px;">
                <img id="modalFoto" src="" style="width: 120px; height: 155px; object-fit: cover; border-radius: 12px; border: 2px solid #e2e8f0; display:inline-block;">
                <div id="modalNoFoto" style="width: 120px; height: 155px; background: #f1f5f9; border-radius: 12px; display: none; align-items: center; justify-content: center; font-size: 12px; color: #94a3b8; font-weight: 600; border: 2px dashed #cbd5e1; margin:0 auto;">NO FOTO</div>
            </div>
            
            <div class="detail-section-title">IDENTITAS KEPENDUDUKAN</div>
            <div class="detail-item"><div class="detail-label">Nomor NIK KTP</div><div class="detail-val" id="m_nik">-</div></div>
            <div class="detail-item"><div class="detail-label">Nama Lengkap</div><div class="detail-val" id="m_nama">-</div></div>
            <div class="detail-item"><div class="detail-label">Tempat / Tgl Lahir</div><div class="detail-val" id="m_ttl">-</div></div>
            <div class="detail-item"><div class="detail-label">Jenis Kelamin</div><div class="detail-val" id="m_jk">-</div></div>
            <div class="detail-item"><div class="detail-label">Agama</div><div class="detail-val" id="m_agama">-</div></div>
            <div class="detail-item"><div class="detail-label">Status Hubungan</div><div class="detail-val" id="m_status">-</div></div>
            
            <div class="detail-section-title">ALAMAT DOMISILI & KONTAK</div>
            <div class="detail-item"><div class="detail-label">Alamat Email</div><div class="detail-val" id="m_email">-</div></div>
            <div class="detail-item"><div class="detail-label">Nomor Telepon / WA</div><div class="detail-val" id="m_telepon">-</div></div>
            <div class="detail-item"><div class="detail-label">Kota / Provinsi</div><div class="detail-val" id="m_lokasi">-</div></div>
            <div class="detail-item" style="flex-direction: column; align-items: flex-start; gap: 4px;"><div class="detail-label">Alamat Rumah</div><div class="detail-val" id="m_alamat">-</div></div>

            <div class="detail-section-title">RIWAYAT PENDIDIKAN</div>
            <div class="detail-item"><div class="detail-label">Kampus / Sekolah</div><div class="detail-val" id="m_kampus">-</div></div>
            <div class="detail-item"><div class="detail-label">Jurusan / Prodi</div><div class="detail-val" id="m_jurusan">-</div></div>
            <div class="detail-item"><div class="detail-label">IPK / Nilai Akhir</div><div class="detail-val" id="m_ipk">-</div></div>

            <!-- FORM PROSES EDIT STATUS SELEKSI -->
            <form action="" method="POST" style="margin-top: 25px; border-top: 2px solid #f1f5f9; padding-top: 20px;">
                <input type="hidden" name="lamaran_id" id="formLamaranId">

                <div style="margin-bottom: 20px; text-align: left;">
                    <label style="display: block; font-size: 13px; font-weight: 700; color: #475569; margin-bottom: 8px; text-transform: uppercase; letter-spacing:0.3px;">Ubah Tahap Seleksi Pelamar:</label>
                    <select name="status_aksi" id="formStatusAksi" required style="width: 100%; padding: 12px; border: 1px solid #cbd5e1; border-radius: 10px; font-size: 14px; color:#1e293b; font-weight:600; background:#f8fafc;">
                        <option value="Pending">🟡 Pending</option>
                        <option value="Diterima">🟢 Terima & Loloskan</option>
                        <option value="Ditolak">🔴 Tolak Berkas</option>
                    </select>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" onclick="tutupDetailPelamar()" style="background-color: #cbd5e1; color: #334155; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; font-size: 13px;">Batal</button>
                    <button type="submit" name="update_status_seleksi" style="background-color: #4f46e5; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 13px; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT KONTROL SUNTIK DATA POP-UP -->
    <script>
        function bukaDetailModal(id, nama, nik, foto, ttl, jk, agama, status, email, telepon, lokasi, alamat, kampus, jurusan, ipk, statusAksi) {
            document.getElementById('detailModal').style.display = 'flex';
            
            // Suntik data teks ke elemen HTML modal secara presisi
            document.getElementById('m_nama').innerText = nama;
            document.getElementById('m_nik').innerText = nik;
            document.getElementById('m_ttl').innerText = ttl;
            document.getElementById('m_jk').innerText = jk;
            document.getElementById('m_agama').innerText = agama;
            document.getElementById('m_status').innerText = status;
            document.getElementById('m_email').innerText = email;
            document.getElementById('m_telepon').innerText = telepon;
            document.getElementById('m_lokasi').innerText = lokasi;
            document.getElementById('m_alamat').innerText = alamat;
            document.getElementById('m_kampus').innerText = kampus;
            document.getElementById('m_jurusan').innerText = jurusan;
            document.getElementById('m_ipk').innerText = ipk;
            
            // Suntik foto profil pelamar ke dalam komponen gambar modal
            const imgObj = document.getElementById('modalFoto');
            const noImgObj = document.getElementById('modalNoFoto');
            if (foto && foto !== '') {
                imgObj.src = 'uploads/' + foto;
                imgObj.style.display = 'inline-block';
                if (noImgObj) noImgObj.style.display = 'none';
            } else {
                imgObj.style.display = 'none';
                if (noImgObj) noImgObj.style.display = 'flex';
            }
            
            // Siapkan parameter ID & Pilihan nilai ke input form submit backend
            document.getElementById('formLamaranId').value = id;
            document.getElementById('formStatusAksi').value = statusAksi;
        }

        function tutupDetailPelamar() {
            document.getElementById('detailModal').style.display = 'none';
        }

        // Auto close ketika klik area blur luar kotak modal box
        window.onclick = function(event) {
            const modal = document.getElementById('detailModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
