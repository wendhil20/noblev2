<?php
// index-authguard.php
if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/loginadmin');
    exit;
}
?>