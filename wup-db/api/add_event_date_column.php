<?php
// ══════════════════════════════════════════════════
//  ONE-TIME MIGRATION: Add event_date column to announcements
//  Visit this URL once in your browser, then DELETE the file.
// ══════════════════════════════════════════════════
require_once 'config.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE announcements ADD COLUMN event_date DATE NULL DEFAULT NULL AFTER image_url");
    echo "<h2 style='color:green'>✅ event_date column added successfully!</h2>";
    echo "<p>Delete this file now for security: <code>api/add_event_date_column.php</code></p>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "<h2 style='color:blue'>ℹ️ Column already exists — nothing to do.</h2>";
    } else {
        echo "<h2 style='color:red'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</h2>";
    }
}
