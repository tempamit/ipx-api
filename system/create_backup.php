<?php
// api/v1/system/create_backup.php
require_once '../api_bootstrap.php';

function create_database_backup($conn) {
    $backup_dir = realpath(__DIR__ . '/../../backup');
    if (!$backup_dir || !is_writable($backup_dir)) {
        throw new Exception('Backup directory is not writable or does not exist.');
    }

    $filename = "backup_" . date("Y-m-d_H-i-s") . ".sql";
    $filepath = $backup_dir . '/' . $filename;
    $handle = fopen($filepath, 'w+');

    if ($handle === false) {
        throw new Exception('Cannot open file for writing: ' . $filepath);
    }

    $tables = [];
    $result = $conn->query('SHOW TABLES');
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sql_script = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\nSTART TRANSACTION;\nSET time_zone = \"+00:00\";\n\n";

    foreach ($tables as $table) {
        // Get table structure
        $result = $conn->query("SHOW CREATE TABLE `{$table}`");
        $row = $result->fetch_row();
        $sql_script .= "\n\n-- --------------------------------------------------------\n\n" . $row[1] . ";\n\n";

        // Get table data
        $result = $conn->query("SELECT * FROM `{$table}`");
        $num_fields = $result->field_count;

        if ($result->num_rows > 0) {
            $sql_script .= "--\n-- Dumping data for table `{$table}`\n--\n\n";
            while ($row = $result->fetch_assoc()) {
                $sql_script .= "INSERT INTO `{$table}` VALUES(";
                $field_count = 0;
                foreach ($row as $value) {
                    if (isset($value)) {
                        $sql_script .= "'" . $conn->real_escape_string($value) . "'";
                    } else {
                        $sql_script .= 'NULL';
                    }
                    if ($field_count < $num_fields - 1) {
                        $sql_script .= ', ';
                    }
                    $field_count++;
                }
                $sql_script .= ");\n";
            }
        }
    }
    $sql_script .= "\nCOMMIT;";
    
    fwrite($handle, $sql_script);
    fclose($handle);

    return ['filename' => $filename, 'filepath' => $filepath];
}

try {
    $backup_info = create_database_backup($conn);
    
    // Record in the database
    $size_kb = round(filesize($backup_info['filepath']) / 1024);
    $stmt = $conn->prepare("INSERT INTO backup_history (filename, filepath, size_kb, type) VALUES (?, ?, ?, 'manual')");
    $stmt->bind_param("ssi", $backup_info['filename'], $backup_info['filepath'], $size_kb);
    $stmt->execute();

    echo json_encode(['success' => true, 'message' => 'Backup created successfully.', 'filename' => $backup_info['filename']]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create backup.', 'details' => $e->getMessage()]);
}

$conn->close();
?>