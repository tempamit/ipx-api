<?php
// api/v1/dashboard/stats.php
require_once '../db_connect.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); // Allow from any origin

$stats = [
    'pending_orders' => 0,
    'non_invoiced_value' => 0.00,
    'items_ready_for_dispatch' => 0
];

// Query 1: Get count of pending orders
$sql_pending = "SELECT COUNT(id) as count FROM orders WHERE overall_status IN ('Pending', 'Processing', 'Partially Dispatched', 'Ready to Dispatch')";
$result_pending = $conn->query($sql_pending);
if ($result_pending) {
    $stats['pending_orders'] = (int) $result_pending->fetch_assoc()['count'];
}

// Query 2: Get sum of non-invoiced dispatches
$sql_invoiced = "SELECT SUM(value) as total_value FROM dispatch_items WHERE invoiced_date IS NULL";
$result_invoiced = $conn->query($sql_invoiced);
if ($result_invoiced) {
    $total = $result_invoiced->fetch_assoc()['total_value'];
    $stats['non_invoiced_value'] = $total ? (float) $total : 0.00;
}

// Query 3: Get count of items ready to dispatch
$sql_ready = "SELECT SUM(quantity_available_for_dispatch) as total_items FROM receiving_events WHERE quantity_available_for_dispatch > 0";
$result_ready = $conn->query($sql_ready);
if ($result_ready) {
    $stats['items_ready_for_dispatch'] = (int) $result_ready->fetch_assoc()['total_items'];
}

echo json_encode($stats);

$conn->close();
?>