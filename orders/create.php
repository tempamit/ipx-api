<?php
// api/v1/orders/create.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method is accepted.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['client_id']) || !isset($input['order_date']) || !is_array($input['items']) || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input. client_id, order_date, and at least one item are required.']);
    exit;
}

$conn->begin_transaction();

try {
    $stmt_order = $conn->prepare("INSERT INTO orders (client_id, order_date, overall_status) VALUES (?, ?, 'Pending')");
    $stmt_order->bind_param("is", $input['client_id'], $input['order_date']);
    $stmt_order->execute();

    $order_id = $conn->insert_id;
    if ($order_id == 0) {
        throw new Exception("Failed to create order record.");
    }

    $stmt_items = $conn->prepare("INSERT INTO order_items (order_id, master_item_id, ordered_quantity, item_description_override, vendor_id, status) VALUES (?, ?, ?, ?, ?, 'Pending')");

    // Loop through items and insert them
    foreach ($input['items'] as $item) {
        if (!isset($item['master_item_id']) || !isset($item['ordered_quantity'])) continue;

        // --- THIS IS THE CORRECTED LOGIC ---
        // We assign all values to local variables before binding them.
        // This is a more robust way to handle potential null values with mysqli.
        $master_item_id = $item['master_item_id'];
        $ordered_quantity = $item['ordered_quantity'];
        $description = isset($item['item_description_override']) ? $item['item_description_override'] : '';
        $vendor_id = !empty($item['vendor_id']) ? $item['vendor_id'] : null;

        $stmt_items->bind_param("iiisi", 
            $order_id, 
            $master_item_id, 
            $ordered_quantity, 
            $description, 
            $vendor_id
        );
        $stmt_items->execute();
        // --- END OF CORRECTION ---
    }

    $conn->commit();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Order created successfully.', 'order_id' => $order_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create order.', 'details' => $e->getMessage()]);
}

$conn->close();
?>