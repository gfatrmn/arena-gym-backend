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

// Set zona waktu Asia/Jakarta agar log pencatatan sinkron
date_default_timezone_set('Asia/Jakarta');

// FIXED: Mengambil data POST menggunakan parameter 'login' sesuai request dari Android
$username_input = isset($_POST['login']) ? trim($_POST['login']) : '';
$password_input = isset($_POST['password']) ? trim($_POST['password']) : '';

if (empty($username_input) || empty($password_input)) {
    echo json_encode(array("status" => "error", "message" => "Username/Login dan Password wajib diisi!"));
    exit();
}

$username_clean = mysqli_real_escape_string($conn, $username_input);

// Query memeriksa kecocokan data input ke kolom login ataupun email di database
$sql = "SELECT id, name, login, email, password, role FROM users 
        WHERE login = '$username_clean' OR email = '$username_clean' LIMIT 1";
        
$result = mysqli_query($conn, $sql);

if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    
    // Verifikasi enkripsi Bcrypt ($2y$12$...) atau fallback teks biasa
    if (password_verify($password_input, $row['password']) || $password_input === $row['password']) {
        
        // Memetakan nilai string role agar sesuai dengan navigasi intent Kotlin di Android Studio
        $current_role = strtolower($row['role']);
        if ($current_role === 'cashier') {
            $mapped_role = 'kasir';
        } else if ($current_role === 'master_admin') {
            $mapped_role = 'admin';
        } else {
            $mapped_role = $current_role; // tetap 'admin', 'kasir', atau 'member'
        }

        $response = array(
            "status" => "success",
            "message" => "Selamat Datang, " . $row['name'],
            "user" => array(
                "id" => $row['id'],
                "full_name" => $row['name'], // Alias 'name' ke 'full_name' agar dibaca lancar oleh ViewBinding
                "email" => $row['email'],
                "role" => $mapped_role
            )
        );
        echo json_encode($response);
    } else {
        echo json_encode(array("status" => "error", "message" => "Password yang Anda masukkan salah!"));
    }
} else {
    echo json_encode(array("status" => "error", "message" => "Akun tidak ditemukan atau belum terdaftar!"));
}

mysqli_close($conn);
?>