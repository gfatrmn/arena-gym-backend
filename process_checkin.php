<?php
// Mengamankan agar PHP tidak membuang error HTML yang merusak format JSON Android
error_reporting(0);
ini_set('display_errors', 0);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$conn = mysqli_connect("localhost", "root", "", "psi");

if (!$conn) {
    echo json_encode(array("status" => "error", "message" => "Database gagal terhubung"));
    exit();
}

$mode = $_POST['mode'] ?? '';

// 1. MODE: Ambil Pilihan Nama Member untuk AutoComplete Android
if ($mode == 'get_members') {
    $result = mysqli_query($conn, "SELECT id, full_name FROM gym_members ORDER BY full_name ASC");
    $data = array();
    while ($row = mysqli_fetch_assoc($result)) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit();
} else if ($mode == 'get_logs') {
    // FIX: Mengubah g.visit_at menjadi g.created_at untuk mengambil jam & menit realtime
    $query = "SELECT m.full_name AS name, 'MEMBER' AS type, DATE_FORMAT(c.checked_in_at, '%H:%i') AS checkin_time, c.checked_in_at AS raw_time
              FROM gym_checkins c
              JOIN gym_members m ON c.gym_member_id = m.id 
              WHERE DATE(c.checked_in_at) = CURDATE()
              
              UNION ALL
              
              SELECT g.full_name AS name, 'DAILY PASS' AS type, DATE_FORMAT(g.created_at, '%H:%i') AS checkin_time, g.created_at AS raw_time
              FROM daily_guests g 
              WHERE DATE(g.created_at) = CURDATE()
              
              ORDER BY raw_time DESC LIMIT 10";

    $result = mysqli_query($conn, $query);
    $data = array();

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = array(
                "name" => $row['name'],
                "type" => $row['type'],
                "checkin_time" => $row['checkin_time'] ?? '--:--'
            );
        }
    }

    echo json_encode($data);
    exit();
}

// 3. MODE: Proses Submit Check-In Form (DIPERBAIKI TOTAL)
else if ($mode == 'submit_checkin') {
    $type = $_POST['type'] ?? 'member';

    if ($type == 'member') {
        $member_id = mysqli_real_escape_string($conn, $_POST['member_id'] ?? '');

        // Proteksi jika ID kosong
        if (empty($member_id)) {
            echo json_encode(array("status" => "error", "message" => "ID Member tidak boleh kosong!"));
            exit();
        }

        // Cek validasi tanggal kedaluwarsa member
        $check = mysqli_query($conn, "SELECT expires_at, full_name FROM gym_members WHERE id = '$member_id'");
        $member = mysqli_fetch_assoc($check);

        if ($member) {
            $expiry = $member['expires_at'];
            // Jika member expired, batalkan checkin
            if ($expiry != null && $expiry != 'null' && !empty($expiry) && strtotime($expiry) < time()) {
                echo json_encode(array(
                    "status" => "error",
                    "message" => "Gagal! Masa aktif member " . $member['full_name'] . " sudah habis ($expiry)."
                ));
                exit();
            }
        }

        // Jalankan Query Insert murni ke tabel gym_checkins
        $sql = "INSERT INTO gym_checkins (gym_member_id, checked_in_at, checkin_method, verification_status) 
                VALUES ('$member_id', NOW(), 'admin', 'verified')";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Check-In Member Berhasil!"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal simpan SQL: " . mysqli_error($conn)));
        }
    } else {
        // Mode Non-Member (FIXED TYPO FUNGSI)
        $name = mysqli_real_escape_string($conn, $_POST['non_member_name'] ?? '');

        if (empty($name)) {
            echo json_encode(array("status" => "error", "message" => "Nama non-member kosong!"));
            exit();
        }

        $sql = "INSERT INTO daily_guests (full_name, payment_amount, payment_method, visit_at, created_at) 
        VALUES ('$name', 10000, 'cash', NOW(), NOW())";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Check-In Non-Member Sukses!"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal simpan SQL: " . mysqli_error($conn)));
        }
    }
    exit();
}

mysqli_close($conn);
