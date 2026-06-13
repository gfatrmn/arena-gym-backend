<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$DB_NAME = "psi"; 
$DB_USER = "root";
$DB_PASS = "";
$DB_SERVER_LOC = "localhost";

$conn = mysqli_connect($DB_SERVER_LOC, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    echo json_encode(array("status" => "error", "message" => "Koneksi database gagal: " . mysqli_connect_error()));
    exit();
}

$path_folder = "member-photos/";
if (!file_exists($path_folder)) {
    mkdir($path_folder, 0777, true);
}

$mode = $_POST['mode'] ?? 'show';

switch ($mode) {
    case "show":
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $status_filter = isset($_POST['status_filter']) ? trim($_POST['status_filter']) : 'all';
        
        $where_conditions = array();

        if (!empty($search)) {
            $search = mysqli_real_escape_string($conn, $search);
            $where_conditions[] = "(full_name LIKE '%$search%' OR phone LIKE '%$search%')";
        }

        if ($status_filter === 'active') {
            $where_conditions[] = "(status = 'active' OR status = 'aktif' OR status = 'member')";
        } else if ($status_filter === 'inactive') {
            $where_conditions[] = "(status = 'inactive' OR status = 'non-aktif' OR status = 'non_active')";
        }

        $where_clause = "";
        if (count($where_conditions) > 0) {
            $where_clause = " WHERE " . implode(" AND ", $where_conditions);
        }

        $sql = "SELECT id, full_name, email, phone, status, expires_at, profile_photo_path FROM gym_members" . $where_clause . " ORDER BY id DESC";
        $result = mysqli_query($conn, $sql);
        $data_list = array();
        
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $domainName = $_SERVER['HTTP_HOST']; 
        $baseUrl = $protocol . $domainName . "/mobile/";

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                if (!empty($row['profile_photo_path']) && file_exists($row['profile_photo_path'])) {
                    $row['photo_url'] = $baseUrl . $row['profile_photo_path'];
                } else {
                    $row['photo_url'] = "";
                }
                array_push($data_list, $row);
            }
        }
        echo json_encode($data_list);
        exit();

    case "insert":
        $full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $imstr = isset($_POST['image']) ? trim($_POST['image']) : '';
        $file_name = isset($_POST['file']) ? trim($_POST['file']) : '';

        if (empty($full_name)) {
            echo json_encode(array("status" => "error", "message" => "Nama atlet tidak boleh kosong!"));
            exit();
        }

        if (!empty($email)) {
            $check_email = mysqli_query($conn, "SELECT id FROM gym_members WHERE email = '" . mysqli_real_escape_string($conn, $email) . "'");
            if (mysqli_num_rows($check_email) > 0) {
                echo json_encode(array("status" => "error", "message" => "Gagal: Email sudah digunakan oleh atlet lain!"));
                exit();
            }
        }

        $full_name = mysqli_real_escape_string($conn, $full_name);
        $email_db = !empty($email) ? "'" . mysqli_real_escape_string($conn, $email) . "'" : "NULL";
        $phone_db = !empty($phone) ? "'" . mysqli_real_escape_string($conn, $phone) . "'" : "NULL";

        $photo_db_path = "NULL";
        if (!empty($imstr) && !empty($file_name)) {
            if (file_put_contents($path_folder . $file_name, base64_decode($imstr)) !== false) {
                $photo_db_path = "'" . mysqli_real_escape_string($conn, $path_folder . $file_name) . "'";
            }
        }

        $checkin_code = "AGM-" . strtoupper(substr(md5(time() . rand()), 0, 8));

        $sql = "INSERT INTO gym_members (full_name, email, phone, checkin_code, profile_photo_path, joined_at, created_at, expires_at, status) 
                VALUES ('$full_name', $email_db, $phone_db, '$checkin_code', $photo_db_path, CURDATE(), CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH), 'member')";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Atlet baru berhasil disimpan (Aktif 1 Bulan)"));
        } else {
            echo json_encode(array("status" => "error", "message" => "MySQL Error: " . mysqli_error($conn)));
        }
        break;

    case "update":
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $imstr = isset($_POST['image']) ? trim($_POST['image']) : '';
        $file_name = isset($_POST['file']) ? trim($_POST['file']) : '';

        $res_old = mysqli_query($conn, "SELECT profile_photo_path FROM gym_members WHERE id='$id'");
        $data_old = mysqli_fetch_assoc($res_old);
        $photo_db_path = $data_old['profile_photo_path'] ?? '';

        if (!empty($imstr) && !empty($file_name)) {
            if (!empty($photo_db_path) && file_exists($photo_db_path)) {
                unlink($photo_db_path); 
            }
            if (file_put_contents($path_folder . $file_name, base64_decode($imstr)) !== false) {
                $photo_db_path = $path_folder . $file_name;
            }
        }

        $email_db = !empty($email) ? "'" . mysqli_real_escape_string($conn, $email) . "'" : "NULL";
        $phone_db = !empty($phone) ? "'" . mysqli_real_escape_string($conn, $phone) . "'" : "NULL";
        $photo_db = !empty($photo_db_path) ? "'" . mysqli_real_escape_string($conn, $photo_db_path) . "'" : "NULL";

        $sql = "UPDATE gym_members SET full_name='$full_name', email=$email_db, phone=$phone_db, profile_photo_path=$photo_db WHERE id='$id'";
        
        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Profil atlet berhasil diperbarui"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal update: " . mysqli_error($conn)));
        }
        break;

    case "renew":
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        
        // 1. Ambil nama member untuk keperluan data pencatatan transaksi kasir
        $res_member = mysqli_query($conn, "SELECT full_name FROM gym_members WHERE id = '$id'");
        $member_data = mysqli_fetch_assoc($res_member);
        
        if (!$member_data) {
            echo json_encode(array("status" => "error", "message" => "Atlet tidak ditemukan!"));
            exit();
        }
        
        $customer_name = mysqli_real_escape_string($conn, $member_data['full_name']);
        
        // 2. Generate kode invoice unik otomatis (Contoh: INV-20260613XXXX)
        $invoice = "INV-" . date('Ymd') . strtoupper(substr(md5(time() . rand()), 0, 4));

        // 3. Mulai Database Transaction agar jika salah satu query gagal, data tidak rusak
        mysqli_begin_transaction($conn);

        // Query A: Perbarui masa aktif di tabel gym_members
        $sql_update_member = "UPDATE gym_members 
                              SET expires_at = DATE_ADD(GREATEST(IFNULL(expires_at, CURDATE()), CURDATE()), INTERVAL 1 MONTH), 
                                  status = 'member',
                                  updated_at = NOW() 
                              WHERE id = '$id'";
        
        $query_a_success = mysqli_query($conn, $sql_update_member);

        // Query B: Masukkan log riwayat iuran Rp90.000 ke dalam tabel cashier_transactions (Sesuai struktur CMD Anda)
        $sql_insert_transaction = "INSERT INTO cashier_transactions 
                                   (invoice, gym_member_id, customer_name, transaction_group, transaction_type, amount, paid_amount, quantity, payment_method, payment_status, receipt_status, transaction_at, created_at, updated_at) 
                                   VALUES 
                                   ('$invoice', '$id', '$customer_name', 'membership', 'renewal', 90000, 90000, 1, 'cash', 'paid', 'completed', NOW(), NOW(), NOW())";
        
        $query_b_success = mysqli_query($conn, $sql_insert_transaction);

        // 4. Eksekusi pengecekan kesuksesan kedua query
        if ($query_a_success && $query_b_success) {
            mysqli_commit($conn); // Simpan permanen ke database Laragon
            echo json_encode(array("status" => "success", "message" => "Masa aktif diperpanjang 1 bulan!"));
        } else {
            mysqli_rollback($conn); // Batalkan semua jika ada yang gagal biar tidak error
            echo json_encode(array("status" => "error", "message" => "Gagal perpanjang transaksi: " . mysqli_error($conn)));
        }
        break;

    case "delete":
        $id = mysqli_real_escape_string($conn, $_POST['id']);
        $res_photo = mysqli_query($conn, "SELECT profile_photo_path FROM gym_members WHERE id='$id'");
        $data_photo = mysqli_fetch_assoc($res_photo);
        $file_photo = $data_photo['profile_photo_path'] ?? '';

        if (!empty($file_photo) && file_exists($file_photo)) {
            unlink($file_photo); 
        }

        $sql = "DELETE FROM gym_members WHERE id='$id'";
        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Atlet berhasil deleted"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal menghapus data"));
        }
        break;
}

mysqli_close($conn);
?>