<?php
// api/v1/orders/list.php
require_once '../api_bootstrap.php';

$sql = "
    SELECT
        o.id,
        o.order_date,
        o.overall_status,
        c.client_name,
        (
            SELECT GROUP_CONCAT(mi.item_name SEPARATOR ', ')
            FROM order_items oi
            JOIN master_items mi ON oi.master_item_id = mi.id
            WHERE oi.order_id = o.id
        ) as items_ordered,
        (
            SELECT COUNT(re.id) 
            FROM receiving_events re
            JOIN order_items oi ON re.order_item_id = oi.id
            WHERE oi.order_id = o.id
        ) as inward_count
    FROM
        orders o
    LEFT JOIN
        clients c ON o.client_id = c.id
    ORDER BY
        o.id DESC
";

$result = $conn->query($sql);
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

echo json_encode($orders);
$conn->close();
?>