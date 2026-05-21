<?php
// api/v1/dispatched-items/update_value.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['dispatch_item_id']) || !isset($input['value'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. dispatch_item_id and value are required.']);
    exit;
}

$stmt = $conn->prepare("UPDATE dispatch_items SET value = ? WHERE id = ?");
$stmt->bind_param("di", $input['value'], $input['dispatch_item_id']);

if ($stmt->execute()) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Value updated successfully.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update value.']);
}

$conn->close();
?>