<?php
// Cancel order script
include_once '../includes/config.php';

// Check if user is logged in
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'customer') {
    $_SESSION['message'] = "You need to login as a customer to access this page";
    $_SESSION['message_type'] = "danger";
    header("Location: ../login.php");
    exit;
}

// Check if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['message'] = "Invalid order ID";
    $_SESSION['message_type'] = "danger";
    header("Location: orders.php");
    exit;
}

$order_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Check if order exists and belongs to the user
$check_sql = "SELECT id, status FROM orders WHERE id = ? AND user_id = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("ii", $order_id, $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows != 1) {
    $_SESSION['message'] = "Order not found";
    $_SESSION['message_type'] = "danger";
    header("Location: orders.php");
    exit;
}

$order = $check_result->fetch_assoc();

// Check if order status is pending
if ($order['status'] != 'pending') {
    $_SESSION['message'] = "Only pending orders can be canceled";
    $_SESSION['message_type'] = "danger";
    header("Location: order_details.php?id=" . $order_id);
    exit;
}

// Cancel order (delete in real application, just update status for demo)
$cancel_sql = "UPDATE orders SET status = 'canceled' WHERE id = ?";
$cancel_stmt = $conn->prepare($cancel_sql);
$cancel_stmt->bind_param("i", $order_id);

if ($cancel_stmt->execute()) {
    $_SESSION['message'] = "Order has been canceled successfully";
    $_SESSION['message_type'] = "success";
} else {
    $_SESSION['message'] = "Failed to cancel order. Please try again.";
    $_SESSION['message_type'] = "danger";
}

header("Location: orders.php");
exit;
?>
