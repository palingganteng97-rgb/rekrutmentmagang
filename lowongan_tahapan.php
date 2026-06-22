<?php 
session_start(); 
date_default_timezone_set('Asia/Jakarta');

// =========================================================================
// 1. KONEKSI DATABASE SERVER LANGSUNG
// =========================================================================
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. [CRUD - CREATE / UPDATE] PROSES SIMPAN FORM POP-UP MODAL
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $lowongan_id   = intval($_POST['lowongan_id']);
    $tahapan_id    = intval($_POST['tahapan_id']);
    $urutan        = intval($_POST['urutan']);
    $minimal_nilai = floatval($_POST['minimal_nilai']);
    $wajib_lulus   = intval($_POST['wajib_lulus']); // TINYINT (1 atau 0)
    $waktu_sekarang = date('Y-m-d H:i:s');

    if ($_POST['action'] == 'edit') {
        $id = intval($_POST['id']);
        $query_update = "UPDATE lowongan_tahapan 
                         SET lowongan_id = $lowongan_id, tahapan_id = $tahapan_id, urutan = $urutan, minimal_nilai = $minimal_nilai, wajib_lulus = $wajib_lulus, updated_at = '$waktu_sekarang' 
                         WHERE id = $id";
        mysqli_query($koneksi, $query_update);
    } else {
        $query_insert = "INSERT INTO lowongan_tahapan (lowongan_id, tahapan_id, urutan, minimal_nilai, wajib_lulus, created_at, updated_at) 
                         VALUES ($lowongan_id, $tahapan_id, $urutan, $minimal_nilai, $wajib_lulus, '$waktu_sekarang', '$waktu_sekarang')";
        mysqli_query($koneksi, $query_insert);
    }
    header("Location: lowongan_tahapan.php" . (isset($_GET['lowongan_id']) ? "?lowongan_id=".$_GET['lowongan_id'] : ""));
    exit;
}

// =========================================================================
// 3. [CRUD - DELETE] PROSES HAPUS DATA BARIS
// =========================================================================
if (isset($_GET['delete'])) {
    $id_hapus = intval($_GET['delete']);
    mysqli_query($koneksi, "DELETE FROM lowongan_tahapan WHERE id = $id_hapus");
    header("Location: lowongan_tahapan.php" . (isset($_GET['lowongan_id']) ? "?lowongan_id=".$_GET['lowongan_id'] : ""));
    exit;
}

// =========================================================================
// 4. [CRUD - READ] SINKRONISASI QUERY & FILTER BERDASARKAN LOWONGAN_ID
// =========================================================================
$lowongan_id_filter = isset($_GET['lowongan_id']) ? intval($_GET['lowongan_id']) : 0;

$query_tampil = "SELECT lt.*, rl.judul_lowongan, mts.nama_tahapan 
                 FROM lowongan_tahapan lt
                 LEFT JOIN rekrutmen_lowongan rl ON lt.lowongan_id = rl.id
                 LEFT JOIN mst_tahapan_seleksi mts ON lt.tahapan_id = mts.id";

if ($lowongan_id_filter > 0) {
    $query_tampil .= " WHERE lt.lowongan_id = $lowongan_id_filter";
}
$query_tampil .= " ORDER BY lt.urutan ASC";
$ambil_data = mysqli_query($koneksi, $query_tampil);

// Menangkap nama judul lowongan kerja untuk judul sub-header di atas tabel
$nama_lowongan_header = "Semua Formasi";
if ($lowongan_id_filter > 0) {
    $q_head = mysqli_query($koneksi, "SELECT judul_lowongan FROM rekrutmen_lowongan WHERE id = $lowongan_id_filter");
    if ($q_head && mysqli_num_rows($q_head) > 0) {
        $d_head = mysqli_fetch_assoc($q_head);
        $nama_lowongan_header = $d_head['judul_lowongan'];
    }
}

// Data pendukung isi dropdown select-option di modal box
$list_lowongan = mysqli_query($koneksi, "SELECT id, judul_lowongan FROM rekrutmen_lowongan ORDER BY judul_lowongan ASC");
$list_tahapan  = mysqli_query($koneksi, "SELECT id, nama_tahapan FROM mst_tahapan_seleksi ORDER BY nama_tahapan ASC");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detail Alur Tahapan Seleksi</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 40px 20px; color: #475569; }
        
        /* Container Fokus Utama Penuh Tanpa Sidebar */
        .main-content-standalone { width: 100%; max-width: 1200px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); padding: 45px; display: flex; flex-direction: column; gap: 32px; }
        
        .content-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; letter-spacing: -0.5px; }
        .content-header h1 span { color: #4f46e5; }
        
        /* Tombol Aksi Navigasi */
        .btn-layout-flex { display: flex; gap: 12px; }
        .btn-purple { background: #4f46e5; color: white; border-radius: 14px; font-weight: 700; padding: 14px 24px; border: none; cursor: pointer; font-size: 14px; transition: background 0.2s; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-purple:hover { background: #3b33c7; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }
        .btn-back { background: #f1f5f9; color: #475569; border-radius: 14px; font-weight: 700; padding: 14px 24px; text-decoration: none; font-size: 14px; transition: all 0.2s; display: inline-flex; align-items: center; }
        .btn-back:hover { background: #e2e8f0; color: #1e293b; }

        /* Tabel Komponen */
        .table-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 10px 20px; box-shadow: 0 4px 10px rgba(0,0,0,0.005); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding: 16px 15px; font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #f1f5f9; }
        td { padding: 18px 15px; color: #475569; border-bottom: 1px solid #f8fafc; vertical-align: middle; }
        
        /* Badge Status */
        .badge { display: inline-block; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; text-align: center; }
        .badge-wajib { background: #dcfce7; color: #15803d; }
        .badge-opsional { background: #e2e8f0; color: #475569; }
        .text-empty { text-align: center; color: #94a3b8; font-style: italic; padding: 5px 0; }

        /* Kotak Ikon Aksi */
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 34px; height: 34px; border-radius: 10px; text-decoration: none; font-size: 14px; transition: all 0.2s ease; border: none; cursor: pointer; }
        .btn-icon.edit { background-color: #0ea5e9; color: #ffffff; margin-right: 4px; }
        .btn-icon.delete { background-color: #ef4444; color: #ffffff; }

        /* MODAL INTERAKTIF */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(5px); justify-content: center; align-items: center; z-index: 999999; }
        .modal-box { background: #ffffff; width: 90%; max-width: 450px; padding: 35px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 5px; }
        .form-group label { font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase; }
        .form-control { padding: 12px 16px; border-radius: 12px; border: 1px solid #cbd5e1; width: 100%; font-size: 14px; font-weight: 600; color: #1e293b; background-color: #f8fafc; outline: none; }
    </style>
</head>
<body>

    <!-- CONTAINER MANDIRI INDEPENDEN -->
    <div class="main-content-standalone">
        <div class="content-header">
            <div>
                <h1 style="font-size: 22px;">Alur Tahapan Seleksi</h1>
                <p style="font-size: 14px; color: #94a3b8; margin-top: 4px;">Formasi Lowongan: <strong style="color: #4f46e5;"><?= htmlspecialchars($nama_lowongan_header); ?></strong></p>
            </div>
            <div class="btn-layout-flex">
                <a href="master_lowongan.php" class="btn-back">⬅️ Kembali</a>
                <button class="btn-purple" onclick="bukaModalTambah()">+ Tambah Alur</button>
            </div>
        </div>

        <!-- TABEL KONTEN DATA ALUR SELEKSI -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>No</th>
                        <th>Nama Tahapan Seleksi</th>
                        <th>Urutan Alur</th>
                        <th>Minimal Nilai</th>
                        <th>Aturan Kelulusan</th>
                        <th style="text-align: center; width: 110px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $no = 1;
                if ($ambil_data && mysqli_num_rows($ambil_data) > 0) :
                    while ($row = mysqli_fetch_assoc($ambil_data)) : 
                        $badge_class = ($row['wajib_lulus'] == 1) ? 'badge-wajib' : 'badge-opsional';
                        $badge_text  = ($row['wajib_lulus'] == 1) ? 'Ya (Wajib Lulus)' : 'Opsional';
                ?>
                    <tr>
                        <td style="font-weight: bold; color: #94a3b8;"><?= $no++; ?></td>
                        <td style="font-weight: 700; color: #1e293b;"><?= htmlspecialchars($row['nama_tahapan'] ?? 'Administrasi'); ?></td>
                            <td style="font-weight: bold; color: #4f46e5;">Tahap Ke-<?= $row['urutan']; ?></td>
                            <td><strong><?= number_format($row['minimal_nilai'], 2); ?></strong></td>
                            <td><span class="badge <?= $badge_class; ?>"><?= $badge_text; ?></span></td>
                            <td style="text-align: center;">
                                <!-- Tombol aksi kotak ikon modern -->
                                <a href="javascript:void(0)" class="btn-icon edit" onclick="bukaModalEdit(<?= htmlspecialchars(json_encode($row)); ?>)" title="Edit">✏️</a>
                                <a href="lowongan_tahapan.php?delete=<?= $row['id']; ?><?= $lowongan_id_filter > 0 ? '&lowongan_id='.$lowongan_id_filter : ''; ?>" class="btn-icon delete" onclick="return confirm('Hapus tahapan seleksi untuk posisi ini?')" title="Hapus">🗑️</a>
                            </td>
                        </tr>
                    <?php 
                        endwhile;
                    else : 
                    ?>
                        <tr><td colspan="6" class="text-empty" style="padding: 30px 0; text-align: center; color: #94a3b8; font-style: italic;">Belum ada alur tahapan seleksi yang dikonfigurasi untuk formasi ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- =========================================================================
         5. JENDELA POP-UP FORM MODAL BOX (TAMBAH / EDIT DATA)
         ========================================================================= -->
    <div class="modal-overlay" id="modalLowonganTahapan">
        <div class="modal-box">
            <h2 id="modalTitle" style="font-size: 20px; font-weight: 800; color: #1e293b;">Tambah Alur Seleksi</h2><br>
            <form action="" method="POST">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="id" id="modalId">
                
                <!-- Otomatis mengunci pilihan jika diakses via parameter lowongan_id URL -->
                <input type="hidden" name="lowongan_id" id="formLowongan" value="<?= $lowongan_id_filter > 0 ? $lowongan_id_filter : '1'; ?>">
                
                <div class="form-group">
                    <label>Pilih Jenis Tahapan</label>
                    <select name="tahapan_id" id="formTahapan" class="form-control" required>
                        <?php mysqli_data_seek($list_tahapan, 0); while($ts = mysqli_fetch_assoc($list_tahapan)) : ?>
                            <option value="<?= $ts['id']; ?>"><?= htmlspecialchars($ts['nama_tahapan']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Urutan Alur Ke-</label>
                    <input type="number" name="urutan" id="formUrutan" class="form-control" required min="1" placeholder="Contoh: 1">
                </div>
                
                <div class="form-group">
                    <label>Minimal Nilai Kelulusan</label>
                    <input type="number" step="0.01" name="minimal_nilai" id="formNilai" class="form-control" required placeholder="Contoh: 75.00">
                </div>
                
                <div class="form-group">
                    <label>Apakah Wajib Lulus?</label>
                    <select name="wajib_lulus" id="formWajib" class="form-control">
                        <option value="1">Ya (Wajib Lulus)</option>
                        <option value="0">Tidak (Opsional)</option>
                    </select>
                </div>
                
                <div style="display:flex; gap:10px; justify-content: flex-end; margin-top:24px;">
                    <button type="button" class="form-control" style="width:100px; cursor:pointer; font-weight: bold; background: #f1f5f9; color: #475569; border: none; border-radius: 12px;" onclick="tutupModal()">Batal</button>
                    <button type="submit" class="btn-purple" id="btnSubmit" style="border-radius:12px; padding:10px 20px; border:none; color:white; cursor:pointer; font-weight:bold;">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>

    <!-- =========================================================================
         6. LOGIKA SCRIPT JAVASCRIPT CONTROL POP-UP MODAL
         ========================================================================= -->
    <script>
        var modal = document.getElementById('modalLowonganTahapan');
        
        function bukaModalTambah() {
            document.getElementById('modalTitle').innerText = "Tambah Alur Seleksi";
            document.getElementById('modalAction').value = "add";
            document.getElementById('modalId').value = "";
            document.getElementById('formUrutan').value = "";
            document.getElementById('formNilai').value = "";
            document.getElementById('formWajib').value = "1";
            document.getElementById('btnSubmit').innerText = "Simpan Data";
            modal.style.display = 'flex';
        }

        function bukaModalEdit(dataJson) {
            document.getElementById('modalTitle').innerText = "Ubah Alur Seleksi";
            document.getElementById('modalAction').value = "edit";
            document.getElementById('modalId').value = dataJson.id;
            document.getElementById('formLowongan').value = dataJson.lowongan_id;
            document.getElementById('formTahapan').value = dataJson.tahapan_id;
            document.getElementById('formUrutan').value = dataJson.urutan;
            document.getElementById('formNilai').value = dataJson.minimal_nilai;
            document.getElementById('formWajib').value = dataJson.wajib_lulus;
            document.getElementById('btnSubmit').innerText = "Simpan Perubahan";
            modal.style.display = 'flex';
        }

        function tutupModal() { 
            modal.style.display = 'none'; 
        }
        
        window.onclick = function(event) { 
            if (event.target == modal) { tutupModal(); } 
        }
    </script>
</body>
</html>
