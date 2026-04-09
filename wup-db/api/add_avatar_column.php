<?php
// ONE-TIME MIGRATION: adds avatar_url to the users table.
// Visit once in browser, then DELETE this file.
require_once 'config.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE users ADD COLUMN avatar_url VARCHAR(255) DEFAULT NULL AFTER department");
    echo "<h2 style='color:green'>✅ avatar_url column added to users!</h2>";
    echo "<p>Delete this file now: <code>api/add_avatar_column.php</code></p>";
} catch (PDOException $e) {
    echo strpos($e->getMessage(),'Duplicate column') !== false
        ? "<h2 style='color:blue'>ℹ️ Column already exists.</h2>"
        : "<h2 style='color:red'>❌ " . htmlspecialchars($e->getMessage()) . "</h2>";
}
