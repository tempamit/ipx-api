<?php
// api/v1/orders/list-all-dispatchable-items.php
require_once '../api_bootstrap.php';

// This query now uses LEFT JOINs to be more robust.
$sql = "SELECT
            oi.id as order_item_id,
            oi.item_description_override,
            o.id as order_id,
            c.client_name,
            mi.item_name,
            SUM(re.quantity_available_for_dispatch) as total_quantity_available
        FROM
            order_items oi
        JOIN
            receiving_events re ON oi.id = re.order_item_id
        LEFT JOIN
            orders o ON oi.order_id = o.id
        LEFT JOIN
            clients c ON o.client_id = c.id
        LEFT JOIN
            master_items mi ON oi.master_item_id = mi.id
        WHERE
            re.quantity_available_for_dispatch > 0
            AND o.overall_status NOT IN ('Completed', 'Cancelled')
        GROUP BY
            oi.id, o.id, c.client_name, mi.item_name, oi.item_description_override
        HAVING 
            total_quantity_available > 0
        ORDER BY
            c.client_name, o.id, mi.item_name";

$result = $conn->query($sql);
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
echo json_encode($items);
$conn->close();
?>