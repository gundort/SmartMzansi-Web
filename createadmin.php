<?php
require 'db.php';

$email = "admin@admin.com";
$plainPassword = "Admin123";
$role = "admin";
$fullName = "System Admin";
$idNumber = "0000000000000"; // Dummy
$proofOfID = "none";
$proofOfResidence = "none";
$createdAt = date("Y-m-d H:i:s");

$hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

// Optional: Delete existing admin first to avoid duplicate entry
$conn->query("DELETE FROM users WHERE email = '$email'");

$stmt = $conn->prepare("INSERT INTO users (email, password, role, full_name, id_number, proof_of_id, proof_of_residence, created_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("ssssssss", $email, $hashedPassword, $role, $fullName, $idNumber, $proofOfID, $proofOfResidence, $createdAt);

if ($stmt->execute()) {
    echo "✅ Admin inserted successfully.";
} else {
    echo "❌ Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>