<?php
$page_title = "Place Order";
include_once 'includes/header.php';

// Just redirect to the main order page
header("Location: ../order.php" . (isset($_GET['service_id']) ? "?service_id=" . $_GET['service_id'] : ""));
exit;
?>
