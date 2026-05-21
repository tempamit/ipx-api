<?php
// api/v1/items.php
require_once 'api_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $sql = "SELECT * FROM master_items ORDER BY item_name";
        $result = $conn->query($sql);
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode($items);
        break;
    // ... The rest of the POST, PUT, DELETE cases remain the same ...
}
$conn->close();
?>