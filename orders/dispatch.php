<?php
// api/v1/orders/dispatch.php
require_once '../api_bootstrap.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input['items']) || empty($input['items'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input.']);
    exit;
}

try {
    global $conn;
    $conn->begin_transaction();

    // 1. Create the main dispatch record
    $stmt_dispatch = $conn->prepare("INSERT INTO dispatches (dispatch_date, tracking_number, mode_of_dispatch, dispatch_amount, comments) VALUES (?, ?, ?, ?, ?)");
    $stmt_dispatch->bind_param("sssis", $input['dispatch_date'], $input['tracking_number'], $input['mode_of_dispatch'], $input['dispatch_amount'], $input['comments']);
    $stmt_dispatch->execute();
    $dispatch_id = $conn->insert_id;

    $affected_order_ids = [];

    // Prepare all statements ONCE before any loops
    $stock_batches_stmt = $conn->prepare("SELECT id, quantity_available_for_dispatch FROM receiving_events WHERE order_item_id = ? AND quantity_available_for_dispatch > 0 ORDER BY received_date ASC, id ASC FOR UPDATE");
    $stmt_insert_item = $conn->prepare("INSERT INTO dispatch_items (dispatch_id, order_item_id, receiving_event_id, quantity_dispatched, value) VALUES (?, ?, ?, ?, ?)");
    $stmt_update_stock = $conn->prepare("UPDATE receiving_events SET quantity_available_for_dispatch = quantity_available_for_dispatch - ? WHERE id = ?");
    $order_id_stmt = $conn->prepare("SELECT order_id FROM order_items WHERE id = ?");

    // 2. Loop through items and process FIFO dispatch
    foreach ($input['items'] as $item) {
        $order_item_id = $item['order_item_id'];
        $quantity_to_dispatch = $item['quantity_dispatched'];
        $item_value = isset($item['value']) ? $item['value'] : 0;

        // Get all available stock batches for this item, oldest first
        $stock_batches_stmt->bind_param("i", $order_item_id);
        $stock_batches_stmt->execute();
        $stock_batches_result = $stock_batches_stmt->get_result();

        $total_available_stock = 0;
        $batches = [];
        // --- THIS WHILE LOOP WAS MISSING ---
        while($row = $stock_batches_result->fetch_assoc()) {
            $total_available_stock += $row['quantity_available_for_dispatch'];
            $batches[] = $row;
        }

        if ($quantity_to_dispatch > $total_available_stock) {
            throw new Exception("Not enough total stock for order item ID {$order_item_id}. Available: {$total_available_stock}, Tried to dispatch: {$quantity_to_dispatch}");
        }

        // Loop through the batches and "consume" the stock
        foreach ($batches as $batch) {
            if ($quantity_to_dispatch <= 0) break;

            $receiving_event_id = $batch['id'];
            $available_in_batch = $batch['quantity_available_for_dispatch'];
            $qty_from_this_batch = min($quantity_to_dispatch, $available_in_batch);

            // For simplicity, we assign the entire item value to the first dispatch record created for it.
            $value_for_this_batch = $item_value;
            $item_value = 0; 

            $stmt_insert_item->bind_param("iiiid", $dispatch_id, $order_item_id, $receiving_event_id, $qty_from_this_batch, $value_for_this_batch);
            $stmt_insert_item->execute();

            $stmt_update_stock->bind_param("ii", $qty_from_this_batch, $receiving_event_id);
            $stmt_update_stock->execute();

            $quantity_to_dispatch -= $qty_from_this_batch;
        }

        $order_id_stmt->bind_param("i", $order_item_id);
        $order_id_stmt->execute();
        $res_order_id = $order_id_stmt->get_result()->fetch_assoc()['order_id'];
        if (!in_array($res_order_id, $affected_order_ids)) {
            $affected_order_ids[] = $res_order_id;
        }
    }

    // 3. Update the overall status for each affected order
    foreach($affected_order_ids as $order_id) {
        $res_totals = $conn->query("SELECT (SELECT SUM(ordered_quantity) FROM order_items WHERE order_id = {$order_id}) as total_ordered, (SELECT COALESCE(SUM(quantity_dispatched), 0) FROM dispatch_items di JOIN order_items oi ON di.order_item_id = oi.id WHERE oi.order_id = {$order_id}) as total_dispatched")->fetch_assoc();

        $new_status = 'Partially Dispatched';
        if ($res_totals['total_dispatched'] > 0 && $res_totals['total_dispatched'] >= $res_totals['total_ordered']) {
            $new_status = 'Completed';
        }
        $conn->query("UPDATE orders SET overall_status = '{$new_status}' WHERE id = {$order_id}");
    }

    $conn->commit();
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Dispatch created successfully.', 'dispatch_id' => $dispatch_id]);

} catch (Exception $e) {
    $conn->rollback();
    http_response_code(422);
    echo json_encode(['error' => 'Failed to create dispatch.', 'details' => $e->getMessage()]);
}

$conn->close();
?>