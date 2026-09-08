<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login/Register</title>
  <link rel="stylesheet" href="auth.css">
</head>
<body>

<?php if (isset($_GET['error'])): ?>
  <div class="error-message">
    <?php echo htmlspecialchars($_GET['error']); ?>
  </div>
<?php endif; ?>

  <div class="container">
    <div class="trolley">
      <div class="eye left-eye">
        <div class="pupil"></div>
      </div>
      <div class="eye right-eye">
        <div class="pupil"></div>
      </div>
    </div>

    <div class="toggle-buttons">
      <button class="active">Login</button>
      <button>Register</button>
    </div>

    <form id="mainForm" method="POST" action="Database/login.php">
  <input type="email" id="email" name="email" placeholder="Email address" required>

  <div class="password-container">
    <input type="password" id="password" name="password" placeholder="Password" required>
    <span class="toggle-password" id="togglePassword">&#128065;</span>
  </div>

  <div class="password-container confirm-password-container" style="display: none;">
  <input type="password" id="confirmPassword" name="confirm_password" placeholder="Confirm Password">
    <span class="toggle-password">&#128065;</span>
  </div>

  <div class="roles" style="display: none;">
    <label><input type="radio" name="role" value="buyer"> Buyer</label>
    <label><input type="radio" name="role" value="seller"> Seller</label>
  </div>

  <div class="options">
    <label><input type="checkbox"> Remember me</label>
    <a href="#">Forgot password?</a>
  </div>

  <button type="submit" class="login-btn">Log in</button>
</form>
  </div>

  <script src="auth.js"></script>

  <script>
  // If there's an error and it's from registration, switch to Register mode
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.has("error")) {
    toggleButtons[1].click(); // switch to Register mode
  }
</script>

</body>
</html>