<?php
// api/v1/orders/lifecycle.php

// Use the new centralized bootstrap file, which handles DB connection and all headers
require_once '../api_bootstrap.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required.']);
    exit;
}
$order_id = (int)$_GET['id'];
$response = [];

// 1. Fetch main order details
$stmt_order = $conn->prepare("SELECT o.*, c.client_name FROM orders o JOIN clients c ON o.client_id = c.id WHERE o.id = ?");
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$response['order_details'] = $stmt_order->get_result()->fetch_assoc();

// 2. Fetch all order items
$stmt_items = $conn->prepare("
    SELECT 
        oi.*, 
        mi.item_name,
        o.order_date,
        c.client_name
    FROM order_items oi 
    JOIN master_items mi ON oi.master_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN clients c ON o.client_id = c.id
    WHERE oi.order_id = ?
");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
$items = [];
while ($row = $result_items->fetch_assoc()) { $items[] = $row; }
$response['order_items'] = $items;

// 3. Fetch all receiving events for this order
$stmt_receiving = $conn->prepare("SELECT re.*, mi.item_name, v.vendor_name FROM receiving_events re JOIN order_items oi ON re.order_item_id = oi.id JOIN master_items mi ON oi.master_item_id = mi.id JOIN vendors v ON re.supplier_id = v.id WHERE oi.order_id = ? ORDER BY re.received_date DESC");
$stmt_receiving->bind_param("i", $order_id);
$stmt_receiving->execute();
$result_receiving = $stmt_receiving->get_result();
$receiving_history = [];
while ($row = $result_receiving->fetch_assoc()) { $receiving_history[] = $row; }
$response['receiving_history'] = $receiving_history;

// 4. Fetch all dispatch events for this order
$stmt_dispatch = $conn->prepare("SELECT d.*, di.quantity_dispatched, mi.item_name FROM dispatches d JOIN dispatch_items di ON d.id = di.dispatch_id JOIN order_items oi ON di.order_item_id = oi.id JOIN master_items mi ON oi.master_item_id = mi.id WHERE oi.order_id = ? ORDER BY d.dispatch_date DESC");
$stmt_dispatch->bind_param("i", $order_id);
$stmt_dispatch->execute();
$result_dispatch = $stmt_dispatch->get_result();
$dispatch_history = [];
while ($row = $result_dispatch->fetch_assoc()) { $dispatch_history[] = $row; }
$response['dispatch_history'] = $dispatch_history;

echo json_encode($response);
$conn->close();
?>