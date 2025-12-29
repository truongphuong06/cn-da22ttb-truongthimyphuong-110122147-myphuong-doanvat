<?php
/**
 * Product Controller
 * Handles all product-related API endpoints
 */
class ProductController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function handleRequest($method, $id, $action) {
        switch ($method) {
            case 'GET':
                if ($action === 'categories') {
                    $this->getCategories();
                } elseif ($id) {
                    $this->getProduct($id);
                } else {
                    $this->getProducts();
                }
                break;
                
            case 'POST':
                Auth::requireAdmin();
                $this->createProduct();
                break;
                
            case 'PUT':
                Auth::requireAdmin();
                $this->updateProduct($id);
                break;
                
            case 'DELETE':
                Auth::requireAdmin();
                $this->deleteProduct($id);
                break;
                
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function getProducts() {
        $page = $_GET['page'] ?? 1;
        $perPage = $_GET['per_page'] ?? 20;
        $category = $_GET['category'] ?? null;
        $search = $_GET['search'] ?? null;
        $sort = $_GET['sort'] ?? 'id';
        $order = $_GET['order'] ?? 'DESC';
        
        $offset = ($page - 1) * $perPage;
        
        $where = ["sp.trang_thai = 1"];
        $params = [];
        
        if ($category) {
            $where[] = "dm.id = ?";
            $params[] = $category;
        }
        
        if ($search) {
            $where[] = "(sp.ten_san_pham LIKE ? OR sp.ma_san_pham LIKE ? OR sp.mo_ta LIKE ?)";
            $searchTerm = "%{$search}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $where);
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM san_pham sp WHERE {$whereClause}";
        $countStmt = $this->conn->prepare($countSql);
        $countStmt->execute($params);
        $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get products
        $sql = "SELECT sp.* FROM san_pham sp
                WHERE {$whereClause}
                ORDER BY sp.{$sort} {$order}
                LIMIT {$perPage} OFFSET {$offset}";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format products
        foreach ($products as &$product) {
            $product['hinh_anh_url'] = $product['hinh_anh'] ? 
                'http://' . $_SERVER['HTTP_HOST'] . '/Web/uploads/' . $product['hinh_anh'] : null;
            $product['gia'] = (float)$product['gia'];
            $product['so_luong'] = (int)$product['so_luong'];
            $product['id'] = (int)$product['id'];
        }
        
        Response::paginated($products, $page, $perPage, $total);
    }
    
    private function getProduct($id) {
        $sql = "SELECT sp.*, dm.ten_danh_muc, dm.slug as danh_muc_slug
                FROM san_pham sp
                LEFT JOIN danh_muc dm ON sp.danh_muc_id = dm.id
                WHERE sp.id = ?";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            Response::error('Product not found', 404);
        }
        
        $product['hinh_anh_url'] = $product['hinh_anh'] ? 
            'http://' . $_SERVER['HTTP_HOST'] . '/Web/uploads/' . $product['hinh_anh'] : null;
        $product['gia'] = (float)$product['gia'];
        $product['so_luong'] = (int)$product['so_luong'];
        $product['id'] = (int)$product['id'];
        
        // Get reviews
        $reviewSql = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews 
                      FROM danh_gia WHERE san_pham_id = ?";
        $reviewStmt = $this->conn->prepare($reviewSql);
        $reviewStmt->execute([$id]);
        $reviews = $reviewStmt->fetch(PDO::FETCH_ASSOC);
        
        $product['avg_rating'] = $reviews['avg_rating'] ? round($reviews['avg_rating'], 1) : null;
        $product['total_reviews'] = (int)$reviews['total_reviews'];
        
        Response::success($product, 'Product retrieved successfully');
    }
    
    private function createProduct() {
        $data = Auth::getInput();
        
        Auth::validateRequired($data, ['ten_san_pham', 'ma_san_pham', 'gia', 'danh_muc_id']);
        
        $sql = "INSERT INTO san_pham (ma_san_pham, ten_san_pham, mo_ta, gia, so_luong, danh_muc_id, hinh_anh, size, mau_sac, trang_thai) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
        
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([
            $data['ma_san_pham'],
            $data['ten_san_pham'],
            $data['mo_ta'] ?? null,
            $data['gia'],
            $data['so_luong'] ?? 0,
            $data['danh_muc_id'],
            $data['hinh_anh'] ?? null,
            $data['size'] ?? null,
            $data['mau_sac'] ?? null
        ]);
        
        if ($result) {
            $productId = $this->conn->lastInsertId();
            
            // Auto notify new product
            if (file_exists(__DIR__ . '/../../notification_helpers.php')) {
                require_once __DIR__ . '/../../notification_helpers.php';
                $categoryStmt = $this->conn->prepare("SELECT ten_danh_muc FROM danh_muc WHERE id = ?");
                $categoryStmt->execute([$data['danh_muc_id']]);
                $category = $categoryStmt->fetch(PDO::FETCH_ASSOC);
                auto_notify_new_product($productId, $data['ten_san_pham'], $category['ten_danh_muc'] ?? '');
            }
            
            Response::success(['id' => $productId], 'Product created successfully', 201);
        } else {
            Response::error('Failed to create product');
        }
    }
    
    private function updateProduct($id) {
        if (!$id) {
            Response::error('Product ID is required');
        }
        
        $data = Auth::getInput();
        
        $fields = [];
        $params = [];
        
        $allowedFields = ['ma_san_pham', 'ten_san_pham', 'mo_ta', 'gia', 'so_luong', 'danh_muc_id', 'hinh_anh', 'size', 'mau_sac', 'trang_thai'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            Response::error('No fields to update');
        }
        
        $params[] = $id;
        
        $sql = "UPDATE san_pham SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            // Check for sale notification
            if (isset($data['gia'])) {
                $oldPriceStmt = $this->conn->prepare("SELECT gia, ten_san_pham FROM san_pham WHERE id = ?");
                $oldPriceStmt->execute([$id]);
                $oldProduct = $oldPriceStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($oldProduct && file_exists(__DIR__ . '/../../notification_helpers.php')) {
                    require_once __DIR__ . '/../../notification_helpers.php';
                    auto_notify_sale($id, $oldProduct['ten_san_pham'], $oldProduct['gia'], $data['gia']);
                }
            }
            
            Response::success(['id' => $id], 'Product updated successfully');
        } else {
            Response::error('Failed to update product');
        }
    }
    
    private function deleteProduct($id) {
        if (!$id) {
            Response::error('Product ID is required');
        }
        
        // Soft delete
        $sql = "UPDATE san_pham SET trang_thai = 0 WHERE id = ?";
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([$id]);
        
        if ($result) {
            Response::success(['id' => $id], 'Product deleted successfully');
        } else {
            Response::error('Failed to delete product');
        }
    }
    
    private function getCategories() {
        $sql = "SELECT * FROM danh_muc ORDER BY ten_danh_muc ASC";
        $stmt = $this->conn->query($sql);
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($categories as &$category) {
            $category['id'] = (int)$category['id'];
            
            // Count products
            $countStmt = $this->conn->prepare("SELECT COUNT(*) as total FROM san_pham WHERE danh_muc_id = ? AND trang_thai = 1");
            $countStmt->execute([$category['id']]);
            $category['product_count'] = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        }
        
        Response::success($categories, 'Categories retrieved successfully');
    }
}
?>
