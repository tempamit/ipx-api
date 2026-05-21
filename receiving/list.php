<?php
// api/v1/receiving/list.php

// Use the new centralized bootstrap file, which handles DB connection and all headers
require_once '../api_bootstrap.php';

$sql = "
    SELECT
        o.id as order_id,
        re.received_date,
        c.client_name,
        GROUP_CONCAT(DISTINCT mi.item_name SEPARATOR ', ') as items
    FROM
        receiving_events re
    JOIN
        order_items oi ON re.order_item_id = oi.id
    JOIN
        orders o ON oi.order_id = o.id
    JOIN
        clients c ON o.client_id = c.id
    JOIN
        master_items mi ON oi.master_item_id = mi.id
    GROUP BY
        o.id, re.received_date, c.client_name
    ORDER BY
        re.received_date DESC, o.id DESC
";

$result = $conn->query($sql);
$history = [];
while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

echo json_encode($history);
$conn->close();
?>