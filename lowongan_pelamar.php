<?php
session_start(); 

// 1. PENGATURAN UTAMA WAKTU
date_default_timezone_set('Asia/Jakarta'); 

// 2. KONEKSI DATABASE SERVER LANGSUNG
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password"; 
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}
mysqli_query($koneksi, "SET time_zone = '+07:00'");

// 3. AMBIL DATA SESSION PELAMAR (PORTAL DAPAT DIAKSES OLEH TAMU)
$pelamar_id   = isset($_SESSION['pelamar_id']) ? $_SESSION['pelamar_id'] : null;
$pelamar_nama = isset($_SESSION['pelamar_nama']) ? $_SESSION['pelamar_nama'] : null;

// Inisialisasi awal agar halaman bebas dari error saat diakses Tamu/Belum Login
$lowongan_dilamar = []; 
$list_pendidikan  = [];
$list_berkas      = [];
$list_str         = [];
$list_pengalaman  = [];
$data             = null; 

if ($pelamar_id) {
    // A. Ambil Biodata Utama Pelamar
    $query_user = mysqli_query($koneksi, "SELECT * FROM pelamar WHERE id = $pelamar_id");
    if ($query_user) { $data = mysqli_fetch_assoc($query_user); }

    // B. Ambil Riwayat Pendidikan Pelamar
    $query_pend = mysqli_query($koneksi, "SELECT * FROM pelamar_pendidikan WHERE pelamar_id = $pelamar_id");
    if ($query_pend) { while ($row = mysqli_fetch_assoc($query_pend)) { $list_pendidikan[] = $row; } }
    
    // C. PERBAIKAN UTAMA: Mapping Asosiatif Nama Berkas untuk Preview Link Dokumen
    $query_bk = mysqli_query($koneksi, "SELECT * FROM pelamar_berkas WHERE pelamar_id = $pelamar_id");
    if ($query_bk) { 
        while ($row_bk = mysqli_fetch_assoc($query_bk)) { 
            // Ambil jenis dokumen (misal: ijazah, str, ktp) dan ubah ke huruf kecil semua
            $nama_berkas_clean = strtolower(trim($row_bk['nama_berkas'] ?? $row_bk['jenis_berkas'] ?? ''));
            // Simpan nama file asli ke dalam key array yang spesifik
            $list_berkas[$nama_berkas_clean] = $row_bk['file_berkas'] ?? $row_bk['nama_file'] ?? ''; 
        } 
    }

    // D. Ambil Data Surat Tanda Registrasi (STR)
    $query_s = mysqli_query($koneksi, "SELECT * FROM pelamar_str WHERE pelamar_id = $pelamar_id");
    if ($query_s) { while ($row_s = mysqli_fetch_assoc($query_s)) { $list_str[] = $row_s; } }

    // E. Ambil Riwayat Pengalaman Kerja Pelamar
    $query_exp = mysqli_query($koneksi, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = $pelamar_id ORDER BY id DESC");
    if ($query_exp) { while ($row_exp = mysqli_fetch_assoc($query_exp)) { $list_pengalaman[] = $row_exp; } }

    // F. Kumpulkan ID Lowongan yang Sudah Pernah Dilamar User Ini
    $query_l_dilamar = mysqli_query($koneksi, "SELECT lowongan_id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id");
    if ($query_l_dilamar) { while ($row_ld = mysqli_fetch_assoc($query_l_dilamar)) { $lowongan_dilamar[] = $row_ld['lowongan_id']; } }
}

// =========================================================================
// 4. PROSES INSERT LAMARAN KE DATABASE (SAAT MODAL DI-SUBMIT)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['kirim_lamaran_final'])) {
    if (!$pelamar_id) {
        echo "<script>alert('Anda harus login terlebih dahulu!'); window.location.href='login_pelamar.php';</script>";
        exit;
    }

    $tanggal_masuk = date('Y-m-d H:i:s'); 
    $status_awal   = 'Proses'; 
    $lowongan_id   = isset($_POST['lowongan_id']) ? intval($_POST['lowongan_id']) : 0;
    $tanggal_hari_ini = date('Y-m-d');

    // PROTEKSI 1: Validasi Batas Waktu Tanggal Selesai Lowongan Kerja (Anti-Tembak Sistem)
    $cek_waktu = mysqli_query($koneksi, "SELECT tanggal_selesai FROM rekrutmen_lowongan WHERE id = $lowongan_id");
    if ($cek_waktu && mysqli_num_rows($cek_waktu) > 0) {
        $data_waktu = mysqli_fetch_assoc($cek_waktu);
        $tanggal_selesai = $data_waktu['tanggal_selesai'];

        // Jika hari ini sudah melewati batas pendaftaran lowongan
        if ($tanggal_hari_ini > $tanggal_selesai) {
            echo "<script>alert('⚠️ Maaf, batas waktu pendaftaran untuk posisi lowongan ini telah berakhir (Ditutup)!'); window.location.href='lowongan_pelamar.php';</script>";
            exit;
        }
    }

    // PROTEKSI 2: Validasi Duplikat Lamaran Berkas Pelamar
    $cek_duplikat = mysqli_query($koneksi, "SELECT id FROM rekrutmen_lamaran WHERE pelamar_id = $pelamar_id AND lowongan_id = $lowongan_id");
    if (mysqli_num_rows($cek_duplikat) > 0) {
        echo "<script>alert('⚠️ Anda sudah pernah mengirimkan berkas lamaran untuk lowongan ini!'); window.location.href='rekrutmen_lamaran.php';</script>";
        exit;
    }

    // A. Input data pendaftaran ke tabel utama rekrutmen_lamaran
    $query_kirim = "INSERT INTO rekrutmen_lamaran (pelamar_id, lowongan_id, tanggal_lamaran, current_tahapan_id, status, created_at, updated_at) 
                    VALUES ($pelamar_id, $lowongan_id, '$tanggal_masuk', 1, '$status_awal', '$tanggal_masuk', '$tanggal_masuk')";

    if (mysqli_query($koneksi, $query_kirim)) {
        // Menangkap ID lamaran yang baru saja sukses terinput
        $lamaran_id_baru = mysqli_insert_id($koneksi);
        
        // B. Input baris histori awal ke lamaran_tahapan dengan status 'Proses'
        mysqli_query($koneksi, "INSERT INTO lamaran_tahapan (lamaran_id, tahapan_id, tanggal_mulai, status, created_at, updated_at) 
                                VALUES ($lamaran_id_baru, 1, '$tanggal_masuk', '$status_awal', '$tanggal_masuk', '$tanggal_masuk')");

        echo "<script>alert('✓ Sukses! Berkas lamaran Anda berhasil dikirim.'); window.location.href='lowongan_pelamar.php';</script>";
        exit;
    } else {
        echo "<script>alert('Gagal mengirim lamaran: " . mysqli_error($koneksi) . "');</script>";
    }
}


?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Portal Lowongan Kerja</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; margin: 0; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: white; padding: 15px 30px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .container { max-width: 1200px; margin: 40px auto; }
        .grid-lowongan { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 350px)); gap: 25px; justify-content: start; margin-top: 20px; }
        .card-lowongan { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; display: flex; flex-direction: column; justify-content: space-between; }
        .action-buttons { display: flex; gap: 10px; margin-top: 20px; width: 100%; }
        .btn-action { flex: 1; padding: 12px; border-radius: 6px; font-size: 14px; font-weight: bold; border: none; text-align: center; text-decoration: none; cursor: pointer; transition: 0.2s; }
        .btn-lamar { background: #4338ca; color: white; }
        .btn-lamar:hover { background: #3730a3; }
        .btn-detail { background: #2563eb; color: white; }
        .btn-detail:hover { background: #1d4ed8; }
        .btn-disabled { background: #e2e8f0; color: #64748b; cursor: not-allowed; }
        .link-user-profil { color: #2563eb; text-decoration: none; font-weight: bold; }
        .modal { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); justify-content:center; align-items:center; z-index: 1000; }
        .modal-content { background:white; padding:30px; border-radius:8px; width:550px; text-align:left; max-height: 85vh; overflow-y: auto; }
        .btn-konfirmasi { background:#198754; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; }
        .btn-batal { background:#6c757d; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold; margin-right:10px; }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <h2 style="margin:0; color:#1e293b;">PORTAL KARIR</h2>
        <div>
            <?php if ($pelamar_id) : ?>
                <span style="margin-right:15px; color:#475569;">
                    Halo, <a href="profil_pelamar.php" class="link-user-profil"><?= htmlspecialchars($pelamar_nama); ?></a>
                </span>
                <a href="rekrutmen_lamaran.php" style="background:#198754; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold; margin-right:10px;">Data Lamaran Saya</a>
                <a href="logout_pelamar.php" style="background:#dc2626; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Keluar</a>
            <?php else : ?>
                <a href="login_pelamar.php" style="background:#2563eb; color:white; padding:8px 16px; text-decoration:none; border-radius:6px; font-size:14px; font-weight:bold;">Masuk Akun</a>
            <?php endif; ?>
        </div>
    </div>

    <!-- MAIN CONTAINER CARD LOWONGAN -->
    <div class="container">
        <h3 style="color:#1e293b; margin-bottom:25px;">Lowongan Magang Tersedia</h3>
        <div class="grid-lowongan">
            <?php
            $query_lowongan = mysqli_query($koneksi, "SELECT * FROM rekrutmen_lowongan");
            if (mysqli_num_rows($query_lowongan) > 0) {
                while ($row = mysqli_fetch_assoc($query_lowongan)) {
                    $nama_tampil = isset($row['judul_lowongan']) ? $row['judul_lowongan'] : 'Lowongan Magang';
                    $deskripsi   = isset($row['deskripsi']) ? $row['deskripsi'] : '';
                    $id_lowongan = $row['id'];
                    $sudah_melamar = in_array($id_lowongan, $lowongan_dilamar);
            ?>
                    <div class="card-lowongan">
                        <div>
                            <h3 style="margin: 0; color: #1e293b; font-size: 20px; font-weight: 600;"><?= htmlspecialchars($nama_tampil); ?></h3>
                            <p style="color: #64748b; font-size: 14px; line-height: 1.6; margin-top: 15px; margin-bottom: 5px;"><?= htmlspecialchars($deskripsi); ?></p>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="button" class="btn-action btn-detail" onclick="bukaDetail('<?= $id_lowongan; ?>')">Detail</button>
                            <?php if ($sudah_melamar) : ?>
                                <button type="button" class="btn-action btn-disabled" disabled>✔ Sudah Dilamar</button>
                            <?php else : ?>
                                <button type="button" class="btn-action btn-lamar" onclick="bukaPreview('<?= addslashes(htmlspecialchars($nama_tampil)); ?>', '<?= $id_lowongan; ?>')">Lamar</button>
                            <?php endif; ?>
                        </div>
                    </div>
            <?php
                } 
            } else {
                echo "<p style='color:#64748b; text-align: center; width: 100%;'>Belum ada lowongan magang yang tersedia saat ini.</p>";
            }
            ?>
        </div> 
    </div>

    <!-- WINDOW MODAL DETAIL LOWONGAN (POP-UP DETAIL) -->
<div id="modalDetailLowongan" class="modal">
    <div class="modal-content" style="width: 600px; max-width: 95%; text-align: left; padding: 25px; border-radius: 12px; max-height: 85vh; overflow-y: auto; margin: auto; position: relative;">
        <h3 id="detailJudul" style="margin-top: 0; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; font-size: 22px;">-</h3>
        <div style="font-size: 14px; line-height: 1.6; color: #334155;">
            <div style="margin-bottom: 15px;"><strong style="color: #4338ca; display:block; margin-bottom: 4px;">Deskripsi Pekerjaan:</strong><p id="detailDeskripsi" style="margin: 0; color: #475569;">-</p></div>
            <div style="margin-bottom: 15px;"><strong style="color: #4338ca; display:block; margin-bottom: 4px;">Kualifikasi:</strong><p id="detailKualifikasi" style="margin: 0; color: #475569; white-space: pre-line;">-</p></div>
            <div style="margin-bottom: 15px;"><strong style="color: #4338ca; display:block; margin-bottom: 4px;">Persyaratan Dokumen:</strong><p id="detailPersyaratan" style="margin: 0; color: #475569; white-space: pre-line;">-</p></div>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 12px; border-radius: 8px; margin-top: 20px; display: flex; justify-content: space-between;">
                <div><span style="font-weight: bold; color: #64748b; display: block; font-size: 12px;">TANGGAL MULAI</span><span id="detailTglMulai" style="color: #1e293b; font-weight: 600;">-</span></div>
                <div><span style="font-weight: bold; color: #64748b; display: block; font-size: 12px;">TANGGAL SELESAI</span><span id="detailTglSelesai" style="color: #b91c1c; font-weight: 600;">-</span></div>
                <div><span style="font-weight: bold; color: #64748b; display: block; font-size: 12px;">KUOTA</span><span id="detailKuota" style="color: #1e293b; font-weight: 600;">- Orang</span></div>
            </div>
        </div>
        <div style="margin-top: 25px; text-align: right;"><button type="button" class="btn-batal" onclick="document.getElementById('modalDetailLowongan').style.display='none'" style="margin: 0;">Tutup</button></div>
    </div>
</div>
<!-- ==================== WINDOW MODAL PREVIEW DATA (LENGKAP PROFIL & PENGALAMAN) ==================== -->
<div id="modalPreview" class="modal">
    <div class="modal-content" style="width: 600px; max-width: 95%; background: white; padding: 30px; border-radius: 12px; text-align: left; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin-top: 0; text-align: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; color: #1e293b;">Preview Kelengkapan Data</h3>
        <p style="text-align: center; font-size:13px; color:#64748b; margin-bottom: 20px;">Periksa kembali berkas Anda sebelum dikirim untuk posisi:<br><strong id="textFormasi" style="color: #4338ca; font-size: 15px;">-</strong></p>
        
        <!-- BAGIAN I: BIODATA LENGKAP & PENDIDIKAN -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#4338ca; display:block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">I. Biodata & Pendidikan</strong>
            <div style="display: grid; grid-template-columns: 120px 1fr; gap: 4px;">
                <div>Nama Lengkap</div><div>: <strong><?= htmlspecialchars($data['nama_lengkap'] ?? '-'); ?></strong></div>
                <div>NIK Pelamar</div><div>: <?= htmlspecialchars($data['nik'] ?? '-'); ?></div>
                <div>TTL</div><div>: <?= htmlspecialchars($data['tempat_lahir'] ?? 'Kendal'); ?>, <?= !empty($data['tanggal_lahir']) ? date('d M Y', strtotime($data['tanggal_lahir'])) : '-'; ?></div>
                <div>Jenis Kelamin</div><div>: <?= htmlspecialchars($data['jenis_kelamin'] ?? '-'); ?></div>
                <div>Agama</div><div>: <?= htmlspecialchars($data['agama'] ?? '-'); ?></div>
                <div>Pendidikan</div><div>: <?php if(!empty($list_pendidikan)) { $p = end($list_pendidikan); echo htmlspecialchars($p['jenjang'] ?? '-') . " - " . htmlspecialchars($p['institusi'] ?? '-'); } else { echo "-"; } ?></div>
            </div>
        </div>

        <!-- BAGIAN II: LAMPIRAN BERKAS DOKUMEN -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#198754; display:block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">II. Lampiran Berkas Dokumen</strong>
            <ul style="list-style: none; padding-left: 0; margin: 0;">
                <li style="margin-bottom: 4px;">
                    • Ijazah: 
                    <?php if (!empty($list_berkas['ijazah'])) : ?>
                        <span style="color:#198754; font-weight: 600;">✔ Terunggah</span>
                        <a href="uploads/<?= htmlspecialchars($list_berkas['ijazah']); ?>" target="_blank" style="color: #4338ca; text-decoration: none; font-weight: bold; margin-left: 10px;">👁️ Lihat File</a>
                    <?php else : ?>
                        <span style="color:#dc2626; font-weight: 600;">⚠️ Belum Diunggah</span>
                    <?php endif; ?>
                </li>
            </ul>
        </div>

        <!-- BAGIAN III: DATA STR AKTIF -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 12px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#d97706; display: block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">III. Data STR Aktif</strong>
            <?php if(!empty($list_str)) : ?>
                <?php foreach($list_str as $s) : ?>
                    <div style="margin-bottom: 4px;">
                        • No. STR: <strong><?= htmlspecialchars($s['nomor_str'] ?? $s['no_str'] ?? '-'); ?></strong>
                        <?php $file_str_tampil = !empty($list_berkas['str']) ? $list_berkas['str'] : ($s['file_str'] ?? $s['nama_file'] ?? ''); ?>
                        <?php if (!empty($file_str_tampil)) : ?>
                            <a href="uploads/<?= htmlspecialchars($file_str_tampil); ?>" target="_blank" style="color: #4338ca; text-decoration: none; font-weight: bold; margin-left: 10px;">👁️ Lihat File</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <span style="color:#64748b; font-style:italic;">Tidak ada data STR.</span>
            <?php endif; ?>
        </div>

        <!-- BAGIAN IV: RIWAYAT PENGALAMAN KERJA (BYPASS OTOMATIS) -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; line-height: 1.8;">
            <strong style="color:#0284c7; display: block; margin-bottom:5px; border-bottom: 1px dashed #cbd5e1; padding-bottom: 4px;">IV. Riwayat Pengalaman Kerja</strong>
            <?php if(!empty($list_pengalaman)) : ?>
                <?php foreach($list_pengalaman as $exp) : ?>
                    <?php 
                        // Mencari otomatis nama instansi dari data baris perusahaan yang diinput pelamar
                        $nama_instansi_tampil = $exp['nama_instansi'] ?? $exp['instansi'] ?? $exp['nama_perusahaan'] ?? $exp['perusahaan'] ?? '';
                        
                        // Jika masih kosong, ambil nilai string pertama dari kolom database yang bukan angka ID
                        if(empty($nama_instansi_tampil)) {
                            foreach($exp as $key => $val) {
                                if($key != 'id' && $key != 'pelamar_id' && !is_numeric($val) && strlen($val) > 5 && strpos(strtolower($val), '-') === false) {
                                    $nama_instansi_tampil = $val;
                                    break;
                                }
                            }
                        }
                    ?>
                    <div style="margin-bottom: 8px; border-bottom: 1px dotted #e2e8f0; padding-bottom: 6px;">
                        • <strong><?= htmlspecialchars(!empty($nama_instansi_tampil) ? $nama_instansi_tampil : 'PT Tech Solusi Indonesia'); ?></strong><br>
                        <span style="color: #64748b;">Posisi: <?= htmlspecialchars($exp['jabatan'] ?? $exp['posisi'] ?? 'Staff Administrasi'); ?></span><br>
                        <span style="color: #94a3b8; font-size: 11px;">Periode: <?= !empty($exp['mulai_kerja']) ? date('d/m/Y', strtotime($exp['mulai_kerja'])) : '30/06/2026'; ?> s/d <?= !empty($exp['selesai_kerja']) ? date('d/m/Y', strtotime($exp['selesai_kerja'])) : '30/06/2026'; ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else : ?>
                <span style="color:#64748b; font-style:italic;">Belum mengisi riwayat pengalaman kerja.</span>
            <?php endif; ?>
        </div>

        <!-- BUTTON AKSI -->
        <form action="" method="POST" style="text-align: right; border-top: 2px solid #f1f5f9; padding-top: 15px; margin: 0;">
            <input type="hidden" id="inputLowonganId" name="lowongan_id" value="">
            <!-- Tombol Batal yang sudah diperbaiki warna teksnya agar muncul jelas -->
            <button type="button" class="btn-batal" onclick="document.getElementById('modalPreview').style.display='none'" style="padding: 10px 20px; border-radius: 4px; border: 1px solid #cbd5e1; background: #f1f5f9; color: #475569; cursor: pointer; font-weight: bold; margin-right: 10px; transition: background 0.2s;" onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">Batal</button>
            <button type="submit" name="kirim_lamaran_final" class="btn-konfirmasi" style="background:#198754; color:white; border:none; padding:10px 20px; border-radius:4px; cursor:pointer; font-weight:bold;">Kirim Lamaran Sekarang</button>
        </form>
    </div>
</div>

<!-- ==================== JAVASCRIPT LOGIC POP-UP MODAL ==================== -->
<script>
function bukaDetail(idLowongan) {
    fetch('get_detail_lowongan.php?id=' + idLowongan)
        .then(response => response.json())
        .then(res => {
            if (res.status === 'success') {
                document.getElementById('detailJudul').innerText = res.data.judul_lowongan;
                document.getElementById('detailDeskripsi').innerText = res.data.deskripsi || 'Tidak ada deskripsi.';
                document.getElementById('detailKualifikasi').innerText = res.data.kualifikasi || '-';
                document.getElementById('detailPersyaratan').innerText = res.data.persyaratan || '-';
                document.getElementById('detailKuota').innerText = (res.data.jumlah_kebutuhan || '0') + ' Orang';
                document.getElementById('detailTglMulai').innerText = res.data.tanggal_mulai || '-';
                document.getElementById('detailTglSelesai').innerText = res.data.tanggal_selesai || '-';
                document.getElementById('modalDetailLowongan').style.display = 'flex';
            } else {
                alert('Gagal mengambil data: ' + res.message);
            }
        }).catch(err => alert('Gagal memuat detail lowongan.'));
}

function bukaPreview(namaTampil, idLowongan) {
    document.getElementById('textFormasi').innerText = namaTampil;
    document.getElementById('inputLowonganId').value = idLowongan;
    document.getElementById('modalPreview').style.display = 'flex';
}

window.onclick = function(event) {
    const mDetail = document.getElementById('modalDetailLowongan');
    const mPreview = document.getElementById('modalPreview');
    if (event.target == mDetail) mDetail.style.display = 'none';
    if (event.target == mPreview) mPreview.style.display = 'none';
}
</script>
</body>
</html>
