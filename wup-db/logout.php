<?php
// ══════════════════════════════════════════════════
//  WUP PORTAL — LOGOUT
// ══════════════════════════════════════════════════
session_start();

// Build absolute base URL dynamically (PHP_SELF = web path, not filesystem path)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base     = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['PHP_SELF']), '/\\');

// Also clear the DB token so the bearer token is invalidated
if (!empty($_SESSION['wup_token'])) {
    require_once 'api/config.php';
    try {
        $db = getDB();
        $db->prepare('UPDATE users SET session_token = NULL, token_expires = NULL WHERE session_token = ?')
           ->execute([$_SESSION['wup_token']]);
    } catch (Exception $e) { /* ignore */ }
}

// Destroy PHP session
$_SESSION = [];
session_destroy();

header("Location: $base/index.php");
exit;
