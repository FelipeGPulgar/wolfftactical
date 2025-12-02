<?php
/**
 * Minimal session configuration for Wolf Tactical
 * Reverted to defaults to fix 401 Unauthorized issues
 */

if (session_status() === PHP_SESSION_NONE) {
    // Absolute bare minimum to ensure compatibility
    session_name('WOLFTACTICAL_SESSION');
    session_start();
}
?>
