<?php
$password = "Admin123";
$hashed = '$2y$10$u/9GhQxkaZ6b6nA3a9RjxO1AMkz2ArRx4IGV7/YDNOmAcSoULr4qe';

if (password_verify($password, $hashed)) {
    echo "✅ Password is valid.";
} else {
    echo "❌ Password is INVALID.";
}
?>