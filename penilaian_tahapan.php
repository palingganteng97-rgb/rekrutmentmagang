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

$lamaran_tahapan_id = $_GET['id'] ?? 0;
if (!$lamaran_tahapan_id) {
    die("Akses ilegal: Parameter ID Pelamar tidak ditemukan.");
}

// Sinkronisasi penilai_id otomatis
$cek_penilai_db = mysqli_query($conn, "SELECT id FROM penilai LIMIT 1");
if (mysqli_num_rows($cek_penilai_db) > 0) {
    $row_p = mysqli_fetch_assoc($cek_penilai_db);
    $penilai_id = $_SESSION['user_id'] ?? $row_p['id'];
} else {
    mysqli_query($conn, "INSERT INTO penilai (id, nama) VALUES (1, 'Tim Penilai Pusat')");
    $penilai_id = 1;
}

// Ambil info nama pelamar
$query_pelamar = mysqli_query($conn, "SELECT lt.id, p.nama_lengkap AS nama_pendaftar 
                                      FROM lamaran_tahapan lt
                                      JOIN rekrutmen_lamaran rl ON lt.lamaran_id = rl.id
                                      JOIN pelamar p ON rl.pelamar_id = p.id
                                      WHERE lt.id = '$lamaran_tahapan_id'");
$data_pelamar = mysqli_fetch_assoc($query_pelamar);

// Ambil daftar master tahapan seleksi untuk tab Chrome
$query_master_tahapan = mysqli_query($conn, "SELECT id, nama_tahapan FROM mst_tahapan_seleksi WHERE status = 'Aktif' OR status = 1 ORDER BY id ASC");
$list_tabs = [];
while ($t = mysqli_fetch_assoc($query_master_tahapan)) {
    $list_tabs[] = $t;
}

// Set tab default pertama aktif saat halaman dimuat awal
$tab_default_aktif = $list_tabs[0]['id'] ?? 0;

$success_msg = "";
$error_msg   = "";

// =========================================================================
// 2. LOGIKA PROSES SIMPAN / UPDATE INDIVIDU TAHAPAN & AUTO STATUS (POST)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $mst_tahapan_id = $_POST['mst_tahapan_id'] ?? $tab_default_aktif; 
    $tanggal        = date('Y-m-d H:i:s');
    
    // VARIABEL STATUS INDIVIDU TAHAPAN
    $status_individu = "Proses"; 
    $is_skipped = false; 

    // DETEKSI LOGIKA BERDASARKAN TOMBOL YANG DIKLIK
    if (isset($_POST['aksi_lewati'])) {
        // A. JIKA ADMIN MEMILIH LEWATI (SKIP)
        $nilai_db = "NULL"; 
        $status_individu = "Dilewati";
        $catatan = !empty($_POST['catatan']) ? "'" . mysqli_real_escape_string($conn, $_POST['catatan']) . "'" : "'Tahapan dilewati oleh penilai.'";
        $is_skipped = true; 
    } else {
        // B. JIKA ADMIN MENGINPUT NILAI (SIMPAN BIASA)
        $nilai_input = floatval($_POST['nilai']);
        $nilai_db = "'" . number_format($nilai_input, 2, '.', '') . "'";
        $catatan = "'" . mysqli_real_escape_string($conn, $_POST['catatan']) . "'";

        // Aturan kelulusan mutlak berdasarkan batas nilai 75.00
        if ($nilai_input >= 75.00) {
            $status_individu = "Lulus";
        } else {
            $status_individu = "Tidak Lulus";
        }
    }

    // 1. Simpan atau Update Nilai ke Tabel penilaian_tahapan
    $cek_existing = mysqli_query($conn, "SELECT id FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'");

    if (mysqli_num_rows($cek_existing) > 0) {
        $query_save = "UPDATE penilaian_tahapan SET 
                        penilai_id = '$penilai_id',
                        nilai = $nilai_db, 
                        status_tahap = '$status_individu',
                        catatan = $catatan, 
                        tanggal = '$tanggal',
                        updated_at = NOW()
                       WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$mst_tahapan_id'";
    } else {
        $query_save = "INSERT INTO penilaian_tahapan (lamaran_tahapan_id, mst_tahapan_id, penilai_id, nilai, status_tahap, catatan, tanggal, created_at) 
                       VALUES ('$lamaran_tahapan_id', '$mst_tahapan_id', '$penilai_id', $nilai_db, '$status_individu', $catatan, '$tanggal', NOW())";
    }

    if (mysqli_query($conn, $query_save)) {
        $success_msg = "Data penilaian tahapan berhasil disimpan dengan status: " . strtoupper($status_individu);
        $tab_default_aktif = $mst_tahapan_id;

        // =========================================================================
        // AUTOMATIC GLOBAL STATUS CALCULATION ENGINE
        // =========================================================================
        if ($is_skipped) {
            $status_final = "SKIP";
        } else {
            $q_lamaran = mysqli_query($conn, "SELECT lamaran_id FROM lamaran_tahapan WHERE id = '$lamaran_tahapan_id' LIMIT 1");
            $d_lamaran = mysqli_fetch_assoc($q_lamaran);
            $id_lamaran_asli = $d_lamaran['lamaran_id'] ?? 0;

            $q_lowongan = mysqli_query($conn, "SELECT lowongan_id FROM rekrutmen_lamaran WHERE id = '$id_lamaran_asli' LIMIT 1");
            $d_lowongan = mysqli_fetch_assoc($q_lowongan);
            $lowongan_id_pelamar = $d_lowongan['lowongan_id'] ?? 0;

            $q_total_tahapan = mysqli_query($conn, "SELECT COUNT(*) as total FROM lowongan_tahapan WHERE lowongan_id = '$lowongan_id_pelamar'");
            $d_total_tahapan = mysqli_fetch_assoc($q_total_tahapan);
            $total_tahapan_wajib = $d_total_tahapan['total'] ?? 0;

            $q_total_dinilai = mysqli_query($conn, "SELECT COUNT(*) as total FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id'");
            $d_total_dinilai = mysqli_fetch_assoc($q_total_dinilai);
            $total_sudah_dinilai = $d_total_dinilai['total'] ?? 0;

            $q_koreksi_nilai = mysqli_query($conn, "SELECT nilai, status_tahap FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id'");

            $status_final = "PROSES"; 
            $ada_yang_tidak_lulus = false;

            while ($koreksi = mysqli_fetch_assoc($q_koreksi_nilai)) {
                if (($koreksi['nilai'] !== null && $koreksi['nilai'] < 75.00) || strtoupper($koreksi['status_tahap']) == "TIDAK LULUS") {
                    $ada_yang_tidak_lulus = true;
                    break;
                }
            }

            if ($ada_yang_tidak_lulus) {
                $status_final = "TIDAK LULUS";
            } else {
                if ($total_sudah_dinilai == 0) {
                    $status_final = "PENDING";
                } elseif ($total_sudah_dinilai < $total_tahapan_wajib) {
                    $status_final = "PROSES";
                } elseif ($total_sudah_dinilai >= $total_tahapan_wajib) {
                    $status_final = "LULUS";
                }
            }
        }

        // PERBAIKAN UTAMA BARIS 155: Kolom 'status_tahap' dihapus karena tidak ada di tabel lamaran_tahapan
        mysqli_query($conn, "UPDATE lamaran_tahapan SET status = '$status_final', tanggal_mulai = NOW() WHERE id = '$lamaran_tahapan_id'");
        
        // Deteksi jika ini dari AJAX Auto-Save, jangan di-redirect scriptnya
        if (!isset($_POST['is_ajax'])) {
            echo "<script>
                    alert('Penilaian berhasil disimpan! Status Akhir: $status_final');
                    window.location.href = 'lamaran_tahapan.php';
                  </script>";
            exit;
        }
    } else {
        $error_msg = "Gagal menyimpan penilaian: " . mysqli_error($conn);
    }
}

// Ambil nilai lama untuk data tab aktif saat render halaman awal
$query_nilai = mysqli_query($conn, "SELECT * FROM penilaian_tahapan WHERE lamaran_tahapan_id = '$lamaran_tahapan_id' AND mst_tahapan_id = '$tab_default_aktif'");
$data_nilai  = mysqli_fetch_assoc($query_nilai);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Input Penilaian Tahapan</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; background-color: #f8fafc; padding: 40px; }
        .container { max-width: 650px; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin: 0 auto; }
        h1 { font-size: 22px; margin-bottom: 5px; color: #0f172a; }
        .sub-title { color: #64748b; font-size: 14px; margin-bottom: 25px; }
        .form-group { margin-bottom: 18px; }
        label { display: block; font-weight: 600; margin-bottom: 8px; font-size: 14px; }
        .form-control { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        
.chrome-tabs-container { 
    display: flex; 
    background-color: #e2e8f0; 
    padding: 6px 6px 0 6px; 
    border-radius: 12px 12px 0 0; 
    gap: 4px; 
    overflow-x: auto; 
    margin-bottom: 20px; 
    border-bottom: 1px solid #cbd5e1; 
    
    /* Mematikan fitur scrollbar standar agar panah hilang di Firefox/IE */
    scrollbar-width: none; 
    -ms-overflow-style: none; 
}

/* Menyembunyikan total batang dan tombol panah scrollbar di Chrome, Safari, dan Edge */
.chrome-tabs-container::-webkit-scrollbar { 
    display: none; 
    width: 0;
    height: 0;
}

.chrome-tab { 
    padding: 10px 16px; 
    background-color: #f1f5f9; 
    color: #475569; 
    border-radius: 10px 10px 0 0; 
    font-size: 12px; 
    font-weight: 700; 
    cursor: pointer; 
    white-space: nowrap; 
    transition: all 0.15s; 
}

.chrome-tab.active { 
    background-color: #ffffff; 
    color: #4f46e5; 
    border: 1px solid #cbd5e1; 
    border-bottom: none; 
    position: relative; 
    margin-bottom: -1px; 
    padding-bottom: 11px; 
}


        .btn-submit { background-color: #4f46e5; color: white; padding: 12px 20px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600; width: 100%; margin-top: 10px; font-size: 15px; }
        .btn-submit:hover { background-color: #4338ca; }
        .btn-back { display: inline-block; text-decoration: none; color: #64748b; font-size: 14px; margin-bottom: 20px; }
        .alert { padding: 12px; border-radius: 6px; font-size: 14px; margin-bottom: 20px; }
        .alert-success { background-color: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .alert-error { background-color: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }

        /* Menghapus tombol panah naik-turun bawaan browser Chrome, Safari, Edge, dan Opera */
input[type=number]::-webkit-inner-spin-button, 
input[type=number]::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}

/* Menghapus tombol panah naik-turun bawaan browser Firefox */
input[type=number] {
    -moz-appearance: textfield;
}

    </style>
</head>
<body>

<div class="container">
    <a href="lamaran_tahapan.php" class="btn-back">&larr; Kembali ke Daftar Progress</a>
    
    <h1>Input Penilaian Tahapan</h1>
    <div class="sub-title">Nama Pelamar: <strong style="color: #4f46e5;"><?= htmlspecialchars($data_pelamar['nama_pendaftar'] ?? 'Kandidat Magang'); ?></strong></div>

    <?php if (!empty($success_msg)): ?><div class="alert alert-success"><?= $success_msg; ?></div><?php endif; ?>
    <?php if (!empty($error_msg)): ?><div class="alert alert-error"><?= $error_msg; ?></div><?php endif; ?>

    <form action="" method="POST" id="formPenilaian">
        
        <div class="form-group">
            <label>Pilih Tahapan Seleksi</label>
            <div class="chrome-tabs-container">
                <?php foreach ($list_tabs as $index => $tab) : ?>
                    <div class="chrome-tab <?= $tab['id'] == $tab_default_aktif ? 'active' : ''; ?>" 
                         id="tab-control-<?= $tab['id']; ?>"
                         onclick="pilihTabChrome(this, '<?= $tab['id']; ?>')">
                        <?= htmlspecialchars($tab['nama_tahapan']); ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <input type="hidden" name="mst_tahapan_id" id="input_mst_tahapan_id" value="<?= $tab_default_aktif; ?>">
        </div>

        <div class="form-group">
            <label for="nilai">Nilai Kompetensi (0.00 - 100.00)</label>
            <input type="number" name="nilai" id="nilai" step="0.01" min="0" max="100" class="form-control" placeholder="Contoh: 85.50" required value="<?= $data_nilai['nilai'] ?? ''; ?>">
            
            <!-- Elemen Preview Status Otomatis -->
            <div style="margin-top: 8px; font-size: 14px; color: #4b5563;">
                Status Otomatis: <span id="previewStatus" style="font-weight: bold; padding: 2px 8px; border-radius: 4px; display: inline-block;">-</span>
            </div>
        </div>

        <div class="form-group">
            <label for="catatan">Catatan / Rekomendasi Penilai</label>
            <textarea name="catatan" id="catatan" class="form-control" rows="5" placeholder="Tuliskan feedback hasil evaluasi teknis kandidat..." required><?= htmlspecialchars($data_nilai['catatan'] ?? ''); ?></textarea>
        </div>

        <!-- Wadah Tombol Aksi (Berdampingan secara responsif) -->
        <div style="display: flex; gap: 12px; margin-top: 20px;">
            <!-- Tombol Simpan Utama -->
            <button type="submit" name="aksi_simpan" class="btn-submit" style="flex: 1; margin-top: 0;">Simpan Hasil Penilaian</button>
            
            <!-- Tombol Lewati (Skip) -->
            <button type="submit" name="aksi_lewati" class="btn-skip" style="background-color: #f59e0b; color: white; border: none; padding: 12px 24px; font-size: 16px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.2s;" onclick="return confirm('Apakah Anda yakin ingin MELEWATI tahapan ini?')">Lewati Tahap</button>
        </div>
    </form>
</div>

<!-- SCRIPT LOGIKA KELULUSAN, SKIP, DAN PERPINDAHAN TAB AUTO-SAVE -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const inputNilai = document.getElementById('nilai');
    const inputCatatan = document.getElementById('catatan');
    const previewStatus = document.getElementById('previewStatus');
    const btnLewati = document.querySelector('button[name="aksi_lewati"]');

    // Fungsi memperbarui badge status kelulusan secara visual
    function perbaruiStatus() {
        let nilai = parseFloat(inputNilai.value);
        
        if (isNaN(nilai) || inputNilai.value.trim() === "") {
            previewStatus.innerText = "MENUNGGU INPUT / DILEWATI";
            previewStatus.style.backgroundColor = "#e5e7eb";
            previewStatus.style.color = "#374151";
            return;
        }

        if (nilai >= 75.00) {
            previewStatus.innerText = "LULUS";
            previewStatus.style.backgroundColor = "#d1fae5";
            previewStatus.style.color = "#065f46";
        } else {
            previewStatus.innerText = "TIDAK LULUS";
            previewStatus.style.backgroundColor = "#fee2e2";
            previewStatus.style.color = "#991b1b";
        }
    }

    // Jalankan pengecekan secara real-time saat user mengetik nilai
    inputNilai.addEventListener('input', perbaruiStatus);
    
    // Gunakan MutationObserver untuk memantau jika nilai berubah via fungsi AJAX fetch data
    const observer = new MutationObserver(perbaruiStatus);
    observer.observe(inputNilai, { attributes: true, attributeFilter: ['value'] });
    
    // Amankan pemanggilan awal
    perbaruiStatus();

    // Hapus validasi 'required' jika admin menekan tombol Lewati secara manual
    if (btnLewati) {
        btnLewati.addEventListener('click', function () {
            inputNilai.removeAttribute('required');
            inputCatatan.removeAttribute('required');
        });
    }
});

// Fungsi perpindahan tab dengan fitur Auto-Save di latar belakang
function pilihTabChrome(elemenTab, idMasterTahapan) {
    // 1. Ambil data form yang sedang aktif SEBELUM diganti
    const nilaiLama = document.getElementById('nilai').value;
    const catatanLama = document.getElementById('catatan').value;
    const idMasterTahapanLama = document.getElementById('input_mst_tahapan_id').value;
    
    const urlParams = new URLSearchParams(window.location.search);
    const idLamaran = urlParams.get('id');

    // Buat fungsi internal untuk memuat data tab baru setelah proses simpan selesai
    const muatDataTabBaru = () => {
        var semuaTab = document.getElementsByClassName('chrome-tab');
        for (var i = 0; i < semuaTab.length; i++) {
            semuaTab[i].classList.remove('active');
        }
        elemenTab.classList.add('active');
        document.getElementById('input_mst_tahapan_id').value = idMasterTahapan;
        
        document.getElementById('nilai').value = "";
        document.getElementById('catatan').value = "Memuat data...";

        fetch('get_nilai_tahapan.php?id_lamaran=' + idLamaran + '&id_mst_tahapan=' + idMasterTahapan)
            .then(response => response.json())
            .then(data => {
                document.getElementById('nilai').value = data.nilai !== null ? data.nilai : "";
                document.getElementById('catatan').value = data.catatan !== null ? data.catatan : "";
                // Trigger event manual agar badge status kelulusan ikut terupdate
                document.getElementById('nilai').dispatchEvent(new Event('input'));
            })
            .catch(error => {
                document.getElementById('catatan').value = "";
                document.getElementById('nilai').value = "";
                document.getElementById('nilai').dispatchEvent(new Event('input'));
            });
    };

    // 2. LOGIKA AUTO-SAVE: Jika ada inputan di tab lama, simpan ke database via AJAX POST dahulu
    if (nilaiLama.trim() !== "" || (catatanLama.trim() !== "" && catatanLama !== "Memuat data...")) {
        const formData = new FormData();
        formData.append('mst_tahapan_id', idMasterTahapanLama);
        formData.append('nilai', nilaiLama);
        formData.append('catatan', catatanLama);
        formData.append('aksi_simpan', '1'); // Trigger penanganan POST di PHP utama Anda

        fetch('', { // Kirim ke file PHP ini sendiri secara background
            method: 'POST',
            body: formData
        })
        .then(() => {
            console.log('Progress tab sebelumnya berhasil disimpan otomatis.');
            muatDataTabBaru();
        })
        .catch(err => {
            console.error('Gagal menyimpan otomatis:', err);
            muatDataTabBaru(); // Tetap pindah tab meskipun simpan gagal demi kenyamanan UX
        });
    } else {
        // Jika tab lama kosong, langsung muat tab baru tanpa membuang waktu query simpan
        muatDataTabBaru();
    }
}
</script>
</body>
</html>
