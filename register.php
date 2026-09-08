<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require 'db.php'; // Connect to database

set_error_handler(function($errno, $errstr, $errfile, $errline){
    file_put_contents('error_log.txt', "$errstr in $errfile on line $errline", FILE_APPEND);
});

set_exception_handler(function($e){
    file_put_contents('error_log.txt', $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine(), FILE_APPEND);
});

// Get values from form
$email = $_POST['email'];
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];
$role = $_POST['role']; // buyer or seller

// Simple validation
if (empty($email) || empty($password) || empty($confirm_password) || empty($role)) {
    die("Please fill in all fields.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Invalid email format.");
}

if ($password !== $confirm_password) {
    header("Location: ../auth.php?error=" . urlencode("Passwords do not match."));
    exit();
}

// Check if email already exists
$check = $conn->prepare("SELECT id FROM users WHERE email = ?");
$check->bind_param("s", $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    die("Email already registered.");
}
$check->close();

// Hash password
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$stmt = $conn->prepare("INSERT INTO users (email, password, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $email, $hashed_password, $role);

if ($stmt->execute()) {
    // Get the newly inserted user's ID (optional for future use)
    $user_id = $stmt->insert_id;

    session_start(); // Start the session
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;  // Optional, if you want to keep it around
    $_SESSION['role'] = $role;    // Optional

    // New line — only do this for sellers:
if ($role === 'seller') {
    $_SESSION['seller_id'] = $user_id;
}
    
    // Redirect based on role
    header("Location: completeprofile.php?role=$role&id=$user_id");
    exit();
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
