<?php
// api/v1/system/list_backups.php
require_once '../api_bootstrap.php';

$result = $conn->query("SELECT * FROM backup_history ORDER BY timestamp DESC");
$backups = [];
while ($row = $result->fetch_assoc()) {
    $backups[] = $row;
}
echo json_encode($backups);
$conn->close();
?>