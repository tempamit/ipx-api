<?php
// api/v1/dispatches/list.php
require_once '../api_bootstrap.php';

$sql = "
    SELECT
        d.id,
        d.dispatch_date,
        COALESCE((
            SELECT GROUP_CONCAT(DISTINCT c.client_name SEPARATOR ', ')
            FROM dispatch_items di
            JOIN order_items oi ON di.order_item_id = oi.id
            JOIN orders o ON oi.order_id = o.id
            JOIN clients c ON o.client_id = c.id
            WHERE di.dispatch_id = d.id
        ), '') as customers,
        COALESCE((
            SELECT GROUP_CONCAT(DISTINCT mi.item_name SEPARATOR ', ')
            FROM dispatch_items di
            JOIN order_items oi ON di.order_item_id = oi.id
            JOIN master_items mi ON oi.master_item_id = mi.id
            WHERE di.dispatch_id = d.id
        ), '') as items
    FROM
        dispatches d
    ORDER BY
        d.id DESC
";

$result = $conn->query($sql);
$dispatches = [];
while ($row = $result->fetch_assoc()) {
    $dispatches[] = $row;
}

echo json_encode($dispatches);
$conn->close();
?>