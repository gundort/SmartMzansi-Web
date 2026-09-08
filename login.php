<?php
session_start();
require 'db.php'; // Connect to database

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get and sanitize form values
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        exit("Please fill in all fields.");
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, password, role FROM users WHERE email = ?");
    if (!$stmt) {
        exit("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];

            // Close resources before redirecting
            $stmt->close();
            $conn->close();

            // Redirect based on role
            switch ($user['role']) {
                case 'admin':
                    header("Location: ../Dashboards/admindashboard.php");
                    exit();
                case 'seller':
                    header("Location: ../Dashboards/sellerdashboard.php");
                    exit();
                case 'buyer':
                    header("Location: ../Dashboards/buyerdashboard.php");
                    exit();
                default:
                    exit("Unknown role.");
            }
        } else {
            $stmt->close();
            $conn->close();
            exit("Invalid password.");
        }
    } else {
        if ($stmt) {
            $stmt->close();
        }
        $conn->close();
        exit("User not found.");
    }
} else {
    exit("Invalid request method.");
}
?>