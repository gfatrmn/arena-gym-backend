<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

$conn = mysqli_connect("localhost", "root", "", "psi");

if (!$conn) {
    echo json_encode(array("status" => "error", "message" => "Koneksi database gagal: " . mysqli_connect_error()));
    exit();
}

// Set zona waktu agar NOW() sinkron dengan waktu lokal
date_default_timezone_set('Asia/Jakarta');

$mode = $_POST['mode'] ?? 'show';

switch ($mode) {
    case "show":
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        $where_clause = "";
        
        if (!empty($search)) {
            $search = mysqli_real_escape_string($conn, $search);
            $where_clause = " WHERE name LIKE '%$search%' OR category LIKE '%$search%' OR brand LIKE '%$search%'";
        }

        $sql = "SELECT id, name, category, brand, price, stock, unit FROM products" . $where_clause . " ORDER BY id DESC";
        $result = mysqli_query($conn, $sql);
        $data_list = array();

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $data_list[] = $row;
            }
        }
        echo json_encode($data_list);
        break;

    case "insert":
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
        $brand = mysqli_real_escape_string($conn, $_POST['brand'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $unit = mysqli_real_escape_string($conn, $_POST['unit'] ?? 'pcs');

        if (empty($name)) {
            echo json_encode(array("status" => "error", "message" => "Nama produk wajib diisi!"));
            exit();
        }

        $sql = "INSERT INTO products (name, category, brand, price, stock, unit, is_active, created_at, updated_at) 
                VALUES ('$name', '$category', '$brand', $price, $stock, '$unit', 1, NOW(), NOW())";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Produk berhasil ditambahkan"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal insert: " . mysqli_error($conn)));
        }
        break;

    case "update":
        $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
        $name = mysqli_real_escape_string($conn, $_POST['name'] ?? '');
        $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
        $brand = mysqli_real_escape_string($conn, $_POST['brand'] ?? '');
        $price = (int)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $unit = mysqli_real_escape_string($conn, $_POST['unit'] ?? 'pcs');

        if (empty($id)) {
            echo json_encode(array("status" => "error", "message" => "ID produk tidak valid untuk update!"));
            exit();
        }

        $sql = "UPDATE products SET name='$name', category='$category', brand='$brand', price=$price, stock=$stock, unit='$unit', updated_at=NOW() WHERE id='$id'";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Data produk berhasil diperbarui"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal update: " . mysqli_error($conn)));
        }
        break;

    case "delete":
        $id = mysqli_real_escape_string($conn, $_POST['id'] ?? '');
        
        if (empty($id)) {
            echo json_encode(array("status" => "error", "message" => "ID produk tidak ditemukan!"));
            exit();
        }

        $sql = "DELETE FROM products WHERE id='$id'";

        if (mysqli_query($conn, $sql)) {
            echo json_encode(array("status" => "success", "message" => "Produk berhasil dihapus"));
        } else {
            echo json_encode(array("status" => "error", "message" => "Gagal menghapus produk: " . mysqli_error($conn)));
        }
        break;
}

mysqli_close($conn);
?>