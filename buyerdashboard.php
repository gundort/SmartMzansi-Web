<?php
// ────────────────────────────────────────────────────────────────────────────
//  1) PHP BOOTSTRAPPING & ROLE CHECK
// ────────────────────────────────────────────────────────────────────────────
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Only allow logged‑in buyers:
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'buyer') {
    header("Location: ../auth.php");
    exit();
}

require_once __DIR__ . "/../Database/db.php";
$buyer_id = (int)$_SESSION['user_id'];

// ────────────────────────────────────────────────────────────────────────────
//  2) Handle POST “add to wishlist / remove wishlist / add to cart”
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($_POST['action'] === 'add_wishlist' && $pid > 0) {
        $stmt = $conn->prepare("
          INSERT IGNORE INTO wishlists 
            (buyer_id, product_id, added_at) 
          VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $buyer_id, $pid);
        $stmt->execute();
        $stmt->close();
        header("Location: buyerdashboard.php?tab=products");
        exit();
    }

    if ($_POST['action'] === 'remove_wishlist' && $pid > 0) {
        $stmt = $conn->prepare("
          DELETE FROM wishlists 
           WHERE buyer_id = ? 
             AND product_id = ?
        ");
        $stmt->bind_param("ii", $buyer_id, $pid);
        $stmt->execute();
        $stmt->close();
        header("Location: buyerdashboard.php?tab=products");
        exit();
    }

    if ($_POST['action'] === 'add_to_cart' && $pid > 0) {
        // If already in cart, increment; otherwise insert
        $stmt = $conn->prepare("
          SELECT quantity 
            FROM cart_items 
           WHERE buyer_id = ? 
             AND product_id = ?
        ");
        $stmt->bind_param("ii", $buyer_id, $pid);
        $stmt->execute();
        $stmt->bind_result($existingQty);
        if ($stmt->fetch()) {
            $stmt->close();
            $newQty = $existingQty + 1;
            $upd = $conn->prepare("
              UPDATE cart_items 
                 SET quantity = ? 
               WHERE buyer_id = ? 
                 AND product_id = ?
            ");
            $upd->bind_param("iii", $newQty, $buyer_id, $pid);
            $upd->execute();
            $upd->close();
        } else {
            $stmt->close();
            $ins = $conn->prepare("
              INSERT INTO cart_items 
                (buyer_id, product_id, quantity, added_at) 
              VALUES (?, ?, 1, NOW())
            ");
            $ins->bind_param("ii", $buyer_id, $pid);
            $ins->execute();
            $ins->close();
        }
        header("Location: buyerdashboard.php?tab=products");
        exit();
    }
}

// ────────────────────────────────────────────────────────────────────────────
//  3) Handle POST “Cart: remove or update quantity”
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cart'])) {
    if ($_POST['action_cart'] === 'remove_item' && isset($_POST['cart_item_id'])) {
        $ci_id = (int)$_POST['cart_item_id'];
        $del = $conn->prepare("
          DELETE FROM cart_items 
           WHERE id = ? 
             AND buyer_id = ?
        ");
        $del->bind_param("ii", $ci_id, $buyer_id);
        $del->execute();
        $del->close();
        header("Location: buyerdashboard.php?tab=cart");
        exit();
    }
    if ($_POST['action_cart'] === 'update_qty' 
        && isset($_POST['cart_item_id']) 
        && isset($_POST['new_quantity'])
    ) {
        $ci_id  = (int)$_POST['cart_item_id'];
        $newQty = max(1, (int)$_POST['new_quantity']);
        $upd = $conn->prepare("
          UPDATE cart_items 
             SET quantity = ?
           WHERE id = ? 
             AND buyer_id = ?
        ");
        $upd->bind_param("iii", $newQty, $ci_id, $buyer_id);
        $upd->execute();
        $upd->close();
        header("Location: buyerdashboard.php?tab=cart");
        exit();
    }
}

// ────────────────────────────────────────────────────────────────────────────
//  4) Handle POST “Wishlist: move to cart / remove from wishlist”
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_wl'])) {
    if ($_POST['action_wl'] === 'move_to_cart' 
        && isset($_POST['wishlist_id']) 
        && isset($_POST['product_id'])
    ) {
        $wl_id = (int)$_POST['wishlist_id'];
        $pid   = (int)$_POST['product_id'];

        // Remove from wishlists
        $del = $conn->prepare("
          DELETE FROM wishlists 
           WHERE id = ? 
             AND buyer_id = ?
        ");
        $del->bind_param("ii", $wl_id, $buyer_id);
        $del->execute();
        $del->close();

        // Then add/update cart_items
        $stmt = $conn->prepare("
          SELECT quantity 
            FROM cart_items 
           WHERE buyer_id = ? 
             AND product_id = ?
        ");
        $stmt->bind_param("ii", $buyer_id, $pid);
        $stmt->execute();
        $stmt->bind_result($existingQty);
        if ($stmt->fetch()) {
            $stmt->close();
            $newQty = $existingQty + 1;
            $upd = $conn->prepare("
              UPDATE cart_items 
                 SET quantity = ?
               WHERE buyer_id = ? 
                 AND product_id = ?
            ");
            $upd->bind_param("iii", $newQty, $buyer_id, $pid);
            $upd->execute();
            $upd->close();
        } else {
            $stmt->close();
            $ins = $conn->prepare("
              INSERT INTO cart_items 
                (buyer_id, product_id, quantity, added_at) 
              VALUES (?, ?, 1, NOW())
            ");
            $ins->bind_param("ii", $buyer_id, $pid);
            $ins->execute();
            $ins->close();
        }
        header("Location: buyerdashboard.php?tab=wishlist");
        exit();
    }

    if ($_POST['action_wl'] === 'remove_wishlist' && isset($_POST['wishlist_id'])) {
        $wl_id = (int)$_POST['wishlist_id'];
        $del = $conn->prepare("
          DELETE FROM wishlists 
           WHERE id = ? 
             AND buyer_id = ?
        ");
        $del->bind_param("ii", $wl_id, $buyer_id);
        $del->execute();
        $del->close();
        header("Location: buyerdashboard.php?tab=wishlist");
        exit();
    }
}

// ────────────────────────────────────────────────────────────────────────────
//  5) Handle POST “Profile: update info”
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_profile'])) {
    if ($_POST['action_profile'] === 'update_profile') {
        $fullname = $conn->real_escape_string(trim($_POST['full_name'] ?? ''));
        $email    = $conn->real_escape_string(trim($_POST['email']   ?? ''));
        $upd = $conn->prepare("
          UPDATE users 
             SET full_name = ?, 
                 email     = ?
           WHERE id = ?
        ");
        $upd->bind_param("ssi", $fullname, $email, $buyer_id);
        $upd->execute();
        $upd->close();
        header("Location: buyerdashboard.php?tab=profile&msg=profile_updated");
        exit();
    }
}

// ────────────────────────────────────────────────────────────────────────────
//  6) Handle POST “Messages: send message”
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_msg'])) {
    if ($_POST['action_msg'] === 'send_message'
        && isset($_POST['order_id']) 
        && isset($_POST['receiver_id']) 
        && isset($_POST['message'])
    ) {
        $msgText    = $conn->real_escape_string(trim($_POST['message']));
        $receiverId = (int)$_POST['receiver_id'];
        $oid        = (int)$_POST['order_id'];

        // Verify that this order belongs to this buyer
        $check = $conn->prepare("
          SELECT DISTINCT seller_id 
            FROM order_items 
           WHERE order_id = ?
           LIMIT 1
        ");
        $check->bind_param("i", $oid);
        $check->execute();
        $check->bind_result($trueSellerId);
        if ($check->fetch()) {
            $check->close();
            if ($trueSellerId === $receiverId) {
                $ins = $conn->prepare("
                  INSERT INTO messages
                    (order_id, sender_id, receiver_id, message, timestamp)
                  VALUES (?, ?, ?, ?, NOW())
                ");
                $ins->bind_param("iiis", $oid, $buyer_id, $receiverId, $msgText);
                $ins->execute();
                $ins->close();
                header("Location: buyerdashboard.php?tab=messages&order_id=$oid");
                exit();
            }
        } else {
            $check->close();
        }
    }
}

// ────────────────────────────────────────────────────────────────────────────
//  7) Fetch “Welcome Back” Name or Email
// ────────────────────────────────────────────────────────────────────────────
$userRes = $conn->query("
  SELECT full_name, email 
    FROM users 
   WHERE id = $buyer_id
");
$userRow     = $userRes->fetch_assoc();
$displayName = !empty($userRow['full_name']) 
               ? $userRow['full_name'] 
               : $userRow['email'];

// ────────────────────────────────────────────────────────────────────────────
//  8) Snippet helper
// ────────────────────────────────────────────────────────────────────────────
function snippet($str, $len = 80) {
    if (strlen($str) <= $len) return htmlspecialchars($str);
    $tr = substr($str, 0, $len);
    return htmlspecialchars(rtrim($tr) . '…');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title>Smart Mzansi | Buyer Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <!-- ───────── CSS (embedded, unique to buyer dashboard) ───────── -->
  <style>
    /* Import Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Georgia&family=Poppins&display=swap');

    /* RESET & BASE */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body {
      font-family: 'Poppins', sans-serif;
      background-color: #f5f5f5;
      color: #333;
      line-height: 1.5;
    }
    a { text-decoration: none; color: inherit; }
    img { max-width: 100%; display: block; }

    /* ── TOPBAR NAVIGATION ── */
    .topbar {
      background-color: #fff;
      border-bottom: 1px solid #ddd;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.75rem 1.5rem;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .topbar .logo {
      font-family: 'Georgia', serif;
      font-size: 1.6rem;
      font-weight: bold;
      color: #568203;
    }
    .topbar nav {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .topbar nav a {
      padding: 0.3rem 0;
      font-weight: 500;
      color: #333;
      position: relative;
    }
    .topbar nav a::after {
      content: '';
      position: absolute;
      bottom: -2px;
      left: 0;
      width: 0;
      height: 2px;
      background-color: #568203;
      transition: width 0.3s;
    }
    .topbar nav a:hover::after,
    .topbar nav a.active::after {
      width: 100%;
    }

    .topbar .search-form {
      margin-left: 1rem;
    }
    .topbar .search-form input {
      padding: 0.3rem 0.5rem;
      font-size: 0.9rem;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    .topbar .search-form button {
      padding: 0.3rem 0.5rem;
      font-size: 0.9rem;
      border: 1px solid #568203;
      background-color: #568203;
      color: #fff;
      border-radius: 4px;
      margin-left: 0.3rem;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    .topbar .search-form button:hover {
      background-color: #446e02;
    }

    .topbar .right-icons {
      display: flex;
      align-items: center;
      gap: 1rem;
    }
    .icon-btn {
      position: relative;
      font-size: 1.2rem;
      color: #333;
      background: none;
      border: none;
      cursor: pointer;
    }
    .icon-btn .badge {
      position: absolute;
      top: -4px;
      right: -4px;
      background-color: #dc3545;
      color: #fff;
      font-size: 0.65rem;
      padding: 0.15rem 0.35rem;
      border-radius: 50%;
    }

    /* ── MAIN CONTENT ── */
    .content {
      max-width: 1200px;
      margin: 2rem auto;
      padding: 0 1rem;
    }
    .alert {
      margin-bottom: 1rem;
      padding: 0.75rem 1rem;
      border-radius: 5px;
      font-size: 0.95rem;
    }
    .alert-success { background-color: #d4edda; color: #155724; }
    .alert-warning { background-color: #fff3cd; color: #856404; }
    .alert-info    { background-color: #cce5ff; color: #004085; }

    /* ── DASHBOARD BANNER ── */
    /* Banner image file: put dashboard-banner.jpg under /register/ */
    .dashboard-banner {
      background-image: url('../Dashboards/home.webp');
      background-size: cover;
      background-position: center;
      border-radius: 8px;
      padding: 2rem;
      color: #fff;
      margin-bottom: 2rem;
      position: relative;
      overflow: hidden;
    }
    .dashboard-banner::after {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background-color: rgba(0, 0, 0, 0.4);
      z-index: 0;
    }
    .dashboard-banner .text {
      position: relative;
      z-index: 1;
      max-width: 600px;
    }
    .dashboard-banner h1 {
      font-family: 'Georgia', serif;
      font-size: 2rem;
      margin-bottom: 0.5rem;
    }
    .dashboard-banner p {
      font-size: 1rem;
      margin-bottom: 1rem;
    }
    .dashboard-banner .btn {
      background-color: #568203;
      color: #fff;
      padding: 0.6rem 1.2rem;
      border-radius: 5px;
      font-weight: bold;
      transition: background-color 0.3s;
    }
    .dashboard-banner .btn:hover {
      background-color: #446e02;
      color: #fff;
    }

    /* ── DASHBOARD CATEGORIES GRID ── */
    .dashboard-categories {
      margin-bottom: 2rem;
    }
    .dashboard-categories h2 {
      font-size: 1.6rem;
      color: #2e7d32;
      margin-bottom: 1rem;
    }
    .dashboard-cat-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 1rem;
    }
    .dashboard-cat-card {
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
      text-align: center;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .dashboard-cat-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .dashboard-cat-card img {
      height: 100px;
      object-fit: cover;
    }
    .dashboard-cat-card span {
      display: block;
      padding: 0.6rem 0;
      font-weight: 500;
      color: #333;
    }

    /* ── SHOP TAB: CATEGORY BANNER & GRID ── */
    .cat-banner {
      position: relative;
      height: 200px;
      border-radius: 8px;
      overflow: hidden;
      margin-bottom: 1.5rem;
    }
    .cat-banner img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      filter: brightness(0.6);
    }
    .cat-banner h2 {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: #fff;
      font-family: 'Georgia', serif;
      font-size: 2rem;
      z-index: 1;
    }

    .shop-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .shop-header select {
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 0.95rem;
    }
    .shop-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1.5rem;
      margin-bottom: 2rem;
    }
    .shop-card {
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: transform 0.3s, box-shadow 0.3s;
    }
    .shop-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .shop-card img {
      width: 100%;
      height: 160px;
      object-fit: cover;
    }
    .shop-card .card-info {
      padding: 0.8rem;
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .shop-card h5 {
      font-size: 1rem;
      margin-bottom: 0.4rem;
      color: #2e7d32;
    }
    .shop-card p.price {
      margin-bottom: 0.4rem;
      color: #333;
      font-size: 0.95rem;
    }
    .shop-card p.snippet {
      font-size: 0.85rem;
      color: #555;
      margin-bottom: 0.6rem;
      flex: 1;
    }
    .shop-card .card-footer {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.5rem 0.8rem;
      border-top: 1px solid #eee;
    }
    .shop-card .card-footer .btn‐cart {
      background-color: #568203;
      color: #fff;
      border: none;
      padding: 0.4rem 0.8rem;
      border-radius: 5px;
      font-size: 0.9rem;
      cursor: pointer;
      transition: background-color 0.3s;
    }
    .shop-card .card-footer .btn‐cart:hover {
      background-color: #446e02;
    }
    .shop-card .card-footer .wishlist-btn {
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      color: #555;
      display: flex;
      align-items: center;
      gap: 0.25rem;
    }
    .shop-card .card-footer .wishlist-btn.added {
      color: #e91e63;
    }

    /* ── TABLE STYLES (Cart, Orders) ── */
    .table-responsive {
      overflow-x: auto;
      margin-bottom: 2rem;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 1rem;
    }
    table th, table td {
      padding: 0.75rem;
      text-align: left;
      border-bottom: 1px solid #eee;
      font-size: 0.95rem;
    }
    table th {
      background-color: #f4f4f4;
      font-weight: 600;
      color: #2e7d32;
    }
    table td img {
      border-radius: 5px;
    }

    /* ── BUTTONS & FORMS ── */
    .btn {
      display: inline-block;
      padding: 0.5rem 1rem;
      border: none;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.95rem;
      text-align: center;
    }
    .btn-primary {
      background-color: #568203;
      color: #fff;
    }
    .btn-primary:hover {
      background-color: #446e02;
    }
    .btn‐small {
      padding: 0.3rem 0.6rem;
      font-size: 0.85rem;
    }
    .form-control {
      width: 100%;
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 0.9rem;
      margin-bottom: 1rem;
    }

    /* ── PROFILE FORM ── */
    .profile-form {
      max-width: 500px;
      margin: 0 auto 2rem;
    }
    .profile-form label {
      font-weight: 500;
      margin-bottom: 0.3rem;
      display: block;
      font-size: 0.95rem;
    }
    .profile-form input {
      margin-bottom: 1rem;
      font-size: 0.9rem;
    }
    .profile-form .btn-primary {
      margin-top: 0.5rem;
    }

    /* ── MESSAGES LAYOUT ── */
    .messages-container {
      display: flex;
      gap: 1rem;
      flex-wrap: wrap;
    }
    .messages-sidebar {
      flex: 1 1 200px;
      max-width: 240px;
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      overflow-y: auto;
      height: calc(100vh - 8rem);
      padding: 1rem;
    }
    .messages-sidebar h5 {
      font-size: 1rem;
      color: #2e7d32;
      margin-bottom: 0.75rem;
    }
    .messages-sidebar .order‐item {
      padding: 0.6rem 1rem;
      margin-bottom: 0.5rem;
      border: 1px solid #eee;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.95rem;
      transition: background 0.3s;
    }
    .messages-sidebar .order‐item.active,
    .messages-sidebar .order‐item:hover {
      background-color: #568203;
      color: #fff;
    }
    .messages-main {
      flex: 3 1 0;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .chat-box {
      flex: 1;
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 1rem;
      overflow-y: auto;
      height: calc(100vh - 10rem);
    }
    .message {
      margin-bottom: 1rem;
      font-size: 0.9rem;
    }
    .message .sender {
      font-weight: 600;
      color: #2e7d32;
      font-size: 0.95rem;
    }
    .message .timestamp {
      font-size: 0.8rem;
      color: #777;
      margin-left: 0.5rem;
    }
    .chat-input {
      display: flex;
      gap: 0.5rem;
    }
    .chat-input textarea {
      flex: 1;
      padding: 0.5rem;
      border: 1px solid #ccc;
      border-radius: 5px;
      font-size: 0.9rem;
      resize: vertical;
    }
    .chat-input button {
      background-color: #568203;
      border: none;
      color: #fff;
      padding: 0.6rem 1rem;
      border-radius: 5px;
      cursor: pointer;
      font-size: 0.9rem;
    }
    .chat-input button:hover {
      background-color: #446e02;
    }

    /* ── NOTIFICATIONS ── */
    .notifications-list {
      max-width: 500px;
      margin: 0 auto 2rem;
    }
    .notifications-list .notification {
      background-color: #fff;
      border: 1px solid #ddd;
      border-radius: 8px;
      padding: 0.75rem 1rem;
      margin-bottom: 0.5rem;
      font-size: 0.95rem;
    }
    .notifications-list .notification small {
      display: block;
      margin-top: 0.3rem;
      color: #777;
      font-size: 0.85rem;
    }

    /* ── UTILITY ── */
    .mb-4 { margin-bottom: 1rem; }
    .mb-5 { margin-bottom: 1.5rem; }
    .mt-4 { margin-top: 1rem; }
    .mt-5 { margin-top: 1.5rem; }
    .text-center { text-align: center; }
  </style>

  <!-- Font Awesome for icons -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
  />
</head>
<body>
  <!-- ───────── TOPBAR NAVIGATION ───────── -->
  <div class="topbar">
    <div class="logo">Smart <span style="color:#2e7d32;">Mzansi</span></div>

    <nav>
      <?php
        $activeTab = $_GET['tab'] ?? 'dashboard';
      ?>
      <a
        href="buyerdashboard.php?tab=dashboard"
        class="<?= $activeTab === 'dashboard' ? 'active' : '' ?>"
      >
        <i class="fas fa-home"></i> Home
      </a>
      <a
        href="buyerdashboard.php?tab=products"
        class="<?= $activeTab === 'products' ? 'active' : '' ?>"
      >
        <i class="fas fa-store"></i> Shop
      </a>
    </nav>

    <!-- ─── SEARCH FORM ─── -->
    <form
      class="search-form"
      method="GET"
      action="buyerdashboard.php"
      style="display:flex; align-items:center;"
    >
      <input type="hidden" name="tab" value="products"/>
      <input
        type="text"
        name="q"
        placeholder="Search products…"
        style="border-radius:4px; border:1px solid #ccc; padding:0.3rem 0.5rem; font-size:0.9rem;"
      />
      <button type="submit">
        <i class="fas fa-search"></i>
      </button>
    </form>

    <div class="right-icons">
      <!-- WISHLIST ICON -->
      <button
        class="icon-btn"
        title="Wishlist"
        onclick="location.href='buyerdashboard.php?tab=wishlist'"
      >
        <i class="fas fa-heart"></i>
        <?php
          $wishCount = $conn->query("
            SELECT COUNT(*) AS cnt
              FROM wishlists
             WHERE buyer_id = $buyer_id
          ")->fetch_assoc()['cnt'];
          if ($wishCount > 0):
        ?>
          <span class="badge"><?= $wishCount ?></span>
        <?php endif; ?>
      </button>

      <!-- CART ICON -->
      <button
        class="icon-btn"
        title="Cart"
        onclick="location.href='buyerdashboard.php?tab=cart'"
      >
        <i class="fas fa-shopping-cart"></i>
        <?php
          $cartCount = $conn->query("
            SELECT COUNT(*) AS cnt
              FROM cart_items
             WHERE buyer_id = $buyer_id
          ")->fetch_assoc()['cnt'];
          if ($cartCount > 0):
        ?>
          <span class="badge"><?= $cartCount ?></span>
        <?php endif; ?>
      </button>

      <!-- ORDERS ICON -->
      <button
        class="icon-btn"
        title="My Orders"
        onclick="location.href='buyerdashboard.php?tab=orders'"
      >
        <i class="fas fa-box-open"></i>
        <?php
          $pendingOrders = $conn->query("
            SELECT COUNT(*) AS cnt
              FROM orders
             WHERE buyer_id = $buyer_id
               AND status = 'pending'
          ")->fetch_assoc()['cnt'];
          if ($pendingOrders > 0):
        ?>
          <span class="badge"><?= $pendingOrders ?></span>
        <?php endif; ?>
      </button>

      <!-- MESSAGES ICON -->
      <button
        class="icon-btn"
        title="Messages"
        onclick="location.href='buyerdashboard.php?tab=messages'"
      >
        <i class="fas fa-envelope"></i>
        <?php
          $msgCount = $conn->query("
            SELECT COUNT(*) AS cnt
              FROM messages
             WHERE receiver_id = $buyer_id
          ")->fetch_assoc()['cnt'];
          if ($msgCount > 0):
        ?>
          <span class="badge"><?= $msgCount ?></span>
        <?php endif; ?>
      </button>

      <!-- NOTIFICATIONS ICON -->
      <button
        class="icon-btn"
        title="Notifications"
        onclick="location.href='buyerdashboard.php?tab=notifications'"
      >
        <i class="fas fa-bell"></i>
        <?php
          $notifCount = $conn->query("
            SELECT COUNT(*) AS cnt
              FROM notifications
             WHERE user_id = $buyer_id
               AND is_read = 0
          ")->fetch_assoc()['cnt'];
          if ($notifCount > 0):
        ?>
          <span class="badge"><?= $notifCount ?></span>
        <?php endif; ?>
      </button>

      <!-- PROFILE ICON -->
      <button
        class="icon-btn"
        title="Profile"
        onclick="location.href='buyerdashboard.php?tab=profile'"
      >
        <i class="fas fa-user-circle"></i>
      </button>
    </div>
  </div>

  <!-- ───────── MAIN CONTENT AREA ───────── -->
  <div class="content">
    <?php
      // Flash message if ?msg=…
      if (isset($_GET['msg'])) {
        $alerts = [
          'added_to_cart'     => ['text'=>'Product added to cart','type'=>'alert-success'],
          'removed_from_cart' => ['text'=>'Removed from cart','type'=>'alert-warning'],
          'added_to_wishlist' => ['text'=>'Added to wishlist','type'=>'alert-success'],
          'cart_empty'        => ['text'=>'Your cart is empty','type'=>'alert-info'],
          'profile_updated'   => ['text'=>'Profile updated','type'=>'alert-success'],
        ];
        if (array_key_exists($_GET['msg'], $alerts)) {
          $a = $alerts[$_GET['msg']];
          echo "<div class=\"{$a['type']}\">{$a['text']}</div>";
        }
      }

      // Determine which tab to load; default = dashboard
      $tab = $_GET['tab'] ?? 'dashboard';
    ?>

    <?php if ($tab === 'dashboard'): ?>
      <!-- ─── DASHBOARD HOME VIEW ─── -->
      <section class="dashboard-banner">
        <div class="text">
          <h1>Welcome Back, <?= htmlspecialchars($displayName) ?>!</h1>
          <p>Explore categories or start shopping now.</p>
          <a href="buyerdashboard.php?tab=products" class="btn">Shop Now</a>
        </div>
      </section>

      <section class="dashboard-categories">
        <h2>Shop by Category</h2>
        <div class="dashboard-cat-grid">
          <a href="buyerdashboard.php?tab=products&cat=Electronics" class="dashboard-cat-card">
            <img src="../Dashboards/electronics.jpg" alt="Electronics" />
            <span>Electronics</span>
          </a>
          <a href="buyerdashboard.php?tab=products&cat=Fashion" class="dashboard-cat-card">
            <img src="../Dashboards/fashion-ecommerce.webp" alt="Fashion" />
            <span>Fashion</span>
          </a>
          <a href="buyerdashboard.php?tab=products&cat=Home & Living" class="dashboard-cat-card">
            <img src="../Dashboards/home.webp" alt="Home &amp; Living" />
            <span>Home &amp; Living</span>
          </a>
          <a href="buyerdashboard.php?tab=products&cat=Beauty" class="dashboard-cat-card">
            <img src="../Dashboards/beauty.jpg" alt="Beauty" />
            <span>Beauty</span>
          </a>
          <a href="buyerdashboard.php?tab=products&cat=Sports" class="dashboard-cat-card">
            <img src="../Dashboards/sports.jpg" alt="Sports" />
            <span>Sports</span>
          </a>
          <a href="buyerdashboard.php?tab=products&cat=Grocery" class="dashboard-cat-card">
            <img src="../Dashboards/grocery.jpeg" alt="Grocery" />
            <span>Grocery</span>
          </a>
        </div>
      </section>

    <?php elseif ($tab === 'products'): ?>
      <!-- ─── SHOP TAB: ALL PRODUCTS (with optional category banner & search) ─── -->
      <?php
        // Fetch categories for dropdown
        $catsRes     = $conn->query("SELECT name FROM categories ORDER BY name ASC");
        $selectedCat = $_GET['cat'] ?? '';
        $searchTerm  = trim($_GET['q'] ?? '');

        // If a category is selected, show a banner
        if (!empty($selectedCat)):
          switch ($selectedCat) {
            case 'Electronics':
              $catImg = '../Dashboards/electronics.jpg'; break;
            case 'Fashion':
              $catImg = '../Dashboards/fashion-ecommerce.webp'; break;
            case 'Home & Living':
              $catImg = '../Dashboards/home.webp'; break;
            case 'Beauty':
              $catImg = '../Dashboards/beauty.jpg'; break;
            case 'Sports':
              $catImg = '../Dashboards/sports.jpg'; break;
            case 'Grocery':
              $catImg = '../Dashboards/grocery.jpeg'; break;
            default:
              $catImg = ''; break;
          }
          if (!empty($catImg)):
      ?>
            <div class="cat-banner mb-4">
              <img src="<?= htmlspecialchars($catImg) ?>" alt="<?= htmlspecialchars($selectedCat) ?>" />
              <h2><?= htmlspecialchars($selectedCat) ?></h2>
            </div>
      <?php
          endif;
        endif;

        // Build base product query
        $baseSQL = "
          SELECT 
            p.id, p.name, p.price, p.image, p.description
          FROM products p
          JOIN categories c ON p.category_id = c.id
          WHERE p.approved = 1
            AND p.quantity > 0
        ";
        if (!empty($selectedCat)) {
          $catEsc = $conn->real_escape_string($selectedCat);
          $baseSQL .= " AND c.name = '$catEsc'";
        }
        if (!empty($searchTerm)) {
          $term     = $conn->real_escape_string($searchTerm);
          $baseSQL .= " AND (
            p.name LIKE '%$term%' 
            OR p.description LIKE '%$term%'
          )";
        }
        $baseSQL .= " ORDER BY p.created_at DESC";

        $result = $conn->query($baseSQL);
      ?>

      <div class="shop-header">
        <form method="GET" action="buyerdashboard.php" style="display:flex; align-items:center; gap:0.5rem;">
          <input type="hidden" name="tab" value="products"/>
          <label for="cat" style="font-size:0.95rem;">Filter by Category:</label>
          <select name="cat" id="cat" onchange="this.form.submit()">
            <option value="">All</option>
            <?php while ($c = $catsRes->fetch_assoc()): ?>
              <option
                value="<?= htmlspecialchars($c['name']) ?>"
                <?= ($c['name'] === $selectedCat) ? 'selected' : '' ?>
              >
                <?= htmlspecialchars($c['name']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </form>
        <h2>
          <?php
            if (!empty($searchTerm)) {
              echo "Results for “" . htmlspecialchars($searchTerm) . "”";
            } elseif (!empty($selectedCat)) {
              echo "Category: " . htmlspecialchars($selectedCat);
            } else {
              echo "All Products";
            }
          ?>
        </h2>
      </div>

      <div class="shop-grid">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <?php
              $pid        = (int)$row['id'];
              $inWishlist = (bool)$conn->query("
                SELECT 1 FROM wishlists 
                 WHERE buyer_id = $buyer_id 
                   AND product_id = $pid
              ")->fetch_assoc();

              // Product image path (uploads folder under Dashboards)
              $imgPath = '../Dashboards/uploads/' . $row['image'];
            ?>
            <div class="shop-card">
              <a href="product.php?id=<?= $pid ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($imgPath) ?>"
                     alt="<?= htmlspecialchars($row['name']) ?>"/>
                <div class="card-info">
                  <h5><?= htmlspecialchars($row['name']) ?></h5>
                  <p class="price">R<?= number_format($row['price'], 2) ?></p>
                  <p class="snippet"><?= snippet($row['description'], 80) ?></p>
                </div>
              </a>
              <div class="card-footer">
                <!-- Add to Cart -->
                <form method="POST" action="buyerdashboard.php?tab=products">
                  <input type="hidden" name="action" value="add_to_cart"/>
                  <input type="hidden" name="product_id" value="<?= $pid ?>"/>
                  <button type="submit" class="btn‐cart btn‐small">
                    <i class="fas fa-cart-plus"></i>
                  </button>
                </form>

                <!-- Wishlist toggle -->
                <form method="POST" action="buyerdashboard.php?tab=products">
                  <?php if ($inWishlist): ?>
                    <input type="hidden" name="action" value="remove_wishlist"/>
                    <input type="hidden" name="product_id" value="<?= $pid ?>"/>
                    <button type="submit" class="wishlist-btn added" title="Remove from Wishlist">
                      <i class="fas fa-heart"></i> Wishlist
                    </button>
                  <?php else: ?>
                    <input type="hidden" name="action" value="add_wishlist"/>
                    <input type="hidden" name="product_id" value="<?= $pid ?>"/>
                    <button type="submit" class="wishlist-btn" title="Add to Wishlist">
                      <i class="far fa-heart"></i> Wishlist
                    </button>
                  <?php endif; ?>
                </form>
              </div>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="text-center">No products found.</p>
        <?php endif; ?>
      </div>

    <?php elseif ($tab === 'cart'): ?>
      <!-- ─── CART TAB ─── -->
      <?php
        $cartRes = $conn->query("
          SELECT
            ci.id            AS cart_item_id,
            p.id             AS product_id,
            p.name,
            p.price,
            p.image,
            ci.quantity,
            (p.price * ci.quantity) AS line_total
          FROM cart_items ci
          JOIN products p ON ci.product_id = p.id
          WHERE ci.buyer_id = $buyer_id
        ");
      ?>

      <h2 class="mb-4">Your Cart</h2>
      <?php if ($cartRes && $cartRes->num_rows > 0): ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Unit Price</th>
                <th>Quantity</th>
                <th>Line Total</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php
                $grandTotal = 0;
                while ($ci = $cartRes->fetch_assoc()):
                  $grandTotal += $ci['line_total'];
                  $imgPathCart = '../Dashboards/uploads/' . $ci['image'];
              ?>
                <tr>
                  <td>
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                      <img
                        src="<?= htmlspecialchars($imgPathCart) ?>"
                        alt="<?= htmlspecialchars($ci['name']) ?>"
                        width="60"
                        style="border-radius:5px;"
                      />
                      <span><?= htmlspecialchars($ci['name']) ?></span>
                    </div>
                  </td>
                  <td>R<?= number_format($ci['price'], 2) ?></td>
                  <td>
                    <form method="POST" action="buyerdashboard.php?tab=cart" style="display:flex; align-items:center; gap:0.3rem;">
                      <input type="hidden" name="action_cart" value="update_qty"/>
                      <input type="hidden" name="cart_item_id" value="<?= $ci['cart_item_id'] ?>"/>
                      <input
                        type="number"
                        name="new_quantity"
                        min="1"
                        value="<?= $ci['quantity'] ?>"
                        style="width:60px; padding:0.3rem; border:1px solid #ccc; border-radius:5px; font-size:0.9rem;"
                      />
                      <button type="submit" class="btn btn-primary btn‐small">Update</button>
                    </form>
                  </td>
                  <td>R<?= number_format($ci['line_total'], 2) ?></td>
                  <td>
                    <form method="POST" action="buyerdashboard.php?tab=cart">
                      <input type="hidden" name="action_cart" value="remove_item"/>
                      <input type="hidden" name="cart_item_id" value="<?= $ci['cart_item_id'] ?>"/>
                      <button type="submit" class="btn btn‐small" style="background:none; border:1px solid #dc3545; color:#dc3545; border-radius:5px;">
                        <i class="fas fa-trash-alt"></i>
                      </button>
                    </form>
                  </td>
                </tr>
              <?php endwhile; ?>
              <tr>
                <td colspan="3" style="text-align:right; font-weight:600;">Grand Total:</td>
                <td colspan="2" style="font-weight:600;">R<?= number_format($grandTotal, 2) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
        <a href="checkout.php" class="btn btn-primary">Proceed to Checkout</a>
      <?php else: ?>
        <p class="text-center">Your cart is empty. <a href="buyerdashboard.php?tab=products">Browse products</a>.</p>
      <?php endif; ?>

    <?php elseif ($tab === 'wishlist'): ?>
      <!-- ─── WISHLIST TAB ─── -->
      <?php
        $wlRes = $conn->query("
          SELECT
            w.id            AS wishlist_id,
            p.id            AS product_id,
            p.name,
            p.price,
            p.image
          FROM wishlists w
          JOIN products p ON w.product_id = p.id
          WHERE w.buyer_id = $buyer_id
        ");
      ?>

      <h2 class="mb-4">Your Wishlist</h2>
      <?php if ($wlRes && $wlRes->num_rows > 0): ?>
        <div class="shop-grid">
          <?php while ($w = $wlRes->fetch_assoc()): ?>
            <?php
              $imgPathWl = '../Dashboards/uploads/' . $w['image'];
            ?>
            <div class="shop-card">
              <a href="product.php?id=<?= $w['product_id'] ?>" style="text-decoration:none; color:inherit;">
                <img src="<?= htmlspecialchars($imgPathWl) ?>" alt="<?= htmlspecialchars($w['name']) ?>"/>
                <div class="card-info">
                  <h5><?= htmlspecialchars($w['name']) ?></h5>
                  <p class="price">R<?= number_format($w['price'], 2) ?></p>
                </div>
              </a>
              <div class="card-footer">
                <!-- Move to Cart -->
                <form method="POST" action="buyerdashboard.php?tab=wishlist">
                  <input type="hidden" name="action_wl" value="move_to_cart"/>
                  <input type="hidden" name="wishlist_id" value="<?= $w['wishlist_id'] ?>"/>
                  <input type="hidden" name="product_id" value="<?= $w['product_id'] ?>"/>
                  <button type="submit" class="btn‐small btn-primary">
                    <i class="fas fa-cart-plus"></i>
                  </button>
                </form>
                <!-- Remove from Wishlist -->
                <form method="POST" action="buyerdashboard.php?tab=wishlist">
                  <input type="hidden" name="action_wl" value="remove_wishlist"/>
                  <input type="hidden" name="wishlist_id" value="<?= $w['wishlist_id'] ?>"/>
                  <button type="submit" class="btn btn‐small" style="background:none; border:1px solid #dc3545; color:#dc3545; border-radius:5px;">
                    <i class="fas fa-trash-alt"></i>
                  </button>
                </form>
              </div>
            </div>
          <?php endwhile; ?>
        </div>
      <?php else: ?>
        <p class="text-center">Your wishlist is empty.</p>
      <?php endif; ?>

    <?php elseif ($tab === 'orders'): ?>
      <!-- ─── ORDERS TAB ─── -->
      <?php
        $ordersRes = $conn->query("
          SELECT 
            o.id AS order_id,
            o.total_price,
            o.status,
            o.created_at
          FROM orders o
          WHERE o.buyer_id = $buyer_id
          ORDER BY o.created_at DESC
        ");
      ?>

      <h2 class="mb-4">My Orders</h2>
      <?php if ($ordersRes && $ordersRes->num_rows > 0): ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Order #</th>
                <th>Total Price</th>
                <th>Status</th>
                <th>Placed On</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php while ($ord = $ordersRes->fetch_assoc()): ?>
                <tr>
                  <td>#<?= $ord['order_id'] ?></td>
                  <td>R<?= number_format($ord['total_price'], 2) ?></td>
                  <td>
                    <?php if ($ord['status'] === 'pending'): ?>
                      <span style="background:#ffc107; color:#212529; padding:0.3rem 0.6rem; border-radius:5px; font-size:0.9rem;">Pending</span>
                    <?php elseif ($ord['status'] === 'shipped'): ?>
                      <span style="background:#17a2b8; color:#fff; padding:0.3rem 0.6rem; border-radius:5px; font-size:0.9rem;">Shipped</span>
                    <?php elseif ($ord['status'] === 'delivered'): ?>
                      <span style="background:#28a745; color:#fff; padding:0.3rem 0.6rem; border-radius:5px; font-size:0.9rem;">Delivered</span>
                    <?php else: ?>
                      <span style="background:#dc3545; color:#fff; padding:0.3rem 0.6rem; border-radius:5px; font-size:0.9rem;">Cancelled</span>
                    <?php endif; ?>
                  </td>
                  <td><?= date('d M Y, H:i', strtotime($ord['created_at'])) ?></td>
                  <td>
                    <a
                      href="buyerdashboard.php?tab=orders&order_id=<?= $ord['order_id'] ?>"
                      class="btn btn-primary btn‐small"
                    >
                      View Details
                    </a>
                  </td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>

        <?php
          if (!empty($_GET['order_id'])):
            $oid = (int)$_GET['order_id'];
            $chk = $conn->query("
              SELECT id FROM orders 
               WHERE id = $oid 
                 AND buyer_id = $buyer_id
            ");
            if ($chk->num_rows === 1):
              $details = $conn->query("
                SELECT 
                  oi.quantity,
                  oi.unit_price,
                  p.name AS product_name,
                  p.image AS product_image,
                  u.full_name AS seller_name
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                JOIN users u ON oi.seller_id = u.id
                WHERE oi.order_id = $oid
              ");
        ?>
              <div class="mt-4">
                <h4>Order #<?= $oid ?> Details</h4>
                <div class="table-responsive">
                  <table>
                    <thead>
                      <tr>
                        <th>Product</th>
                        <th>Seller</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Line Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php
                        $subTotal = 0;
                        while ($di = $details->fetch_assoc()):
                          $line = $di['quantity'] * $di['unit_price'];
                          $subTotal += $line;
                          $imgLine = 'uploads/' . $di['product_image'];
                      ?>
                        <tr>
                          <td>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                              <img
                                src="<?= htmlspecialchars($imgLine) ?>"
                                alt="<?= htmlspecialchars($di['product_name']) ?>"
                                width="60"
                                style="border-radius:5px;"
                              />
                              <span><?= htmlspecialchars($di['product_name']) ?></span>
                            </div>
                          </td>
                          <td><?= htmlspecialchars($di['seller_name']) ?></td>
                          <td><?= $di['quantity'] ?></td>
                          <td>R<?= number_format($di['unit_price'], 2) ?></td>
                          <td>R<?= number_format($line, 2) ?></td>
                        </tr>
                      <?php endwhile; ?>
                      <tr>
                        <td colspan="4" style="text-align:right; font-weight:600;">Subtotal:</td>
                        <td style="font-weight:600;">R<?= number_format($subTotal, 2) ?></td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
        <?php
            else:
              echo "<p style=\"color:#dc3545;\">You don’t have permission to view that order.</p>";
            endif;
          endif;
        ?>

      <?php else: ?>
        <p class="text-center">You have no orders yet.</p>
      <?php endif; ?>

    <?php elseif ($tab === 'profile'): ?>
      <!-- ─── PROFILE TAB ─── -->
      <?php
        $profile = $conn->query("
          SELECT full_name, email
            FROM users
           WHERE id = $buyer_id
        ")->fetch_assoc();
      ?>

      <h2 class="mb-4">My Profile</h2>
      <form method="POST" action="buyerdashboard.php?tab=profile" class="profile-form">
        <input type="hidden" name="action_profile" value="update_profile"/>
        <label for="full_name">Full Name</label>
        <input
          type="text"
          id="full_name"
          name="full_name"
          class="form-control"
          value="<?= htmlspecialchars($profile['full_name']) ?>"
        />
        <label for="email">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          value="<?= htmlspecialchars($profile['email']) ?>"
          required
        />
        <button type="submit" class="btn btn-primary">Update Profile</button>
      </form>

      <hr class="mb-5">

      <h4>Change Password</h4>
      <form method="POST" action="buyerdashboard.php?tab=profile" class="profile-form">
        <input type="hidden" name="action_profile" value="change_password"/>
        <label for="current_password">Current Password</label>
        <input
          type="password"
          id="current_password"
          name="current_password"
          class="form-control"
          required
        />
        <label for="new_password">New Password</label>
        <input
          type="password"
          id="new_password"
          name="new_password"
          class="form-control"
          required
        />
        <label for="confirm_password">Confirm New Password</label>
        <input
          type="password"
          id="confirm_password"
          name="confirm_password"
          class="form-control"
          required
        />
        <button type="submit" class="btn btn-warning">Change Password</button>
      </form>
      <?php
        // TODO: Implement actual password‐change logic if needed
      ?>

    <?php elseif ($tab === 'messages'): ?>
      <!-- ─── MESSAGES TAB ─── -->
      <?php
        $ordersList     = $conn->query("
          SELECT o.id AS order_id, o.status, o.created_at
            FROM orders o
           WHERE o.buyer_id = $buyer_id
           ORDER BY o.created_at DESC
        ");
        $currentOrderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : null;
        if ($currentOrderId) {
          $chk = $conn->query("
            SELECT id FROM orders 
             WHERE id = $currentOrderId 
               AND buyer_id = $buyer_id
          ");
          if ($chk->num_rows === 0) {
            echo "<p style=\"color:#dc3545;\">You don’t have access to this chat.</p>";
            $currentOrderId = null;
          }
        }
      ?>

      <div class="messages-container">
        <div class="messages-sidebar">
          <h5>Your Orders</h5>
          <?php if ($ordersList && $ordersList->num_rows > 0): ?>
            <?php while ($o = $ordersList->fetch_assoc()): ?>
              <div
                class="order‐item <?= ($currentOrderId === $o['order_id']) ? 'active' : '' ?>"
                onclick="location.href='buyerdashboard.php?tab=messages&order_id=<?= $o['order_id'] ?>';"
              >
                Order #<?= $o['order_id'] ?>
                <?php if ($o['status'] === 'pending'): ?>
                  <span style="background:#ffc107; color:#212529; padding:0.2rem 0.4rem; border-radius:5px; font-size:0.8rem; margin-left:0.5rem;">Pending</span>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <div style="padding:0.5rem; border:1px solid #eee; border-radius:5px; font-size:0.9rem; color:#777;">No orders to show.</div>
          <?php endif; ?>
        </div>

        <div class="messages-main">
          <?php if ($currentOrderId): ?>
            <?php
              $msgs = $conn->query("
                SELECT 
                  m.*,
                  u.full_name AS sender_name
                FROM messages m
                JOIN users u ON m.sender_id = u.id
                WHERE m.order_id = $currentOrderId
                ORDER BY m.timestamp ASC
              ");
              $sellerRow = $conn->query("
                SELECT DISTINCT seller_id 
                  FROM order_items 
                 WHERE order_id = $currentOrderId
                 LIMIT 1
              ")->fetch_assoc();
              $sellerId = $sellerRow['seller_id'];
            ?>

            <h5>Chat about Order #<?= $currentOrderId ?></h5>
            <div class="chat-box">
              <?php if ($msgs && $msgs->num_rows > 0): ?>
                <?php while ($m = $msgs->fetch_assoc()): ?>
                  <div class="message">
                    <span class="sender"><?= htmlspecialchars($m['sender_name']) ?></span>
                    <span class="timestamp"><?= date('d M Y, H:i', strtotime($m['timestamp'])) ?></span>
                    <p><?= nl2br(htmlspecialchars($m['message'])) ?></p>
                  </div>
                <?php endwhile; ?>
              <?php else: ?>
                <p class="text-center" style="color:#777;">No messages yet. Say hello!</p>
              <?php endif; ?>
            </div>

            <form method="POST" action="buyerdashboard.php?tab=messages&order_id=<?= $currentOrderId ?>" class="chat-input">
              <input type="hidden" name="action_msg" value="send_message"/>
              <input type="hidden" name="order_id" value="<?= $currentOrderId ?>"/>
              <input type="hidden" name="receiver_id" value="<?= $sellerId ?>"/>
              <textarea name="message" placeholder="Type your message…" required></textarea>
              <button type="submit"><i class="fas fa-paper-plane"></i></button>
            </form>
          <?php else: ?>
            <p style="color:#777;">Select an order on the left to view or send messages.</p>
          <?php endif; ?>
        </div>
      </div>

    <?php elseif ($tab === 'notifications'): ?>
      <!-- ─── NOTIFICATIONS TAB ─── -->
      <?php
        // Mark all unread as read
        $conn->query("
          UPDATE notifications 
             SET is_read = 1 
           WHERE user_id = $buyer_id 
             AND is_read = 0
        ");
        $notifRes = $conn->query("
          SELECT text, created_at 
            FROM notifications 
           WHERE user_id = $buyer_id 
           ORDER BY created_at DESC
        ");
      ?>

      <h2 class="mb-4">Notifications</h2>
      <div class="notifications-list">
        <?php if ($notifRes && $notifRes->num_rows > 0): ?>
          <?php while ($n = $notifRes->fetch_assoc()): ?>
            <div class="notification">
              <?= htmlspecialchars($n['text']) ?>
              <small><?= date('d M Y, H:i', strtotime($n['created_at'])) ?></small>
            </div>
          <?php endwhile; ?>
        <?php else: ?>
          <p class="text-center" style="color:#777;">No notifications at this time.</p>
        <?php endif; ?>
      </div>

    <?php else: ?>
      <?php
        header("Location: buyerdashboard.php?tab=dashboard");
        exit();
      ?>
<?php endif; ?>
  </div>

  <!-- ───────── JS ───────── -->
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      console.log('Buyer dashboard loaded');
    });
  </script>
</body>
</html>