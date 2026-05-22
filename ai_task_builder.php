<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

include_once 'db.php'; 

// === CONFIGURATION ===
// Put your Groq API Key here
$groq_api_key = getenv('GROQ_API_KEY');

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->voice_text)) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "No voice text provided."]);
    exit();
}

$voiceText = $data->voice_text;

try {
    // 1. Fetch live context from your database so the AI knows your team
    // (Assuming you have a 'users' or 'staff' table. Adjust table/column names if needed)
    $stmt = $conn->prepare("SELECT id, name FROM users WHERE status = 'active'");
    $stmt->execute();
    $activeUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the users into a string for the AI: "ID 1: Amit S. Loomba, ID 2: Garima"
    $userListString = "";
    foreach ($activeUsers as $user) {
        $userListString .= "ID " . $user['id'] . ": " . $user['name'] . ", ";
    }

    // 2. The System Prompt (This is the secret sauce that controls the LLM)
    $systemPrompt = "
    You are an intelligent task parsing assistant for the IPX internal dashboard.
    Your job is to read user commands and extract task details into a strict JSON format.
    
    TODAY'S DATE: " . date("Y-m-d") . "
    AVAILABLE USERS: " . $userListString . "
    
    RULES:
    1. Match the spoken names to the closest AVAILABLE USERS and return their exact IDs in an array.
    2. Extract a concise, professional task title.
    3. Determine priority (Low, Medium, Urgent). If not specified, default to Medium.
    4. Extract the due date in YYYY-MM-DD format. If 'today', use today's date. If not specified, use null.
    5. Return ONLY valid JSON. No markdown formatting, no conversational text.
    
    EXPECTED JSON SCHEMA:
    {
      \"title\": \"string\",
      \"assigned_to\": [integer],
      \"priority\": \"string\",
      \"due_date\": \"YYYY-MM-DD\"
    }
    ";

    // 3. Connect to Groq's lightning-fast API
    $ch = curl_init("https://api.groq.com/openai/v1/chat/completions");
    
    $payload = json_encode([
        "model" => "llama3-8b-8192", // Extremely fast, perfect for simple parsing
        "messages" => [
            ["role" => "system", "content" => $systemPrompt],
            ["role" => "user", "content" => $voiceText]
        ],
        "temperature" => 0.1, // Keep it strictly logical, not creative
        "response_format" => ["type" => "json_object"] // Force Groq to return pure JSON
    ]);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer " . $groq_api_key,
        "Content-Type: application/json"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Groq API Error: " . $response);
    }

    $groqData = json_decode($response, true);
    $parsedTaskJSON = $groqData['choices'][0]['message']['content'];

    // 4. Send the perfectly formatted JSON back to the Vue frontend
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "original_text" => $voiceText,
        "parsed_task" => json_decode($parsedTaskJSON) // Decode the string back into an object for clean output
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>