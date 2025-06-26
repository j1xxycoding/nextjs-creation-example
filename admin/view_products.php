<?php
require_once '../includes/config.php';

// Require admin login
requireAdmin();

// Handle product deletion
if (isset($_POST['delete_product'])) {
    $product_id = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
    
    if ($product_id) {
        // Get product images before deletion
        $stmt = $connection->prepare("
            SELECT main_image FROM products WHERE id = :id
            UNION ALL
            SELECT image_path FROM product_images WHERE product_id = :id
        ");
        $stmt->bindValue(':id', $product_id, SQLITE3_INTEGER);
        $images = $stmt->execute();

        // Start transaction
        $connection->exec('BEGIN');

        try {
            // Delete product (will cascade to product_images)
            $stmt = $connection->prepare("DELETE FROM products WHERE id = :id");
            $stmt->bindValue(':id', $product_id, SQLITE3_INTEGER);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete product.");
            }

            // Delete image files
            while ($image = $images->fetchArray(SQLITE3_ASSOC)) {
                $image_path = "../uploads/products/" . $image['main_image'];
                if (file_exists($image_path)) {
                    unlink($image_path);
                }
            }

            $connection->exec('COMMIT');
            $success = "Product deleted successfully.";
        } catch (Exception $e) {
            $connection->exec('ROLLBACK');
            $error = $e->getMessage();
        }
    }
}

// Fetch all products with their image count and order count
$query = "
    SELECT 
        p.*,
        (SELECT COUNT(*) FROM product_images WHERE product_id = p.id) as gallery_count,
        (SELECT COUNT(*) FROM orders WHERE product_id = p.id) as order_count
    FROM products p
    ORDER BY p.id DESC
";
$products = $connection->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Products - Shada Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 2rem;
            margin: 2rem 0;
        }

        .product-card {
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .product-info {
            padding: 1.5rem;
        }

        .product-info h3 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
        }

        .product-meta {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 1rem;
        }

        .product-meta span {
            display: block;
            margin-bottom: 0.25rem;
        }

        .delete-form {
            margin-top: 1rem;
        }

        .delete-btn {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s ease;
        }

        .delete-btn:hover {
            background: #c82333;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background: #fff;
            padding: 2rem;
            border-radius: var(--border-radius);
            max-width: 400px;
            width: 90%;
            text-align: center;
        }

        .modal-buttons {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Manage Products</h1>
        </div>
    </header>

    <nav class="admin-nav">
        <div class="container">
            <ul>
                <li><a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
                <li><a href="add_product.php"><i class="fas fa-plus"></i> Add Product</a></li>
                <li><a href="view_products.php"><i class="fas fa-box"></i> Products</a></li>
                <li><a href="view_orders.php"><i class="fas fa-shopping-cart"></i> Orders</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <main class="container">
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?php echo h($success); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <?php echo h($error); ?>
            </div>
        <?php endif; ?>

        <div class="products-grid">
            <?php 
            $hasProducts = false;
            while($product = $products->fetchArray(SQLITE3_ASSOC)):
                $hasProducts = true;
            ?>
                <div class="product-card">
                    <img src="../uploads/products/<?php echo h($product['main_image']); ?>" 
                         alt="<?php echo h($product['name']); ?>"
                         class="product-image">
                    
                    <div class="product-info">
                        <h3><?php echo h($product['name']); ?></h3>
                        
                        <div class="product-meta">
                            <span>Price: DZD <?php echo number_format($product['price_dzd'], 2); ?></span>
                            <span>Sizes: <?php echo h($product['tailles']); ?></span>
                            <span>Gallery Images: <?php echo $product['gallery_count']; ?></span>
                            <span>Orders: <?php echo $product['order_count']; ?></span>
                        </div>

                        <form method="POST" class="delete-form" onsubmit="return confirmDelete(this);">
                            <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                            <button type="submit" name="delete_product" class="delete-btn">
                                <i class="fas fa-trash"></i> Delete Product
                            </button>
                        </form>
                    </div>
                </div>
            <?php endwhile; ?>

            <?php if (!$hasProducts): ?>
                <p>No products found.</p>
            <?php endif; ?>
        </div>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this product? This action cannot be undone.</p>
            <div class="modal-buttons">
                <button id="confirmDelete" class="btn">Yes, Delete</button>
                <button id="cancelDelete" class="btn" style="background: #6c757d;">Cancel</button>
            </div>
        </div>
    </div>

    <footer>
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> Shada Admin Panel</p>
        </div>
    </footer>

    <script>
        function confirmDelete(form) {
            const modal = document.getElementById('deleteModal');
            const confirmBtn = document.getElementById('confirmDelete');
            const cancelBtn = document.getElementById('cancelDelete');

            modal.style.display = 'flex';

            return new Promise((resolve) => {
                confirmBtn.onclick = () => {
                    modal.style.display = 'none';
                    resolve(true);
                };

                cancelBtn.onclick = () => {
                    modal.style.display = 'none';
                    resolve(false);
                };
            }).then((confirmed) => {
                if (confirmed) {
                    form.submit();
                }
                return false;
            });
        }
    </script>
</body>
</html>
