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

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);
if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// =========================================================================
// 2. [CRUD - CREATE / UPDATE] PROSES FORM & VALIDASI ANTI-DUPLIKAT
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $lowongan_id   = intval($_POST['lowongan_id']);
    $tahapan_id    = intval($_POST['tahapan_id']); 
    $urutan        = intval($_POST['urutan']);
    $minimal_nilai = floatval($_POST['minimal_nilai']);
    $wajib_lulus   = intval($_POST['wajib_lulus']); 
    $waktu_sekarang = date('Y-m-d H:i:s');
    
    $action = $_POST['action'];
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    // VALIDASI A: Cek duplikasi Jenis Tahapan pada lowongan ini
    $sql_cek_tahapan = "SELECT id FROM lowongan_tahapan WHERE lowongan_id = $lowongan_id AND tahapan_id = $tahapan_id";
    if ($action == 'edit') { $sql_cek_tahapan .= " AND id != $id"; }
    $cek_tahapan = mysqli_query($koneksi, $sql_cek_tahapan);
    
    if (mysqli_num_rows($cek_tahapan) > 0) {
        $_SESSION['toast_error'] = 'Gagal! Jenis tahapan seleksi tersebut sudah terdaftar untuk posisi lowongan ini.';
        header("Location: lowongan_tahapan.php?lowongan_id=" . $lowongan_id);
        exit;
    }

    // VALIDASI B: Cek duplikasi Nomor Urutan Alur pada lowongan ini
    $sql_cek_urutan = "SELECT id FROM lowongan_tahapan WHERE lowongan_id = $lowongan_id AND urutan = $urutan";
    if ($action == 'edit') { $sql_cek_urutan .= " AND id != $id"; }
    $cek_urutan = mysqli_query($koneksi, $sql_cek_urutan);
    
    if (mysqli_num_rows($cek_urutan) > 0) {
        $_SESSION['toast_error'] = 'Gagal! Tahap Ke-' . $urutan . ' sudah digunakan oleh jenis tahapan seleksi yang lain.';
        header("Location: lowongan_tahapan.php?lowongan_id=" . $lowongan_id);
        exit;
    }

    // PROSES EKSEKUSI DATA JIKA LOLOS VALIDASI
    if ($action == 'edit') {
        $query_update = "UPDATE lowongan_tahapan 
                         SET lowongan_id = $lowongan_id, tahapan_id = $tahapan_id, urutan = $urutan, minimal_nilai = $minimal_nilai, wajib_lulus = $wajib_lulus, updated_at = '$waktu_sekarang' 
                         WHERE id = $id";
        mysqli_query($koneksi, $query_update);
        $_SESSION['toast_success'] = 'Sukses memperbarui alur tahapan seleksi.';
    } else {
        $query_insert = "INSERT INTO lowongan_tahapan (lowongan_id, tahapan_id, urutan, minimal_nilai, wajib_lulus, created_at, updated_at) 
                         VALUES ($lowongan_id, $tahapan_id, $urutan, $minimal_nilai, $wajib_lulus, '$waktu_sekarang', '$waktu_sekarang')";
        mysqli_query($koneksi, $query_insert);
        $_SESSION['toast_success'] = 'Sukses menambahkan alur tahapan seleksi baru.';
    }
    
    header("Location: lowongan_tahapan.php?lowongan_id=" . $lowongan_id);
    exit;
}

// =========================================================================
// 3. [CRUD - DELETE] PROSES HAPUS DATA BARIS
// =========================================================================
if (isset($_GET['delete'])) {
    $id_hapus = intval($_GET['delete']);
    mysqli_query($koneksi, "DELETE FROM lowongan_tahapan WHERE id = $id_hapus");
    $_SESSION['toast_success'] = 'Alur tahapan seleksi berhasil dihapus.';
    header("Location: lowongan_tahapan.php" . (isset($_GET['lowongan_id']) ? "?lowongan_id=".$_GET['lowongan_id'] : ""));
    exit;
}

// =========================================================================
// 4. [CRUD - READ] AMBIL DATA UTAMA & DROPDOWN RELASI
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

$nama_lowongan_header = "Semua Formasi";
if ($lowongan_id_filter > 0) {
    $q_head = mysqli_query($koneksi, "SELECT judul_lowongan FROM rekrutmen_lowongan WHERE id = $lowongan_id_filter");
    if ($q_head && mysqli_num_rows($q_head) > 0) {
        $d_head = mysqli_fetch_assoc($q_head);
        $nama_lowongan_header = $d_head['judul_lowongan'];
    }
}

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
        body { background-color: #f8fafc; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 40px 20px; color: #334155; }
        
        /* Layout Card Standalone Premium */
        .main-content-standalone { width: 100%; max-width: 1200px; background: #ffffff; border-radius: 24px; box-shadow: 0 4px 30px rgba(0,0,0,0.03); padding: 40px; display: flex; flex-direction: column; gap: 32px; border: 1px solid #f1f5f9; }
        
        .content-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 24px; }
        .content-header h1 { font-size: 24px; font-weight: 800; color: #0f172a; letter-spacing: -0.5px; }
        
        /* Tombol Navigasi Premium */
        .btn-layout-flex { display: flex; gap: 12px; }
        .btn-purple { background: #4f46e5; color: white; border-radius: 12px; font-weight: 700; padding: 12px 22px; border: none; cursor: pointer; font-size: 14px; transition: all 0.2s ease; text-decoration: none; display: inline-flex; align-items: center; }
        .btn-purple:hover { background: #4338ca; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); }
        .btn-back { background: #ffffff; color: #475569; border-radius: 12px; font-weight: 700; padding: 12px 22px; text-decoration: none; font-size: 14px; transition: all 0.2s ease; display: inline-flex; align-items: center; border: 1px solid #e2e8f0; }
        .btn-back:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }

        /* Tabel Minimalis Modern */
        .table-wrapper { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 16px; padding: 8px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; min-width: 1000px; }
/* =========================================================================
   PERBAIKAN CSS: MEMBUAT TABEL RAPAT & TOMBOL AKSI SEJAJAR SINKRON
   ========================================================================= */
th { 
    color: #64748b; 
    padding: 12px 15px; /* Sedikit dirapatkan */
    font-weight: 700; 
    font-size: 11px; 
    text-transform: uppercase; 
    letter-spacing: 0.75px; 
    border-bottom: 1px solid #e2e8f0; 
    background: #f8fafc; 
}

td { 
    padding: 10px 15px; /* PERBAIKAN: Dari 18px dipangkas ke 10px agar tabel rapat & padat */
    color: #334155; 
    border-bottom: 1px solid #f1f5f9; 
    vertical-align: middle; 
}

/* Mengubah kolom aksi menjadi flexbox agar tombol ✏️ dan 🗑️ sejajar lurus horizontal */
td:last-child {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px; /* Jarak konstan antar tombol */
    border-bottom: 1px solid #f1f5f9; /* Mempertahankan garis bawah */
}

/* Tombol Aksi Minimalis */
.btn-icon { 
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    width: 32px; /* Dikecilkan sedikit agar proporsional */
    height: 32px; 
    border-radius: 8px; 
    text-decoration: none; 
    font-size: 12px; 
    transition: all 0.2s ease; 
    border: none; 
    cursor: pointer; 
}

.btn-icon.edit { 
    background-color: #e0f2fe; 
    color: #0284c7; 
    margin: 0; /* Hapus margin kanan lama karena sudah digantikan gap */
}
.btn-icon.edit:hover { background-color: #0ea5e9; color: #ffffff; }

.btn-icon.delete { background-color: #fee2e2; color: #dc2626; }
.btn-icon.delete:hover { background-color: #ef4444; color: #ffffff; }


        /* =========================================================================
           REDESIGN PREMIUM: FLOATING FORM MODAL SYSTEM
           ========================================================================= */
        .modal-overlay { 
            display: none; 
            position: fixed; 
            top: 0; 
            left: 0; 
            width: 100vw; 
            height: 100vh; 
            background: rgba(15, 23, 42, 0.4); 
            backdrop-filter: blur(8px); 
            -webkit-backdrop-filter: blur(8px);
            justify-content: center; 
            align-items: center; 
            z-index: 99999; 
        }

        .modal-box { 
            background: #ffffff; 
            width: 92%; 
            max-width: 460px; 
            padding: 40px; 
            border-radius: 28px; 
            box-shadow: 0 25px 60px -15px rgba(15, 23, 42, 0.12); 
            border: 1px solid rgba(255, 255, 255, 0.8);
            animation: modalPopIn 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        @keyframes modalPopIn { 
            from { transform: scale(0.92) translateY(10px); opacity: 0; } 
            to { transform: scale(1) translateY(0); opacity: 1; } 
        }

        .modal-box h2 { 
            font-size: 22px; 
            font-weight: 800; 
            color: #0f172a; 
            margin-bottom: 28px; 
            letter-spacing: -0.75px; 
        }

        .form-group { 
            margin-bottom: 20px; 
            display: flex; 
            flex-direction: column; 
            gap: 8px; 
        }

        .form-group label { 
            font-size: 11px; 
            font-weight: 800; 
            color: #64748b; 
            text-transform: uppercase; 
            letter-spacing: 0.75px; 
        }

        .form-control { 
            padding: 14px 18px; 
            border-radius: 14px; 
            border: 1px solid #e2e8f0; 
            width: 100%; 
            font-size: 14px; 
            font-weight: 600; 
            color: #1e293b; 
            background-color: #f8fafc; 
            outline: none; 
            transition: all 0.2s ease; 
            appearance: none; 
            -webkit-appearance: none;
        }

        select.form-control {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://w3.org' fill='none' viewBox='0 0 24 24' stroke='%2364748b' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            background-size: 14px;
            padding-right: 45px;
        }

        .form-control:focus { 
            border-color: #4f46e5; 
            background: #ffffff; 
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1); 
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 32px;
        }

        .btn-modal-cancel {
            padding: 14px 24px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            color: #475569;
            transition: all 0.2s;
        }
        .btn-modal-cancel:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1;
        }

        .btn-modal-submit {
            padding: 14px 28px;
            border-radius: 14px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            background: #4f46e5;
            color: #ffffff;
            transition: all 0.2s;
        }
        .btn-modal-submit:hover {
            background: #4338ca;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }
        
        /* MODERN TOAST NOTIFICATION PREMIUM STYLE */
        .toast-container { position: fixed; top: 24px; right: 24px; z-index: 999999; display: flex; flex-direction: column; gap: 12px; }
        .toast-item { background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); border-radius: 16px; padding: 16px 20px; min-width: 320px; max-width: 420px; box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.08), 0 8px 16px -6px rgba(0, 0, 0, 0.05); display: flex; align-items: center; gap: 14px; animation: toastSlideIn 0.35s cubic-bezier(0.175, 0.885, 0.32, 1.15); border: 1px solid rgba(255, 255, 255, 0.5); }
        @keyframes toastSlideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .toast-icon { width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 16px; font-weight: bold; flex-shrink: 0; }
        .toast-error .toast-icon { background: #fee2e2; color: #ef4444; }
        .toast-success .toast-icon { background: #dcfce7; color: #15803d; }
        .toast-message { font-size: 13px; font-weight: 600; color: #334155; line-height: 1.4; }
    </style>
</head>
<body>

<!-- CONTAINER UTAMA -->
<div class="main-content-standalone">
    <div class="content-header">
        <div>
            <h1>Alur Tahapan Seleksi</h1>
            <p style="font-size: 14px; color: #64748b; margin-top: 6px;">Formasi Lowongan: <strong style="color: #4f46e5; font-weight: 700;"><?= htmlspecialchars($nama_lowongan_header ?? 'Perawat'); ?></strong></p>
        </div>
        <div class="btn-layout-flex">
            <a href="master_lowongan.php" class="btn-back">&larr; Kembali</a>
            <button class="btn-purple" onclick="bukaModalTambah()">+ Tambah Alur</button>
        </div>
    </div>
<!-- TABEL RENDER DATA -->
<div class="table-wrapper">
    <table>
        <thead>
            <tr>
                <th style="width: 60px; text-align: center;">No</th>
                <th>Nama Tahapan Seleksi</th>
                <th>Urutan Alur</th>
                <th>Minimal Nilai</th>
                <th>Aturan Kelulusan</th>
                <th style="text-align: center; width: 160px;">Aksi</th>
            </tr>
        </thead>
                <tbody>
<?php 
$no = 1;
if (isset($ambil_data) && $ambil_data && mysqli_num_rows($ambil_data) > 0) :
    while ($row = mysqli_fetch_assoc($ambil_data)) : 
        $badge_class = (isset($row['wajib_lulus']) && $row['wajib_lulus'] == 1) ? 'badge-wajib' : 'badge-opsional';
        $badge_text  = (isset($row['wajib_lulus']) && $row['wajib_lulus'] == 1) ? 'Ya (Wajib Lulus)' : 'Opsional';
        
        $id_tahapan  = $row['id'] ?? ''; 
        $id_lowongan = $_GET['lowongan_id'] ?? 6;
        
        // Menyiapkan data JSON agar bisa dilempar ke fungsi Javascript Edit Modal
        $data_json = json_encode([
            'id'            => $id_tahapan,
            'lowongan_id'   => $id_lowongan, // Pastikan baris ini ada agar dibaca oleh fungsi JS Anda
            'tahapan_id'    => $row['tahapan_id'] ?? '',
            'urutan'        => $row['urutan'] ?? '',
            'minimal_nilai' => $row['minimal_nilai'] ?? '',
            'wajib_lulus'   => $row['wajib_lulus'] ?? '1'
        ]);

?>

            <tr>
                <td style="font-weight: bold; color: #94a3b8; text-align: center;"><?= $no++; ?></td>
                <td style="font-weight: 700; color: #0f172a;"><?= htmlspecialchars($row['nama_tahapan'] ?? 'Administrasi'); ?></td>
                <td style="font-weight: 700; color: #4f46e5;">Tahap Ke-<?= $row['urutan'] ?? ''; ?></td>
                <td><strong><?= number_format($row['minimal_nilai'] ?? 0, 2); ?></strong></td>
                <td><span class="badge <?= $badge_class; ?>"><?= $badge_text; ?></span></td>
                <td style="text-align: center; white-space: nowrap;">
                    <!-- Tombol Edit (Kini memicu Javascript bukaModalEdit, bukan pindah halaman) -->
                    <button type="button" onclick='bukaModalEdit(<?= $data_json; ?>)' class="btn-action btn-edit" title="Edit" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background-color: #fef08a; border: none; border-radius: 6px; margin-right: 6px; cursor: pointer;">
                        <svg xmlns="http://w3.org" width="16" height="16" fill="#a16207" viewBox="0 0 16 16">
                            <path d="M12.146.146a.5.5 0 0 1 .708 0l3 3a.5.5 0 0 1 0 .708l-10 10a.5.5 0 0 1-.168.11l-5 2a.5.5 0 0 1-.65-.65l2-5a.5.5 0 0 1 .11-.168zM11.207 2.5 13.5 4.793 14.793 3.5 12.5 1.207zm1.586 3L10.5 3.207 4 9.707V10h.5a.5.5 0 0 1 .5.5v.5h.5a.5.5 0 0 1 .5.5v.5h.293zm-9.761 5.175-.106.106-1.528 3.821 3.821-1.528.106-.106A.5.5 0 0 1 5 12.5V12h-.5a.5.5 0 0 1-.5-.5V11h-.5a.5.5 0 0 1-.466-.316"/>
                        </svg>
                    </button>

                    <!-- Tombol Hapus (Diubah menjadi parameter GET hapus pada file ini sendiri) -->
                    <a href="lowongan_tahapan.php?lowongan_id=<?= $id_lowongan; ?>&action=delete&id=<?= $id_tahapan; ?>" class="btn-action btn-delete" onclick="return confirm('Apakah Anda yakin ingin menghapus tahapan ini?')" title="Hapus" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background-color: #fee2e2; border-radius: 6px; margin-right: 6px; text-decoration: none;">
                        <svg xmlns="http://w3.org" width="16" height="16" fill="#b91c1c" viewBox="0 0 16 16">
                            <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5m3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0z"/>
                            <path d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4zM2.5 3h11V2h-11z"/>
                        </svg>
                    </a>

                    <!-- Tombol Jadwal Seleksi (Arahkan ke jadwal_seleksi.php) -->
<a href="jadwal_seleksi.php?lowongan_id=<?= $id_lowongan; ?>&tahapan_id=<?= $id_tahapan; ?>" class="btn-action btn-schedule" title="Jadwal Seleksi" style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; background-color: #dbeafe; border-radius: 6px; text-decoration: none;">
    <svg xmlns="http://w3.org" width="16" height="16" fill="#1d4ed8" viewBox="0 0 16 16">
        <path d="M14 0H2a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M1 3.857C1 3.384 1.448 3 2 3h12c.552 0 1 .384 1 .857v10.286c0 .473-.448.857-1 .857H2c-.552 0-1-.384-1-.857z"/>
        <path d="M6.5 7a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m-9 3a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2m3 0a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
    </svg>
</a>
                </td>
            </tr>
<?php 
    endwhile;
else : 
?>
            <tr><td colspan="6" class="text-empty" style="text-align: center; padding: 24px; color: #64748b;">Belum ada alur tahapan seleksi yang dikonfigurasi untuk formasi ini.</td></tr>
<?php endif; ?>
        </tbody>

    </table>
</div>

    <!-- WINDOW POP-UP SYSTEM FORM MODAL -->
    <div class="modal-overlay" id="modalLowonganTahapan">
        <div class="modal-box">
            <h2 id="modalTitle">Tambah Alur Seleksi</h2>
            <form action="" method="POST">
                <input type="hidden" name="action" id="modalAction" value="add">
                <input type="hidden" name="id" id="modalId">
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
                
                <div class="modal-buttons">
                    <button type="button" class="btn-modal-cancel" onclick="tutupModal()">Batal</button>
                    <button type="submit" class="btn-modal-submit" id="btnSubmit">Simpan Data</button>
                </div>
            </form>
        </div>
    </div>
    <!-- CONTAINER LAYER ENGINE TOAST ALERT -->
    <div class="toast-container">
        <!-- Toast Gagal / Error Duplikat -->
        <?php if (isset($_SESSION['toast_error'])) : ?>
            <div class="toast-item toast-error" id="tError">
                <div class="toast-icon">✕</div>
                <div class="toast-message"><?= htmlspecialchars($_SESSION['toast_error']); ?></div>
            </div>
            <?php unset($_SESSION['toast_error']); ?>
        <?php endif; ?>

        <!-- Toast Berhasil / Success -->
        <?php if (isset($_SESSION['toast_success'])) : ?>
            <div class="toast-item toast-success" id="tSuccess">
                <div class="toast-icon">✓</div>
                <div class="toast-message"><?= htmlspecialchars($_SESSION['toast_success']); ?></div>
            </div>
            <?php unset($_SESSION['toast_success']); ?>
        <?php endif; ?>
    </div> <!-- Penutup toast-container -->

    <!-- LOGIKA JAVASCRIPT GLOBAL CONTROL POP-UP -->
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

        function tutupModal() { modal.style.display = 'none'; }
        window.onclick = function(event) { if (event.target == modal) { tutupModal(); } }

        // Otomatis menghilangkan toast setelah 4 detik berputar di layar monitor
        setTimeout(function() {
            var eAlert = document.getElementById('tError');
            var sAlert = document.getElementById('tSuccess');
            if(eAlert) eAlert.style.display = 'none';
            if(sAlert) sAlert.style.display = 'none';
        }, 4000);
    </script>
</body>
</html>
