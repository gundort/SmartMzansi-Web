<?php
session_start();
require_once '../Database/db.php';

if ($_SESSION['role'] !== 'admin') {
    header("Location: auth.php");
    exit();
}

// =======================
// 1) Fetch all categories for filtering/dropdowns
// =======================
$categories = $conn->query("
    SELECT id, name
      FROM categories
     ORDER BY name
");

// =======================
// Handle Approve (GET)
// =======================
if (isset($_GET['approve']) && is_numeric($_GET['approve'])) {
    $pid = intval($_GET['approve']);
    $conn->query("UPDATE products SET approved = 1 WHERE id = $pid");
    header("Location: admindashboard.php?tab=pending&msg=approved");
    exit();
}

// =======================
// Handle Reject (POST with reason)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_product'])) {
    $pid    = intval($_POST['reject_product']);
    $reason = $conn->real_escape_string(trim($_POST['reject_reason']));
    $conn->query("
        UPDATE products
           SET approved = -1,
               rejected_reason = '$reason'
         WHERE id = $pid
    ");
    header("Location: admindashboard.php?tab=pending&msg=rejected");
    exit();
}

// =======================
// Handle User Deletion (GET)
// =======================
if (isset($_GET['delete_user']) && is_numeric($_GET['delete_user'])) {
    $uid = intval($_GET['delete_user']);
    $conn->query("DELETE FROM users WHERE id = $uid");
    header("Location: admindashboard.php?tab=users&msg=user_deleted");
    exit();
}

// =======================
// Tab Selection
// =======================
$tab = $_GET['tab'] ?? 'pending';

// =======================
// Fetch Data for Each Tab
// =======================
switch ($tab) {
    case 'pending':
        // Pending: show only unapproved products (approved = 0), join seller email & category
        $pendingProducts = $conn->query("
            SELECT 
              p.*,
              u.email          AS seller_email,
              c.name           AS category_name
            FROM products p
            JOIN users u       ON p.seller_id   = u.id
       LEFT JOIN categories c ON p.category_id = c.id
           WHERE p.approved = 0
        ORDER BY p.created_at DESC
        ");
        break;

    case 'products':
        //
        // “All Products” tab with optional category filter
        //
        // ‑ If ?cat=ID (numeric > 0) → WHERE p.category_id = ID
        // ‑ If ?cat=uncat       → WHERE p.category_id IS NULL
        // ‑ Otherwise (no cat param) → no WHERE → show all
        //
        $filterCat = $_GET['cat'] ?? null;
        $whereClause = "";
        if ($filterCat !== null) {
            if (is_numeric($filterCat) && intval($filterCat) > 0) {
                $catId = intval($filterCat);
                $whereClause = "WHERE p.category_id = $catId";
            } elseif ($filterCat === 'uncat') {
                $whereClause = "WHERE p.category_id IS NULL";
            }
        }

        // Fetch all (joined with seller email and category name)
        $allProducts = $conn->query("
            SELECT 
              p.*,
              u.email          AS seller_email,
              c.name           AS category_name
            FROM products p
            JOIN users u       ON p.seller_id   = u.id
       LEFT JOIN categories c ON p.category_id = c.id
            $whereClause
        ORDER BY p.created_at DESC
        ");
        break;

    case 'users':
        $allUsers = $conn->query("
            SELECT id, email, role, full_name, created_at
              FROM users
             ORDER BY created_at DESC
        ");
        break;

    case 'orders':
        $allOrders = $conn->query("
            SELECT 
              o.id            AS order_id,
              u_b.email       AS buyer_email,
              u_s.email       AS seller_email,
              p.name          AS product_name,
              o.quantity,
              o.total_price,
              o.status,
              o.created_at
            FROM orders o
            JOIN users u_b ON o.buyer_id = u_b.id
            JOIN users u_s ON o.seller_id = u_s.id
            JOIN products p ON o.product_id = p.id
           ORDER BY o.created_at DESC
        ");
        // If admin wants to view the message thread for a given order:
        if (isset($_GET['view'], $_GET['order_id']) 
            && $_GET['view'] === 'messages' 
            && is_numeric($_GET['order_id'])) 
        {
            $viewOrderId = intval($_GET['order_id']);
            $stmt = $conn->prepare("
                SELECT m.*, u.full_name AS sender_name
                  FROM messages m
                  JOIN users u ON u.id = m.sender_id
                 WHERE m.order_id = ?
                 ORDER BY m.timestamp ASC
            ");
            $stmt->bind_param("i", $viewOrderId);
            $stmt->execute();
            $msgResult     = $stmt->get_result();
            $orderMessages = $msgResult->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        break;

    case 'messages':
        //
        // “Messages” tab: list every order_id that has at least one message,
        // show buyer/seller info; then if order_id is selected, fetch that thread.
        //
        // 1) Fetch all distinct order_ids from messages, with buyer + seller emails
        $ordersWithMessages = $conn->query("
            SELECT DISTINCT
              m.order_id,
              u_b.email AS buyer_email,
              u_s.email AS seller_email
            FROM messages m
            JOIN orders o       ON m.order_id = o.id
            JOIN users u_b      ON o.buyer_id  = u_b.id
            JOIN users u_s      ON o.seller_id = u_s.id
           ORDER BY m.order_id DESC
        ");

        // 2) If admin has clicked a particular order_id, fetch that conversation:
        $messagesThread = [];
        $selectedOrder  = $_GET['order_id'] ?? null;
        if ($selectedOrder !== null && is_numeric($selectedOrder)) {
            $selOrderId = intval($selectedOrder);
            $stmt = $conn->prepare("
                SELECT m.*, u.full_name AS sender_name
                  FROM messages m
                  JOIN users u ON u.id = m.sender_id
                 WHERE m.order_id = ?
                 ORDER BY m.timestamp ASC
            ");
            $stmt->bind_param("i", $selOrderId);
            $stmt->execute();
            $res = $stmt->get_result();
            $messagesThread = $res->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }
        break;

    default:
        header("Location: admindashboard.php?tab=pending");
        exit();
}

$msgAlert = '';
if (isset($_GET['msg'])) {
    $alerts = [
        'approved'     => ['text' => 'Product approved successfully.', 'type' => 'success'],
        'rejected'     => ['text' => 'Product rejected.',             'type' => 'warning'],
        'user_deleted' => ['text' => 'User deleted.',                 'type' => 'warning'],
        'order_updated'=> ['text' => 'Order status updated.',         'type' => 'info'],
    ];
    if (array_key_exists($_GET['msg'], $alerts)) {
        $alert = $alerts[$_GET['msg']];
        $msgAlert = "<div class=\"alert alert-{$alert['type']}\">{$alert['text']}</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Smart Mzansi | Admin Dashboard</title>
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
      background-color: #f5f5f5;
    }
    .sidebar {
      background-color: var(--sidebar-dark);
      height: 100vh;
      padding-top: 1rem;
      color: #fff;
      position: fixed;
      width: 240px;
    }
    .sidebar a {
      color: #ddd;
      text-decoration: none;
      display: block;
      padding: 10px 20px;
      margin-bottom: 5px;
    }
    .sidebar a:hover,
    .sidebar a.active {
      background-color: var(--smart-green);
      color: #fff;
    }
    .main-content {
      margin-left: 240px;
      padding: 2rem;
    }
    .rejection-form {
      max-width: 500px;
      margin-bottom: 1rem;
    }
    .message-thread {
      max-height: 300px;
      overflow-y: auto;
    }
  </style>
</head>
<body>

<div class="sidebar">
  <h4 class="text-center mb-4">Admin Panel</h4>
  <a
    href="admindashboard.php?tab=pending"
    class="<?= $tab === 'pending' ? 'active' : '' ?>"
  >
    <i class="fas fa-clock me-2"></i> Pending Approvals
  </a>
  <a
    href="admindashboard.php?tab=products"
    class="<?= $tab === 'products' ? 'active' : '' ?>"
  >
    <i class="fas fa-boxes me-2"></i> All Products
  </a>
  <a
    href="admindashboard.php?tab=users"
    class="<?= $tab === 'users' ? 'active' : '' ?>"
  >
    <i class="fas fa-users me-2"></i> Users
  </a>
  <a
    href="admindashboard.php?tab=orders"
    class="<?= $tab === 'orders' ? 'active' : '' ?>"
  >
    <i class="fas fa-shopping-bag me-2"></i> Orders
  </a>
  <a
    href="admindashboard.php?tab=messages"
    class="<?= $tab === 'messages' ? 'active' : '' ?>"
  >
    <i class="fas fa-envelope me-2"></i> Messages
  </a>
</div>

<div class="main-content">
  <?= $msgAlert ?>

  <!-- =====================
       Pending Approvals Tab
       ===================== -->
  <?php if ($tab === 'pending'): ?>
    <h3>Pending Product Approvals</h3>

    <?php
    if (isset($_GET['confirm_reject']) && is_numeric($_GET['confirm_reject'])):
      $rejId = intval($_GET['confirm_reject']);
      $prStmt = $conn->prepare("SELECT name FROM products WHERE id = ? AND approved = 0");
      $prStmt->bind_param("i", $rejId);
      $prStmt->execute();
      $prStmt->bind_result($prName);
      if ($prStmt->fetch()):
        $prStmt->close();
    ?>
        <div class="card mb-4 p-3 rejection-form">
          <h5>Reject “<?= htmlspecialchars($prName) ?>”</h5>
          <form method="POST">
            <input type="hidden" name="reject_product" value="<?= $rejId ?>">
            <div class="mb-3">
              <label for="reject_reason" class="form-label">Reason for Rejection</label>
              <textarea
                id="reject_reason"
                name="reject_reason"
                class="form-control"
                rows="3"
                required
              ></textarea>
            </div>
            <button type="submit" class="btn btn-danger">Submit Rejection</button>
            <a
              href="admindashboard.php?tab=pending"
              class="btn btn-secondary ms-2"
            >
              Cancel
            </a>
          </form>
        </div>
    <?php
      else:
        $prStmt->close();
      endif;
    endif;
    ?>

    <?php if ($pendingProducts->num_rows > 0): ?>
      <div class="card mb-4 p-3">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>Image</th>
              <th>Name</th>
              <th>Category</th>
              <th>Description</th>
              <th>Price (ZAR)</th>
              <th>Quantity</th>
              <th>Seller Email</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $pendingProducts->fetch_assoc()): ?>
              <tr>
                <td>
                  <?php if (!empty($row['image'])): ?>
                    <img
                      src="<?= htmlspecialchars($row['image']) ?>"
                      width="60"
                      style="object-fit: cover;"
                      alt="Product Image"
                    >
                  <?php else: ?>
                    <em>No image</em>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td>R<?= number_format($row['price'], 2) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= htmlspecialchars($row['seller_email']) ?></td>
                <td>
                  <a
                    href="admindashboard.php?tab=pending&approve=<?= $row['id'] ?>"
                    class="btn btn-success btn-sm"
                  >
                    Approve
                  </a>
                  <a
                    href="admindashboard.php?tab=pending&confirm_reject=<?= $row['id'] ?>"
                    class="btn btn-danger btn-sm"
                  >
                    Reject
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info">No pending products at the moment.</div>
    <?php endif; ?>


  <!-- =====================
       All Products Tab
       ===================== -->
  <?php elseif ($tab === 'products'): ?>
    <h3>All Products</h3>

    <!-- Category Filter Dropdown -->
    <div class="mb-3">
      <form method="GET" class="d-flex align-items-center gx-2">
        <input type="hidden" name="tab" value="products">
        <label class="me-2">Filter by category:</label>
        <select name="cat" class="form-select w-auto me-2">
          <option value="">All Categories</option>
          <option value="uncat" <?= (isset($_GET['cat']) && $_GET['cat'] === 'uncat') ? 'selected' : '' ?>>
            Uncategorized
          </option>
          <?php 
            $categories->data_seek(0);
            while ($cat = $categories->fetch_assoc()): 
          ?>
            <option 
              value="<?= $cat['id'] ?>" 
              <?= (isset($_GET['cat']) && $_GET['cat'] == $cat['id']) ? 'selected' : '' ?>
            >
              <?= htmlspecialchars($cat['name']) ?>
            </option>
          <?php endwhile; ?>
        </select>
        <button type="submit" class="btn btn-primary">Apply</button>
      </form>
    </div>

    <?php if ($allProducts->num_rows > 0): ?>
      <div class="card mb-4 p-3">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Image</th>
              <th>Seller Email</th>
              <th>Name</th>
              <th>Category</th>
              <th>Price (ZAR)</th>
              <th>Quantity</th>
              <th>Approved?</th>
              <th>Created At</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $allProducts->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td>
                  <?php if (!empty($row['image'])): ?>
                    <img
                      src="<?= htmlspecialchars($row['image']) ?>"
                      width="40"
                      style="object-fit: cover;"
                      alt="Product Image"
                    >
                  <?php else: ?>
                    <em>No image</em>
                  <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($row['seller_email']) ?></td>
                <td><?= htmlspecialchars($row['name']) ?></td>
                <td><?= htmlspecialchars($row['category_name'] ?? 'Uncategorized') ?></td>
                <td>R<?= number_format($row['price'], 2) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td>
                  <?php if ($row['approved'] == 1): ?>
                    <span class="badge bg-success">Yes</span>
                  <?php elseif ($row['approved'] == -1): ?>
                    <span class="badge bg-danger">Rejected</span>
                  <?php else: ?>
                    <span class="badge bg-warning text-dark">No</span>
                  <?php endif; ?>
                </td>
                <td><?= $row['created_at'] ?></td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info">No products found.</div>
    <?php endif; ?>


  <!-- =====================
       Users Tab
       ===================== -->
  <?php elseif ($tab === 'users'): ?>
    <h3>All Users</h3>
    <?php if ($allUsers->num_rows > 0): ?>
      <div class="card mb-4 p-3">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>ID</th>
              <th>Email</th>
              <th>Role</th>
              <th>Full Name</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $allUsers->fetch_assoc()): ?>
              <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars(ucfirst($row['role'])) ?></td>
                <td><?= htmlspecialchars($row['full_name']) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td>
                  <a
                    href="admindashboard.php?tab=users&delete_user=<?= $row['id'] ?>"
                    class="btn btn-sm btn-danger"
                    onclick="return confirm('Are you sure you want to delete this user?')"
                  >
                    Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    <?php else: ?>
      <div class="alert alert-info">No users found.</div>
    <?php endif; ?>


  <!-- =====================
       Orders Tab
       ===================== -->
  <?php elseif ($tab === 'orders'): ?>
    <h3>All Orders</h3>
    <?php if ($allOrders->num_rows > 0): ?>
      <div class="card mb-4 p-3">
        <table class="table table-bordered table-hover">
          <thead class="table-dark">
            <tr>
              <th>Order #</th>
              <th>Buyer Email</th>
              <th>Seller Email</th>
              <th>Product Name</th>
              <th>Quantity</th>
              <th>Total (ZAR)</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($row = $allOrders->fetch_assoc()): ?>
              <tr>
                <td><?= $row['order_id'] ?></td>
                <td><?= htmlspecialchars($row['buyer_email']) ?></td>
                <td><?= htmlspecialchars($row['seller_email']) ?></td>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td>R<?= number_format($row['total_price'], 2) ?></td>
                <td><?= ucfirst($row['status']) ?></td>
                <td><?= $row['created_at'] ?></td>
                <td>
                  <a
                    href="admindashboard.php?tab=orders&view=messages&order_id=<?= $row['order_id'] ?>"
                    class="btn btn-sm btn-outline-primary"
                  >
                    View Messages
                  </a>
                </td>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <?php if (isset($orderMessages)): ?>
        <div class="card p-3 mb-4">
          <h5>Messages for Order #<?= htmlspecialchars($viewOrderId) ?></h5>
          <div class="border rounded p-2 message-thread mb-3">
            <?php if (!empty($orderMessages)): ?>
              <?php foreach ($orderMessages as $msg): ?>
                <div class="mb-2">
                  <strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong>
                  <span><?= htmlspecialchars($msg['message']) ?></span><br>
                  <small class="text-muted"><?= $msg['timestamp'] ?></small>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="text-muted">No messages for this order.</p>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="alert alert-info">No orders found.</div>
    <?php endif; ?>


  <!-- =====================
       Messages Tab
       ===================== -->
  <?php elseif ($tab === 'messages'): ?>
    <h3>All Message Threads</h3>

    <div class="row">
      <!-- Sidebar: list of all orders that have messages -->
      <div class="col-md-4">
        <div class="card p-3">
          <h5>Orders with Messages</h5>
          <ul class="list-group">
            <?php if ($ordersWithMessages->num_rows > 0): ?>
              <?php while ($om = $ordersWithMessages->fetch_assoc()): ?>
                <li
                  class="list-group-item d-flex justify-content-between align-items-center 
                         <?= (isset($selectedOrder) && intval($selectedOrder) === $om['order_id']) 
                              ? 'active text-white' : '' ?>"
                >
                  <a
                    href="admindashboard.php?tab=messages&order_id=<?= $om['order_id'] ?>"
                    class="<?= (isset($selectedOrder) && intval($selectedOrder) === $om['order_id']) 
                               ? 'text-white' : '' ?>"
                  >
                    Order #<?= $om['order_id'] ?><br>
                    <small>
                      Buyer: <?= htmlspecialchars($om['buyer_email']) ?><br>
                      Seller: <?= htmlspecialchars($om['seller_email']) ?>
                    </small>
                  </a>
                </li>
              <?php endwhile; ?>
            <?php else: ?>
              <li class="list-group-item text-muted">No message threads found.</li>
            <?php endif; ?>
          </ul>
        </div>
      </div>

      <!-- Message Thread Panel -->
      <div class="col-md-8">
        <?php if (isset($selectedOrder) && is_numeric($selectedOrder)): ?>
          <div class="card p-3 mb-3">
            <h5>Conversation for Order #<?= htmlspecialchars($selectedOrder) ?></h5>
            <div
              class="border rounded p-2 mb-3 message-thread"
            >
              <?php if (!empty($messagesThread)): ?>
                <?php foreach ($messagesThread as $msg): ?>
                  <div class="mb-2">
                    <strong><?= htmlspecialchars($msg['sender_name']) ?>:</strong>
                    <span><?= htmlspecialchars($msg['message']) ?></span><br>
                    <small class="text-muted"><?= $msg['timestamp'] ?></small>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p class="text-muted">No messages yet for this order.</p>
              <?php endif; ?>
            </div>
          </div>
        <?php else: ?>
          <p>Select an order from the left to view its messages.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

</body>
</html>