<?php
// api/v1/orders/detail.php
require_once '../api_bootstrap.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required.']);
    exit;
}
$order_id = (int)$_GET['id'];
$response = [];

// Fetch main order details
$stmt_order = $conn->prepare("SELECT o.*, c.client_name FROM orders o JOIN clients c ON o.client_id = c.id WHERE o.id = ?");
$stmt_order->bind_param("i", $order_id);
$stmt_order->execute();
$response['order_details'] = $stmt_order->get_result()->fetch_assoc();

// Fetch order items
$stmt_items = $conn->prepare("SELECT oi.*, mi.item_name FROM order_items oi JOIN master_items mi ON oi.master_item_id = mi.id WHERE oi.order_id = ?");
$stmt_items->bind_param("i", $order_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();
$items = [];
while ($row = $result_items->fetch_assoc()) { $items[] = $row; }
$response['order_items'] = $items;

echo json_encode($response);
$conn->close();
?>