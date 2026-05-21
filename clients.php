<?php
// api/v1/clients.php
require_once 'api_bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true);

switch ($method) {
    case 'GET':
        $sql = "SELECT * FROM clients ORDER BY client_name";
        $result = $conn->query($sql);
        $clients = [];
        while ($row = $result->fetch_assoc()) {
            $clients[] = $row;
        }
        echo json_encode($clients);
        break;
    // ... The rest of the POST, PUT, DELETE cases remain the same ...
}
$conn->close();
?>