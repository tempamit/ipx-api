<?php
// api/v1/dispatched-items/update_comment.php
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

$comment = isset($input['comment']) ? $input['comment'] : '';

$stmt = $conn->prepare("UPDATE dispatch_items SET internal_comments = ? WHERE id = ?");
$stmt->bind_param("si", $comment, $input['dispatch_item_id']);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Comment updated.']);
} else {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update comment.']);
}

$conn->close();
?>