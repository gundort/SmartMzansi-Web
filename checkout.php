<?php
// ────────────────────────────────────────────────────────────────────────────
//  1) BOOTSTRAP & ROLE CHECK
// ────────────────────────────────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../auth.php");
    exit();
}

require_once __DIR__ . "/../Database/db.php";
$buyer_id = (int)$_SESSION['user_id'];

// ────────────────────────────────────────────────────────────────────────────
//  2) FETCH CART ITEMS & CALCULATE SUBTOTAL
// ────────────────────────────────────────────────────────────────────────────
$cartRes = $conn->query("
  SELECT
    ci.id            AS cart_item_id,
    p.id             AS product_id,
    p.name,
    p.price,
    p.image,
    p.seller_id,
    ci.quantity,
    (p.price * ci.quantity) AS line_total
  FROM cart_items ci
  JOIN products p ON ci.product_id = p.id
  WHERE ci.buyer_id = $buyer_id
");

$cartItems = [];
$subtotal  = 0.00;
if ($cartRes && $cartRes->num_rows > 0) {
    while ($ci = $cartRes->fetch_assoc()) {
        // Force seller_id to an integer
        $ci['seller_id'] = isset($ci['seller_id']) ? (int)$ci['seller_id'] : 0;
        $cartItems[]     = $ci;
        $subtotal      += $ci['line_total'];
    }
}

// If cart is empty, send back with a message
if (empty($cartItems)) {
    header("Location: buyerdashboard.php?tab=cart&msg=cart_empty");
    exit();
}

// Determine shipping fee (R50 if subtotal < R500)
$shippingFee = ($subtotal < 500) ? 50.00 : 0.00;
$totalPrice  = $subtotal + $shippingFee;

// ────────────────────────────────────────────────────────────────────────────
//  3) HANDLE “CONFIRM ORDER” POST
// ────────────────────────────────────────────────────────────────────────────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_checkout'])) {
    // (A) Validate shipping fields
    $fullname       = trim($_POST['fullname'] ?? '');
    $address_line1  = trim($_POST['address_line1'] ?? '');
    $address_city   = trim($_POST['address_city'] ?? '');
    $address_postal = trim($_POST['address_postal'] ?? '');
    $country        = trim($_POST['country'] ?? '');

    if (!$fullname || !$address_line1 || !$address_city || !$address_postal || !$country) {
        $errors[] = "Please fill in all shipping address fields.";
    }

    // (B) Validate payment fields
    $card_number = preg_replace('/\D+/', '', $_POST['card_number'] ?? ''); // digits only
    $expiry      = trim($_POST['expiry'] ?? '');
    $cvv         = trim($_POST['cvv'] ?? '');

    // Card number: exactly 16 digits
    if (!preg_match('/^\d{16}$/', $card_number)) {
        $errors[] = "Please enter a valid 16‑digit card number.";
    }
    // CVV: exactly 3 digits
    if (!preg_match('/^\d{3}$/', $cvv)) {
        $errors[] = "Please enter a valid 3‑digit CVV.";
    }
    // Expiry: must be MM/YY (e.g., “01/26”)
    if (!preg_match('/^(0[1-9]|1[0-2])\/\d{2}$/', $expiry)) {
        $errors[] = "Expiry must be in MM/YY format.";
    }

    if (empty($errors)) {
        // (C) Insert each cart item into the OLD “orders” table (single‐item per order)
        foreach ($cartItems as $ci) {
            $stmt = $conn->prepare("
              INSERT INTO orders 
                (buyer_id, seller_id, product_id, quantity, total_price, status, created_at)
              VALUES (?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $pid    = (int)$ci['product_id'];
            $sid    = (int)$ci['seller_id'];
            $qty    = (int)$ci['quantity'];
            $unit   = (float)$ci['price'];
            $line   = (float)$ci['line_total'];

            $stmt->bind_param(
              "iiiid",
              $buyer_id,
              $sid,
              $pid,
              $qty,
              $line
            );
            $stmt->execute();
            $stmt->close();
        }

        // (D) Clear buyer’s cart
        $conn->query("
          DELETE FROM cart_items 
           WHERE buyer_id = $buyer_id
        ");

        // (E) Thank‑you message
        $_SESSION['just_placed_order'] = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Checkout | Smart Mzansi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <!-- Font Awesome (for icons) -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
  />

  <!-- Embedded CSS for checkout.php -->
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Georgia&family=Poppins&display=swap');

    * { margin:0; padding:0; box-sizing:border-box; }
    body {
      font-family:'Poppins', sans-serif;
      background:#f5f5f5;
      color:#333;
      line-height:1.4;
    }
    a { text-decoration:none; color:#568203; }
    a:hover { text-decoration:underline; }

    .container {
      max-width:800px;
      margin:2rem auto;
      padding:0 1rem;
    }
    h1 {
      font-family:'Georgia', serif;
      color:#2e7d32;
      margin-bottom:1rem;
    }

    .alert {
      margin-bottom:1rem;
      padding:0.75rem 1rem;
      border-radius:5px;
      font-size:0.95rem;
    }
    .alert-error {
      background-color:#f8d7da;
      color:#721c24;
    }
    .alert-success {
      background-color:#d4edda;
      color:#155724;
    }

    /* CART SUMMARY TABLE */
    .cart-table {
      width:100%;
      border-collapse:collapse;
      margin-bottom:1.5rem;
      background:#fff;
      border:1px solid #ddd;
      border-radius:8px;
      overflow:hidden;
    }
    .cart-table th,
    .cart-table td {
      padding:0.75rem;
      border-bottom:1px solid #eee;
      font-size:0.95rem;
      text-align:left;
    }
    .cart-table th {
      background:#f4f4f4;
      color:#2e7d32;
      font-weight:600;
    }
    .cart-table img {
      border-radius:5px;
      width:60px;
      height:auto;
    }
    .text-right { text-align:right; }

    /* SHIPPING + PAYMENT FORM */
    form {
      background:#fff;
      border:1px solid #ddd;
      border-radius:8px;
      padding:1rem;
      margin-bottom:2rem;
    }
    .form-group { margin-bottom:1rem; }
    .form-group label {
      display:block;
      margin-bottom:0.3rem;
      font-size:0.9rem;
    }
    .form-group input,
    .form-group select {
      width:100%;
      padding:0.5rem;
      border:1px solid #ccc;
      border-radius:4px;
      font-size:0.9rem;
    }

    .payment-group {
      display:flex;
      gap:1rem;
      flex-wrap:wrap;
    }
    .payment-group .form-group {
      flex:1 1 200px;
    }

    .btn {
      display:inline-block;
      padding:0.6rem 1.2rem;
      background-color:#568203;
      color:#fff;
      border:none;
      border-radius:5px;
      cursor:pointer;
      font-size:0.95rem;
      margin-top:0.5rem;
      transition:background 0.3s;
    }
    .btn:hover {
      background-color:#446e02;
    }

    /* Thank‑you block */
    .thanks {
      background-color:#d4edda;
      color:#155724;
      padding:1rem;
      border-radius:8px;
      margin-bottom:2rem;
      font-size:1rem;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Checkout</h1>

    <?php
      // Show thank-you if just placed an order
      if (isset($_SESSION['just_placed_order'])):
        unset($_SESSION['just_placed_order']);
    ?>
      <div class="thanks">
        <h2>Thank you for your order!</h2>
        <p>Your order has been placed successfully.</p>
        <p>You can track it under <a href="buyerdashboard.php?tab=orders">My Orders</a>.</p>
      </div>
      <p><a href="buyerdashboard.php?tab=dashboard">← Back to Dashboard</a></p>
      <?php exit(); ?>
<?php endif; ?>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $e) {
          echo "<div>" . htmlspecialchars($e) . "</div>";
        } ?>
      </div>
    <?php endif; ?>

    <!-- ─── 1) CART SUMMARY TABLE ─── -->
    <table class="cart-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Qty</th>
          <th>Unit Price (each)</th>
          <th>Line Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($cartItems as $ci):
          $imgPath = "../Dashboards/uploads/" . $ci['image'];
        ?>
          <tr>
            <td style="display:flex; align-items:center; gap:0.5rem;">
              <img
                src="<?= htmlspecialchars($imgPath) ?>"
                alt="<?= htmlspecialchars($ci['name']) ?>"
              />
              <?= htmlspecialchars($ci['name']) ?>
            </td>
            <td><?= $ci['quantity'] ?></td>
            <td>R<?= number_format($ci['price'], 2) ?></td>
            <td>R<?= number_format($ci['line_total'], 2) ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td colspan="3" class="text-right"><strong>Subtotal:</strong></td>
          <td>R<?= number_format($subtotal, 2) ?></td>
        </tr>
        <tr>
          <td colspan="3" class="text-right"><strong>Shipping Fee:</strong></td>
          <td>R<?= number_format($shippingFee, 2) ?></td>
        </tr>
        <tr>
          <td colspan="3" class="text-right"><strong>Total:</strong></td>
          <td>R<?= number_format($totalPrice, 2) ?></td>
        </tr>
      </tbody>
    </table>

    <!-- ─── 2) SHIPPING ADDRESS & PAYMENT FORM ─── -->
    <form method="POST" action="checkout.php">
      <input type="hidden" name="action_checkout" value="confirm_order"/>

      <h3>Shipping Address</h3>
      <div class="form-group">
        <label for="fullname">Full Name:</label>
        <input
          type="text"
          id="fullname"
          name="fullname"
          value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>"
          required
        />
      </div>
      <div class="form-group">
        <label for="address_line1">Address Line 1:</label>
        <input
          type="text"
          id="address_line1"
          name="address_line1"
          value="<?= htmlspecialchars($_POST['address_line1'] ?? '') ?>"
          required
        />
      </div>
      <div class="form-group">
        <label for="address_city">City / Town:</label>
        <input
          type="text"
          id="address_city"
          name="address_city"
          value="<?= htmlspecialchars($_POST['address_city'] ?? '') ?>"
          required
        />
      </div>
      <div class="form-group">
        <label for="address_postal">Postal Code:</label>
        <input
          type="text"
          id="address_postal"
          name="address_postal"
          value="<?= htmlspecialchars($_POST['address_postal'] ?? '') ?>"
          required
        />
      </div>
      <div class="form-group">
        <label for="country">Country:</label>
        <input
          type="text"
          id="country"
          name="country"
          value="<?= htmlspecialchars($_POST['country'] ?? '') ?>"
          required
        />
      </div>

      <h3>Payment Details</h3>
      <div class="form-group">
        <label for="card_number">Card Number (16 digits):</label>
        <input
          type="text"
          id="card_number"
          name="card_number"
          placeholder="XXXX XXXX XXXX XXXX"
          value="<?= htmlspecialchars($_POST['card_number'] ?? '') ?>"
          maxlength="19"
          required
        />
      </div>
      <div class="form-group">
        <label for="expiry">Expiry (MM/YY):</label>
        <input
          type="text"
          id="expiry"
          name="expiry"
          placeholder="MM/YY"
          value="<?= htmlspecialchars($_POST['expiry'] ?? '') ?>"
          maxlength="5"
          required
        />
      </div>
      <div class="form-group">
        <label for="cvv">CVV (3 digits):</label>
        <input
          type="text"
          id="cvv"
          name="cvv"
          placeholder="123"
          value="<?= htmlspecialchars($_POST['cvv'] ?? '') ?>"
          maxlength="3"
          required
        />
      </div>

      <button type="submit" class="btn">Confirm Order</button>
    </form>
  </div>

  <!-- ──────────────────────────────────────────────────────────────────────────── -->
  <!--   JavaScript for auto‑spacing card number every 4 digits                  -->
  <!-- ──────────────────────────────────────────────────────────────────────────── -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const cardInput = document.getElementById('card_number');
      cardInput.addEventListener('input', () => {
        let val = cardInput.value.replace(/\D/g, ''); 
        // group into chunks of 4
        let newVal = '';
        for (let i = 0; i < val.length; i++) {
          if (i > 0 && i % 4 === 0) {
            newVal += ' ';
          }
          newVal += val[i];
        }
        cardInput.value = newVal;
      });

      // Also keep the expiry‑slash code (just in case):
      const expiryInput = document.getElementById('expiry');
      expiryInput.addEventListener('input', () => {
        let v = expiryInput.value.replace(/\D/g, '');
        if (v.length >= 3) {
          v = v.slice(0, 2) + '/' + v.slice(2, 4);
        } else if (v.length === 2 && !expiryInput.value.includes('/')) {
          v = v + '/';
        }
        expiryInput.value = v;
      });
    });
  </script>
</body>
</html>