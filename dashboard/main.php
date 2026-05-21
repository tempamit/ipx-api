<?php
// api/v1/dashboard/main.php
require_once '../api_bootstrap.php';

$response = [];

// 1. KPI Stats (same as before)
$stats = [
    'pending_orders' => 0,
    'non_invoiced_value' => 0.00,
    'items_ready_for_dispatch' => 0
];
$stats_result = $conn->query("SELECT
    (SELECT COUNT(id) FROM orders WHERE overall_status NOT IN ('Completed', 'Cancelled')) as pending_orders,
    (SELECT COALESCE(SUM(value), 0) FROM dispatch_items WHERE invoiced_date IS NULL) as non_invoiced_value,
    (SELECT COALESCE(SUM(quantity_available_for_dispatch), 0) FROM receiving_events) as items_ready_for_dispatch
")->fetch_assoc();
if ($stats_result) {
    $stats['pending_orders'] = (int)$stats_result['pending_orders'];
    $stats['non_invoiced_value'] = (float)$stats_result['non_invoiced_value'];
    $stats['items_ready_for_dispatch'] = (int)$stats_result['items_ready_for_dispatch'];
}
$response['stats'] = $stats;

// 2. Recent Orders (Last 5)
$recent_orders_result = $conn->query("
    SELECT o.id, o.order_date, o.overall_status, c.client_name 
    FROM orders o 
    JOIN clients c ON o.client_id = c.id 
    ORDER BY o.id DESC 
    LIMIT 5
");
$recent_orders = [];
while($row = $recent_orders_result->fetch_assoc()) {
    $recent_orders[] = $row;
}
$response['recent_orders'] = $recent_orders;

// 3. Top 5 Clients by Outstanding Value
$top_clients_result = $conn->query("
    SELECT c.client_name, SUM(di.value) as outstanding_value
    FROM dispatch_items di
    JOIN order_items oi ON di.order_item_id = oi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN clients c ON o.client_id = c.id
    WHERE di.invoiced_date IS NULL AND di.value > 0
    GROUP BY c.client_name
    ORDER BY outstanding_value DESC
    LIMIT 5
");
$top_clients = [];
while($row = $top_clients_result->fetch_assoc()) {
    $top_clients[] = $row;
}
$response['top_clients'] = $top_clients;

// 4. Action Required: Items Pending Inward (Top 5)
$pending_inward_result = $conn->query("
    SELECT oi.id, mi.item_name, o.id as order_id, c.client_name, (oi.ordered_quantity - COALESCE(SUM(re.quantity_received), 0)) as pending_quantity
    FROM order_items oi
    JOIN master_items mi ON oi.master_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN clients c ON o.client_id = c.id
    LEFT JOIN receiving_events re ON oi.id = re.order_item_id
    WHERE o.overall_status NOT IN ('Completed', 'Cancelled')
    GROUP BY oi.id, mi.item_name, o.id, c.client_name
    HAVING pending_quantity > 0
    ORDER BY o.order_date ASC
    LIMIT 5
");
$pending_inwards = [];
while($row = $pending_inward_result->fetch_assoc()) {
    $pending_inwards[] = $row;
}
$response['pending_inwards'] = $pending_inwards;


echo json_encode($response);
$conn->close();
?>