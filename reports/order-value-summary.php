<?php
// api/v1/reports/order-value-summary.php
require_once '../api_bootstrap.php';

$sql = "
    SELECT
        c.id as client_id,
        c.client_name,
        COUNT(DISTINCT o.id) as number_of_orders,
        SUM(di.value) as total_amount
    FROM
        clients c
    JOIN orders o ON c.id = o.client_id
    JOIN order_items oi ON o.id = oi.order_id
    JOIN dispatch_items di ON oi.id = di.order_item_id
    WHERE
        di.value > 0
        AND di.invoiced_date IS NULL -- <-- THIS IS THE NEW CONDITION
    GROUP BY
        c.id, c.client_name
    ORDER BY
        total_amount DESC
";

$result = $conn->query($sql);
$summary = [];
while ($row = $result->fetch_assoc()) {
    $summary[] = $row;
}

echo json_encode($summary);
$conn->close();
?>