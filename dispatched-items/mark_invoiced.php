<?php
// api/v1/dispatched-items/mark_invoiced.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['dispatch_item_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'dispatch_item_id is required.']);
    exit;
}

// Set the invoiced_date to the current date
$stmt = $conn->prepare("UPDATE dispatch_items SET invoiced_date = CURDATE() WHERE id = ?");
$stmt->bind_param("i", $input['dispatch_item_id']);

if ($stmt->execute()) {
    // Return the new date to update the UI
    echo json_encode(['success' => true, 'message' => 'Item marked as invoiced.', 'invoiced_date' => date('Y-m-d')]);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to mark as invoiced.']);
}

$conn->close();
?>