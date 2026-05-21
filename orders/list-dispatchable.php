<?php
// api/v1/orders/list-dispatchable.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Selects orders that have at least one receiving event with quantity available
$sql = "SELECT DISTINCT o.id, c.client_name
        FROM orders o
        JOIN clients c ON o.client_id = c.id
        JOIN order_items oi ON o.id = oi.order_id
        JOIN receiving_events re ON oi.id = re.order_item_id
        WHERE re.quantity_available_for_dispatch > 0
        AND o.overall_status NOT IN ('Completed', 'Cancelled')
        ORDER BY o.id DESC";

$result = $conn->query($sql);
$orders = [];
while ($row = $result->fetch_assoc()) {
    $row['display_name'] = '#' . $row['id'] . ' - ' . $row['client_name'];
    $orders[] = $row;
}
echo json_encode($orders);
$conn->close();
?>