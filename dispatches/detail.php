<?php
// api/v1/dispatches/detail.php
require_once '../api_bootstrap.php'; // Use the centralized bootstrap file

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Dispatch ID is required.']);
    exit;
}
$dispatch_id = (int)$_GET['id'];
$response = [];

// 1. Fetch main dispatch details
$stmt_dispatch = $conn->prepare("SELECT * FROM dispatches WHERE id = ?");
$stmt_dispatch->bind_param("i", $dispatch_id);
$stmt_dispatch->execute();
$response['dispatch_details'] = $stmt_dispatch->get_result()->fetch_assoc();

// 2. Fetch items, grouped by customer
$stmt_items = $conn->prepare("
    SELECT
        c.client_name,
        mi.item_name,
        di.quantity_dispatched,
        re.receiving_description_override as description
    FROM dispatch_items di
    JOIN receiving_events re ON di.receiving_event_id = re.id
    JOIN order_items oi ON di.order_item_id = oi.id
    JOIN master_items mi ON oi.master_item_id = mi.id
    JOIN orders o ON oi.order_id = o.id
    JOIN clients c ON o.client_id = c.id
    WHERE di.dispatch_id = ?
    ORDER BY c.client_name, mi.item_name
");
$stmt_items->bind_param("i", $dispatch_id);
$stmt_items->execute();
$result_items = $stmt_items->get_result();

$items_by_customer = [];
while ($row = $result_items->fetch_assoc()) {
    $items_by_customer[$row['client_name']][] = $row;
}
$response['items_by_customer'] = $items_by_customer;

echo json_encode($response);
$conn->close();
?>