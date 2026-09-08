<?php
session_start();
include 'includes/db.php';

// Redirect to dashboard if profile is complete
$seller_id = $_SESSION['seller_id'] ?? null;
if (!$seller_id) {
    header("Location: login.php");
    exit;
}

// Check profile completion
$check = $conn->prepare("SELECT profile_step FROM sellers WHERE id = ?");
$check->bind_param("i", $seller_id);
$check->execute();
$check->bind_result($profile_step);
$check->fetch();
$check->close();

if ($profile_step === 'done') {
    header("Location: sellerdashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complete Profile</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; }
        .form-step { display: none; }
        .form-step.active { display: block; background: white; padding: 30px; border-radius: 8px; max-width: 500px; margin: 50px auto; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; }
        input[type="text"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 20px; border: none; border-radius: 4px; background-color: #007bff; color: white; cursor: pointer; }
        button:hover { background-color: #0056b3; }
        #form-message { color: red; margin-bottom: 10px; }
    </style>
</head>
<body>

<div class="form-step active" id="step-1">
    <h2>Step 1: Basic Info</h2>
    <div id="form-message"></div>
    <div class="form-group">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" required>
    </div>
    <div class="form-group">
        <label for="gender">Gender</label>
        <select id="gender" required>
            <option value="">-- Select Gender --</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
    </div>
    <button id="continue-step-1">Continue</button>
</div>

<!-- Step 2: SA ID Number -->
<div class="step step-2" style="display:none;">
  <h2>Step 2: Identification</h2>
  
  <label for="id_number">South African ID Number:</label>
  <input type="text" id="id_number" name="id_number" maxlength="13" required>

  <label for="dob">Date of Birth (auto-filled):</label>
  <input type="text" id="dob" name="dob" readonly>

  <label for="id_document">Upload ID/Passport/Permit:</label>
  <input type="file" id="id_document" name="id_document" accept=".jpg, .jpeg, .png, .pdf" required>

  <button type="button" onclick="submitStep2()">Next</button>
  <button type="button" onclick="skipStep2()">Skip</button>
</div>

<!-- Additional steps will load dynamically -->

<script>
document.getElementById("continue-step-1").addEventListener("click", function() {
    const fullname = document.getElementById("fullname").value.trim();
    const gender = document.getElementById("gender").value;

    if (fullname === "" || gender === "") {
        document.getElementById("form-message").textContent = "Please fill in all fields.";
        return;
    }

    const formData = new FormData();
    formData.append("step", "1");
    formData.append("fullname", fullname);
    formData.append("gender", gender);

    fetch("updateprofile.php", {
        method: "POST",
        body: formData
    })
    .then(res => res.text())
    .then(response => {
      if (response === "next") {
    document.getElementById("step-1").classList.remove("active");
    document.querySelector(".step-2").style.display = "block";
} else {
            document.getElementById("form-message").textContent = response;
        }
    })
    .catch(error => {
        console.error(error);
        document.getElementById("form-message").textContent = "Something went wrong.";
    });
});
</script>

<script>
document.getElementById("id_number").addEventListener("input", function() {
  const id = this.value;
  if (id.length >= 6) {
    const year = id.substring(0, 2);
    const month = id.substring(2, 4);
    const day = id.substring(4, 6);

    let fullYear = parseInt(year, 10) >= 0 && parseInt(year, 10) <= 24 ? "20" + year : "19" + year;

    document.getElementById("dob").value = `${fullYear}-${month}-${day}`;
  }
});

function submitStep2() {
  const idNumber = document.getElementById("id_number").value.trim();
  const dob = document.getElementById("dob").value.trim();
  const fileInput = document.getElementById("id_document");

  if (!idNumber || !dob || fileInput.files.length === 0) {
    alert("Please complete all fields.");
    return;
  }

  const formData = new FormData();
  formData.append("step", "2");
  formData.append("id_number", idNumber);
  formData.append("dob", dob);
  formData.append("id_document", fileInput.files[0]);

  fetch("../updateprofile.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    if (data === "next") {
      goToStep(3); // You can add Step 3 or send them to seller-dashboard.php
    } else {
      alert(data);
    }
  })
  .catch(err => alert("An error occurred"));
}

function skipStep2() {
  // Still send the step number so we can track their progress
  fetch("Database/updateprofile.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "step=2&skipped=true"
  })
  .then(res => res.text())
  .then(data => {
    if (data === "next") {
      goToStep(3); // Or redirect to dashboard
    } else {
      alert(data);
    }
  })
  .catch(err => alert("Error skipping step."));
}
</script>

</body>
</html>