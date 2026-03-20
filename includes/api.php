<?php
/**
 * Inventory Management System - API Endpoint
 * Handles all backend requests for inventory data
 * Uses JSON file as rough database (no MySQL required)
 */

// Set proper headers - MUST be before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load JSON database
$db_file = __DIR__ . '/db.json';
if (!file_exists($db_file)) {
    sendErrorResponse('Database file not found.');
}

$db_content = file_get_contents($db_file);
$database = json_decode($db_content, true);

if (!$database) {
    sendErrorResponse('Failed to parse database file.');
}

// Get the action parameter
$action = isset($_GET['action']) ? sanitize_input($_GET['action']) : '';
if (!$action && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? sanitize_input($_POST['action']) : 'get_all_inventory';
}

// Route to appropriate function
switch ($action) {
    case 'get_all_inventory':
        handleGetAllInventory();
        break;
    
    case 'get_inventory_stats':
        handleGetInventoryStats();
        break;
    
    case 'get_product':
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        handleGetProduct($product_id);
        break;
    
    case 'update_quantity':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw_product_id = isset($_POST['product_id']) ? trim((string)$_POST['product_id']) : '';
            $raw_quantity = isset($_POST['quantity']) ? trim((string)$_POST['quantity']) : '';

            if (!preg_match('/^\d+$/', $raw_product_id) || !preg_match('/^\d+$/', $raw_quantity)) {
                sendErrorResponse('Invalid input. product_id and quantity must be whole numbers.');
            }

            $product_id = intval($raw_product_id);
            $quantity = intval($raw_quantity);
            handleUpdateQuantity($product_id, $quantity);
        } else {
            sendErrorResponse('Invalid request method. POST required.');
        }
        break;

    case 'add_product':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            handleAddProduct();
        } else {
            sendErrorResponse('Invalid request method. POST required.');
        }
        break;

    case 'delete_product':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $raw_product_id = isset($_POST['product_id']) ? trim((string)$_POST['product_id']) : '';
            if (!preg_match('/^\d+$/', $raw_product_id)) {
                sendErrorResponse('Invalid input. product_id must be a whole number.');
            }
            handleDeleteProduct(intval($raw_product_id));
        } else {
            sendErrorResponse('Invalid request method. POST required.');
        }
        break;
    
    default:
        sendErrorResponse('Invalid action parameter.');
        break;
}

/**
 * Get all inventory items with statistics
 */
function handleGetAllInventory() {
    global $database;
    
    try {
        // Merge products with inventory data
        $inventory = [];
        foreach ($database['inventory'] as $inv_item) {
            // Find matching product
            $product = array_filter($database['products'], function($p) use ($inv_item) {
                return $p['product_id'] == $inv_item['product_id'];
            });
            
            if ($product) {
                $product = reset($product);
                $inventory[] = [
                    'product_id' => $product['product_id'],
                    'product_name' => $product['product_name'],
                    'sku' => $product['sku'],
                    'quantity' => $inv_item['quantity'],
                    'min_stock_level' => $inv_item['min_stock_level'],
                    'last_updated' => $inv_item['last_updated']
                ];
            }
        }
        
        // Sort by product name
        usort($inventory, function($a, $b) {
            return strcmp($a['product_name'], $b['product_name']);
        });
        
        // Get statistics
        $stats = getInventoryStats();
        
        // Send successful response
        sendSuccessResponse([
            'inventory' => $inventory,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch inventory: ' . $e->getMessage());
    }
}

/**
 * Get inventory statistics
 */
function handleGetInventoryStats() {
    $stats = getInventoryStats();
    sendSuccessResponse($stats);
}

/**
 * Calculate inventory statistics
 */
function getInventoryStats() {
    global $database;
    
    try {
        $total_products = count($database['products']);
        $in_stock = 0;
        $low_stock = 0;
        $out_of_stock = 0;
        
        foreach ($database['inventory'] as $item) {
            if ($item['quantity'] == 0) {
                $out_of_stock++;
            } elseif ($item['quantity'] <= $item['min_stock_level']) {
                $low_stock++;
            } else {
                $in_stock++;
            }
        }
        
        return [
            'total_products' => intval($total_products),
            'in_stock' => intval($in_stock),
            'low_stock' => intval($low_stock),
            'out_of_stock' => intval($out_of_stock)
        ];
        
    } catch (Exception $e) {
        return [
            'total_products' => 0,
            'in_stock' => 0,
            'low_stock' => 0,
            'out_of_stock' => 0,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Get single product details
 */
function handleGetProduct($product_id) {
    global $database;
    
    if ($product_id <= 0) {
        sendErrorResponse('Invalid product ID.');
        return;
    }
    
    try {
        // Find product
        $product = null;
        foreach ($database['products'] as $p) {
            if ($p['product_id'] == $product_id) {
                $product = $p;
                break;
            }
        }
        
        if (!$product) {
            sendErrorResponse('Product not found.');
            return;
        }
        
        // Find inventory
        $inventory = null;
        foreach ($database['inventory'] as $inv) {
            if ($inv['product_id'] == $product_id) {
                $inventory = $inv;
                break;
            }
        }
        
        $result = [
            'product_id' => $product['product_id'],
            'product_name' => $product['product_name'],
            'sku' => $product['sku'],
            'description' => $product['description'],
            'unit_price' => $product['unit_price'],
            'quantity' => $inventory['quantity'] ?? 0,
            'min_stock_level' => $inventory['min_stock_level'] ?? 0,
            'last_updated' => $inventory['last_updated'] ?? null
        ];
        
        sendSuccessResponse($result);
        
    } catch (Exception $e) {
        sendErrorResponse('Failed to fetch product: ' . $e->getMessage());
    }
}

/**
 * Update product quantity
 */
function handleUpdateQuantity($product_id, $quantity) {
    global $database;
    
    if ($product_id <= 0) {
        sendErrorResponse('Invalid product ID.');
        return;
    }
    
    if ($quantity < 0) {
        sendErrorResponse('Quantity cannot be negative.');
        return;
    }
    
    try {
        $db_file = __DIR__ . '/db.json';
        $product_exists = false;
        foreach ($database['products'] as $product) {
            if ($product['product_id'] == $product_id) {
                $product_exists = true;
                break;
            }
        }

        if (!$product_exists) {
            sendErrorResponse('Product not found.');
            return;
        }

        $found = false;
        $old_quantity = null;
        
        // Update inventory in memory
        foreach ($database['inventory'] as &$inv) {
            if ($inv['product_id'] == $product_id) {
                $old_quantity = isset($inv['quantity']) ? intval($inv['quantity']) : 0;
                $inv['quantity'] = $quantity;
                $inv['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            sendErrorResponse('Product not found or quantity not updated.');
            return;
        }
        
        // Write back to JSON file using lock to avoid concurrent write corruption
        saveDatabaseWithLock($db_file, $database);
        
        sendSuccessResponse([
            'product_id' => $product_id,
            'old_quantity' => $old_quantity,
            'new_quantity' => $quantity,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        sendErrorResponse('Failed to update quantity: ' . $e->getMessage());
    }
}

/**
 * Add a new product and inventory record
 */
function handleAddProduct() {
    global $database;

    $name = isset($_POST['product_name']) ? trim((string)$_POST['product_name']) : '';
    $sku = isset($_POST['sku']) ? trim((string)$_POST['sku']) : '';
    $description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';
    $unit_price_raw = isset($_POST['unit_price']) ? trim((string)$_POST['unit_price']) : '0';
    $quantity_raw = isset($_POST['quantity']) ? trim((string)$_POST['quantity']) : '0';
    $min_stock_raw = isset($_POST['min_stock_level']) ? trim((string)$_POST['min_stock_level']) : '0';

    if ($name === '' || $sku === '') {
        sendErrorResponse('Product name and SKU are required.');
        return;
    }

    if (!preg_match('/^\d+(\.\d{1,2})?$/', $unit_price_raw)) {
        sendErrorResponse('Unit price must be a valid number with up to 2 decimal places.');
        return;
    }

    if (!preg_match('/^\d+$/', $quantity_raw) || !preg_match('/^\d+$/', $min_stock_raw)) {
        sendErrorResponse('Quantity and minimum stock level must be whole numbers.');
        return;
    }

    foreach ($database['products'] as $product) {
        if (strcasecmp($product['sku'], $sku) === 0) {
            sendErrorResponse('SKU already exists.');
            return;
        }
    }

    $new_product_id = 1;
    foreach ($database['products'] as $product) {
        if ($product['product_id'] >= $new_product_id) {
            $new_product_id = $product['product_id'] + 1;
        }
    }

    $new_product = [
        'product_id' => $new_product_id,
        'product_name' => $name,
        'sku' => $sku,
        'description' => $description,
        'unit_price' => round(floatval($unit_price_raw), 2)
    ];

    $new_inventory = [
        'product_id' => $new_product_id,
        'quantity' => intval($quantity_raw),
        'min_stock_level' => intval($min_stock_raw),
        'last_updated' => gmdate('Y-m-d\TH:i:s\Z')
    ];

    $database['products'][] = $new_product;
    $database['inventory'][] = $new_inventory;

    try {
        $db_file = __DIR__ . '/db.json';
        saveDatabaseWithLock($db_file, $database);
        sendSuccessResponse([
            'product_id' => $new_product_id,
            'product_name' => $name,
            'sku' => $sku
        ]);
    } catch (Exception $e) {
        sendErrorResponse('Failed to add product: ' . $e->getMessage());
    }
}

/**
 * Delete product and its inventory entry
 */
function handleDeleteProduct($product_id) {
    global $database;

    if ($product_id <= 0) {
        sendErrorResponse('Invalid product ID.');
        return;
    }

    $product_found = false;
    $filtered_products = [];
    foreach ($database['products'] as $product) {
        if ($product['product_id'] == $product_id) {
            $product_found = true;
            continue;
        }
        $filtered_products[] = $product;
    }

    if (!$product_found) {
        sendErrorResponse('Product not found.');
        return;
    }

    $filtered_inventory = [];
    foreach ($database['inventory'] as $inventory_item) {
        if ($inventory_item['product_id'] != $product_id) {
            $filtered_inventory[] = $inventory_item;
        }
    }

    $database['products'] = array_values($filtered_products);
    $database['inventory'] = array_values($filtered_inventory);

    try {
        $db_file = __DIR__ . '/db.json';
        saveDatabaseWithLock($db_file, $database);
        sendSuccessResponse([
            'product_id' => $product_id,
            'deleted' => true
        ]);
    } catch (Exception $e) {
        sendErrorResponse('Failed to delete product: ' . $e->getMessage());
    }
}

/**
 * Save database content with an exclusive file lock
 */
function saveDatabaseWithLock($file_path, $content) {
    $fp = fopen($file_path, 'c+');
    if (!$fp) {
        throw new Exception('Failed to open database file.');
    }

    if (!flock($fp, LOCK_EX)) {
        fclose($fp);
        throw new Exception('Failed to lock database file.');
    }

    $encoded = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        flock($fp, LOCK_UN);
        fclose($fp);
        throw new Exception('Failed to encode database file.');
    }

    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $encoded);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
}

/**
 * Send successful JSON response
 */
function sendSuccessResponse($data) {
    echo json_encode([
        'success' => true,
        'data' => $data,
        'message' => 'Request processed successfully'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error JSON response
 */
function sendErrorResponse($message) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'error' => true
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Sanitize user input
 */
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

?>
