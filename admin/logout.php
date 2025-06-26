<?php
require_once '../includes/config.php';

// Clear all session data
session_unset();
session_destroy();

// Redirect to login page
header("Location: login.php");
exit;
