<?php
require_once __DIR__ . '/runtime.php';

function getSqliteDatabasePath() {
    $localPath = __DIR__ . '/inventory.sqlite';

    if (file_exists($localPath)) {
        return $localPath;
    }

    if (is_dir(__DIR__) && is_writable(__DIR__)) {
        return $localPath;
    }

    return getRuntimeStorageDir() . DIRECTORY_SEPARATOR . 'inventory.sqlite';
}

function getSqliteConnection() {
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = getSqliteDatabasePath();
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');

    initializeSqliteDatabase($pdo);

    return $pdo;
}

function initializeSqliteDatabase(PDO $pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            user_id INTEGER PRIMARY KEY,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'user',
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS products (
            product_id INTEGER PRIMARY KEY,
            product_name TEXT NOT NULL,
            sku TEXT NOT NULL UNIQUE,
            description TEXT DEFAULT '',
            unit_price REAL NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS inventory (
            product_id INTEGER PRIMARY KEY,
            quantity INTEGER NOT NULL DEFAULT 0,
            min_stock_level INTEGER NOT NULL DEFAULT 0,
            last_updated TEXT NOT NULL,
            FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
        );
    ");

    seedUsersIfEmpty($pdo);
    seedInventoryIfEmpty($pdo);
}

function seedUsersIfEmpty(PDO $pdo) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $usersPath = __DIR__ . '/users.json';
    $users = file_exists($usersPath) ? json_decode(file_get_contents($usersPath), true) : [];

    if (!is_array($users)) {
        $users = [];
    }

    if (!$users) {
        $now = date('Y-m-d H:i:s');
        $users = [
            [
                'user_id' => 1,
                'username' => 'admin',
                'password' => 'password123',
                'email' => 'admin@inventory.local',
                'role' => 'admin',
                'active' => true,
                'created_at' => $now
            ],
            [
                'user_id' => 2,
                'username' => 'manager',
                'password' => 'manager123',
                'email' => 'manager@inventory.local',
                'role' => 'manager',
                'active' => true,
                'created_at' => $now
            ],
            [
                'user_id' => 3,
                'username' => 'staff',
                'password' => 'staff123',
                'email' => 'staff@inventory.local',
                'role' => 'staff',
                'active' => true,
                'created_at' => $now
            ]
        ];
    }

    $stmt = $pdo->prepare('
        INSERT INTO users (user_id, username, password, email, role, active, created_at)
        VALUES (:user_id, :username, :password, :email, :role, :active, :created_at)
    ');

    foreach ($users as $user) {
        $stmt->execute([
            ':user_id' => (int) $user['user_id'],
            ':username' => $user['username'],
            ':password' => $user['password'],
            ':email' => $user['email'],
            ':role' => $user['role'] ?? 'user',
            ':active' => !empty($user['active']) ? 1 : 0,
            ':created_at' => $user['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
}

function seedInventoryIfEmpty(PDO $pdo) {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
    if ($count > 0) {
        return;
    }

    $dbPath = __DIR__ . '/db.json';
    $payload = file_exists($dbPath) ? json_decode(file_get_contents($dbPath), true) : [];
    $products = isset($payload['products']) && is_array($payload['products']) ? $payload['products'] : [];
    $inventoryItems = isset($payload['inventory']) && is_array($payload['inventory']) ? $payload['inventory'] : [];

    if (!$products) {
        return;
    }

    $inventoryByProductId = [];
    foreach ($inventoryItems as $item) {
        $inventoryByProductId[(int) $item['product_id']] = $item;
    }

    $productStmt = $pdo->prepare('
        INSERT INTO products (product_id, product_name, sku, description, unit_price, created_at, updated_at)
        VALUES (:product_id, :product_name, :sku, :description, :unit_price, :created_at, :updated_at)
    ');

    $inventoryStmt = $pdo->prepare('
        INSERT INTO inventory (product_id, quantity, min_stock_level, last_updated)
        VALUES (:product_id, :quantity, :min_stock_level, :last_updated)
    ');

    foreach ($products as $product) {
        $productId = (int) $product['product_id'];
        $seedInventory = $inventoryByProductId[$productId] ?? [];
        $createdAt = $seedInventory['last_updated'] ?? date('Y-m-d H:i:s');

        $productStmt->execute([
            ':product_id' => $productId,
            ':product_name' => $product['product_name'],
            ':sku' => $product['sku'],
            ':description' => $product['description'] ?? '',
            ':unit_price' => (float) ($product['unit_price'] ?? 0),
            ':created_at' => $createdAt,
            ':updated_at' => $createdAt
        ]);

        $inventoryStmt->execute([
            ':product_id' => $productId,
            ':quantity' => (int) ($seedInventory['quantity'] ?? 0),
            ':min_stock_level' => (int) ($seedInventory['min_stock_level'] ?? 0),
            ':last_updated' => $seedInventory['last_updated'] ?? gmdate('Y-m-d\TH:i:s\Z')
        ]);
    }
}
