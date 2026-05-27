<?php
require_once("../connection.php");

// Set header response JSON
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['method'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Method tidak disertakan']);
    exit;
}

$method = $data['method'];

switch ($method) {
    case 'read':
        $query_data = $data;

        // Ambil semua supplier beserta produk yang dijual
        $sql = "SELECT su.id_supplier as id, su.nama_supplier as nama, su.nomor_hp as no_hp, 
                COALESCE(GROUP_CONCAT(DISTINCT pr.nama_product ORDER BY pr.nama_product SEPARATOR ', '), '-') as produk
                FROM supplier su
                LEFT JOIN product pr ON su.id_supplier = pr.id_supplier
                WHERE su.status = 'Y'";
        if (isset($query_data['id'])) {
            $id = (int)$query_data['id'];
            $sql .= " AND su.id_supplier = $id";
        }
        $sql .= ' GROUP BY su.id_supplier';

        $result = $conn->query($sql);

        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }

        echo json_encode($suppliers);
        break;

    case 'create':
        // Tambah supplier baru

        if (!isset($data['nama'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Nama wajib diisi']);
            exit;
        }

        $nama = $conn->real_escape_string($data['nama']);
        $no_hp = $conn->real_escape_string($data['no_hp']);

        $sql = "INSERT INTO supplier (nama_supplier, nomor_hp) VALUES ('$nama', '$no_hp')";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true, 'id' => $conn->insert_id]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal menambah supplier']);
        }
        break;

    case 'update':
        // Update supplier

        if (!isset($data['id'], $data['nama'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Data tidak lengkap']);
            exit;
        }

        $id = (int)$data['id'];
        $nama = $conn->real_escape_string($data['nama']);
        $no_hp = $conn->real_escape_string($data['no_hp']);

        $sql = "UPDATE supplier SET nama_supplier='$nama', nomor_hp='$no_hp' WHERE id_supplier=$id";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal update supplier']);
        }
        break;

    case 'delete':
        if (!isset($data['id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'ID tidak disertakan']);
            exit;
        }

        $id = (int)$data['id'];
        $sql = "UPDATE supplier SET status = 'N' WHERE id_supplier=$id";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal hapus supplier']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Method tidak valid']);
        break;
}
