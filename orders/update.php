<?php
// api/v1/orders/update.php
require_once '../api_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... */ }

// The rest of the update logic from Step 75 remains the same.
// Just ensure the require_once line at the top is correct.

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id']) || !isset($input['items']) || !is_array($input['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}

$order_id = $input['order_id'];
$submitted_items = $input['items'];

$conn->begin_transaction();
try {
    // 1. Get existing item IDs for this order from the DB
    $existing_item_ids = [];
    $result = $conn->query("SELECT id FROM order_items WHERE order_id = {$order_id}");
    while($row = $result->fetch_assoc()) {
        $existing_item_ids[] = $row['id'];
    }

    $submitted_item_ids = [];
    $stmt_update = $conn->prepare("UPDATE order_items SET ordered_quantity=?, item_description_override=?, vendor_id=? WHERE id=?");
    $stmt_insert = $conn->prepare("INSERT INTO order_items (order_id, master_item_id, ordered_quantity, item_description_override, vendor_id, status) VALUES (?, ?, ?, ?, ?, 'Pending')");

    // 2. Loop through submitted items to update existing or insert new
    foreach ($submitted_items as $item) {
        $item_id = isset($item['id']) ? $item['id'] : null;
        $vendor_id = !empty($item['vendor_id']) ? $item['vendor_id'] : null;
        $description = isset($item['item_description_override']) ? $item['item_description_override'] : '';

        if ($item_id && in_array($item_id, $existing_item_ids)) {
            // This is an existing item, UPDATE it
            $stmt_update->bind_param("isii", $item['ordered_quantity'], $description, $vendor_id, $item_id);
            $stmt_update->execute();
            $submitted_item_ids[] = $item_id;
        } else {
            // This is a new item, INSERT it
            $stmt_insert->bind_param("iiisi", $order_id, $item['master_item_id'], $item['ordered_quantity'], $description, $vendor_id);
            $stmt_insert->execute();
        }
    }

    // 3. Determine which items to DELETE
    $items_to_delete = array_diff($existing_item_ids, $submitted_item_ids);
    if (!empty($items_to_delete)) {
        $stmt_delete = $conn->prepare("DELETE FROM order_items WHERE id = ?");
        foreach ($items_to_delete as $id_to_delete) {
            $stmt_delete->bind_param("i", $id_to_delete);
            $stmt_delete->execute();
        }
    }

    // 4. Update the main order date
    $stmt_order_update = $conn->prepare("UPDATE orders SET order_date = ? WHERE id = ?");
    $stmt_order_update->bind_param("si", $input['order_date'], $order_id);
    $stmt_order_update->execute();

    $conn->commit();
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Order updated successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update order.', 'details' => $e->getMessage()]);
}

$conn->close();
?>