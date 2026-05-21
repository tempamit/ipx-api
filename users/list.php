<?php
// api/v1/users/list.php
require_once '../api_bootstrap.php';

// Fetch id and username from all users.
// We don't fetch the password_hash for security.
$sql = "SELECT id, username FROM users ORDER BY username ASC";

$result = $conn->query($sql);
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);
$conn->close();
?>