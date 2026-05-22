<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once 'db.php'; 

try {
    // Initialize our payload
    $stats = [
        "pending_orders" => 0,
        "outstanding_value" => 0,
        "ready_dispatch" => 0,
        "overdue_tasks" => 0
    ];

    // 1. Pending Orders Count (Pending or Processing)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE overall_status IN ('Pending', 'Processing')");
    $stats['pending_orders'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 2. Outstanding Value (Money stuck in Pending/Processing)
    $stmt = $conn->query("SELECT SUM(total_value) as total FROM orders WHERE overall_status IN ('Pending', 'Processing')");
    $stats['outstanding_value'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // 3. Ready to Dispatch
    $stmt = $conn->query("SELECT COUNT(*) as count FROM orders WHERE overall_status = 'Ready to Dispatch'");
    $stats['ready_dispatch'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // 4. Overdue Tasks (Assuming a 'tasks' table exists)
    // We wrap this in a try-catch just in case the tasks table schema is slightly different
    try {
        $stmt = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status != 'Completed' AND due_date < CURDATE()");
        $stats['overdue_tasks'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;
    } catch (Exception $e) {
        $stats['overdue_tasks'] = 0; // Failsafe if table is missing/different
    }

    // 5. Recent Orders (Join with clients to get the name)
    $stmt = $conn->query("
        SELECT o.id, o.overall_status, o.order_date, c.client_name 
        FROM orders o 
        JOIN clients c ON o.client_id = c.id 
        ORDER BY o.created_at DESC 
        LIMIT 5
    ");
    $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 6. Open Tasks List
    try {
        $stmt = $conn->query("SELECT id, title, due_date FROM tasks WHERE status != 'Completed' ORDER BY due_date ASC LIMIT 4");
        $open_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $open_tasks = [];
    }

    // Send the master payload back to Vue
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "stats" => $stats,
        "recent_orders" => $recent_orders,
        "open_tasks" => $open_tasks
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>