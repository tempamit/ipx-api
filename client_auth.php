<?php
// 1. Allow cross-origin requests from your Vue frontend
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 2. Include your secure database connection
include_once 'db.php'; 

// 3. Get the raw POST data from Vue
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->email) || !isset($data->pin)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email and PIN are required."]);
    exit();
}

$email = trim($data->email);
$pin = trim($data->pin);

try {
    // 4. The Smart Authentication Query
    // We check if ANY client profile has this email AND a phone number ending in the 4-digit pin.
    // Replace 'clients' with your actual table name if it's different.
    $authQuery = "SELECT id, client_name, email, phone FROM clients WHERE email = :email AND RIGHT(phone, 4) = :pin LIMIT 1";
    $stmt = $conn->prepare($authQuery);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':pin', $pin);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        // Successfully verified the user's identity
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        // 5. The Unified Aggregation Query
        // Using `overall_status`, `order_date`, and `total_value` from your exact schema
        $ordersQuery = "
            SELECT 
                o.id as order_id, 
                o.overall_status, 
                o.order_date,
                o.total_value,
                c.client_name 
            FROM orders o
            JOIN clients c ON o.client_id = c.id
            WHERE c.email = :email
            ORDER BY o.created_at DESC
        ";
        $orderStmt = $conn->prepare($ordersQuery);
        $orderStmt->bindParam(':email', $email);
        $orderStmt->execute();
        
        $unifiedOrders = $orderStmt->fetchAll(PDO::FETCH_ASSOC);

        // 6. Send the golden payload back to Vue
        http_response_code(200);
        echo json_encode([
            "status" => "success",
            "message" => "Authentication successful",
            "user_identity" => [
                "email" => $client['email'],
                "primary_company" => $client['client_name']
            ],
            "total_orders_found" => count($unifiedOrders),
            "data" => $unifiedOrders
        ]);

    } else {
        // Security: Give a generic error so attackers can't guess if an email exists
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid email or PIN combination."]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
?>