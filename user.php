<?php 
session_start(); 

// 1. KONEKSI DATABASE SERVER
$host     = "10.10.6.59"; 
$user_db  = "root_host";      
$pass_db  = "password";          
$nama_db  = "magang_rekrutmen_rs"; 

$koneksi = mysqli_connect($host, $user_db, $pass_db, $nama_db);

if (!$koneksi) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

// Data Cadangan Default jika Session Belum Terisi
$nama_tampilan   = "Administrator";
$email_tampilan  = "admin@magang.id";
$username_tampilan = "admin";
$status_akun     = "Aktif";
$login_terakhir  = "Baru Saja";
$terdaftar_sejak = "12 Juni 2026";
$inisial_tampilan = "A";

if (isset($_SESSION['username'])) {
    $username_aktif = $_SESSION['username'];
} else {
    $username_aktif = "admin"; // Cadangan jalur testing
}

// 2. PROSES UPDATE DATA PROFIL (FITUR UBAH DATA)
if (isset($_POST['update_profile'])) {
    $nama_baru  = mysqli_real_escape_string($koneksi, $_POST['nama_lengkap']);
    $user_baru  = mysqli_real_escape_string($koneksi, $_POST['username_sistem']);
    $email_baru = mysqli_real_escape_string($koneksi, $_POST['alamat_email']);
    
    $query_update = "UPDATE users SET nama='$nama_baru', username='$user_baru', email='$email_baru' WHERE username='$username_aktif'";
    if (mysqli_query($koneksi, $query_update)) {
        if (isset($_SESSION['username'])) {
            $_SESSION['username'] = $user_baru; // Sinkronisasi session jika username berubah
        }
        $username_aktif = $user_baru;
        echo "<script>alert('Data profil berhasil diperbarui!'); window.location='user.php';</script>";
    }
}

// 3. PROSES TAMBAH AKUN BARU (FITUR TAMBAH AKUN)
if (isset($_POST['tambah_user'])) {
    $reg_nama  = mysqli_real_escape_string($koneksi, $_POST['reg_nama']);
    $reg_user  = mysqli_real_escape_string($koneksi, $_POST['reg_user']);
    $reg_email = mysqli_real_escape_string($koneksi, $_POST['reg_email']);
    $reg_pass  = mysqli_real_escape_string($koneksi, $_POST['reg_pass']); // Sebaiknya pakai password_hash jika login menggunakan hash
    
    $query_insert = "INSERT INTO users (nama, username, email, password, status, created_at) VALUES ('$reg_nama', '$reg_user', '$reg_email', '$reg_pass', 'Aktif', NOW())";
    if (mysqli_query($koneksi, $query_insert)) {
        echo "<script>alert('Akun pengguna baru berhasil ditambahkan!'); window.location='user.php';</script>";
    }
}

// 4. AMBIL DATA TERBARU SEBELUM DISPLAY HAML
$query = "SELECT nama, username, email, last_login, status, created_at FROM users WHERE username = '$username_aktif'";
$hasil = mysqli_query($koneksi, $query);

if ($hasil && mysqli_num_rows($hasil) > 0) {
    $data_user = mysqli_fetch_assoc($hasil);
    $nama_tampilan     = $data_user['nama'];
    $username_tampilan = $data_user['username'];
    $email_tampilan    = $data_user['email'];
    $status_akun       = $data_user['status'];
    $login_terakhir    = !empty($data_user['last_login']) ? date('d-m-Y H:i', strtotime($data_user['last_login'])) : 'Baru Saja';
    $terdaftar_sejak   = !empty($data_user['created_at']) ? date('d F Y', strtotime($data_user['created_at'])) : '12 Juni 2026';
    $inisial_tampilan  = strtoupper(substr($nama_tampilan, 0, 1));
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Pengguna - Magang ID</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, sans-serif; }
        body { background-color: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; color: #475569; }
        .dashboard-container { width: 100%; max-w: 1440px; max-width: 1440px; background: #ffffff; border-radius: 32px; box-shadow: 0 20px 40px rgba(0,0,0,0.04); display: flex; min-height: 850px; overflow: hidden; }
        
        .sidebar-left { width: 280px; background: #ffffff; border-right: 1px solid #f1f5f9; padding: 35px; display: flex; flex-direction: column; justify-content: space-between; flex-shrink: 0; }
        .brand-logo { font-size: 22px; font-weight: 800; color: #1e293b; margin-bottom: 45px; display: flex; align-items: center; gap: 10px; }
        .brand-logo span { width: 10px; height: 20px; background: #4f46e5; border-radius: 4px; display: inline-block; }
        .menu-list { display: flex; flex-direction: column; gap: 6px; }
        .menu-item { display: block; padding: 14px 18px; color: #94a3b8; text-decoration: none; border-radius: 16px; font-size: 14px; font-weight: 600; }
        .menu-item.active { background: #f5f3ff; color: #4f46e5; border-right: 4px solid #4f46e5; font-weight: 700; }
        .support-card { background: #fff5f5; border: 1px solid #fee2e2; padding: 16px; border-radius: 20px; text-align: center; margin-top: 20px; }
        .support-card a { display: block; width: 100%; background: #dc2626; color: white; padding: 12px; border-radius: 12px; font-size: 13px; font-weight: 700; text-decoration: none; }

        .main-content { flex: 1; background: #fbfbfd; padding: 40px 50px; display: flex; flex-direction: column; gap: 32px; overflow-y: auto; }
        .content-header { display: flex; justify-content: space-between; align-items: center; }
        .content-header h1 { font-size: 26px; font-weight: 800; color: #1e293b; }
        
        .profile-card { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 35px; display: flex; align-items: center; gap: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        .profile-avatar-big { width: 90px; height: 90px; background: #4f46e5; color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 36px; box-shadow: 0 10px 20px rgba(79, 70, 229, 0.2); }
        .profile-meta h2 { font-size: 22px; font-weight: 800; color: #1e293b; text-transform: capitalize; }
        .status-badge-active { display: inline-block; background: #ecfdf5; color: #059669; padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: 700; margin-top: 10px; text-transform: uppercase; }

        .details-wrapper { background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01); }
        table { width: 100%; border-collapse: collapse; text-align: left; font-size: 14px; }
        th { color: #94a3b8; padding-bottom: 16px; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9; }
        td { padding: 14px 10px; color: #475569; border-bottom: 1px solid #f8fafc; }
        
        /* Input dalam tabel */
        .table-input { width: 100%; padding: 10px 14px; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-weight: 600; color: #334155; background: #f8fafc; outline: none; transition: border 0.2s; }
        .table-input:focus { border-color: #4f46e5; background: #ffffff; }
        
        .btn-primary { background: #4f46e5; color: white; border: none; padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2); transition: background 0.2s; }
        .btn-primary:hover { background: #4338ca; }
        .btn-success { background: #059669; color: white; border: none; padding: 10px 20px; border-radius: 12px; font-size: 13px; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2); }
        .btn-success:hover { background: #047857; }

        /* Modal Pop-up Box */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); justify-content: center; align-items: center; z-index: 100; }
        .modal-content { background: #ffffff; padding: 35px; border-radius: 24px; width: 100%; max-width: 450px; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); display: flex; flex-direction: column; gap: 16px; }
        .modal-content h3 { font-size: 18px; font-weight: 800; color: #1e293b; border-bottom: 2px solid #f1f5f9; padding-bottom: 12px; }
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
                    <a href="lowongan_tahapan.php" class="menu-item">Lowongan Tahapan</a>
                                        <a href="user.php" class="menu-item active">Profil Pengguna</a>

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


        <!-- AREA KONTEN UTAMA TENGAH -->
        <main class="main-content">
            <div class="content-header">
                <h1>Detail Akun Pengguna</h1>
                <!-- FITUR: TOMBOL TAMBAH AKUN BARU -->
                <button type="button" class="btn-success" onclick="openModal()">+ Tambah Akun Baru</button>
            </div>

            <div class="profile-card">
                <div class="profile-avatar-big"><?php echo $inisial_tampilan; ?></div>
                <div class="profile-meta">
                    <h2><?php echo $nama_tampilan; ?></h2>
                    <p style="font-size: 14px; color: #94a3b8; margin-top: 4px;">Terdaftar Sejak: <?php echo $terdaftar_sejak; ?></p>
                    <span class="status-badge-active"><?php echo $status_akun; ?></span>
                </div>
            </div>

            <!-- FITUR: FORM TABEL INTERAKTIF UNTUK EDIT PROFIL -->
                        <!-- TABEL FORM PROFIL DENGAN FITUR LOCK / UNLOCK TOMBOL EDIT -->
            <div class="details-wrapper" style="background: #ffffff; border: 1px solid #f1f5f9; border-radius: 24px; padding: 30px; box-shadow: 0 4px 10px rgba(0,0,0,0.01);">
                <form action="" method="POST">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;">
                        <thead>
                            <tr style="color: #94a3b8; font-weight: 700; font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #f1f5f9;">
                                <th style="width: 30%; padding-bottom: 16px;">Kategori Kredensial</th>
                                <th style="width: 50%; padding-bottom: 16px;">Data Informasi Akun</th>
                                <th style="width: 20%; text-align: right; padding-right: 10px; padding-bottom: 16px;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 10px;">Nama Lengkap</td>
                                <!-- Ditambahkan properti id dan readonly agar kolom terkunci otomatis di awal -->
                                <td><input type="text" id="input_nama" name="nama_lengkap" class="table-input" value="<?php echo $nama_tampilan; ?>" readonly style="background: #f8fafc; color: #64748b; cursor: not-allowed;"></td>
                                <td rowspan="3" style="text-align: right; vertical-align: middle; padding-right: 10px;">
                                    <div style="display: flex; flex-direction: column; gap: 10px; align-items: flex-end;">
                                        <!-- TOMBOL EDIT BARU UNTUK MEMBUKA KUNCI INPUT -->
                                        <button type="button" id="btn_edit" class="btn-primary" style="background: #f5f3ff; color: #4f46e5; border: 1px solid #c7d2fe; box-shadow: none;" onclick="aktifkanEdit()">Edit</button>
                                        <!-- TOMBOL SIMPAN (TERKUNCI DI AWAL, NYALA SETELAH KLIK EDIT) -->
                                        <button type="submit" id="btn_simpan" name="update_profile" class="btn-primary" style="display: none;">Simpan</button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 10px;">Username Sistem</td>
                                <td><input type="text" id="input_user" name="username_sistem" class="table-input" value="<?php echo $username_tampilan; ?>" readonly style="background: #f8fafc; color: #64748b; cursor: not-allowed;"></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 10px;">Alamat Email</td>
                                <td><input type="email" id="input_email" name="alamat_email" class="table-input" value="<?php echo $email_tampilan; ?>" readonly style="background: #f8fafc; color: #64748b; cursor: not-allowed;"></td>
                            </tr>
                            <tr>
                                <td style="font-weight: 600; color: #94a3b8; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 10px;">Login Terakhir</td>
                                <td style="font-weight: 600; color: #334155; padding-left: 15px;"><?php echo $login_terakhir; ?></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </form>
            </div>
        </main>

    <!-- ================= MODAL BOX POP-UP TAMBAH AKUN ================= -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <h3>Registrasi Akun Baru</h3>
            <form action="" method="POST" style="display: flex; flex-direction: column; gap: 14px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Nama Lengkap</label>
                    <input type="text" name="reg_nama" class="table-input" placeholder="Masukkan nama lengkap..." required>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Username</label>
                    <input type="text" name="reg_user" class="table-input" placeholder="Masukkan username..." required>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Alamat Email</label>
                    <input type="email" name="reg_email" class="table-input" placeholder="Masukkan email..." required>
                </div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-size: 11px; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Password</label>
                    <input type="password" name="reg_pass" class="table-input" placeholder="Buat password keamanan..." required>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 10px; justify-content: flex-end;">
                    <button type="button" class="btn-primary" style="background: #cbd5e1; color: #475569; box-shadow: none;" onclick="closeModal()">Batal</button>
                    <button type="submit" name="tambah_user" class="btn-success">Buat Akun</button>
                </div>
            </form>
        </div>
    </div>
        <!-- KODE SAKELAR JAVASCRIPT AKTIF - TARUH DI BAWAH DI wrapper TABEL -->
    <script>
        function aktifkanEdit() {
            // 1. Ambil elemen input kolom berdasarkan ID masing-masing
            var nama  = document.getElementById('input_nama');
            var user  = document.getElementById('input_user');
            var email = document.getElementById('input_email');
            
            // 2. Ambil elemen tombol aksi
            var btnEdit   = document.getElementById('btn_edit');
            var btnSimpan = document.getElementById('btn_simpan');

            if (nama && user && email) {
                // 3. Matikan status readonly (kolom sekarang resmi bisa diketik)
                nama.removeAttribute('readonly');
                user.removeAttribute('readonly');
                email.removeAttribute('readonly');

                // 4. Ubah visual latar belakang menjadi putih bersih aktif
                nama.style.background = '#ffffff'; nama.style.color = '#1e293b'; nama.style.cursor = 'text';
                user.style.background = '#ffffff'; user.style.color = '#1e293b'; user.style.cursor = 'text';
                email.style.background = '#ffffff'; email.style.color = '#1e293b'; email.style.cursor = 'text';

                // 5. Sembunyikan tombol Edit dan memunculkan tombol Simpan biru secara instan
                if (btnEdit) btnEdit.style.display = 'none';
                if (btnSimpan) btnSimpan.style.display = 'block';
            } else {
                console.error("Elemen input tidak ditemukan! Pastikan atribut id='input_nama', id='input_user', dan id='input_email' sudah terpasang di tag <input> Anda.");
            }
        }
    </script>


    <!-- SCRIPT POP-UP CONTROL -->
    <script>
        function openModal() {
            document.getElementById('addUserModal').style.display = 'flex';
        }
        function closeModal() {
            document.getElementById('addUserModal').style.display = 'none';
        }
        window.onclick = function(event) {
            let modal = document.getElementById('addUserModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>
