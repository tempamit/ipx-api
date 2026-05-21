<?php
// api/v1/orders/receive.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['received_date']) || !is_array($input['items']) || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}

$conn->begin_transaction();
try {
    $order_id_to_update = null;

    $stmt_receive = $conn->prepare(
        "INSERT INTO receiving_events (order_item_id, received_date, quantity_received, supplier_id, receiving_description_override, quantity_available_for_dispatch) VALUES (?, ?, ?, ?, ?, ?)"
    );
    $stmt_validate = $conn->prepare("
        SELECT oi.ordered_quantity, 
               (SELECT COALESCE(SUM(quantity_received), 0) FROM receiving_events WHERE order_item_id = oi.id) as total_already_received
        FROM order_items oi 
        WHERE oi.id = ?
    ");

    foreach ($input['items'] as $item) {
        if (isset($item['quantity_received']) && $item['quantity_received'] > 0) {

            // --- THIS IS THE FIX ---
            // The key from the frontend is 'id', not 'order_item_id'.
            $order_item_id = $item['id'];

            // --- VALIDATION RULE ---
            $stmt_validate->bind_param("i", $order_item_id);
            $stmt_validate->execute();
            $result = $stmt_validate->get_result()->fetch_assoc();

            $ordered = $result['ordered_quantity'];
            $already_received = $result['total_already_received'];
            $newly_receiving = $item['quantity_received'];

            if (($already_received + $newly_receiving) > $ordered) {
                throw new Exception("Cannot receive {$newly_receiving} for item ID {$order_item_id}. You have already received {$already_received} of {$ordered} ordered.");
            }

            $received_date = $input['received_date'];
            $quantity_received = $newly_receiving;
            $supplier_id = !empty($item['supplier_id']) ? $item['supplier_id'] : null;
            $description = isset($item['description']) ? $item['description'] : '';

            $stmt_receive->bind_param("isiisi", $order_item_id, $received_date, $quantity_received, $supplier_id, $description, $quantity_received);
            $stmt_receive->execute();

            if (!$order_id_to_update) {
                $res = $conn->query("SELECT order_id FROM order_items WHERE id = {$order_item_id}");
                $order_id_to_update = $res->fetch_assoc()['order_id'];
            }
        }
    }

    if ($order_id_to_update) {
        $stmt_update_order = $conn->prepare("UPDATE orders SET overall_status = 'Processing' WHERE id = ? AND overall_status = 'Pending'");
        $stmt_update_order->bind_param("i", $order_id_to_update);
        $stmt_update_order->execute();
    }

    $conn->commit();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Materials received successfully.']);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(422); 
    echo json_encode(['error' => 'Failed to record materials.', 'details' => $e->getMessage()]);
}

$conn->close();
?>