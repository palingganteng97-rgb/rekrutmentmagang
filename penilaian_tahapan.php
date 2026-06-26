<?php
session_start();

// =========================================================================
// 1. HUBUNGKAN KONEKSI DATABASE & AMBIL ID DARI URL (HURUF KECIL SEMUA)
// =========================================================================
include 'koneksi.php';

// Ambil ID Pelamar dari URL (?id=5)
$id_pelamar = isset($_GET['id']) ? intval($_GET['id']) : 0;

$pesan = "";

// 2. AMBIL DAFTAR TABEL NYATA UNTUK PERLINDUNGAN UTAMA (ANTI FATAL ERROR)
$daftar_tabel = [];
$q_show_tables = mysqli_query($koneksi, "SHOW TABLES");
if ($q_show_tables) {
    while ($t_row = mysqli_fetch_row($q_show_tables)) {
        $daftar_tabel[] = strtolower($t_row[0]);
    }
}

// 3. AMBIL DATA BIODATA UTAMA PELAMAR SEKALIGUS POSISI LOWONGANNYA
$query_pelamar = "SELECT p.*, COALESCE(low.judul_lowongan, 'Belum Memilih') AS posisi_dilamar 
                  FROM pelamar p
                  LEFT JOIN rekrutmen_lamaran rl ON rl.pelamar_id = p.id
                  LEFT JOIN rekrutmen_lowongan low ON rl.lowongan_id = low.id
                  WHERE p.id = '$id_pelamar' LIMIT 1";

$result_pelamar = mysqli_query($koneksi, $query_pelamar);
$data_pelamar = mysqli_fetch_assoc($result_pelamar);

// --- OTOMATISASI MENCARI ID LAMARAN TAHAPAN YANG VALID ---
$id_lamaran_otomatis = 0;
$cek_kolom = mysqli_query($koneksi, "SHOW COLUMNS FROM lamaran_tahapan");
$kolom_dihung = "";
if ($cek_kolom) {
    while ($k = mysqli_fetch_assoc($cek_kolom)) {
        if (in_array($k['Field'], ['pelamar_id', 'id_pelamar', 'pendaftaran_id', 'id_pendaftaran'])) {
            $kolom_dihung = $k['Field'];
            break;
        }
    }
}
if (!empty($kolom_dihung)) {
    $query_lamaran = "SELECT id FROM lamaran_tahapan WHERE $kolom_dihung = '$id_pelamar' LIMIT 1";
    $result_lamaran = mysqli_query($koneksi, $query_lamaran);
    if ($result_lamaran && mysqli_num_rows($result_lamaran) > 0) {
        $row_lamaran = mysqli_fetch_assoc($result_lamaran);
        $id_lamaran_otomatis = $row_lamaran['id'];
    }
}
if ($id_lamaran_otomatis == 0) {
    $query_backup = mysqli_query($koneksi, "SELECT id FROM lamaran_tahapan LIMIT 1");
    if ($query_backup && mysqli_num_rows($query_backup) > 0) {
        $row_backup = mysqli_fetch_assoc($query_backup);
        $id_lamaran_otomatis = $row_backup['id'];
    }
}

$id_penilai_otomatis = isset($_SESSION['id_user']) ? $_SESSION['id_user'] : 1;

// 4. AMBIL DATA TAHAPAN SELEKSI UNTUK TAB CHROME
$query_tahapan = "SELECT id, nama_tahapan FROM mst_tahapan_seleksi WHERE status = 'Aktif'";
$result_tahapan = mysqli_query($koneksi, $query_tahapan);
$list_tahapan = [];
if ($result_tahapan) {
    while ($row = mysqli_fetch_assoc($result_tahapan)) {
        $list_tahapan[] = $row;
    }
}

// 5. AMBIL DATA BERKAS / IJAZAH PELAMAR (DENGAN PROTEKSI SATPAM TABEL)
$file_berkas_db = "";
$nama_berkas_txt = "Berkas Lamaran";
if (in_array('berkas_pelamar', $daftar_tabel)) {
    $q_berkas = mysqli_query($koneksi, "SELECT file_berkas, nama_berkas, berkas, file FROM berkas_pelamar WHERE pelamar_id = '$id_pelamar' LIMIT 1");
    if ($q_berkas && mysqli_num_rows($q_berkas) > 0) {
        $r_berkas = mysqli_fetch_assoc($q_berkas);
        $file_berkas_db = $r_berkas['file_berkas'] ?? $r_berkas['berkas'] ?? $r_berkas['file'] ?? "";
        $nama_berkas_txt = $r_berkas['nama_berkas'] ?? "Berkas Lamaran";
    }
}

// 6. AMBIL DATA STR PELAMAR (DENGAN PROTEKSI SATPAM TABEL)
$file_str_db = "";
$nomor_str_txt = "";
if (in_array('str_pelamar', $daftar_tabel)) {
    $q_str = mysqli_query($koneksi, "SELECT file_str, nomor_str, str FROM str_pelamar WHERE pelamar_id = '$id_pelamar' LIMIT 1");
    if ($q_str && mysqli_num_rows($q_str) > 0) {
        $r_str = mysqli_fetch_assoc($q_str);
        $file_str_db = $r_str['file_str'] ?? $r_str['str'] ?? "";
        $nomor_str_txt = $r_str['nomor_str'] ?? "";
    }
}

// 7. AMBIL DATA PENGALAMAN KERJA (DENGAN PROTEKSI SATPAM TABEL)
$teks_pengalaman_db = "";
if (in_array('pengalaman_pelamar', $daftar_tabel)) {
    $q_pengalaman = mysqli_query($koneksi, "SELECT nama_perusahaan, jabatan, posisi, mulai_kerja, selesai_kerja, alasan_keluar FROM pengalaman_pelamar WHERE pelamar_id = '$id_pelamar' LIMIT 1");
    if ($q_pengalaman && mysqli_num_rows($q_pengalaman) > 0) {
        $r_pengalaman = mysqli_fetch_assoc($q_pengalaman);
        $perusahaan = $r_pengalaman['nama_perusahaan'] ?? '';
        $jabatan    = $r_pengalaman['jabatan'] ?? $r_pengalaman['posisi'] ?? '';
        $periode    = ($r_pengalaman['mulai_kerja'] ?? '') . ' s/d ' . ($r_pengalaman['selesai_kerja'] ?? '');
        $alasan     = $r_pengalaman['alasan_keluar'] ?? '';
        if (!empty($perusahaan)) {
            $teks_pengalaman_db = "<b>Instansi:</b> $perusahaan<br><b>Jabatan:</b> $jabatan<br><b>Periode:</b> $periode<br><b>Alasan Keluar:</b> $alasan";
        }
    }
}

// 8. AMBIL DATA RIWAYAT NOTIFIKASI YANG SUDAH PERNAH DIKIRIM (UNTUK DITAMPILKAN SAAT REFRESH)
$list_riwayat_notif = [];
if (in_array('notifikasi_rekrutmen', $daftar_tabel)) {
    $q_riwayat = mysqli_query($koneksi, "SELECT jenis, tujuan, pesan, status, created_at FROM notifikasi_rekrutmen WHERE lamaran_id = '$id_lamaran_otomatis' ORDER BY id DESC");
    if ($q_riwayat) {
        while ($r_row = mysqli_fetch_assoc($q_riwayat)) {
            $list_riwayat_notif[] = $r_row;
        }
    }
}

// =========================================================================
// 9. LOGIKA BACKEND AJAX - SKIP GLOBAL, AUTOSAVE NILAI, & AUTO-SAVE NOTIFIKASI
// =========================================================================
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // A. LOGIKA SKIP PELAMAR GLOBAL
    if ($_POST['action'] == 'skip_pelamar_global') {
        $lamaran_id = intval($_POST['lamaran_tahapan_id']);
        $tanggal_sekarang = date('Y-m-d H:i:s');
        $query_skip = "UPDATE lamaran_tahapan SET status = 'Skip', tanggal_mulai = '$tanggal_sekarang' WHERE id = '$lamaran_id'";
        if (mysqli_query($koneksi, $query_skip)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
        }
        exit;
    }

    // B. LOGIKA AUTOSAVE NILAI INDIVIDU TAHAPAN
    if ($_POST['action'] == 'autosave') {
        $lamaran_tahapan_id = intval($_POST['lamaran_tahapan_id']);
        $mst_tahapan_id     = intval($_POST['mst_tahapan_id']);
        $penilai_id         = intval($_POST['penilai_id']);
        $nilai              = $_POST['nilai'] !== "" ? floatval($_POST['nilai']) : "NULL";
        $status_tahap       = mysqli_real_escape_string($koneksi, $_POST['status_tahap']);
        $catatan            = mysqli_real_escape_string($koneksi, $_POST['catatan']);
        $tanggal            = date('Y-m-d H:i:s');

        $cek_exist = mysqli_query($koneksi, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id' AND penilai_id = '$penilai_id'");
        
        if (mysqli_num_rows($cek_exist) > 0) {
            $query_save = "UPDATE penilaian_tahapan SET nilai = " . ($nilai !== "NULL" ? "'$nilai'" : "NULL") . ", status_tahap = '$status_tahap', catatan = '$catatan', tanggal = '$tanggal' WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id' AND penilai_id = '$penilai_id'";
        } else {
            $query_save = "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, status_tahap, catatan, tanggal) VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', " . ($nilai !== "NULL" ? "'$nilai'" : "NULL") . ", '$status_tahap', '$catatan', '$tanggal')";
        }

        if (mysqli_query($koneksi, $query_save)) {
            mysqli_query($koneksi, "UPDATE lamaran_tahapan SET status = '$status_tahap', tanggal_mulai = '$tanggal' WHERE id = '$lamaran_tahapan_id'");
            echo json_encode(['status' => 'success', 'message' => 'Data berhasil diproses!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
        }
        exit;
    }

    // C. LOGIKA AUTO-SAVE JALUR NYATA: SIMPAN DATA DULU BARU JAVASCRIPT REDIRECT (ANTI DOWN SERVER)
    if ($_POST['action'] == 'kirim_notifikasi') {
        $lamaran_id  = intval($_POST['lamaran_id']);
        $jenis       = mysqli_real_escape_string($koneksi, $_POST['jenis']);
        $tujuan      = mysqli_real_escape_string($koneksi, $_POST['tujuan']);
        $pesan_txt   = mysqli_real_escape_string($koneksi, $_POST['pesan_notif']);
        $status_init = 'Sent'; // Berhasil dikirim lewat WhatsApp Web
        
        $query_notif = "INSERT INTO notifikasi_rekrutmen (lamaran_id, jenis, tujuan, pesan, status, created_at) 
                        VALUES ('$lamaran_id', '$jenis', '$tujuan', '$pesan_txt', '$status_init', NOW())";
        
        if (mysqli_query($koneksi, $query_notif)) {
            echo json_encode(['status' => 'success', 'message' => 'Notifikasi otomatis tersimpan ke database!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($koneksi)]);
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Penilaian Tahapan</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background-color: #f8fafc; }
        .main-layout-table { width: 100%; max-width: 1250px; margin: 0 auto; border-collapse: separate; border-spacing: 25px 0; }
        .left-panel-td { width: 45%; vertical-align: top; background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .right-panel-td { width: 55%; vertical-align: top; background: white; padding: 25px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        
        .btn-kembali { display: inline-block; padding: 10px 15px; background-color: #64748b; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; margin-bottom: 20px; }
        .btn-kembali:hover { background-color: #475569; }
        .bio-table { width: 100%; border-collapse: collapse; margin-top: 10px; text-align: left; }
        .bio-table th, .bio-table td { text-align: left; padding: 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
        .bio-table th { width: 38%; color: #64748b; font-weight: bold; }

        .form-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px; margin-bottom: 15px; }
        .form-header h3 { margin: 0; color: #1e293b; }
        .btn-skip { background-color: #ea580c; color: white; border: none; padding: 8px 16px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
        .btn-skip:hover { background-color: #c2410c; }

        .chrome-tabs-container { display: flex; background: #e2e8f0; padding: 8px 8px 0 8px; border-radius: 8px 8px 0 0; gap: 4px; overflow-x: auto; margin-bottom: 20px; }
        .chrome-tab { padding: 10px 20px; background: #cbd5e1; color: #475569; border-radius: 8px 8px 0 0; cursor: pointer; font-size: 13px; font-weight: bold; white-space: nowrap; border: none; transition: all 0.2s; }
        .chrome-tab:hover { background: #d1d5db; }
        .chrome-tab.active { background: white; color: #2563eb; box-shadow: 0 -2px 4px rgba(0,0,0,0.05); position: relative; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; font-weight: bold; margin-bottom: 5px; color: #334155; }
        .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; }
        .status-box { font-weight: bold; padding: 8px; border-radius: 6px; border: 1px solid #cbd5e1; background-color: #f8fafc; }
        #notif-autosave { padding: 8px; border-radius: 6px; margin-bottom: 10px; font-size: 13px; display: none; text-align: center; font-weight: bold; }
        .badge-posisi { display: inline-block; padding: 6px 16px; background-color: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; font-weight: bold; border-radius: 20px; margin-top: 10px; font-size: 14px; }
        
        /* Style Tabel Riwayat Notifikasi */
        .notif-table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 13px; }
        .notif-table th, .notif-table td { padding: 10px; border: 1px solid #cbd5e1; text-align: left; }
        .notif-table th { background-color: #f1f5f9; color: #475569; font-weight: bold; }
        .badge-status { padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; }
        .badge-sent { background-color: #dcfce7; color: #16a34a; }
        .badge-pending { background-color: #fef9c3; color: #ca8a04; }
    </style>
</head>
<body>

    <table class="main-layout-table">
        <tr>
            <!-- PANEL SEBELAH KIRI (BIODATA & DOKUMEN PELAMAR) -->
            <td class="left-panel-td">
                <div style="text-align: left;">
                    <a href="lamaran_tahapan.php" class="btn-kembali">← Kembali ke Lamaran Tahapan</a>
                </div>
                <h3 style="color: #1e293b; margin-top: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">Biodata Pelamar</h3>
                
                <?php if (isset($data_pelamar) && $data_pelamar): ?>
                    <div style="margin-bottom: 20px; text-align: center;">
                        <?php if (!empty($data_pelamar['foto']) && file_exists("uploads/" . $data_pelamar['foto'])): ?>
                            <img src="uploads/<?php echo $data_pelamar['foto']; ?>" alt="Foto Pelamar" style="width: 150px; height: 180px; object-fit: cover; border-radius: 8px; border: 1px solid #cbd5e1;">
                        <?php else: ?>
                            <div style="width: 150px; height: 180px; background: #e2e8f0; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; color: #64748b; font-size: 14px; font-weight: bold;">No Photo</div>
                        <?php endif; ?>
                        <br>
                        <span class="badge-posisi">Posisi: <?php echo strtoupper($data_pelamar['posisi_dilamar'] ?? 'Belum Memilih'); ?></span>
                    </div>

                    <table class="bio-table">
                        <?php foreach ($data_pelamar as $kolom => $nilai_kolom): ?>
                            <?php 
                            $kolom_dihapus = ['id', 'password', 'foto', 'foto_pelamar', 'email', 'telepon', 'created_at', 'updated_at', 'berkas', 'str', 'pengalaman', 'posisi_dilamar'];
                            if (in_array(strtolower($kolom), $kolom_dihapus)) { continue; }
                            ?>
                            <tr>
                                <th><?php echo ucwords(str_replace('_', ' ', $kolom)); ?></th>
                                <td>: <?php 
                                    if (strtolower($kolom) == 'pendidikan' && strpos($nilai_kolom, 'S1 (Sarjan') !== false) {
                                        echo "S1 (Sarjana)";
                                    } else {
                                        echo $nilai_kolom ?? '-'; 
                                    }
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <h4 style="color: #1e293b; margin-top: 25px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px;">Dokumen & Pengalaman</h4>
                    
                    <table class="bio-table">
                        <tr>
                            <th>Berkas Lamaran</th>
                            <td>: <?php if (!empty($file_berkas_db)): ?>
                                    <a href="uploads/<?php echo $file_berkas_db; ?>" target="_blank" style="color: #2563eb; font-weight: bold; text-decoration: none;">👁 Lihat <?php echo $nama_berkas_txt ?? 'Berkas'; ?></a>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Berkas belum diunggah</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Data STR</th>
                            <td>: <?php if (!empty($file_str_db)): ?>
                                    <a href="uploads/<?php echo $file_str_db; ?>" target="_blank" style="color: #2563eb; font-weight: bold; text-decoration: none;">📄 Lihat STR (<?php echo $nomor_str_txt ?? '-'; ?>)</a>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">STR tidak tersedia</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Pengalaman Kerja</th>
                            <td>: <?php if (!empty($teks_pengalaman_db)): ?>
                                    <span style="font-weight: normal; color: #1e293b; line-height: 1.5;"><?php echo $teks_pengalaman_db; ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-style: italic;">Tidak ada riwayat kerja</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                <?php endif; ?>
            </td>

            <!-- PANEL SEBELAH KANAN (FORM INPUT PENILAIAN & PEMBERITAHUAN) -->
            <td class="right-panel-td">
                <div class="form-header">
                    <h3>Form Input Penilaian Tahapan</h3>
                    <button type="button" class="btn-skip" onclick="lewatiTahapan()">Lewati (Skip) Pelamar →</button>
                </div>
                
                <div id="notif-autosave"></div>

                <div class="chrome-tabs-container">
                    <?php if (isset($list_tahapan)): ?>
                        <?php foreach ($list_tahapan as $index => $tahap): ?>
                            <button type="button" class="chrome-tab <?php echo $index === 0 ? 'active' : ''; ?>" data-id="<?php echo $tahap['id']; ?>" onclick="pilihTab(this, <?php echo $tahap['id']; ?>)">
                                <?php echo $tahap['nama_tahapan']; ?>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                 <form id="form-penilaian" style="border-bottom: 2px dashed #cbd5e1; padding-bottom: 25px; margin-bottom: 25px;">
                    <input type="hidden" name="action" value="autosave">
                    <input type="hidden" name="mst_tahapan_id" id="input_mst_tahapan_id" value="<?php echo isset($list_tahapan[0]) ? $list_tahapan[0]['id'] : ''; ?>">
                    <input type="hidden" name="lamaran_tahapan_id" value="<?php echo $id_lamaran_otomatis ?? 0; ?>">
                    <input type="hidden" name="penilai_id" value="<?php echo $id_penilai_otomatis ?? 1; ?>">

                    <div class="form-group">
                        <label>Nilai:</label>
                        <input type="number" step="0.01" name="nilai" id="input_nilai" oninput="hitungStatusOtomatis()" placeholder="Masukkan nilai angka (0 - 100)">
                    </div>

                    <div class="form-group">
                        <label>Status Tahap:</label>
                        <input type="text" name="status_tahap" id="status_tahap_view" class="status-box" value="Proses" readonly style="color: #64748b;">
                    </div>

                    <div class="form-group">
                        <label>Catatan:</label>
                        <textarea name="catatan" id="input_catatan" rows="4" placeholder="Tambahkan catatan penilaian di sini..."></textarea>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" class="btn-simpan" onclick="simpanDataOtomatis()" style="width: 100%; padding: 12px; background-color: #2563eb; color: white; border: none; font-weight: bold; border-radius: 6px; cursor: pointer;">
                            Simpan Penilaian
                        </button>
                    </div>
                </form>

                <div class="form-header" style="margin-top: 10px;">
                    <h3>Form Pemberitahuan Rekrutmen</h3>
                </div>
                
                <form id="form-notifikasi">
                    <input type="hidden" name="action" value="kirim_notifikasi">
                    <input type="hidden" name="lamaran_id" value="<?php echo $id_lamaran_otomatis ?? 0; ?>">

                    <div class="form-group">
                        <label>Jenis Media:</label>
                        <select name="jenis" required>
                            <option value="WhatsApp" selected>WhatsApp</option>
                            <option value="Email">Email</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label id="label-tujuan">Tujuan (Nomor WhatsApp):</label>
                        <input type="text" name="tujuan" required value="<?php echo $data_pelamar['no_hp'] ?? $data_pelamar['telepon'] ?? ''; ?>" placeholder="Contoh: 083846352006">
                    </div>

                    <div class="form-group">
                        <label>Pesan Pemberitahuan:</label>
                        <textarea name="pesan_notif" rows="4" required placeholder="Ketik draf isi pesan pengumuman tahapan pelamar di sini..."></textarea>
                    </div>

                    <div style="margin-top: 15px;">
                        <button type="button" onclick="kirimDanAutoSimpanNotif()" style="width: 100%; padding: 12px; background-color: #16a34a; color: white; border: none; font-weight: bold; border-radius: 6px; cursor: pointer;">
                            Kirim Pemberitahuan
                        </button>
                    </div>
                </form>

                <div style="margin-top: 30px; border-top: 2px dashed #cbd5e1; padding-top: 20px;">
                    <h3 style="color: #1e293b; margin: 0 0 10px 0; font-size: 16px;">Riwayat Pengiriman Pemberitahuan</h3>
                    <?php
                    $id_lamaran_rekap = $id_lamaran_otomatis ?? 0;
                    $q_riwayat = mysqli_query($koneksi, "SELECT jenis, tujuan, pesan, status, created_at FROM notifikasi_rekrutmen WHERE lamaran_id = '$id_lamaran_rekap' ORDER BY id DESC");
                    if ($q_riwayat && mysqli_num_rows($q_riwayat) > 0): ?>
                        <table class="notif-table">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Media</th>
                                    <th>Tujuan</th>
                                    <th>Isi Pesan</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($notif = mysqli_fetch_assoc($q_riwayat)): ?>
                                    <tr>
                                        <td><?php echo date('d/m H:i', strtotime($notif['created_at'])); ?></td>
                                        <td><?php echo $notif['jenis']; ?></td>
                                        <td><?php echo $notif['tujuan']; ?></td>
                                        <td><?php echo htmlspecialchars($notif['pesan']); ?></td>
                                        <td><span class="badge-status badge-sent">Sent</span></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="color: #94a3b8; font-style: italic; margin-top: 10px;">Belum ada riwayat pemberitahuan untuk pelamar ini.</p>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
    </table>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        let formNotif = document.getElementById('form-notifikasi');
        if (formNotif) {
            let selectJenis = formNotif.querySelector('select[name="jenis"]');
            let labelTujuan = document.getElementById('label-tujuan');
            let inputTujuan = formNotif.querySelector('input[name="tujuan"]');

            function sesuaikanForm() {
                if (selectJenis.value === 'WhatsApp') {
                    labelTujuan.innerText = "Tujuan (Nomor WhatsApp):";
                    inputTujuan.placeholder = "Contoh: 083846352006";
                } else {
                    labelTujuan.innerText = "Tujuan (Alamat Email):";
                    inputTujuan.placeholder = "Contoh: pelamar@email.com";
                }
            }
            selectJenis.addEventListener('change', sesuaikanForm);
        }
    });

    function kirimDanAutoSimpanNotif() {
        let form = document.getElementById('form-notifikasi');
        let formData = new FormData(form);
        
        let jenis = form.querySelector('select[name="jenis"]').value;
        let tujuan = form.querySelector('input[name="tujuan"]').value;
        let pesanNotif = form.querySelector('textarea[name="pesan_notif"]').value;

        if (tujuan.trim() === "" || pesanNotif.trim() === "") {
            alert("Harap isi tujuan dan pesan pemberitahuan terlebih dahulu!");
            return;
        }

        let xhr = new XMLHttpRequest();
        xhr.open("POST", "penilaian_tahapan.php", true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                window.location.reload();
            }
        };
        xhr.send(formData);

        if (jenis === 'WhatsApp') {
            let nomorBersih = tujuan.replace(/\D/g, '');
            if (nomorBersih.startsWith('0')) {
                nomorBersih = '62' + nomorBersih.slice(1);
            }
            let urlWA = "https://whatsapp.com" + nomorBersih + "&text=" + encodeURIComponent(pesanNotif);
            window.open(urlWA, '_blank');
        } else if (jenis === 'Email') {
            let urlEmail = "mailto:" + tujuan + "?subject=Pengumuman Seleksi&body=" + encodeURIComponent(pesanNotif);
            window.open(urlEmail, '_self');
        }
    }

    function pilihTab(elemen, idTahapan) {
        if (document.getElementById('input_nilai').value !== "") {
            simpanDataOtomatis();
        }
        let semuaTab = document.querySelectorAll('.chrome-tab');
        semuaTab.forEach(tab => tab.classList.remove('active'));
        elemen.classList.add('active');
        document.getElementById('input_mst_tahapan_id').value = idTahapan;

        let lamaran_tahapan_id = document.querySelector('input[name="lamaran_tahapan_id"]').value;
        let xhr = new XMLHttpRequest();
        xhr.open("GET", "get_nilai_tahapan.php?id_lamaran=" + lamaran_tahapan_id + "&id_mst_tahapan=" + idTahapan, true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    let data = JSON.parse(xhr.responseText);
                    if (data.nilai !== undefined && data.nilai !== null && data.nilai !== "") {
                        document.getElementById('input_nilai').value = data.nilai;
                        document.getElementById('input_catatan').value = data.catatan ? data.catatan : "";
                    } else {
                        document.getElementById('input_nilai').value = "";
                        document.getElementById('input_catatan').value = "";
                    }
                    hitungStatusOtomatis(); 
                } catch (e) {
                    document.getElementById('input_nilai').value = "";
                    document.getElementById('input_catatan').value = "";
                    hitungStatusOtomatis();
                }
            }
        };
        xhr.send();
    }

    function hitungStatusOtomatis() {
        let inputNilai = document.getElementById('input_nilai').value;
        let statusView = document.getElementById('status_tahap_view');
        if (inputNilai === "") {
            statusView.value = "Proses";
            statusView.style.color = "#64748b";
            statusView.style.backgroundColor = "#f8fafc";
            return;
        }
        let nilaiAngka = parseFloat(inputNilai);
        if (nilaiAngka >= 75) {
            statusView.value = "Lulus";
            statusView.style.color = "#15803d";
            statusView.style.backgroundColor = "#f0fdf4";
        } else {
            statusView.value = "Tidak Lulus";
            statusView.style.color = "#b91c1c";
            statusView.style.backgroundColor = "#fef2f2";
        }
    }

    function simpanDataOtomatis() {
        let form = document.getElementById('form-penilaian');
        let formData = new FormData(form);
        let xhr = new XMLHttpRequest();
        xhr.open("POST", "penilaian_tahapan.php", true);
        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4 && xhr.status === 200) {
                let notif = document.getElementById('notif-autosave');
                if (notif) {
                    notif.style.display = 'block';
                    notif.style.backgroundColor = '#f0fdf4';
                    notif.style.color = '#16a34a';
                    notif.innerText = '✓ Nilai tahapan berhasil disimpan!';
                    setTimeout(() => { notif.style.display = 'none'; }, 3000);
                }
            }
        };
        xhr.send(formData);
    }

    function lewatiTahapan() {
        if (confirm("Apakah Anda yakin ingin melewati (skip) penilaian pelamar ini sepenuhnya?")) {
            let lamaran_tahapan_id = document.querySelector('input[name="lamaran_tahapan_id"]').value;
            let formData = new FormData();
            formData.append('action', 'skip_pelamar_global');
            formData.append('lamaran_tahapan_id', lamaran_tahapan_id);

            let xhr = new XMLHttpRequest();
            xhr.open("POST", "penilaian_tahapan.php", true);
            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4 && xhr.status === 200) {
                    window.location.href = "lamaran_tahapan.php";
                }
            };
            xhr.send(formData);
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        let tabPertama = document.querySelector('.chrome-tab');
        if (tabPertama) { tabPertama.click(); }
    });
</script>
</body>
</html>
