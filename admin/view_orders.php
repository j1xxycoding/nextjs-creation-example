<?php
require_once '../includes/config.php';

// Require admin login
requireAdmin();

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_order'])) {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        if ($order_id) {
            $stmt = $connection->prepare("UPDATE orders SET status = 'confirmed' WHERE id = :id");
            $stmt->bindValue(':id', $order_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                $success = "Order #$order_id has been confirmed.";
            } else {
                $error = "Failed to confirm order.";
            }
        }
    } elseif (isset($_POST['delete_order'])) {
        $order_id = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
        if ($order_id) {
            $stmt = $connection->prepare("DELETE FROM orders WHERE id = :id");
            $stmt->bindValue(':id', $order_id, SQLITE3_INTEGER);
            if ($stmt->execute()) {
                $success = "Order #$order_id has been deleted.";
            } else {
                $error = "Failed to delete order.";
            }
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$sort_by = $_GET['sort'] ?? 'newest';

// Build query based on filters
$query = "
    SELECT 
        o.*,
        p.name as product_name,
        p.price_dzd
    FROM orders o
    JOIN products p ON o.product_id = p.id
";

if ($status_filter !== 'all') {
    $query .= " WHERE o.status = '" . SQLite3::escapeString($status_filter) . "'";
}

$query .= " ORDER BY o.id " . ($sort_by === 'oldest' ? 'ASC' : 'DESC');

$orders = $connection->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Orders - Shada Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/style.css">
    <style>
        .filters {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .orders-table {
            width: 100%;
            background: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .orders-table th,
        .orders-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        .orders-table th {
            background: #f8f9fa;
            font-weight: 500;
        }

        .orders-table tr:last-child td {
            border-bottom: none;
        }

        .status {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .action-buttons button {
            padding: 0.25rem 0.5rem;
            font-size: 0.9rem;
        }

        .confirm-btn {
            background: #28a745;
        }

        .delete-btn {
            background: #dc3545;
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

        @media (max-width: 768px) {
            .orders-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Manage Orders</h1>
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

        <div class="filters">
            <div class="filter-group">
                <label for="status">Status:</label>
                <select id="status" class="form-control" onchange="updateFilters()">
                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                </select>
            </div>

            <div class="filter-group">
                <label for="sort">Sort:</label>
                <select id="sort" class="form-control" onchange="updateFilters()">
                    <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="oldest" <?php echo $sort_by === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                </select>
            </div>
        </div>

        <table class="orders-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Product</th>
                    <th>Size</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $hasOrders = false;
                while($order = $orders->fetchArray(SQLITE3_ASSOC)):
                    $hasOrders = true;
                ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td>
                            <?php echo h($order['customer_name']); ?><br>
                            <small><?php echo h($order['phone']); ?></small><br>
                            <small><?php echo h($order['address']); ?></small>
                        </td>
                        <td><?php echo h($order['product_name']); ?></td>
                        <td><?php echo h($order['taille']); ?></td>
                        <td>DZD <?php echo number_format($order['price_dzd'], 2); ?></td>
                        <td>
                            <span class="status status-<?php echo $order['status']; ?>">
                                <?php echo ucfirst($order['status']); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <?php if ($order['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <button type="submit" name="confirm_order" class="btn confirm-btn">
                                            <i class="fas fa-check"></i> Confirm
                                        </button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete(this);">
                                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                    <button type="submit" name="delete_order" class="btn delete-btn">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>

                <?php if (!$hasOrders): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">No orders found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h3>Confirm Deletion</h3>
            <p>Are you sure you want to delete this order? This action cannot be undone.</p>
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
        function updateFilters() {
            const status = document.getElementById('status').value;
            const sort = document.getElementById('sort').value;
            window.location.href = `view_orders.php?status=${status}&sort=${sort}`;
        }

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
