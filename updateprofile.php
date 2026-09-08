<?php
session_start();
include 'db.php';

$seller_id = $_SESSION['seller_id'] ?? null;

if (!$seller_id) {
    echo "Session expired.";
    exit;
}

$step = $_POST['step'] ?? null;

if ($step === "1") {
    $fullname = trim($_POST['fullname'] ?? "");
    $gender = $_POST['gender'] ?? "";

    if ($fullname === "" || $gender === "") {
        echo "All fields are required.";
        exit;
    }

    // Save to DB
    $stmt = $conn->prepare("UPDATE sellers SET full_name = ?, gender = ?, profile_step = 'step1' WHERE id = ?");
    $stmt->bind_param("ssi", $fullname, $gender, $seller_id);

    if ($stmt->execute()) {
        echo "next";
    } else {
        echo "Database error.";
    }

    $stmt->close();
    exit;
}

if ($step === "2") {
    // Handle skip
    if (isset($_POST['skipped'])) {
        $stmt = $conn->prepare("UPDATE sellers SET profile_step = 'step2_skipped' WHERE id = ?");
        $stmt->bind_param("i", $seller_id);
        if ($stmt->execute()) {
            echo "next";
        } else {
            echo "Error skipping step.";
        }
        $stmt->close();
        exit;
    }

    $id_number = $_POST['id_number'] ?? "";
    $dob = $_POST['dob'] ?? "";

    if (!isset($_FILES['id_document']) || $_FILES['id_document']['error'] !== UPLOAD_ERR_OK) {
        echo "Please upload a valid document.";
        exit;
    }

    $upload_dir = "uploads/"; // Make sure this folder exists and is writable
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_tmp = $_FILES['id_document']['tmp_name'];
    $file_name = basename($_FILES['id_document']['name']);
    $file_path = $upload_dir . time() . "_" . $file_name;

    if (!move_uploaded_file($file_tmp, $file_path)) {
        echo "Failed to upload file.";
        exit;
    }

    $stmt = $conn->prepare("UPDATE sellers SET id_number = ?, dob = ?, id_document = ?, profile_step = 'step2' WHERE id = ?");
    $stmt->bind_param("sssi", $id_number, $dob, $file_path, $seller_id);

    if ($stmt->execute()) {
        echo "next";
    } else {
        echo "Failed to update.";
    }

    $stmt->close();
    exit;
}