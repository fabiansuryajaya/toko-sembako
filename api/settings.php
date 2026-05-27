<?php
require_once("../connection.php");

// Set header response JSON
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query_data = $_GET;
        
        // Get all settings or specific setting
        $sql = "SELECT nama_setting, nilai_setting FROM settings";
        
        if (isset($query_data['nama'])) {
            $nama = $conn->real_escape_string($query_data['nama']);
            $sql .= " WHERE nama_setting = '$nama'";
        }
        
        $result = $conn->query($sql);
        
        $settings = [];
        while ($row = $result->fetch_assoc()) {
            // Convert boolean settings to proper format
            if ($row['nilai_setting'] === '0' || $row['nilai_setting'] === '1') {
                $row['nilai_setting'] = (int)$row['nilai_setting'];
            }
            $settings[] = $row;
        }
        
        echo json_encode(['data' => $settings]);
        break;
        
    case 'PUT':
        // Update setting
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['nama_setting'], $data['nilai_setting'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Data tidak lengkap']);
            exit;
        }
        
        $nama_setting = $conn->real_escape_string($data['nama_setting']);
        $nilai_setting = $conn->real_escape_string($data['nilai_setting']);
        
        $sql = "UPDATE settings SET nilai_setting='$nilai_setting' WHERE nama_setting='$nama_setting'";
        if ($conn->query($sql) === TRUE) {
            echo json_encode(['success' => true]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Gagal update setting']);
        }
        break;

    case 'POST':
        // Handle file upload for signature and deletion action
        // Upload signature image via field name 'signature'
        if (isset($_FILES['signature']) && $_FILES['signature']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../assets/signatures/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $tmpName = $_FILES['signature']['tmp_name'];
            $origName = basename($_FILES['signature']['name']);
            $ext = pathinfo($origName, PATHINFO_EXTENSION);
            $newName = 'signature_' . time() . '.' . $ext;
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($tmpName, $destPath)) {
                $relativePath = 'assets/signatures/' . $newName;
                $nama_setting = 'signature_image';
                $escaped = $conn->real_escape_string($relativePath);

                $sqlCheck = "SELECT id_setting FROM settings WHERE nama_setting='$nama_setting'";
                $res = $conn->query($sqlCheck);
                if ($res && $res->num_rows > 0) {
                    $sql = "UPDATE settings SET nilai_setting='$escaped' WHERE nama_setting='$nama_setting'";
                } else {
                    $sql = "INSERT INTO settings (nama_setting, nilai_setting, tipe_setting) VALUES ('$nama_setting', '$escaped', 'file')";
                }

                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['success' => true, 'path' => $relativePath]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Gagal menyimpan path ke database']);
                }
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Gagal mengupload file']);
            }
            exit;
        }

        // Delete signature action: expects POST field 'action' = 'delete_signature'
        if (isset($_POST['action']) && $_POST['action'] === 'delete_signature') {
            $nama_setting = 'signature_image';
            $q = "SELECT nilai_setting FROM settings WHERE nama_setting='$nama_setting' LIMIT 1";
            $r = $conn->query($q);
            if ($r && $r->num_rows > 0) {
                $row = $r->fetch_assoc();
                $current = $row['nilai_setting'];
                if ($current) {
                    $filePath = __DIR__ . '/../' . $current;
                    if (file_exists($filePath)) {@unlink($filePath);} 
                }

                $sql = "UPDATE settings SET nilai_setting='' WHERE nama_setting='$nama_setting'";
                if ($conn->query($sql) === TRUE) {
                    echo json_encode(['success' => true]);
                } else {
                    http_response_code(500);
                    echo json_encode(['error' => 'Gagal memperbarui database']);
                }
            } else {
                // nothing to delete but return success
                echo json_encode(['success' => true]);
            }
            exit;
        }
        
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Metode tidak diizinkan']);
        break;
}
