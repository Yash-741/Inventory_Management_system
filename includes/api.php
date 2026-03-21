<?php
/**
 * Inventory API backed by SQLite.
 */

require_once __DIR__ . '/sqlite.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : 'get_all_inventory';
}

switch ($action) {
    case 'get_all_inventory':
        handleGetAllInventory();
        break;
    case 'get_inventory_stats':
        handleGetInventoryStats();
        break;
    case 'get_product':
        handleGetProduct(isset($_GET['product_id']) ? (int) $_GET['product_id'] : 0);
        break;
    case 'update_quantity':
        handleUpdateQuantityRequest();
        break;
    case 'add_product':
        handleAddProduct();
        break;
    case 'delete_product':
        handleDeleteProductRequest();
        break;
    default:
        sendErrorResponse('Invalid action parameter.');
        break;
}

function handleGetAllInventory() {
    try {
        $pdo = getSqliteConnection();
        $stmt = $pdo->query("
            SELECT
                p.product_id,
                p.product_name,
                p.sku,
                i.quantity,
                i.min_stock_level,
                i.last_updated
            FROM products p
            INNER JOIN inventory i ON i.product_id = p.product_id
            ORDER BY p.product_name ASC
        ");

        sendSuccessResponse([
            'inventory' => $stmt->fetchAll(),
            'stats' => getInventoryStats(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        sendErrorResponse('Failed to fetch inventory: ' . $e->getMessage());
    }
}

function handleGetInventoryStats() {
    sendSuccessResponse(getInventoryStats());
}

function getInventoryStats() {
    $pdo = getSqliteConnection();
    $row = $pdo->query("
        SELECT
            COUNT(*) AS total_products,
            SUM(CASE WHEN i.quantity > i.min_stock_level AND i.quantity > 0 THEN 1 ELSE 0 END) AS in_stock,
            SUM(CASE WHEN i.quantity <= i.min_stock_level AND i.quantity > 0 THEN 1 ELSE 0 END) AS low_stock,
            SUM(CASE WHEN i.quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock
        FROM products p
        INNER JOIN inventory i ON i.product_id = p.product_id
    ")->fetch();

    return [
        'total_products' => (int) ($row['total_products'] ?? 0),
        'in_stock' => (int) ($row['in_stock'] ?? 0),
        'low_stock' => (int) ($row['low_stock'] ?? 0),
        'out_of_stock' => (int) ($row['out_of_stock'] ?? 0)
    ];
}

function handleGetProduct($productId) {
    if ($productId <= 0) {
        sendErrorResponse('Invalid product ID.');
        return;
    }

    try {
        $pdo = getSqliteConnection();
        $stmt = $pdo->prepare("
            SELECT
                p.product_id,
                p.product_name,
                p.sku,
                p.description,
                p.unit_price,
                i.quantity,
                i.min_stock_level,
                i.last_updated
            FROM products p
            INNER JOIN inventory i ON i.product_id = p.product_id
            WHERE p.product_id = :product_id
            LIMIT 1
        ");
        $stmt->execute([':product_id' => $productId]);
        $product = $stmt->fetch();

        if (!$product) {
            sendErrorResponse('Product not found.');
            return;
        }

        sendSuccessResponse($product);
    } catch (Throwable $e) {
        sendErrorResponse('Failed to fetch product: ' . $e->getMessage());
    }
}

function handleUpdateQuantityRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Invalid request method. POST required.');
        return;
    }

    $rawProductId = isset($_POST['product_id']) ? trim((string) $_POST['product_id']) : '';
    $rawQuantity = isset($_POST['quantity']) ? trim((string) $_POST['quantity']) : '';

    if (!preg_match('/^\d+$/', $rawProductId) || !preg_match('/^\d+$/', $rawQuantity)) {
        sendErrorResponse('Invalid input. product_id and quantity must be whole numbers.');
        return;
    }

    handleUpdateQuantity((int) $rawProductId, (int) $rawQuantity);
}

function handleUpdateQuantity($productId, $quantity) {
    if ($productId <= 0) {
        sendErrorResponse('Invalid product ID.');
        return;
    }

    if ($quantity < 0) {
        sendErrorResponse('Quantity cannot be negative.');
        return;
    }

    try {
        $pdo = getSqliteConnection();
        $pdo->beginTransaction();

        $lookup = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = :product_id LIMIT 1');
        $lookup->execute([':product_id' => $productId]);
        $existing = $lookup->fetch();

        if (!$existing) {
            $pdo->rollBack();
            sendErrorResponse('Product not found.');
            return;
        }

        $update = $pdo->prepare('
            UPDATE inventory
            SET quantity = :quantity, last_updated = :last_updated
            WHERE product_id = :product_id
        ');
        $update->execute([
            ':quantity' => $quantity,
            ':last_updated' => gmdate('Y-m-d\TH:i:s\Z'),
            ':product_id' => $productId
        ]);

        $pdo->commit();

        sendSuccessResponse([
            'product_id' => $productId,
            'old_quantity' => (int) $existing['quantity'],
            'new_quantity' => $quantity,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendErrorResponse('Failed to update quantity: ' . $e->getMessage());
    }
}

function handleAddProduct() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Invalid request method. POST required.');
        return;
    }

    $name = isset($_POST['product_name']) ? trim((string) $_POST['product_name']) : '';
    $sku = isset($_POST['sku']) ? trim((string) $_POST['sku']) : '';
    $description = isset($_POST['description']) ? trim((string) $_POST['description']) : '';
    $unitPriceRaw = isset($_POST['unit_price']) ? trim((string) $_POST['unit_price']) : '0';
    $quantityRaw = isset($_POST['quantity']) ? trim((string) $_POST['quantity']) : '0';
    $minStockRaw = isset($_POST['min_stock_level']) ? trim((string) $_POST['min_stock_level']) : '0';

    if ($name === '' || $sku === '') {
        sendErrorResponse('Product name and SKU are required.');
        return;
    }

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $unitPriceRaw)) {
        sendErrorResponse('Unit price must be a valid number with up to 2 decimal places.');
        return;
    }

    if (!preg_match('/^\d+$/', $quantityRaw) || !preg_match('/^\d+$/', $minStockRaw)) {
        sendErrorResponse('Quantity and minimum stock level must be whole numbers.');
        return;
    }

    try {
        $pdo = getSqliteConnection();
        $pdo->beginTransaction();

        $skuCheck = $pdo->prepare('SELECT 1 FROM products WHERE lower(sku) = lower(:sku) LIMIT 1');
        $skuCheck->execute([':sku' => $sku]);
        if ($skuCheck->fetchColumn()) {
            $pdo->rollBack();
            sendErrorResponse('SKU already exists.');
            return;
        }

        $now = gmdate('Y-m-d\TH:i:s\Z');
        $productStmt = $pdo->prepare('
            INSERT INTO products (product_name, sku, description, unit_price, created_at, updated_at)
            VALUES (:product_name, :sku, :description, :unit_price, :created_at, :updated_at)
        ');
        $productStmt->execute([
            ':product_name' => $name,
            ':sku' => $sku,
            ':description' => $description,
            ':unit_price' => round((float) $unitPriceRaw, 2),
            ':created_at' => $now,
            ':updated_at' => $now
        ]);

        $productId = (int) $pdo->lastInsertId();

        $inventoryStmt = $pdo->prepare('
            INSERT INTO inventory (product_id, quantity, min_stock_level, last_updated)
            VALUES (:product_id, :quantity, :min_stock_level, :last_updated)
        ');
        $inventoryStmt->execute([
            ':product_id' => $productId,
            ':quantity' => (int) $quantityRaw,
            ':min_stock_level' => (int) $minStockRaw,
            ':last_updated' => $now
        ]);

        $pdo->commit();

        sendSuccessResponse([
            'product_id' => $productId,
            'product_name' => $name,
            'sku' => $sku
        ]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        sendErrorResponse('Failed to add product: ' . $e->getMessage());
    }
}

function handleDeleteProductRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendErrorResponse('Invalid request method. POST required.');
        return;
    }

    $rawProductId = isset($_POST['product_id']) ? trim((string) $_POST['product_id']) : '';
    if (!preg_match('/^\d+$/', $rawProductId)) {
        sendErrorResponse('Invalid input. product_id must be a whole number.');
        return;
    }

    handleDeleteProduct((int) $rawProductId);
}

function handleDeleteProduct($productId) {
    if ($productId <= 0) {
        sendErrorResponse('Invalid product ID.');
        return;
    }

    try {
        $pdo = getSqliteConnection();
        $stmt = $pdo->prepare('DELETE FROM products WHERE product_id = :product_id');
        $stmt->execute([':product_id' => $productId]);

        if ($stmt->rowCount() === 0) {
            sendErrorResponse('Product not found.');
            return;
        }

        sendSuccessResponse([
            'product_id' => $productId,
            'deleted' => true
        ]);
    } catch (Throwable $e) {
        sendErrorResponse('Failed to delete product: ' . $e->getMessage());
    }
}

function sendSuccessResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'Request processed successfully'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function sendErrorResponse($message) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => true
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
