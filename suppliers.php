<?php
// api/v1/suppliers.php
require_once 'api_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $sql = "SELECT * FROM vendors ORDER BY vendor_name";
        $result = $conn->query($sql);
        $suppliers = [];
        while ($row = $result->fetch_assoc()) {
            $suppliers[] = $row;
        }
        echo json_encode($suppliers);
        break;
    // ... The rest of the POST, PUT, DELETE cases remain the same ...
}
$conn->close();
?>