<?php
// api/v1/reports/order-value-detail.php
require_once '../api_bootstrap.php';

if (!isset($_GET['client_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Client ID is required.']);
    exit;
}
$client_id = (int)$_GET['client_id'];

// This query now fetches individual items instead of grouping them
$sql = "
    SELECT
        o.id as order_id,
        o.order_date,
        mi.item_name,
        di.quantity_dispatched,
        oi.item_description_override,
        di.value
    FROM
        dispatch_items di
    JOIN 
        order_items oi ON di.order_item_id = oi.id
    JOIN 
        master_items mi ON oi.master_item_id = mi.id
    JOIN 
        orders o ON oi.order_id = o.id
    WHERE
        o.client_id = ? AND di.value > 0 AND di.invoiced_date IS NULL
    ORDER BY
        o.order_date DESC, o.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $client_id);
$stmt->execute();
$result = $stmt->get_result();

$details = [];
while ($row = $result->fetch_assoc()) {
    $details[] = $row;
}

echo json_encode($details);
$conn->close();
?>