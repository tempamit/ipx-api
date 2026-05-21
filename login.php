<?php
// api/v1/login.php

// Include required files
require_once 'db_connect.php';
require_once 'lib/JWT.php'; // Requires the JWT library file
use \Firebase\JWT\JWT;

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Only POST method is accepted.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['username']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Username and password are required.']);
    exit;
}

$username = $input['username'];
$password = $input['password'];

// Define a secret key. This should be a long, random string and kept secret.
$secret_key = "w#L8@z$qB&v9E!p2*F^kH5sR)u7dG_yZ+A-mC4jN6tX=cW1xV3eSgT_aK!iP"; // IMPORTANT: Change this to your own random string

$sql = "SELECT id, username, password_hash, role FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password_hash'])) {
        // Password is correct, generate JWT
        $issuer_claim = "http://localhost";
        $audience_claim = "http://localhost";
        $issuedat_claim = time();
        $notbefore_claim = $issuedat_claim;
        $expire_claim = $issuedat_claim + (60 * 60 * 8); // Expires in 8 hours

        $token = array(
            "iss" => $issuer_claim,
            "aud" => $audience_claim,
            "iat" => $issuedat_claim,
            "nbf" => $notbefore_claim,
            "exp" => $expire_claim,
            "data" => array(
                "id" => $user['id'],
                "username" => $user['username'],
                "role" => $user['role']
            )
        );

        $jwt = JWT::encode($token, $secret_key, 'HS256');

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Login successful.',
            'token' => $jwt // Send the token to the frontend
        ]);

    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials.']);
    }
} else {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid credentials.']);
}

$stmt->close();
$conn->close();
?>