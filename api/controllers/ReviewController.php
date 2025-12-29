<?php
class ReviewController {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    public function handleRequest($method, $id, $action) {
        switch ($method) {
            case 'GET':
                $this->getReviews($id);
                break;
                
            case 'POST':
                $this->addReview($id);
                break;
                
            case 'PUT':
                $this->updateReview($id);
                break;
                
            case 'DELETE':
                $this->deleteReview($id);
                break;
                
            default:
                Response::error('Method not allowed', 405);
        }
    }
    
    private function getReviews($product_id) {
        $sql = "SELECT dg.*, nd.ho_ten 
                FROM danh_gia dg 
                JOIN nguoi_dung nd ON dg.nguoi_dung_id = nd.id 
                WHERE dg.san_pham_id = ? 
                ORDER BY dg.ngay_tao DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $product_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $reviews = [];
        while ($row = $result->fetch_assoc()) {
            $reviews[] = $row;
        }
        
        Response::success($reviews);
    }
    
    private function addReview($product_id) {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        $input = Auth::getInput();
        $required = ['rating', 'comment'];
        
        if (!Auth::validateRequired($input, $required)) {
            Response::error('Thiếu thông tin', 400);
            return;
        }
        
        $sql = "INSERT INTO danh_gia (san_pham_id, nguoi_dung_id, rating, noi_dung) 
                VALUES (?, ?, ?, ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('iiis', $product_id, $user['id'], $input['rating'], $input['comment']);
        
        if ($stmt->execute()) {
            Response::success(['review_id' => $this->conn->insert_id], 'Thêm đánh giá thành công');
        } else {
            Response::error('Thêm đánh giá thất bại', 500);
        }
    }
    
    private function updateReview($review_id) {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        $input = Auth::getInput();
        
        $sql = "UPDATE danh_gia SET rating = ?, noi_dung = ? WHERE id = ? AND nguoi_dung_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('isii', $input['rating'], $input['comment'], $review_id, $user['id']);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            Response::success(null, 'Cập nhật thành công');
        } else {
            Response::error('Cập nhật thất bại', 500);
        }
    }
    
    private function deleteReview($review_id) {
        $user = Auth::getUser($this->conn);
        if (!$user) {
            Response::error('Chưa đăng nhập', 401);
            return;
        }
        
        $sql = "DELETE FROM danh_gia WHERE id = ? AND nguoi_dung_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('ii', $review_id, $user['id']);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            Response::success(null, 'Xóa thành công');
        } else {
            Response::error('Xóa thất bại', 500);
        }
    }
}
?>
