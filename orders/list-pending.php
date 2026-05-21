<?php
// api/v1/orders/list-pending.php

// Use the new centralized bootstrap file, which handles DB connection and all headers
require_once '../api_bootstrap.php';

// This query correctly filters for orders that are not completed/cancelled
// and still have items that need to be received.
$sql = "SELECT
            o.id,
            o.client_id,
            c.client_name
        FROM
            orders o
        JOIN
            clients c ON o.client_id = c.id
        WHERE
            o.overall_status NOT IN ('Completed', 'Cancelled')
            AND
            (SELECT SUM(oi.ordered_quantity) FROM order_items oi WHERE oi.order_id = o.id) >
            (SELECT COALESCE(SUM(re.quantity_received), 0)
             FROM receiving_events re
             JOIN order_items oi ON re.order_item_id = oi.id
             WHERE oi.order_id = o.id)
        ORDER BY
            o.id DESC";

$result = $conn->query($sql);
$orders = [];
while ($row = $result->fetch_assoc()) {
    $row['display_name'] = '#' . $row['id'] . ' - ' . $row['client_name'];
    $orders[] = $row;
}

echo json_encode($orders);
$conn->close();
?>