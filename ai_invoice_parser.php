<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

$groq_api_key = getenv('GROQ_API_KEY');

if (!$groq_api_key) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Server configuration error. AI key missing."]);
    exit();
}

// Check if a file was actually uploaded
if (!isset($_FILES['invoice_image']) || $_FILES['invoice_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Please upload a valid invoice image."]);
    exit();
}

try {
    // 1. Grab the uploaded image and convert it to Base64 so Groq can "see" it
    $imageTmpPath = $_FILES['invoice_image']['tmp_name'];
    $imageType = mime_content_type($imageTmpPath);
    $imageData = file_get_contents($imageTmpPath);
    $base64Image = base64_encode($imageData);
    
    $dataUri = "data:" . $imageType . ";base64," . $base64Image;

    // 2. The Strict Extraction Prompt
    $systemPrompt = "
    You are an expert data extraction AI for an enterprise supply chain. 
    Analyze this invoice image. Extract the line items and return ONLY a valid, strict JSON object.
    Do not include markdown tags like ```json or any conversational text.
    
    EXPECTED JSON SCHEMA:
    {
      \"vendor_name\": \"string\",
      \"invoice_number\": \"string\",
      \"invoice_date\": \"YYYY-MM-DD\",
      \"items\": [
        {
          \"description\": \"string\",
          \"quantity\": integer,
          \"unit_price\": float,
          \"total_price\": float
        }
      ]
    }
    ";

    // 3. Connect to Groq using their newest Vision Model
    $ch = curl_init("[https://api.groq.com/openai/v1/chat/completions](https://api.groq.com/openai/v1/chat/completions)");
    
    $payload = json_encode([
        "model" => "llama-3.2-11b-vision-preview", // Groq's lightning-fast multimodal model
        "messages" => [
            [
                "role" => "user", 
                "content" => [
                    ["type" => "text", "text" => $systemPrompt],
                    ["type" => "image_url", "image_url" => ["url" => $dataUri]]
                ]
            ]
        ],
        "temperature" => 0.1 // Keep it highly factual, no hallucinations
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
    
    // Clean up the response just in case Groq tries to be polite and adds markdown
    $parsedText = $groqData['choices'][0]['message']['content'];
    $parsedText = str_replace(['```json', '```'], '', $parsedText);

    // 4. Send the perfect data back to Vue
    http_response_code(200);
    echo json_encode([
        "status" => "success",
        "message" => "Invoice parsed successfully",
        "data" => json_decode(trim($parsedText))
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
?>