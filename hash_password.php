<?php
// api/v1/hash_password.php

// --- SET YOUR NEW PASSWORD HERE ---
$new_password = "amit6877";
// ----------------------------------

echo "<h1>Password Hash Generator</h1>";
echo "<p><strong>Password to hash:</strong> " . htmlspecialchars($new_password) . "</p>";

// Generate the secure bcrypt hash
$password_hash = password_hash($new_password, PASSWORD_DEFAULT);

echo "<p><strong>Generated Bcrypt Hash:</strong></p>";
echo "<textarea rows='3' cols='80' readonly>" . htmlspecialchars($password_hash) . "</textarea>";
echo "<p><small>Copy the hash above to use in your SQL update query.</small></p>";

?>