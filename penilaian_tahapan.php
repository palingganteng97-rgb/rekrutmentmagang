<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. KONEKSI DATABASE SERVER PUSAT
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$conn = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$conn) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Tangkap parameter ID dari URL (?id=...)
$lamaran_tahapan_id = $_GET['id'] ?? 0;
if (!$lamaran_tahapan_id) {
    die("Akses ilegal: Parameter ID Pelamar tidak ditemukan.");
}

// Inisialisasi data login penilai/admin secara aman
$cek_penilai_db = mysqli_query($conn, "SELECT id FROM penilai LIMIT 1");
if (mysqli_num_rows($cek_penilai_db) > 0) {
    $row_p = mysqli_fetch_assoc($cek_penilai_db);
    $penilai_id = $_SESSION['user_id'] ?? $row_p['id'];
} else {
    mysqli_query($conn, "INSERT INTO penilai (id, nama) VALUES (1, 'Tim Penilai Pusat')");
    $penilai_id = 1;
}

// =========================================================================
// 2. QUERY UTAMA: AMBIL DATA BIODATA LENGKAP PELAMAR (VERSI TANPA JOIN LOWONGAN)
// =========================================================================
$query_pelamar = mysqli_query($conn, "SELECT lt.id AS id_tahapan,
                                             p.*,
                                             p.id AS id_pelamar_murni,
                                             p.nama_lengkap AS nama_pendaftar,
                                             rl.id AS id_lamaran_asli
                                      FROM lamaran_tahapan lt
                                      JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id
                                      JOIN pelamar p ON rl.pelamar_id = p.id
                                      WHERE lt.id = '$lamaran_tahapan_id' LIMIT 1");

$data_pelamar = mysqli_fetch_assoc($query_pelamar);



// LOGIKA INTERSEPTOR PENUTUPAN TAB / NAVIGATOR BEACON
if (isset($_POST['action_meninggalkan_halaman'])) {
    $id_target_tahap = intval($_POST['lamaran_tahapan_id']);
    $query_master_tahapan = mysqli_query($conn, "SELECT id FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1");
    $total_tahapan_wajib = mysqli_num_rows($query_master_tahapan);
    $query_hitung_isi = mysqli_query($conn, "SELECT status_tahap FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$id_target_tahap'");
    $total_terisi = mysqli_num_rows($query_hitung_isi);

    $ada_tidak_lulus = false;
    $ada_skip        = false;

    while ($cek_skor = mysqli_fetch_assoc($query_hitung_isi)) {
        // PERBAIKAN: Menyamakan huruf kapital sesuai data input form dan ENUM database
        $status_tahap_db = strtoupper($cek_skor['status_tahap']);
        if ($status_tahap_db == 'TIDAK LULUS') { $ada_tidak_lulus = true; }
        if ($status_tahap_db == 'DILEWATI') { $ada_skip = true; }
    }

    if ($total_terisi < $total_tahapan_wajib) {
        $status_pulang = "Pending";
    } else {
        if ($ada_tidak_lulus) { $status_pulang = "Tidak Lulus"; }
        elseif ($ada_skip) { $status_pulang = "Skip"; }
        else { $status_pulang = "Lulus"; }
    }

    mysqli_query($conn, "UPDATE lamaran_tahapan SET status = '$status_pulang' WHERE id = '$id_target_tahap'");
    exit;
}

if ($data_pelamar) {
    mysqli_query($conn, "UPDATE lamaran_tahapan SET status = 'Proses' WHERE id = '$lamaran_tahapan_id'");
}

// =========================================================================
// 3. AMBIL DATA DETAIL LAMPIRAN BERKAS & RIWAYAT PENGALAMAN KERJA
// =========================================================================
$list_berkas     = ['ijazah' => '', 'str' => '']; 
$list_pengalaman = []; 

if ($data_pelamar && !empty($data_pelamar['id_pelamar_murni'])) {
    $id_pelamar_target = $data_pelamar['id_pelamar_murni'];

    $query_berkas = mysqli_query($conn, "SELECT * FROM pelamar_berkas WHERE pelamar_id = '$id_pelamar_target'");
    if ($query_berkas && mysqli_num_rows($query_berkas) > 0) {
        while ($row_bk = mysqli_fetch_assoc($query_berkas)) {
            $jenis_clean = strtolower(trim($row_bk['nama_berkas'] ?? $row_bk['jenis_berkas'] ?? ''));
            $file_asli   = $row_bk['file_berkas'] ?? $row_bk['nama_file'] ?? $row_bk['file'] ?? '';

            if (stripos($jenis_clean, 'ijazah') !== false) { $list_berkas['ijazah'] = $file_asli; }
            if (stripos($jenis_clean, 'str') !== false) { $list_berkas['str'] = $file_asli; }
        }
    }

    if (empty($list_berkas['str'])) {
        $query_tabel_str = mysqli_query($conn, "SELECT * FROM pelamar_str WHERE pelamar_id = '$id_pelamar_target' LIMIT 1");
        if ($query_tabel_str && mysqli_num_rows($query_tabel_str) > 0) {
            $row_str = mysqli_fetch_assoc($query_tabel_str);
            foreach ($row_str as $nama_kolom => $isi_kolom) {
                if (is_string($isi_kolom) && preg_match('/\.(pdf|jpg|jpeg|png)$/i', trim($isi_kolom))) {
                    $list_berkas['str'] = trim($isi_kolom);
                    break; 
                }
            }
        }
    }

    $query_kerja = mysqli_query($conn, "SELECT * FROM pelamar_pengalaman WHERE pelamar_id = '$id_pelamar_target' ORDER BY id DESC");
    if ($query_kerja && mysqli_num_rows($query_kerja) > 0) {
        while ($row_exp = mysqli_fetch_assoc($query_kerja)) {
            $row_exp['jabatan']    = $row_exp['jabatan'] ?? $row_exp['posisi'] ?? '-';
            $row_exp['perusahaan'] = $row_exp['perusahaan'] ?? $row_exp['nama_perusahaan'] ?? $row_exp['nama_instansi'] ?? '-';
            $list_pengalaman[]     = $row_exp;
        }
    }
}

// =========================================================================
// 4. MASTER KONTROL TAB SELEKSI & LOGIKA SINKRONISASI GET/POST
// =========================================================================
$query_master_tahapan = mysqli_query($conn, "SELECT id, nama_tahapan FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1 ORDER BY id ASC");
$list_tabs = [];
while ($t = mysqli_fetch_assoc($query_master_tahapan)) {
    $list_tabs[] = $t;
}

$tab_default_aktif = $list_tabs[0]['id'] ?? 0;
$mst_tahapan_id_aktif = $_GET['tahapan_id'] ?? $_POST['mst_tahapan_id'] ?? $tab_default_aktif;
$tab_default_aktif    = $mst_tahapan_id_aktif;

$success_msg = "";
$error_msg   = "";

// =========================================================================
// 5. LOGIKA PROSES SIMPAN / UPDATE EVALUASI TAHAPAN & PENGIRIMAN API
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mst_tahapan_id = $_POST['mst_tahapan_id'] ?? $_GET['tahapan_id'] ?? $tab_default_aktif; 
    $tanggal_sekarang = date('Y-m-d H:i:s');

    // Menerima request baik dari tombol "Simpan" biasa maupun tombol "Kirim Notifikasi"
    if (isset($_POST['nilai']) || isset($_POST['kirim_pemberitahuan']) || isset($_POST['simpan_nilai_saja'])) {
        
        $id_lamaran_induk = $data_pelamar['id_lamaran_asli'] ?? 0;
        
        $nilai_kompetensi = mysqli_real_escape_string($conn, $_POST['nilai']);
        $catatan_penilai  = mysqli_real_escape_string($conn, $_POST['catatan']);
        $jenis_media      = mysqli_real_escape_string($conn, $_POST['jenis'] ?? 'WhatsApp'); 
        $tujuan_kirim     = mysqli_real_escape_string($conn, $_POST['tujuan'] ?? ''); 

        $nama_tahap_aktif = "Tahapan Seleksi";
        foreach ($list_tabs as $t) {
            if ($t['id'] == $mst_tahapan_id) {
                $nama_tahap_aktif = $t['nama_tahapan'];
                break;
            }
        }

        $status_tahap = ($nilai_kompetensi >= 75) ? 'LULUS' : 'TIDAK LULUS';

        // 3. PROSES SIMPAN / UPDATE DATA EVALUASI KE TABEL `penilaian_tahapan`
        $cek_penilaian = mysqli_query($conn, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id' LIMIT 1");
        
        if (mysqli_num_rows($cek_penilaian) > 0) {
            $query_save = "UPDATE penilaian_tahapan 
                           SET nilai = '$nilai_kompetensi', status_tahap = '$status_tahap', catatan = '$catatan_penilai', penilai_id = '$penilai_id', updated_at = '$tanggal_sekarang' 
                           WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'";
        } else {
            $query_save = "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, status_tahap, catatan, created_at, updated_at) 
                           VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', '$nilai_kompetensi', '$status_tahap', '$catatan_penilai', '$tanggal_sekarang', '$tanggal_sekarang')";
        }
        $simpan_nilai_sukses = mysqli_query($conn, $query_save);

        // DEFAULT FEEDBACK ALERT JIKA HANYA SIMPAN NILAI (TOMBOL BIRU)
        if ($simpan_nilai_sukses) {
            $success_msg = "Skor hasil penilaian kompetensi berhasil disimpan ke database!";
        }

        // Inisialisasi status log bawaan sebelum diubah oleh API gateway
        $status_log = 'Pending';

        // =========================================================================
        // LOGIKA WHATSAPP HANYA BERJALAN JIKA TOMBOL PEMBERITAHUAN DIKLIK
        // =========================================================================
        if (isset($_POST['kirim_pemberitahuan']) && $jenis_media === 'WhatsApp' && !empty($tujuan_kirim) && $simpan_nilai_sukses) {
            
            // 4. SUSUN STRUKTUR PESAN WHATSAPP HASIL PENILAIAN

$pesan_custom_penilai = trim($_POST['pesan_custom'] ?? '');
$nama_kandidat = htmlspecialchars($data_pelamar['nama_pendaftar'] ?? 'Kandidat');

$pesan_notif  = "Yth. *{$nama_kandidat}*,\n\n";
$pesan_notif .= "Terima kasih telah mengikuti proses seleksi rekrutmen yang diselenggarakan oleh Rumah Sakit.\n\n";

if ($status_tahap === 'LULUS') {

    $pesan_notif .= "📋 *HASIL EVALUASI TAHAPAN*\n";
    $pesan_notif .= "Status : *LULUS (MEMENUHI SYARAT)* ✅\n\n";

    $pesan_notif .= "Selamat, Anda dinyatakan memenuhi kriteria pada tahapan seleksi ini dan berhak melanjutkan ke tahapan berikutnya.\n";
    $pesan_notif .= "Informasi jadwal, lokasi, serta ketentuan lanjutan akan disampaikan melalui sistem rekrutmen.\n";

} else {

    $pesan_notif .= "📋 *HASIL EVALUASI TAHAPAN*\n";
    $pesan_notif .= "Status : *TIDAK LULUS* ❌\n\n";

    $pesan_notif .= "Terima kasih atas partisipasi dan waktu yang telah Anda berikan dalam proses seleksi ini.\n";
    $pesan_notif .= "Berdasarkan hasil evaluasi, Anda belum dapat melanjutkan ke tahapan berikutnya.\n";
}

if (!empty($catatan_penilai) && strtolower(trim($catatan_penilai)) != 'oke') {
    $pesan_notif .= "\n📝 *Catatan Tim Penilai*\n";
    $pesan_notif .= $catatan_penilai . "\n";
}

if (!empty($pesan_custom_penilai)) {
    $pesan_notif .= "\n📌 *Informasi Tambahan*\n";
    $pesan_notif .= $pesan_custom_penilai . "\n";
}

$pesan_notif .= "\n-----------------------------------";
$pesan_notif .= "\nPesan ini dikirim otomatis oleh";
$pesan_notif .= "\n*Sistem Rekrutmen Rumah Sakit*";
$pesan_notif .= "\nMohon tidak membalas pesan ini.";

                // 5. TEMBAK API CURL KE GATEWAY LOKAL

                $tujuan_kirim = preg_replace('/[^0-9]/', '', $tujuan_kirim);

                // BIARKAN FORMAT ASLI (0838xxxx)
                // Jangan ubah ke 62 dulu sebelum yakin gateway membutuhkannya

                $curl = curl_init();

                curl_setopt_array($curl, array(
                    CURLOPT_URL => 'http://10.10.6.17:8000/kirim-pesan',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => 10,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => http_build_query([
                        'number'  => $tujuan_kirim,
                        'message' => $pesan_notif
                    ]),
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/x-www-form-urlencoded'
                    ]
                ));

                $response = curl_exec($curl);
                $err = curl_error($curl);
                $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                curl_close($curl);

                // PENENTUAN STATUS
                if (!$err) {
                    $res_data = json_decode($response, true);

                    if (
                        isset($res_data['status']) &&
                        (
                            $res_data['status'] === true ||
                            $res_data['status'] === 'true' ||
                            $res_data['status'] === 'success'
                        )
                    ) {
                        $status_log = 'Terkirim';
                    } else {
                        $status_log = 'Gagal';
                    }
                } else {
                    $status_log = 'Gagal';
                }

            // 6. CATAT RIWAYAT TRANSAKSI KE TABEL `notifikasi_rekrutmen`
            $query_log = "INSERT INTO notifikasi_rekrutmen (lamaran_id, jenis, tujuan, pesan, status, tanggal_kirim, created_at, updated_at) 
                          VALUES ('$id_lamaran_induk', '$jenis_media', '$tujuan_kirim', '$pesan_notif', '$status_log', '$tanggal_sekarang', '$tanggal_sekarang', '$tanggal_sekarang')";
            mysqli_query($conn, $query_log);

            // 7. BERIKAN KOMUNIKASI ALERT FEEDBACK DI HALAMAN WEB
            if ($status_log === 'Terkirim') {
                $success_msg = "Penilaian disimpan! Pemberitahuan hasil resmi telah sukses dikirim via WhatsApp ke nomor " . htmlspecialchars($tujuan_kirim);
                $error_msg = ""; 
            } else {
                $error_msg = "Skor nilai berhasil disimpan, tetapi pesan WhatsApp <strong>Gagal Terkirim</strong>. Pastikan gateway di IP 10.10.6.17 port 8000 dalam keadaan aktif.";
                $success_msg = ""; 
            }
        }

        // Ambil ulang record data nilai terbaru untuk me-render isian form input value kembali
        $query_refresh = mysqli_query($conn, "SELECT * FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id' LIMIT 1");
        $data_nilai = mysqli_fetch_assoc($query_refresh);
    } 
} 

// =========================================================================
// 7. KUERI FALLBACK DEFAULT (DI LUAR BLOK POST)
// =========================================================================
if (!isset($data_nilai)) {
    $query_load_nilai = mysqli_query($conn, "SELECT * FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$tab_default_aktif' LIMIT 1");
    $data_nilai = mysqli_fetch_assoc($query_load_nilai);
}

if (!$data_nilai) {
    $data_nilai = [
        'id'                 => '',
        'lamaran_tahapan_id' => $lamaran_tahapan_id,
        'mst_tahapan_id'     => $tab_default_aktif,
        'penilai_id'         => $penilai_id,
        'nilai'              => null, 
        'status_tahap'       => '-',  
        'catatan'            => ''    
    ];
}

if (isset($data_nilai['nilai']) && ($data_nilai['nilai'] === null || $data_nilai['nilai'] === '')) {
    $data_nilai['status_tahap'] = '-';
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Penilaian Tahapan</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8fafc; padding: 40px; color: #334155; }
        
        .btn-back { display: inline-block; text-decoration: none; color: #64748b; font-size: 14px; margin-bottom: 20px; font-weight: 600; }
        .btn-back:hover { color: #4f46e5; }

        /* CONTAINER UTAMA */
        .main-wrapper { 
            max-width: 1250px; 
            margin: 0 auto; 
            display: flex; 
            gap: 30px; 
            align-items: stretch; /* SEBELUMNYA flex-start, UBAH MENJADI stretch AGAR TINGGI KANAN KIRI SELALU SAMA */
        }

        /* PANEL KIRI: DETAIL PROFIL */
        .profile-panel { 
            flex: 0 0 400px; 
            background: white; 
            padding: 24px; 
            border-radius: 16px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
            border: 1px solid #e2e8f0; 
            display: flex;         /* Ditambahkan agar layout di dalam panel kiri mengikuti flex */
            flex-direction: column;
            height: 100%;          /* Memaksa mengambil tinggi maksimal wrapper */
        }

        .profile-header-box { text-align: center; margin-bottom: 25px; }
        .profile-avatar-box { width: 100px; height: 100px; background: #f1f5f9; border-radius: 50%; margin: 0 auto 14px auto; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #94a3b8; font-weight: bold; border: 3px solid #e2e8f0; overflow: hidden; }
        .profile-avatar-box img { width: 100%; height: 100%; object-fit: cover; }
        .profile-main-name { font-size: 18px; font-weight: 700; color: #0f172a; margin-bottom: 4px; }
        .profile-main-sub { font-size: 13px; color: #64748b; }

        .info-section { margin-bottom: 24px; }
        .section-divider { font-size: 11px; font-weight: 700; color: #4f46e5; text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 1px dashed #e2e8f0; padding-bottom: 6px; margin-bottom: 12px; text-align: left; }
        .data-row { display: flex; justify-content: space-between; align-items: flex-start; padding: 8px 0; font-size: 13px; border-bottom: 1px solid #f8fafc; }
        .data-label { color: #94a3b8; font-weight: 500; text-align: left; flex: 0 0 130px; }
        .data-value { color: #334155; font-weight: 600; text-align: right; flex: 1; }
        .no-data-text { font-size: 13px; color: #94a3b8; font-style: italic; text-align: left; padding: 4px 0; }

        /* BUTTONS DOKUMEN */
        .btn-view-doc { display: block; width: 100%; text-align: center; padding: 10px; font-size: 13px; font-weight: 600; text-decoration: none; border-radius: 8px; transition: background 0.2s; }
        .blue-btn { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .blue-btn:hover { background: #bae6fd; }
        .berkas-item-row { display: flex; justify-content: space-between; align-items: center; padding: 10px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 8px; }
        .berkas-name { font-size: 13px; font-weight: 600; color: #334155; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 230px; }
        .btn-view-doc-small { background: #4f46e5; color: white; text-decoration: none; font-size: 11px; font-weight: 700; padding: 5px 12px; border-radius: 6px; transition: background 0.2s; }
        .btn-view-doc-small:hover { background: #4338ca; }

/* CONTAINER UTAMA */
.main-wrapper { 
    max-width: 1250px; 
    margin: 0 auto; 
    display: flex; 
    gap: 30px; 
    align-items: flex-start; /* Dikembalikan ke flex-start agar stabil */
}

/* PANEL KIRI: DETAIL PROFIL (DIKUNCI TINGGINYA) */
.profile-panel { 
    flex: 0 0 400px; 
    background: white; 
    padding: 24px; 
    border-radius: 16px; 
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
    border: 1px solid #e2e8f0; 
    
    /* KUNCI TINGGI DI SINI AGAR SAMA RATA DENGAN PANEL KANAN */
    height: 620px;            
    overflow-y: auto;          /* Menampilkan scrollbar vertikal internal yang rapi */
    box-sizing: border-box;
}

/* PANEL KANAN: FORM PENILAIAN (DIKUNCI TINGGINYA) */
.form-panel { 
    flex: 1; 
    background: white; 
    padding: 30px; 
    border-radius: 16px; 
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); 
    border: 1px solid #e2e8f0; 
    
    /* KUNCI TINGGI YANG SAMA DENGAN PANEL KIRI */
    height: 620px;            
    display: flex;
    flex-direction: column;
    justify-content: space-between; /* Memaksa tombol simpan selalu presisi di batas bawah */
    box-sizing: border-box;
}


        h1 { font-size: 22px; margin-bottom: 20px; color: #0f172a; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; color: #334155; }
        .form-control { width: 100%; padding: 11px 14px; border: 1px solid #cbd5e1; border-radius: 8px; box-sizing: border-box; font-size: 14px; background-color: #f8fafc; transition: all 0.2s; }
        .form-control:focus { background-color: #fff; border-color: #4f46e5; outline: none; box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1); }
        
        /* CHROME TAB SYSTEM */
        .chrome-tabs-container { display: flex; background-color: #e2e8f0; padding: 6px 6px 0 6px; border-radius: 12px 12px 0 0; gap: 4px; overflow-x: auto; margin-bottom: 20px; border-bottom: 1px solid #cbd5e1; scrollbar-width: none; -ms-overflow-style: none; }
        .chrome-tabs-container::-webkit-scrollbar { display: none; width: 0; height: 0; }

        .chrome-tab { padding: 10px 16px; background-color: #f1f5f9; color: #475569; border-radius: 10px 10px 0 0; font-size: 12px; font-weight: 700; cursor: pointer; white-space: nowrap; transition: all 0.15s; }
.chrome-tab.active { background-color: #ffffff; color: #4f46e5; border: 1px solid #cbd5e1; border-bottom: none; position: relative; margin-bottom: -1px; padding-bottom: 11px; }

/* BUTTONS & ALERTS */
.btn-submit { background-color: #4f46e5; color: white; padding: 12px 20px; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 15px; transition: background 0.2s; width: 100%; }
.btn-submit:hover { background-color: #4338ca; }
.btn-skip { background-color: #f59e0b; color: white; border: none; padding: 12px 24px; font-size: 15px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
.btn-skip:hover { background-color: #d97706; }

.alert { padding: 14px; border-radius: 8px; font-size: 14px; margin-bottom: 20px; font-weight: 500; }
.alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
.alert-error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

input[type=number]::-webkit-inner-spin-button, input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
input[type=number] { -moz-appearance: textfield; }
</style>
</head>
<body>

<div style="max-width: 1250px; margin: 0 auto;">
    <a href="lamaran_tahapan.php" class="btn-back">&larr; Kembali ke Daftar Progress</a>
</div>

<div class="main-wrapper">

<!-- ================= SISI KIRI: DATA UTUH DOKUMEN LAMARAN KERJA PELAMAR (READ ONLY) ================= -->
<!-- PERBAIKAN: Menambahkan padding vertikal dan height auto agar fleksibel mengisi ruang -->
<div class="profile-panel" style="padding: 15px 20px; box-sizing: border-box; height: auto;">
    
    <!-- Bagian Foto & Nama Utama Pelamar -->
    <div class="profile-header-box" style="margin-bottom: 25px;">
        <div class="profile-avatar-box">
            <?php if(!empty($data_pelamar['foto'])): ?>
                <img src="uploads/<?= $data_pelamar['foto']; ?>" alt="Foto Profil Pelamar">
            <?php elseif(!empty($data_pelamar['foto_pelamar'])): ?>
                <img src="uploads/<?= $data_pelamar['foto_pelamar']; ?>" alt="Foto Profil Pelamar">
            <?php else: ?>
                <div style="width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:#0d6efd; color:white; font-size:32px; font-weight:bold;">
                    <?= strtoupper(substr($data_pelamar['nama_lengkap'] ?? 'K', 0, 1)); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="profile-main-name" style="margin-top: 10px; font-weight: 700;"><?= htmlspecialchars($data_pelamar['nama_lengkap'] ?? '-'); ?></div>
        <div class="profile-main-sub" style="margin-top: 4px;">Melamar Posisi: <strong><?= htmlspecialchars($data_pelamar['nama_lowongan'] ?? '-'); ?></strong></div>
    </div>

    <!-- SEKTOR 1: DATA IDENTITAS PRIBADI -->
    <!-- PERBAIKAN: Menaikkan margin-bottom sektor menjadi 30px & margin-bottom baris menjadi 14px agar meregang proporsional -->
    <div class="info-section" style="margin-bottom: 30px;">
        <div class="section-divider" style="margin-bottom: 15px; font-weight: 700; color: #4f46e5;">I. Biodata Pribadi Pelamar</div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Nama Lengkap</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['nama_lengkap'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">NIK (No. KTP)</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b; font-family: monospace;"><?= htmlspecialchars($data_pelamar['nik'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Tempat, Tgl Lahir</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;">
                <?= htmlspecialchars($data_pelamar['tempat_lahir'] ?? '-'); ?>, 
                <?= !empty($data_pelamar['tanggal_lahir']) ? date('d M Y', strtotime($data_pelamar['tanggal_lahir'])) : '-'; ?>
            </span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Jenis Kelamin</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b"><?= htmlspecialchars($data_pelamar['jenis_kelamin'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Agama</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['agama'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Status Perkawinan</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['status_sosial'] ?? $data_pelamar['status_hubungan'] ?? '-'); ?></span>
        </div>
    </div>

    <!-- SEKTOR 2: DATA KONTAK & ALAMAT TINGGAL -->
    <div class="info-section" style="margin-bottom: 30px;">
        <div class="section-divider" style="margin-bottom: 15px; font-weight: 700; color: #4f46e5;">II. Kontak & Alamat Domisili</div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Nomor Telepon/WA</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b; font-family: monospace;"><?= htmlspecialchars($data_pelamar['no_telepon'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Alamat Email</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b; word-break: break-all;"><?= htmlspecialchars($data_pelamar['email'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Kota / Kabupaten</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['kota'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Provinsi</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-weight: 500; color: #1e293b;"><?= htmlspecialchars($data_pelamar['provinsi'] ?? '-'); ?></span>
        </div>
        
        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">Alamat Lengkap Rumah</span>
            <span style="width: 15px; color: #94a3b8; text-align: center;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; line-height: 1.5; color: #475569; font-weight: 500; word-break: break-word;"><?= htmlspecialchars($data_pelamar['alamat'] ?? '-'); ?></span>
        </div>
    </div>

<!-- SEKTOR 3: RIWAYAT PENDIDIKAN PELAMAR -->
<div class="info-section" style="margin-bottom: 30px;">
    <div class="section-divider" style="margin-bottom: 15px; font-weight: 700; color: #4f46e5;">
        III. Kualifikasi Pendidikan
    </div>

    <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
        <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">
            Pendidikan Terakhir
        </span>

        <span style="width: 15px; color: #94a3b8; text-align: center;">
            :
        </span>

        <span class="data-value" style="flex: 1; text-align: left; color: #1e293b; font-weight: 700;">
            <?= htmlspecialchars($data_pelamar['pendidikan'] ?? '-'); ?>    </div>
        <?php if(!empty($data_pelamar['institusi']) || !empty($data_pelamar['jurusan'])): ?>

        <div class="data-row" style="display: flex; align-items: flex-start; margin-bottom: 14px;">
            <span class="data-label" style="width: 160px; flex-shrink: 0; color: #64748b;">
                Nama Institusi
            </span>

            <span style="width: 15px; color: #94a3b8; text-align: center;">
                :
            </span>

            <span class="data-value" style="flex: 1; text-align: left; color: #1e293b;">
                <?= htmlspecialchars($data_pelamar['institusi'] ?? '-'); ?>
            </span>
        </div>

    <?php endif; ?>

</div>

    <!-- =========================================================================
         SEKTOR 4: DOKUMEN PENDUKUNG (IJAZAH & STR)
         ========================================================================= -->
    <div class="info-section" style="margin-top: 15px;">
        <div class="section-divider" style="margin-bottom: 10px; font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase;">IV. Dokumen Pendukung</div>
        
        <!-- Baris Dokumen Ijazah -->
        <div class="data-row" style="display: flex; align-items: center; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0; font-size: 13px;">Dokumen Ijazah</span>
            <span style="width: 15px; color: #94a3b8; text-align: center; font-size: 13px;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-size: 13px;">
                <?php if (!empty($list_berkas['ijazah'])): ?>
                    <a href="uploads/<?= $list_berkas['ijazah']; ?>" target="_blank" style="color: #0d6efd; font-weight: 600; text-decoration: none;">
                        👁️ Buka Ijazah (Dapat Dilihat)
                    </a>
                <?php else: ?>
                    <span style="color: #dc3545; font-weight: 500;">Belum Diunggah</span>
                <?php endif; ?>
            </span>
        </div>

        <!-- Baris Dokumen STR (Surat Tanda Registrasi) -->
        <div class="data-row" style="display: flex; align-items: center; margin-bottom: 8px;">
            <span class="data-label" style="width: 200px; flex-shrink: 0; font-size: 13px;">Dokumen STR</span>
            <span style="width: 15px; color: #94a3b8; text-align: center; font-size: 13px;">:</span>
            <span class="data-value" style="flex: 1; text-align: left; font-size: 13px;">
                <?php if (!empty($list_berkas['str'])): ?>
                    <a href="uploads/<?= $list_berkas['str']; ?>" target="_blank" style="color: #0d6efd; font-weight: 600; text-decoration: none;">
                        👁️ Buka STR (Dapat Dilihat)
                    </a>
                <?php elseif (!empty($list_str[0]['file_str'])): ?>
                    <a href="uploads/<?= $list_str[0]['file_str']; ?>" target="_blank" style="color: #0d6efd; font-weight: 600; text-decoration: none;">
                        👁️ Buka STR (Dapat Dilihat)
                    </a>
                <?php else: ?>
                    <span style="color: #64748b; font-weight: 500;">Tidak Ada / Tidak Wajib</span>
                <?php endif; ?>
            </span>
        </div>
    </div>

    <!-- =========================================================================
         SEKTOR 5: RIWAYAT PENGALAMAN KERJA (LOOP DYNAMIC LIST)
         ========================================================================= -->
    <div class="info-section" style="margin-top: 15px; margin-bottom: 0;">
        <div class="section-divider" style="margin-bottom: 10px; font-size: 12px; font-weight: 700; color: #4f46e5; text-transform: uppercase;">V. Pengalaman Kerja</div>
        
        <?php if (!empty($list_pengalaman)): ?>
            <?php foreach ($list_pengalaman as $index => $exp): ?>
                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px; margin-bottom: 8px; font-size: 13px;">
                    <div style="font-weight: 700; color: #1e293b; margin-bottom: 2px;">
                        <?= htmlspecialchars($exp['jabatan'] ?? $exp['posisi'] ?? '-'); ?>
                    </div>
                    <div style="color: #475569; font-weight: 500; margin-bottom: 4px;">
                        🏢 <?= htmlspecialchars($exp['perusahaan'] ?? $exp['nama_perusahaan'] ?? '-'); ?>
                    </div>
                    <div style="font-size: 11px; color: #64748b;">
                        📅 Periode: 
                        <?= !empty($exp['mulai_kerja']) ? date('d M Y', strtotime($exp['mulai_kerja'])) : '-'; ?> 
                        s/d 
                        <?= (!empty($exp['selesai_kerja']) && $exp['selesai_kerja'] != '0000-00-00') ? date('d M Y', strtotime($exp['selesai_kerja'])) : 'Sekarang'; ?>
                    </div>
                    <?php if (!empty($exp['alasan_keluar'])): ?>
                        <div style="font-size: 11px; color: #94a3b8; margin-top: 4px; font-style: italic;">
                            Alasan Keluar: <?= htmlspecialchars($exp['alasan_keluar']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="font-size: 13px; color: #64748b; font-style: italic; background: #f8fafc; padding: 10px; border-radius: 6px; border: 1px dashed #cbd5e1; text-align: center;">
                Pelamar tidak memiliki/mencantumkan riwayat pengalaman kerja (Fresh Graduate).
            </div>
        <?php endif; ?>
    </div>

</div>
    
<!-- ================= SISI KANAN: PANEL PENILAIAN UTAMU (PROPROPIONAL & SIMETRIS) ================= -->
<div style="display: flex; flex-direction: column; gap: 12px; width: 100%; box-sizing: border-box;">

    <!-- ==================== KOTAK 1: INPUT PENILAIAN CORE (LINIER KE BAWAH) ==================== -->
    <div style="padding: 15px; background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; margin-bottom: 15px;">
        
        <h1 style="font-size: 18px; font-weight: 700; margin-top: 0; margin-bottom: 12px; color: #1e293b; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
            Input Penilaian Tahapan
        </h1>

        <!-- Alert Status Feedback -->
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success" style="margin-bottom: 12px; padding: 8px; background-color: #d1fae5; color: #065f46; border-radius: 6px; font-size: 13px; font-weight: 600;"><?= $success_msg; ?></div>
        <?php endif; ?>
        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-error" style="margin-bottom: 12px; padding: 8px; background-color: #fee2e2; color: #991b1b; border-radius: 6px; font-size: 13px; font-weight: 600;"><?= $error_msg; ?></div>
        <?php endif; ?>

        <!-- FORM UTAMA MEMBUNGKUS SELURUH KOTAK -->
        <form action="" method="POST" id="formPenilaian" style="display: flex; flex-direction: column; gap: 12px;">
            
            <!-- 1. PILIHAN TAHAPAN SELEKSI (MELEBAR PENUH KE KANAN) -->
            <div class="form-group" style="width: 100%;">
                <label style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 6px; display: block;">Pilih Tahapan Seleksi</label>
                <div class="chrome-tabs-container" style="display: flex; gap: 6px; flex-wrap: wrap; width: 100%;">
                    <?php foreach ($list_tabs as $index => $tab) : 
                        $is_active = ($tab['id'] == $tab_default_aktif);
                    ?>
                        <div class="chrome-tab <?= $is_active ? 'active' : ''; ?>" 
                             id="tab-control-<?= $tab['id']; ?>"
                             onclick="location.href='?id=<?= $lamaran_tahapan_id; ?>&tahapan_id=<?= $tab['id']; ?>'"
                             style="padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; transition: all 0.2s;
                                    background-color: <?= $is_active ? '#0284c7 !important' : '#e2e8f0'; ?>;
                                    color: <?= $is_active ? '#ffffff !important' : '#475569'; ?>;
                                    font-weight: <?= $is_active ? '700' : 'normal'; ?>;
                                    border: 1px solid <?= $is_active ? '#0284c7' : '#cbd5e1'; ?>;">
                            <?= htmlspecialchars($tab['nama_tahapan']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <input type="hidden" name="mst_tahapan_id" id="input_mst_tahapan_id" value="<?= $tab_default_aktif; ?>">
            </div>

            <!-- ================= PERBAIKAN: STRUKTUR INPUT VERTIKAL BERURUTAN KE BAWAH ================= -->
            <div style="display: flex; flex-direction: column; gap: 12px; width: 100%; margin-top: 5px;">
                
                            <!-- 3. STATUS OTOMATIS (SEKARANG BERADA DI BAWAH NILAI) -->
                <div class="form-group" style="display: flex; align-items: center; gap: 10px; background-color: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0; width: 100%; box-sizing: border-box; height: 38px;">
                    <label style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 0;">Status Kelulusan Otomatis:</label>
                    <span id="badge_status_otomatis" style="padding: 3px 10px; border-radius: 4px; font-size: 12px; font-weight: 700; background: #e2e8f0; color: #64748b; letter-spacing: 0.5px;">
                        <?= !empty($data_nilai['status_tahap']) ? strtoupper($data_nilai['status_tahap']) : '-'; ?>
                    </span>
                </div>

                <!-- 2. INPUT NILAI KOMPETENSI -->
                <div class="form-group" style="width: 100%;">
                    <label style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Nilai Kompetensi (0.00 - 100.00)</label>
                    <input type="number" 
                           name="nilai" 
                           id="input_nilai_kompetensi"
                           class="form-control" 
                           step="0.01" 
                           min="0" 
                           max="100" 
                           placeholder="Contoh: 85.50" 
                           value="<?= isset($data_nilai['nilai']) && $data_nilai['nilai'] !== null ? htmlspecialchars($data_nilai['nilai']) : ''; ?>" 
                           oninput="hitungStatusOtomatis(this.value)"
                           style="padding: 8px 12px; font-size: 13px; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; height: 36px;"
                           required>
                </div>

                <!-- 4. CATATAN REKOMENDASI PENILAI (SEKARANG BERADA DI BAWAH STATUS) -->
                <div class="form-group" style="width: 100%;">
                    <label style="font-size: 12px; font-weight: 600; color: #475569; margin-bottom: 4px; display: block;">Catatan / Rekomendasi Penilai</label>
                    <textarea name="catatan" 
                              class="form-control" 
                              rows="2" 
                              placeholder="Tuliskan feedback hasil evaluasi teknis kandidat..." 
                              style="padding: 8px 12px; font-size: 13px; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; resize: vertical;" 
                              required><?= isset($data_nilai['catatan']) ? htmlspecialchars($data_nilai['catatan']) : ''; ?></textarea>
                </div>

                <!-- TOMBOL SIMPAN PENILAIAN (DI PALING BAWAH KOTAK 1) -->
                <div style="width: 100%; margin-top: 4px;">
                    <button type="submit" name="simpan_nilai_saja" style="width: 100%; background-color: #2563eb; color: white; border: none; padding: 10px; border-radius: 6px; font-weight: 700; font-size: 13px; cursor: pointer; text-align: center; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.1); height: 38px; display: flex; align-items: center; justify-content: center;">
                        Simpan Hasil Penilaian
                    </button>
                </div>

            </div>
    </div> <!-- Batas akhir penutup Kotak 1 fisik -->

    <!-- ==================== KOTAK 2: KHUSUS PENGATURAN & RIWAYAT NOTIFIKASI (VERTIKAL LINIER) ==================== -->
    <div style="padding: 15px; background: #ffffff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0;">
        
        <h2 style="font-size: 15px; font-weight: 700; color: #1e293b; margin-top: 0; margin-bottom: 12px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
            Pengaturan Notifikasi Hasil Pendaftaran
        </h2>

        <!-- CONTAINER UTAMA KOTAK 2: Mengalir Tegak Lurus ke Bawah -->
        <div style="display: flex; flex-direction: column; gap: 12px; width: 100%;">
            
            <!-- BARIS 1: Kirim Lewat (Kiri) & Tujuan Pengiriman (Kanan) -->
            <div style="display: flex; gap: 10px; width: 100%;">
                <div style="flex: 1;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 3px; display: block;">Kirim Lewat</label>
                    <select name="jenis" id="select_jenis_notif" class="form-control" style="padding: 6px 8px; font-size: 12px; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; background-color: #fff; height: 32px; cursor: pointer;" onchange="updateTujuanNotifikasi()" required>
                        <option value="WhatsApp">WhatsApp</option>
                        <option value="Email">Email</option>
                        <option value="SMS">SMS</option>
                    </select>
                </div>
                <div style="flex: 1.5;">
                    <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 3px; display: block;">Tujuan Pengiriman</label>
                    <input type="text" name="tujuan" id="input_tujuan_notif" class="form-control" style="padding: 6px 8px; font-size: 12px; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; height: 32px;" required>
                </div>
            </div>

            <!-- BARIS 2: Kolom Pesan Tambahan Penilai (Berada di Bawahnya) -->
            <div class="form-group" style="width: 100%;">
                <label style="font-size: 11px; font-weight: 600; color: #475569; margin-bottom: 3px; display: block;">Pesan Tambahan Penilai</label>
                <textarea name="pesan_custom" 
                          class="form-control" 
                          rows="2" 
                          placeholder="Tulis pesan tambahan resmi di sini jika ada..." 
                          style="padding: 6px 8px; font-size: 12px; width: 100%; box-sizing: border-box; border: 1px solid #cbd5e1; border-radius: 6px; resize: vertical; min-height: 45px;"></textarea>
            </div>

            <!-- BARIS 3: Riwayat Log Notifikasi Terakhir (Berada di Bawah Pesan) -->
            <?php
            $id_lamaran_induk = $data_pelamar['id_lamaran_asli'] ?? 0;
            $query_cek_log = mysqli_query($conn, "SELECT jenis, tujuan, status, tanggal_kirim FROM notifikasi_rekrutmen WHERE lamaran_id = '$id_lamaran_induk' ORDER BY id DESC LIMIT 1");
            
            if (mysqli_num_rows($query_cek_log) > 0) : 
                $log_notif = mysqli_fetch_assoc($query_cek_log);
                $badge_color = '#e2e8f0'; $text_color = '#64748b';
                if ($log_notif['status'] === 'Terkirim') { $badge_color = '#d1fae5'; $text_color = '#065f46'; }
                if ($log_notif['status'] === 'Gagal') { $badge_color = '#fee2e2'; $text_color = '#991b1b'; }
                if ($log_notif['status'] === 'Pending') { $badge_color = '#fef3c7'; $text_color = '#92400e'; }
            ?>
                <div style="background-color: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 11px; width: 100%; box-sizing: border-box;">
                    <div style="font-weight: 700; color: #334155; margin-bottom: 3px; display: flex; justify-content: space-between; align-items: center;">
                        <span>Status Terakhir:</span>
                        <span style="padding: 1px 6px; border-radius: 4px; font-size: 10px; font-weight: 700; background-color: <?= $badge_color; ?>; color: <?= $text_color; ?>;">
                            <?= strtoupper($log_notif['status']); ?>
                        </span>
                    </div>
                    <div style="color: #64748b; line-height: 1.3; font-size: 10px;">
                        • <?= htmlspecialchars($log_notif['jenis']); ?> (<?= htmlspecialchars($log_notif['tujuan']); ?>) | <em><?= date('d M Y H:i', strtotime($log_notif['tanggal_kirim'])); ?> WIB</em>
                    </div>
                </div>
            <?php else : ?>
                <div style="background-color: #f8fafc; padding: 8px; border-radius: 6px; border: 1px solid #e2e8f0; font-size: 11px; color: #94a3b8; text-align: center; font-style: italic; width: 100%; box-sizing: border-box;">
                    Belum ada riwayat notifikasi untuk kandidat ini.
                </div>
            <?php endif; ?>

            <!-- BARIS 4: Tombol Kirim Notifikasi Hasil Pendaftaran (Di Paling Bawah) -->
            <div style="width: 100%;">
                <button type="submit" name="kirim_pemberitahuan" style="width: 100%; background-color: #059669; color: white; border: none; padding: 10px; border-radius: 4px; font-weight: 700; font-size: 13px; cursor: pointer; text-align: center; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.1); height: 38px; display: flex; align-items: center; justify-content: center;">
                    Kirim Notifikasi Hasil Pendaftaran
                </button>
            </div>

        </div> <!-- Penutup container vertikal Kotak 2 -->

        <!-- ================= SINKRONISASI OTOMATIS DATA PELAMAR ================= -->
<!-- Sistem akan otomatis mencoba mengambil kolom 'no_hp', 'telp', atau 'telepon' dari tabel pelamar Anda -->
<input type="hidden" id="wa_pelamar"
value="<?= htmlspecialchars(
    $data_pelamar['no_telepon'] ??
    $data_pelamar['no_hp'] ??
    $data_pelamar['telp'] ??
    $data_pelamar['telepon'] ??
    $data_pelamar['whatsapp'] ??
    ''
); ?>">

<input type="hidden" id="email_pelamar" value="<?= htmlspecialchars($data_pelamar['email'] ?? $data_pelamar['alamat_email'] ?? ''); ?>"> 

        </form> <!-- FORM UTAMA DITUTUP DI SINI -->
    </div>

<!-- ================= SCRIPT JAVASCRIPT OTOMATISASI ================= -->
<script>
function hitungStatusOtomatis(nilai) {
    const badge = document.getElementById('badge_status_otomatis');
    if(nilai === '' || nilai === null) {
        badge.innerText = '-';
        badge.style.background = '#e2e8f0';
        badge.style.color = '#64748b';
        return;
    }
    if (parseFloat(nilai) >= 75) {
        badge.innerText = 'LULUS';
        badge.style.background = '#d1fae5';
        badge.style.color = '#065f46';
    } else {
        badge.innerText = 'TIDAK LULUS';
        badge.style.background = '#fee2e2';
        badge.style.color = '#991b1b';
    }
}

function updateTujuanNotifikasi() {
    const jenisMedia = document.getElementById('select_jenis_notif').value;
    const inputTujuan = document.getElementById('input_tujuan_notif');
    const waPelamar = document.getElementById('wa_pelamar').value;
    const emailPelamar = document.getElementById('email_pelamar').value;

    if (jenisMedia === 'WhatsApp' || jenisMedia === 'SMS') {
        inputTujuan.value = waPelamar;
    } else if (jenisMedia === 'Email') {
        inputTujuan.value = emailPelamar;
    }
}

document.addEventListener('DOMContentLoaded', function() {
    updateTujuanNotifikasi();
    const nilaiAwal = document.getElementById('input_nilai_kompetensi').value;
    if(nilaiAwal !== '') { hitungStatusOtomatis(nilaiAwal); }
});
</script>
</body>
</html>