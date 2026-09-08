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
//  2) HANDLE “ADD TO CART” & “ADD TO WISHLIST” POSTS
// ────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $pid = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;

    if ($pid > 0 && $_POST['action'] === 'add_to_cart') {
        // If already in cart, increment; otherwise, insert.
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
            $newQty = $existingQty + max(1, (int)$_POST['quantity']);
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
              VALUES (?, ?, ?, NOW())
            ");
            $qtyVal = max(1, (int)$_POST['quantity']);
            $ins->bind_param("iii", $buyer_id, $pid, $qtyVal);
            $ins->execute();
            $ins->close();
        }
        header("Location: product.php?id=$pid&msg=added_to_cart");
        exit();
    }

    if ($pid > 0 && $_POST['action'] === 'add_to_wishlist') {
        $stmt = $conn->prepare("
          INSERT IGNORE INTO wishlists 
            (buyer_id, product_id, added_at) 
          VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $buyer_id, $pid);
        $stmt->execute();
        $stmt->close();
        header("Location: product.php?id=$pid&msg=added_to_wishlist");
        exit();
    }
}

// ────────────────────────────────────────────────────────────────────────────
//  3) FETCH PRODUCT DETAILS (no “Sold by:”)
// ────────────────────────────────────────────────────────────────────────────
if (!isset($_GET['id'])) {
    echo "<p style='color:red;'>No product specified.</p>";
    exit();
}

$pid = (int)$_GET['id'];
$productRes = $conn->prepare("
  SELECT 
    p.name,
    p.description,
    p.price,
    p.image
  FROM products p
  WHERE p.id = ? 
    AND p.approved = 1
");
$productRes->bind_param("i", $pid);
$productRes->execute();
$productRes->bind_result($name, $description, $price, $image);
if (!$productRes->fetch()) {
    $productRes->close();
    echo "<p style='color:red;'>Product not found or not approved.</p>";
    exit();
}
$productRes->close();

$inWishlist = (bool)$conn->query("
  SELECT 1 
    FROM wishlists 
   WHERE buyer_id = $buyer_id 
     AND product_id = $pid
")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <title><?= htmlspecialchars($name) ?> | Smart Mzansi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>

  <!-- Font Awesome for icons -->
  <link
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
    rel="stylesheet"
  />

  <!-- Embedded CSS just for product.php -->
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

    /* Back link */
    .back-link {
      margin-bottom:1.5rem;
      display:inline-flex;
      align-items:center;
      gap:0.3rem;
      font-size:0.95rem;
    }
    .back-link i { font-size:0.9rem; }

    /* Alert */
    .alert {
      margin-bottom:1rem;
      padding:0.75rem 1rem;
      border-radius:5px;
      font-size:0.95rem;
      background-color:#d4edda;
      color:#155724;
    }

    /* Product Detail wrapper */
    .product-detail {
      background:#fff;
      border:1px solid #ddd;
      border-radius:8px;
      overflow:hidden;
      box-shadow:0 2px 6px rgba(0,0,0,0.05);
    }

    /* Top: full‑width product image */
    .product-image img {
      width:100%;
      height:auto;
      display:block;
    }

    /* Info section */
    .product-info {
      padding:1.5rem;
      display:flex;
      flex-direction:column;
      gap:1rem;
    }
    .product-info h1 {
      font-family:'Georgia', serif;
      font-size:1.8rem;
      color:#2e7d32;
    }
    .product-info .price {
      font-size:1.3rem;
      color:#444;
      font-weight:600;
    }
    .product-info .description {
      color:#555;
      font-size:0.95rem;
      white-space:pre-wrap;
    }

    /* Quantity input */
    .form-group {
      margin-top:0.5rem;
    }
    .form-group label {
      display:block;
      font-size:0.9rem;
      margin-bottom:0.3rem;
    }
    .form-group input[type="number"] {
      width:60px;
      padding:0.4rem;
      border:1px solid #ccc;
      border-radius:4px;
      font-size:0.9rem;
    }

    /* Bottom button row */
    .btn-row {
      display:flex;
      gap:1rem;
      margin-top:1.5rem;
    }
    .btn-row button {
      flex:1;
      display:flex;
      justify-content:center;
      align-items:center;
      gap:0.5rem;
      padding:0.75rem;
      border-radius:6px;
      font-size:1rem;
      cursor:pointer;
      transition:all 0.3s;
      border:2px solid transparent;
    }

    /* Add to Cart button */
    .btn-cart {
      background-color:#fff;
      border:2px solid #568203;
      color:#568203;
    }
    .btn-cart i {
      font-size:1.1rem;
    }
    .btn-cart:hover {
      background-color:#568203;
      color:#fff;
    }

    /* Add to Wishlist button */
    .btn-wishlist {
      background-color:#fff;
      border:2px solid #e91e63;
      color:#e91e63;
    }
    .btn-wishlist i {
      font-size:1.1rem;
    }
    .btn-wishlist:hover {
      background-color:#e91e63;
      color:#fff;
    }

    /* Small responsive tweak */
    @media (max-width: 500px) {
      .btn-row {
        flex-direction:column;
      }
      .btn-row button {
        width:100%;
      }
    }
  </style>
</head>
<body>
  <div class="container">
    <!-- Back to Shop link -->
    <a href="buyerdashboard.php?tab=products" class="back-link">
      <i class="fas fa-arrow-left"></i>
      Back to Shop
    </a>

    <!-- Flash message if any -->
    <?php if (isset($_GET['msg'])): ?>
      <?php if ($_GET['msg'] === 'added_to_cart'): ?>
        <div class="alert">Added to cart successfully.</div>
      <?php elseif ($_GET['msg'] === 'added_to_wishlist'): ?>
        <div class="alert">Added to wishlist.</div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="product-detail">
      <!-- 1) IMAGE ON TOP -->
      <div class="product-image">
        <?php
          // Expecting $image is just the filename. The file must exist in:
          // register/Dashboards/uploads/
          $imgPath = "uploads/" . $image;
        ?>
        <img src="<?= htmlspecialchars($imgPath) ?>"
             alt="<?= htmlspecialchars($name) ?>" />
      </div>

      <!-- 2) INFO BELOW IMAGE -->
      <div class="product-info">
        <h1><?= htmlspecialchars($name) ?></h1>
        <div class="price">R<?= number_format($price, 2) ?></div>
        <div class="description"><?= nl2br(htmlspecialchars($description)) ?></div>

        <!-- QUANTITY INPUT + BUTTONS -->
        <form method="POST" action="product.php?id=<?= $pid ?>">
          <input type="hidden" name="action" value="add_to_cart"/>
          <input type="hidden" name="product_id" value="<?= $pid ?>"/>

          <div class="form-group">
            <label for="quantity">Quantity:</label>
            <input
              type="number"
              id="quantity"
              name="quantity"
              value="1"
              min="1"
            />
          </div>

          <div class="btn-row">
            <!-- ADD TO CART -->
            <button type="submit" class="btn-cart">
              <i class="fas fa-shopping-cart"></i>
              Add to Cart
            </button>

            <!-- ADD TO WISHLIST -->
            <button
              type="submit"
              formaction="product.php?id=<?= $pid ?>"
              formmethod="POST"
              name="action"
              value="add_to_wishlist"
              class="btn-wishlist"
            >
              <i class="<?= $inWishlist ? 'fas' : 'far' ?> fa-heart"></i>
              <?= $inWishlist ? 'In Wishlist' : 'Add to Wishlist' ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</body>
</html>