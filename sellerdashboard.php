<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if ($_SESSION['role'] !== 'seller') {
    header("Location: auth.php");
    exit();
}

require_once '../Database/db.php';
$seller_id = $_SESSION['user_id'];

// ──────────────────────────────────────────────────
// 1) Fetch all categories so we can populate dropdowns
// ──────────────────────────────────────────────────
$categories = $conn->query("
  SELECT id, name
    FROM categories
   ORDER BY name
");

// ──────────────────────────────────────────────────
// 2) Handle Profile Update (tab=profile, POST)
// ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = $conn->real_escape_string(trim($_POST['full_name']));
    $email     = $conn->real_escape_string(trim($_POST['email']));
    $conn->query("
        UPDATE users
           SET full_name = '$full_name',
               email     = '$email'
         WHERE id = $seller_id
    ");
    header("Location: sellerdashboard.php?tab=profile&msg=profile_updated");
    exit();
}

// ──────────────────────────────────────────────────
// 3) Handle Sending a Message (tab=messages, POST)
// ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $order_id = intval($_POST['order_id']);
    $message  = trim($_POST['message']);

    // Verify this order belongs to this seller
    $stmt = $conn->prepare("SELECT buyer_id FROM orders WHERE id = ? AND seller_id = ?");
    $stmt->bind_param("ii", $order_id, $seller_id);
    $stmt->execute();
    $stmt->bind_result($buyer_id_check);
    if ($stmt->fetch()) {
        $stmt->close();

        // Insert the new message
        $stmt2 = $conn->prepare("
            INSERT INTO messages (order_id, sender_id, receiver_id, message)
            VALUES (?, ?, ?, ?)
        ");
        $stmt2->bind_param("iiis", $order_id, $seller_id, $buyer_id_check, $message);
        $stmt2->execute();
        $stmt2->close();
    }
}

// ─────────────────────────────────────────────────────────
// 4) Handle Product Add / Update / Delete (tab=products)
// ─────────────────────────────────────────────────────────

// 4a) Update existing product (with re‑approval + category_id)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $product_id   = intval($_POST['product_id']);
    $name         = $conn->real_escape_string($_POST['name']);
    $description  = $conn->real_escape_string($_POST['description']);
    $quantity     = intval($_POST['quantity']);
    $price        = floatval($_POST['price']);
    // If a valid category_id was chosen, use it; else NULL
    $selected_cat = (is_numeric($_POST['category_id']) && intval($_POST['category_id'])>0)
                    ? intval($_POST['category_id'])
                    : 'NULL';
    $image        = '';

    // Force re‑approval if editing (approved → 0)
    $reapproveSql = "approved = 0,";

    // Handle new image upload if present
    if (!empty($_FILES['image']['name'])) {
        $filename   = basename($_FILES["image"]["name"]);
        $target_dir = __DIR__ . "/uploads/";
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $filename)) {
            $image = "uploads/" . $conn->real_escape_string($filename);
            $imgSql = "image = '$image',";
        } else {
            $imgSql = "";
        }
    } else {
        $imgSql = "";
    }

    // Update query including category_id and resetting rejected_reason
    $conn->query("
        UPDATE products
           SET name            = '$name',
               description     = '$description',
               quantity        = $quantity,
               price           = $price,
               $imgSql
               $reapproveSql
               rejected_reason = NULL,
               category_id     = " . ($selected_cat === 'NULL' ? "NULL" : $selected_cat) . "
         WHERE id = $product_id
           AND seller_id = $seller_id
    ");

    header("Location: sellerdashboard.php?tab=products&msg=updated");
    exit();
}

// 4b) Add a new product (default approved = 0 + category_id)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $name         = $conn->real_escape_string($_POST['name']);
    $description  = $conn->real_escape_string($_POST['description']);
    $quantity     = intval($_POST['quantity']);
    $price        = floatval($_POST['price']);
    $selected_cat = (is_numeric($_POST['category_id']) && intval($_POST['category_id'])>0)
                    ? intval($_POST['category_id'])
                    : null;
    $image        = '';

    if (!empty($_FILES['image']['name'])) {
        $filename   = basename($_FILES["image"]["name"]);
        $target_dir = __DIR__ . "/uploads/";
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_dir . $filename)) {
            $image = "uploads/" . $conn->real_escape_string($filename);
        }
    }

    // Insert product (with category_id, default approved=0)
    $stmt = $conn->prepare("
        INSERT INTO products 
          (seller_id, name, description, quantity, price, image, approved, category_id)
        VALUES (?, ?, ?, ?, ?, ?, 0, ?)
    ");
    $stmt->bind_param(
        "issidsi",
        $seller_id,
        $name,
        $description,
        $quantity,
        $price,
        $image,
        $selected_cat
    );
    $stmt->execute();
    $stmt->close();

    header("Location: sellerdashboard.php?tab=products&msg=added");
    exit();
}

// 4c) Prevent deletion if the product has existing orders
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $product_id = intval($_GET['delete']);
    // Count how many orders reference this product
    $check = $conn->query("
      SELECT COUNT(*) AS cnt
        FROM orders
       WHERE product_id = $product_id
    ");
    $count = $check->fetch_assoc()['cnt'];
    if ($count == 0) {
        $conn->query("DELETE FROM products WHERE id = $product_id AND seller_id = $seller_id");
        header("Location: sellerdashboard.php?tab=products&msg=deleted");
    } else {
        header("Location: sellerdashboard.php?tab=products&msg=delete_failed");
    }
    exit();
}

// ─────────────────────────────────────────────────────────
// 5) Handle Monthly Goal Submission (tab=statistics, POST)
// ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_goal'])) {
    $new_goal = floatval($_POST['monthly_goal']);
    // Check if a row exists for this seller
    $exists = $conn->query("
      SELECT COUNT(*) AS cnt
        FROM seller_goals
       WHERE seller_id = $seller_id
    ")->fetch_assoc()['cnt'];

    if ($exists > 0) {
        $conn->query("
          UPDATE seller_goals
             SET monthly_goal = $new_goal
           WHERE seller_id = $seller_id
        ");
    } else {
        $stmt = $conn->prepare("
            INSERT INTO seller_goals (seller_id, monthly_goal)
            VALUES (?, ?)
        ");
        $stmt->bind_param("id", $seller_id, $new_goal);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: sellerdashboard.php?tab=statistics&msg=goal_set");
    exit();
}

// ───────────────────────────────────────────────────
// 6) Fetch Data Used By Multiple Tabs (notifications, stats)
// ───────────────────────────────────────────────────

// Count new orders (status='pending')
$newOrdersCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM orders
   WHERE seller_id = $seller_id
     AND status = 'pending'
")->fetch_assoc()['cnt'];

// Count unread messages (is_read = 0, receiver = this seller)
$newMessagesCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM messages m
    JOIN orders o ON m.order_id = o.id
   WHERE o.seller_id   = $seller_id
     AND m.receiver_id = $seller_id
     AND m.is_read     = 0
")->fetch_assoc()['cnt'];

// Count new reviews (is_viewed = 0)
$newReviewsCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM reviews
   WHERE seller_id  = $seller_id
     AND is_viewed  = 0
")->fetch_assoc()['cnt'];

// Low‑stock count (< 10)
$lowStockCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM products
   WHERE seller_id = $seller_id
     AND quantity < 10
")->fetch_assoc()['cnt'];

// Total orders
$totalOrdersCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM orders
   WHERE seller_id = $seller_id
")->fetch_assoc()['cnt'];

// Total messages (all messages for this seller’s orders)
$totalMessagesCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM messages m
    JOIN orders o ON m.order_id = o.id
   WHERE o.seller_id = $seller_id
")->fetch_assoc()['cnt'];

// Total reviews
$totalReviewsCount = $conn->query("
  SELECT COUNT(*) AS cnt
    FROM reviews
   WHERE seller_id = $seller_id
")->fetch_assoc()['cnt'];

// Monthly goal (if set)
$goalRow = $conn->query("
  SELECT monthly_goal
    FROM seller_goals
   WHERE seller_id = $seller_id
")->fetch_assoc();
$monthlyGoal = $goalRow ? floatval($goalRow['monthly_goal']) : 0.0;

// Sum of sales this month
$salesRow = $conn->query("
  SELECT SUM(total_price) AS sum_sales
    FROM orders
   WHERE seller_id = $seller_id
     AND MONTH(created_at) = MONTH(CURRENT_DATE())
     AND YEAR(created_at)  = YEAR(CURRENT_DATE())
")->fetch_assoc();
$sumSales = floatval($salesRow['sum_sales']);

// Goal percentage
$goalPercent = $monthlyGoal > 0 ? round(($sumSales / $monthlyGoal) * 100) : 0;

// ───────────────────────────────────────────────────
// 7) Tab Selection
// ───────────────────────────────────────────────────
$tab = $_GET['tab'] ?? 'dashboard';

// ───────────────────────────────────────────────────
// 8) Fetch Tab‑Specific Data
// ───────────────────────────────────────────────────
if ($tab === 'products') {
    // Join categories so we can show category_name
    $products = $conn->query("
      SELECT p.*, c.name AS category_name
        FROM products p
   LEFT JOIN categories c ON p.category_id = c.id
       WHERE p.seller_id = $seller_id
    ");
}
elseif ($tab === 'messages') {
    $orders = $conn->query("
      SELECT 
        o.id        AS order_id,
        u.full_name AS buyer_name
      FROM orders o
      JOIN users u ON o.buyer_id = u.id
     WHERE o.seller_id = $seller_id
     ORDER BY o.created_at DESC
    ");
    $current_order_id = $_GET['order_id'] ?? null;
    $messages = [];
    if ($current_order_id) {
        // Verify this order indeed belongs to seller
        $stmt_chk = $conn->prepare("
          SELECT COUNT(*) FROM orders
           WHERE id = ? AND seller_id = ?
        ");
        $stmt_chk->bind_param("ii", $current_order_id, $seller_id);
        $stmt_chk->execute();
        $stmt_chk->bind_result($countChk);
        $stmt_chk->fetch();
        $stmt_chk->close();
        if ($countChk > 0) {
            $stmt2 = $conn->prepare("
                SELECT m.*, u.full_name AS sender_name
                  FROM messages m
                  JOIN users u ON u.id = m.sender_id
                 WHERE m.order_id = ?
                 ORDER BY m.timestamp ASC
            ");
            $stmt2->bind_param("i", $current_order_id);
            $stmt2->execute();
            $res      = $stmt2->get_result();
            $messages = $res->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
        } else {
            $messages = []; // Not this seller's order
        }
    }
}
elseif ($tab === 'orders') {
    $sellerOrders = $conn->query("
      SELECT 
        o.id            AS order_id,
        u_b.full_name   AS buyer_name,
        p.name          AS product_name,
        o.quantity,
        o.total_price,
        o.status,
        o.created_at
      FROM orders o
      JOIN users u_b ON o.buyer_id = u_b.id
      JOIN products p ON o.product_id = p.id
     WHERE o.seller_id = $seller_id
     ORDER BY o.created_at DESC
    ");
}
elseif ($tab === 'reviews') {
    $sellerReviews = $conn->query("
      SELECT
        r.id,
        u.full_name    AS buyer_name,
        p.name         AS product_name,
        r.rating,
        r.comment,
        r.created_at
      FROM reviews r
      JOIN users u ON r.buyer_id = u.id
      JOIN products p ON r.product_id = p.id
     WHERE r.seller_id = $seller_id
     ORDER BY r.created_at DESC
    ");
}
elseif ($tab === 'profile') {
    $userStmt = $conn->prepare("
      SELECT email, full_name
        FROM users
       WHERE id = ?
    ");
    $userStmt->bind_param("i", $seller_id);
    $userStmt->execute();
    $userStmt->bind_result($u_email, $u_full_name);
    $userStmt->fetch();
    $userStmt->close();
}

// ───────────────────────────────────────────────────
// 9) Mark messages as read when viewing a thread
// ───────────────────────────────────────────────────
if ($tab === 'messages' && isset($current_order_id) && $current_order_id) {
    $conn->query("
      UPDATE messages m
      JOIN orders o ON m.order_id = o.id
         SET m.is_read = 1
       WHERE o.seller_id   = $seller_id
         AND m.order_id    = $current_order_id
         AND m.receiver_id = $seller_id
    ");
    // Recompute newMessagesCount
    $newMessagesCount = $conn->query("
      SELECT COUNT(*) AS cnt
        FROM messages m
        JOIN orders o ON m.order_id = o.id
       WHERE o.seller_id   = $seller_id
         AND m.receiver_id = $seller_id
         AND m.is_read     = 0
    ")->fetch_assoc()['cnt'];
}

$msgAlert = '';
if (isset($_GET['msg'])) {
    $alerts = [
        'added'           => ['text' => 'Product added successfully.',    'type' => 'success'],
        'updated'         => ['text' => 'Product updated successfully.',  'type' => 'info'],
        'deleted'         => ['text' => 'Product deleted.',                'type' => 'warning'],
        'delete_failed'   => ['text' => 'Cannot delete: active orders exist.', 'type' => 'danger'],
        'profile_updated' => ['text' => 'Profile updated successfully.', 'type' => 'success'],
        'goal_set'        => ['text' => 'Monthly goal updated.',         'type' => 'success'],
    ];
    if (array_key_exists($_GET['msg'], $alerts)) {
        $alert   = $alerts[$_GET['msg']];
        $msgAlert = "<div class=\"alert alert-{$alert['type']}\">{$alert['text']}</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Smart Mzansi | Seller Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
  >
  <style>
    :root {
      --smart-green: #27ae60;
      --sidebar-dark: #1e3b2f;
    }
    body {
      font-family: 'Segoe UI', sans-serif;
      background-color: #f4f6f9;
    }
    .sidebar {
      background-color: var(--sidebar-dark);
      height: 100vh;
      padding-top: 1rem;
      color: #fff;
      position: fixed;
      width: 240px;
    }
    .sidebar .nav-link {
      color: #ddd;
      margin-bottom: 1rem;
      font-size: 16px;
    }
    .sidebar .nav-link:hover,
    .sidebar .nav-link.active {
      color: var(--smart-green);
    }
    .topbar {
      margin-left: 240px;
      background-color: #fff;
      padding: 1rem 2rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 1px solid #ddd;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .topbar .logo {
      font-weight: bold;
      color: var(--smart-green);
      font-size: 24px;
    }
    .topbar .icon-btn {
      margin-left: 15px;
      background: none;
      border: none;
      font-size: 18px;
      color: #333;
      position: relative;
      cursor: pointer;
    }
    .topbar .icon-btn .badge {
      position: absolute;
      top: -5px;
      right: -8px;
      font-size: 10px;
      background: red;
      color: white;
    }
    .main-content {
      margin-left: 240px;
      padding: 2rem;
    }
    .card-stat {
      border-left: 5px solid var(--smart-green);
      box-shadow: 0 0 8px rgba(0,0,0,0.05);
    }
    .alert-low-stock {
      background: #ffe5e5;
      color: #b20000;
    }
    .progress-bar {
      background-color: var(--smart-green);
    }
    .rejection-reason {
      font-size: 0.9rem;
      color: #666;
    }
    .message-thread {
      max-height: 300px;
      overflow-y: auto;
    }
  </style>
</head>
<body>

<div class="sidebar d-flex flex-column">
  <div class="px-4 mb-4">
    <h4>Seller Panel</h4>
  </div>
  <nav class="nav flex-column px-3">
    <a
      href="sellerdashboard.php?tab=products"
      class="nav-link <?= $tab === 'products' ? 'active' : '' ?>"
    >
      <i class="fas fa-box me-2"></i> Products
    </a>
    <a
      href="sellerdashboard.php?tab=orders"
      class="nav-link <?= $tab === 'orders' ? 'active' : '' ?>"
    >
      <i class="fas fa-shopping-bag me-2"></i> Orders
    </a>
    <a
      href="sellerdashboard.php?tab=reviews"
      class="nav-link <?= $tab === 'reviews' ? 'active' : '' ?>"
    >
      <i class="fas fa-star me-2"></i> Reviews
    </a>
    <a
      href="sellerdashboard.php?tab=statistics"
      class="nav-link <?= $tab === 'statistics' ? 'active' : '' ?>"
    >
      <i class="fas fa-chart-line me-2"></i> Statistics
    </a>
  </nav>
</div>

<div class="topbar">
  <div class="logo">Smart Mzansi</div>
  <div class="d-flex align-items-center">
    <!-- Notifications Icon -->
    <button
      class="icon-btn"
      title="Notifications"
      onclick="window.location.href='sellerdashboard.php?tab=notifications'"
    >
      <i class="fas fa-bell"></i>
      <?php if (($newOrdersCount + $newMessagesCount + $newReviewsCount) > 0): ?>
        <span class="badge rounded-pill"><?= $newOrdersCount + $newMessagesCount + $newReviewsCount ?></span>
      <?php endif; ?>
    </button>

    <!-- Profile Icon -->
    <button
      class="icon-btn"
      title="Profile"
      onclick="window.location.href='sellerdashboard.php?tab=profile'"
    >
      <i class="fas fa-user-circle"></i>
    </button>
  </div>
</div>

<div class="main-content">
  <?= $msgAlert ?>

  <!-- ======================
       Products Tab
       ====================== -->
  <?php if ($tab === 'products'): ?>

    <?php
    if (isset($_GET['msg'])) {
      $alerts2 = [
        'added'         => 'Product added successfully.',
        'updated'       => 'Product updated successfully.',
        'deleted'       => 'Product deleted.',
        'delete_failed' => 'Cannot delete: active orders exist.',
      ];
      $alert_type2 = in_array($_GET['msg'], ['deleted','delete_failed']) ? 'warning' : 'success';
      if (array_key_exists($_GET['msg'], $alerts2)) {
        echo "<div class='alert alert-$alert_type2'>{$alerts2[$_GET['msg']]}</div>";
      }
    }
    ?>

    <!-- Add New Product Form -->
    <div class="card mb-4 p-4">
      <h5 class="mb-3">Add a New Product</h5>
      <form method="POST" enctype="multipart/form-data">
        <div class="row">
          <div class="col-md-6 mb-3">
            <label>Product Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-6 mb-3">
            <label>Category</label>
            <select name="category_id" class="form-select">
              <option value="">— Select Category —</option>
              <?php 
                $categories->data_seek(0);
                while ($cat = $categories->fetch_assoc()): 
              ?>
                <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="mb-3">
          <label>Description</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
        </div>
        <div class="row">
          <div class="col-md-4 mb-3">
            <label>Quantity</label>
            <input type="number" name="quantity" class="form-control" required min="1">
          </div>
          <div class="col-md-4 mb-3">
            <label>Price (ZAR)</label>
            <input type="number" step="0.01" name="price" class="form-control" required>
          </div>
          <div class="col-md-4 mb-3">
            <label>Product Image</label>
            <input type="file" name="image" accept="image/*" class="form-control">
          </div>
        </div>
        <button type="submit" name="add_product" class="btn btn-success">Add Product</button>
      </form>
    </div>

    <!-- Edit Product Form -->
    <?php if (isset($_GET['edit'])):
      $edit_id     = intval($_GET['edit']);
      $edit_result = $conn->query("
        SELECT p.*, c.name AS category_name
          FROM products p
     LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.id = $edit_id
           AND p.seller_id = $seller_id
      ");
      if ($edit_result->num_rows > 0):
        $edit_product = $edit_result->fetch_assoc();
        $categories->data_seek(0);
    ?>
      <div class="card mt-4 p-4 bg-light">
        <h5>Edit Product</h5>
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="product_id" value="<?= $edit_product['id'] ?>">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Product Name</label>
              <input
                type="text"
                name="name"
                class="form-control"
                required
                value="<?= htmlspecialchars($edit_product['name']) ?>"
              >
            </div>
            <div class="col-md-6 mb-3">
              <label>Category</label>
              <select name="category_id" class="form-select">
                <option value="">— Select Category —</option>
                <?php while ($cat = $categories->fetch_assoc()): ?>
                  <option 
                    value="<?= $cat['id'] ?>"
                    <?= ($cat['name'] === $edit_product['category_name']) ? 'selected' : '' ?>
                  >
                    <?= htmlspecialchars($cat['name']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label>Description</label>
            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($edit_product['description']) ?></textarea>
          </div>
          <div class="row">
            <div class="col-md-4 mb-3">
              <label>Quantity</label>
              <input
                type="number"
                name="quantity"
                class="form-control"
                required
                min="1"
                value="<?= $edit_product['quantity'] ?>"
              >
            </div>
            <div class="col-md-4 mb-3">
              <label>Price (ZAR)</label>
              <input
                type="number"
                step="0.01"
                name="price"
                class="form-control"
                required
                value="<?= $edit_product['price'] ?>"
              >
            </div>
            <div class="col-md-4 mb-3">
              <label>Change Image</label>
              <input type="file" name="image" accept="image/*" class="form-control">
              <?php if ($edit_product['image']): ?>
                <small>Current: <strong><?= htmlspecialchars($edit_product['image']) ?></strong></small>
              <?php endif; ?>
            </div>
          </div>
          <button type="submit" name="update_product" class="btn btn-warning">Update Product</button>
        </form>
      </div>
    <?php endif; endif; ?>

    <!-- List of Existing Products -->
    <div class="card p-4">
      <h5>Your Products</h5>
      <?php if (isset($products) && $products->num_rows > 0): ?>
        <table class="table table-bordered mt-3">
          <thead class="table-dark">
            <tr>
              <th>Image</th>
              <th>Name</th>
              <th>Category</th>
              <th>Description</th>
              <th>Qty</th>
              <th>Price (ZAR)</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $products->fetch_assoc()): ?>
              <tr class="<?= ($row['approved'] != 1) ? 'table-secondary opacity-75' : '' ?>">
                <td>
                  <?php if ($row['image']): ?>
                    <img
                      src="<?= htmlspecialchars($row['image']) ?>"
                      width="60" height="60"
                      style="object-fit: cover;"
                      alt="Product Image"
                    >
                  <?php else: ?>
                    <span class="text-muted">No image</span>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td>R<?= number_format($row['price'], 2) ?></td>
                <td>
                  <?php if ($row['approved'] == 1): ?>
                    <span class="badge bg-success">Approved</span>
                  <?php elseif ($row['approved'] == -1): ?>
                    <span class="badge bg-danger">Rejected</span><br>
                    <small class="rejection-reason"><?= htmlspecialchars($row['rejected_reason']) ?></small>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">Pending</span>
                  <?php endif; ?>
                </td>
                <td>
                  <a
                    href="sellerdashboard.php?tab=products&edit=<?= $row['id'] ?>"
                    class="btn btn-sm btn-primary"
                  >
                    Edit
                  </a>
                  <a
                    href="sellerdashboard.php?tab=products&delete=<?= $row['id'] ?>"
                    class="btn btn-sm btn-danger"
                  >
                    Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="text-muted">No products added yet.</p>
      <?php endif; ?>
    </div>

  <!-- ======================
       Orders Tab
       ====================== -->
  <?php elseif ($tab === 'orders'): ?>
    <h3>Your Orders</h3>
    <?php if (isset($sellerOrders) && $sellerOrders->num_rows > 0): ?>
      <div class="card mb-4 p-3">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>Order #</th>
              <th>Buyer Name</th>
              <th>Product Name</th>
              <th>Quantity</th>
              <th>Total (ZAR)</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($ord = $sellerOrders->fetch_assoc()): ?>
              <tr>
                <td><?= $ord['order_id'] ?></td>
                <td><?= htmlspecialchars($ord['buyer_name']) ?></td>
                <td><?= htmlspecialchars($ord['product_name']) ?></td>
                <td><?= $ord['quantity'] ?></td>
                <td>R<?= number_format($ord['total_price'], 2) ?></td>
                <td><?= ucfirst($ord['status']) ?></td>
                <td><?= $ord['created_at'] ?></td>
                <td>
                  <a
                    href="sellerdashboard.php?tab=messages&order_id=<?= $ord['order_id'] ?>"
                    class="btn btn-sm btn-outline-primary"
                  >
                    Message Buyer
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info">You have no orders yet.</div>
    <?php endif; ?>

  <!-- ======================
       Reviews Tab
       ====================== -->
  <?php elseif ($tab === 'reviews'): ?>
    <h3>Product Reviews</h3>
    <?php if (isset($sellerReviews) && $sellerReviews->num_rows > 0): ?>
      <div class="card mb-4 p-3">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>Review #</th>
              <th>Buyer Name</th>
              <th>Product Name</th>
              <th>Rating</th>
              <th>Comment</th>
              <th>Created At</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($rv = $sellerReviews->fetch_assoc()): ?>
              <tr>
                <td><?= $rv['id'] ?></td>
                <td><?= htmlspecialchars($rv['buyer_name']) ?></td>
                <td><?= htmlspecialchars($rv['product_name']) ?></td>
                <td><?= $rv['rating'] ?>/5</td>
                <td><?= htmlspecialchars($rv['comment']) ?></td>
                <td><?= $rv['created_at'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info">No reviews yet for your products.</div>
    <?php endif; ?>

  <!-- ======================
       Statistics Tab
       ====================== -->
  <?php elseif ($tab === 'statistics'): ?>
    <h3>Statistics &amp; Goals</h3>

    <!-- Summary Cards -->
    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card card-stat p-3">
          <h6>Total Orders</h6>
          <h3><?= $totalOrdersCount ?></h3>
        </div>
      </div>
      <div class="col-md=3">
        <div class="card card-stat p-3">
          <h6>Total Messages</h6>
          <h3><?= $totalMessagesCount ?></h3>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-stat p-3">
          <h6>Total Reviews</h6>
          <h3><?= $totalReviewsCount ?></h3>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card card-stat p-3">
          <h6>Goal Progress</h6>
          <div class="progress mt-1">
            <div
              class="progress-bar"
              role="progressbar"
              style="width: <?= min($goalPercent, 100) ?>%"`
            >
              <?= $goalPercent ?>%
            </div>
          </div>
          <small>R<?= number_format($sumSales, 2) ?> / R<?= number_format($monthlyGoal, 2) ?></small>
        </div>
      </div>
    </div>

    <!-- Form to Set / Update Monthly Goal -->
    <div class="card mb-4 p-4">
      <h5>Set Monthly Sales Goal</h5>
      <form method="POST" class="row g-3 align-items-end">
        <div class="col-auto">
          <label class="form-label">Monthly Goal (ZAR)</label>
          <input
            type="number"
            name="monthly_goal"
            class="form-control"
            step="0.01"
            min="0"
            required
            value="<?= htmlspecialchars(number_format($monthlyGoal, 2, '.', '')) ?>"
          >
        </div>
        <div class="col-auto">
          <button type="submit" name="set_goal" class="btn btn-primary">Save Goal</button>
        </div>
      </form>
    </div>

    <!-- Low‑Stock Products List (Qty < 10) -->
    <div class="card mb-4 p-4">
      <h5>Low‑Stock Products (Qty < 10)</h5>
      <?php
        $lowStockProducts = $conn->query("
          SELECT id, name, quantity
            FROM products
           WHERE seller_id = $seller_id
             AND quantity < 10
           ORDER BY quantity ASC
        ");
      ?>
      <?php if ($lowStockProducts->num_rows > 0): ?>
        <table class="table table-sm table-bordered mt-3">
          <thead>
            <tr>
              <th>Product ID</th>
              <th>Name</th>
              <th>Quantity</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($prod = $lowStockProducts->fetch_assoc()): ?>
              <tr class="<?= $prod['quantity'] < 5 ? 'table-danger' : '' ?>">
                <td><?= $prod['id'] ?></td>
                <td><?= htmlspecialchars($prod['name']) ?></td>
                <td><?= $prod['quantity'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
        <small class="text-muted">
          <em>Rows highlighted in red have fewer than 5 items left.</em>
        </small>
      <?php else: ?>
        <p class="text-muted">No products are low on stock.</p>
      <?php endif; ?>
    </div>

  <!-- ======================
       Profile Tab
       ====================== -->
  <?php elseif ($tab === 'profile'): ?>
    <h3>My Profile</h3>
    <?php
      if (isset($_GET['msg']) && $_GET['msg'] === 'profile_updated') {
        echo "<div class='alert alert-success'>Profile updated successfully.</div>";
      }
    ?>
    <div class="card mb-4 p-3">
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input
            type="text"
            name="full_name"
            class="form-control"
            required
            value="<?= htmlspecialchars($u_full_name ?? '') ?>"
          >
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input
            type="email"
            name="email"
            class="form-control"
            required
            value="<?= htmlspecialchars($u_email ?? '') ?>"
          >
        </div>
        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
      </form>
    </div>

  <!-- ======================
       Notifications Tab
       ====================== -->
  <?php elseif ($tab === 'notifications'): ?>
    <h3>Notifications</h3>
    <div class="card mb-4 p-3">
      <ul class="list-group">
        <!-- New Orders -->
        <?php if ($newOrdersCount > 0): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $newOrdersCount ?> new order<?= $newOrdersCount > 1 ? 's' : '' ?> pending
            <a href="sellerdashboard.php?tab=orders" class="btn btn-sm btn-outline-primary">View Orders</a>
          </li>
        <?php endif; ?>

        <!-- New Messages -->
        <?php if ($newMessagesCount > 0): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $newMessagesCount ?> new message<?= $newMessagesCount > 1 ? 's' : '' ?>
            <a href="sellerdashboard.php?tab=messages" class="btn btn-sm btn-outline-primary">View Messages</a>
          </li>
        <?php endif; ?>

        <!-- New Reviews -->
        <?php if ($newReviewsCount > 0): ?>
          <li class="list-group-item d-flex justify-content-between align-items-center">
            <?= $newReviewsCount ?> new review<?= $newReviewsCount > 1 ? 's' : '' ?>
            <a href="sellerdashboard.php?tab=reviews" class="btn btn-sm btn-outline-primary">View Reviews</a>
          </li>
        <?php endif; ?>

        <!-- Goal Milestones -->
        <?php if ($goalPercent >= 25 && $goalPercent < 50): ?>
          <li class="list-group-item">
            > 25% of monthly goal reached!
          </li>
        <?php elseif ($goalPercent >= 50 && $goalPercent < 75): ?>
          <li class="list-group-item">
            > 50% of monthly goal reached!
          </li>
        <?php elseif ($goalPercent >= 75 && $goalPercent < 100): ?>
          <li class="list-group-item">
            > 75% of monthly goal reached!
          </li>
        <?php elseif ($goalPercent >= 100): ?>
          <li class="list-group-item">
            Monthly goal met! Congratulations!
          </li>
        <?php endif; ?>

        <!-- Low Stock Notice -->
        <?php if ($lowStockCount > 0): ?>
          <li class="list-group-item">
            <?= $lowStockCount ?> product<?= $lowStockCount > 1 ? 's' : '' ?> low in stock.
            <a href="sellerdashboard.php?tab=products" class="ms-2">Restock</a>
          </li>
        <?php endif; ?>

        <?php if ($newOrdersCount + $newMessagesCount + $newReviewsCount + $lowStockCount + ($goalPercent >= 25 ? 1 : 0) == 0): ?>
          <li class="list-group-item text-muted">No new notifications.</li>
        <?php endif; ?>
      </ul>
    </div>
  <?php endif; ?>
</div>

<script>
  document.addEventListener("DOMContentLoaded", () => {
    const counters = document.querySelectorAll(".card-stat h3");
    counters.forEach(counter => {
      const updateCount = () => {
        const target = +counter.textContent;
        let count = 0;
        const increment = target / 60;
        const step = () => {
          count += increment;
          if (count < target) {
            counter.textContent = Math.ceil(count);
            requestAnimationFrame(step);
          } else {
            counter.textContent = target;
          }
        };
        requestAnimationFrame(step);
      };
      updateCount();
    });
  });
</script>

</body>
</html>
