<?php
// api/v1/orders/dispatch-details.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if (!isset($_GET['order_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Order ID is required.']);
    exit;
}
$order_id = (int)$_GET['order_id'];

$sql = "SELECT
            re.id as receiving_event_id,
            re.received_date,
            re.quantity_available_for_dispatch,
            oi.id as order_item_id,
            oi.ordered_quantity,
            mi.item_name,
            s.vendor_name as supplier_name
        FROM receiving_events re
        JOIN order_items oi ON re.order_item_id = oi.id
        JOIN master_items mi ON oi.master_item_id = mi.id
        JOIN vendors s ON re.supplier_id = s.id
        WHERE oi.order_id = ? AND re.quantity_available_for_dispatch > 0
        ORDER BY mi.item_name, re.received_date";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();

$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
echo json_encode($items);
$conn->close();
?>