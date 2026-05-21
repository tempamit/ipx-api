<?php
// api/v1/dispatched-items/list.php

// Use the new centralized bootstrap file
require_once '../api_bootstrap.php';

// The database connection ($conn) is now available from the bootstrap file.

$sql = "
    SELECT
        di.id as dispatch_item_id,
        di.quantity_dispatched,
        di.invoiced_date,
        di.value,
        di.internal_comments,
        d.id as dispatch_id,
        o.id as order_id,
        c.client_name,
        mi.item_name
    FROM
        dispatch_items di
    LEFT JOIN
        dispatches d ON di.dispatch_id = d.id
    LEFT JOIN
        order_items oi ON di.order_item_id = oi.id
    LEFT JOIN
        orders o ON oi.order_id = o.id
    LEFT JOIN
        clients c ON o.client_id = c.id
    LEFT JOIN
        master_items mi ON oi.master_item_id = mi.id
    ORDER BY
        d.id DESC, di.id ASC
";

$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}

echo json_encode($items);

$conn->close();
?>