<?php
/**
 * Class Section - Quản lý phần tài liệu
 * Tạo bởi: MiniMax Agent
 */

require_once '../config/database.php';

class Section {
    private $conn;
    private $table = 'document_sections';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Lấy thông tin section
    public function getSection($section_type, $section_category, $section_subject = null) {
        $query = "SELECT * FROM " . $this->table . " 
                  WHERE section_type = :section_type 
                  AND section_category = :section_category";
        
        if ($section_subject !== null) {
            $query .= " AND section_subject = :section_subject";
        } else {
            $query .= " AND section_subject IS NULL";
        }
        
        $query .= " AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_type', $section_type);
        $stmt->bindParam(':section_category', $section_category);
        
        if ($section_subject !== null) {
            $stmt->bindParam(':section_subject', $section_subject);
        }
        
        $stmt->execute();
        return $stmt->fetch();
    }

    // Lấy tất cả sections
    public function getAllSections() {
        $query = "SELECT * FROM " . $this->table . " WHERE is_active = 1 ORDER BY section_type, section_category, section_subject";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    // Thêm/cập nhật reaction cho section
    public function toggleSectionReaction($user_id, $section_id, $reaction_type) {
        $valid_reactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        
        if (!in_array($reaction_type, $valid_reactions)) {
            return ['success' => false, 'message' => 'Loại reaction không hợp lệ'];
        }

        // Kiểm tra xem đã reaction chưa
        $query = "SELECT reaction_type FROM section_reactions 
                  WHERE user_id = :user_id AND section_id = :section_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();

        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['reaction_type'] == $reaction_type) {
                // Xóa reaction nếu click vào cùng loại
                $query = "DELETE FROM section_reactions 
                          WHERE user_id = :user_id AND section_id = :section_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':section_id', $section_id);
                
                if ($stmt->execute()) {
                    return ['success' => true, 'action' => 'removed', 'reaction' => $reaction_type];
                }
            } else {
                // Cập nhật reaction type
                $query = "UPDATE section_reactions 
                          SET reaction_type = :reaction_type 
                          WHERE user_id = :user_id AND section_id = :section_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':reaction_type', $reaction_type);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':section_id', $section_id);
                
                if ($stmt->execute()) {
                    return ['success' => true, 'action' => 'updated', 'reaction' => $reaction_type];
                }
            }
        } else {
            // Thêm reaction mới
            $query = "INSERT INTO section_reactions (user_id, section_id, reaction_type) 
                      VALUES (:user_id, :section_id, :reaction_type)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':section_id', $section_id);
            $stmt->bindParam(':reaction_type', $reaction_type);
            
            if ($stmt->execute()) {
                return ['success' => true, 'action' => 'added', 'reaction' => $reaction_type];
            }
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra'];
    }

    // Lấy reactions của một section
    public function getSectionReactions($section_id) {
        $query = "SELECT reaction_type, COUNT(*) as count 
                  FROM section_reactions 
                  WHERE section_id = :section_id 
                  GROUP BY reaction_type";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();

        $reactions = [];
        while ($row = $stmt->fetch()) {
            $reactions[$row['reaction_type']] = $row['count'];
        }

        return $reactions;
    }

    // Lấy reaction của user cho section cụ thể
    public function getUserSectionReaction($user_id, $section_id) {
        $query = "SELECT reaction_type FROM section_reactions 
                  WHERE user_id = :user_id AND section_id = :section_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ? $result['reaction_type'] : null;
    }

    // Lấy thống kê section
    public function getSectionStats($section_id) {
        // Thống kê reactions
        $reaction_query = "SELECT COUNT(*) as total_reactions,
                                  COUNT(DISTINCT user_id) as unique_reactors
                           FROM section_reactions 
                           WHERE section_id = :section_id";

        $stmt = $this->conn->prepare($reaction_query);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();
        $reaction_stats = $stmt->fetch();

        // Thống kê comments
        $comment_query = "SELECT COUNT(*) as total_comments,
                                 COUNT(DISTINCT user_id) as unique_commenters
                          FROM comments 
                          WHERE section_id = :section_id AND is_deleted = 0";

        $stmt = $this->conn->prepare($comment_query);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();
        $comment_stats = $stmt->fetch();

        return [
            'reactions' => $reaction_stats,
            'comments' => $comment_stats
        ];
    }

    // Tìm section theo ID
    public function getSectionById($section_id) {
        $query = "SELECT * FROM " . $this->table . " WHERE id = :section_id AND is_active = 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();
        return $stmt->fetch();
    }

    // Lấy section ID theo thông tin
    public function getSectionId($section_type, $section_category, $section_subject = null) {
        $section = $this->getSection($section_type, $section_category, $section_subject);
        return $section ? $section['id'] : null;
    }

    // Lấy danh sách reactions phổ biến
    public function getPopularReactions($limit = 10) {
        $query = "SELECT s.section_type, s.section_category, s.section_subject, s.title,
                         sr.reaction_type, COUNT(*) as reaction_count
                  FROM section_reactions sr
                  JOIN " . $this->table . " s ON sr.section_id = s.id
                  WHERE s.is_active = 1
                  GROUP BY sr.section_id, sr.reaction_type
                  ORDER BY reaction_count DESC
                  LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // Admin function: Tạo section mới
    public function createSection($section_type, $section_category, $title, $description = '', $section_subject = null) {
        $query = "INSERT INTO " . $this->table . " 
                  (section_type, section_category, section_subject, title, description) 
                  VALUES (:section_type, :section_category, :section_subject, :title, :description)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_type', $section_type);
        $stmt->bindParam(':section_category', $section_category);
        $stmt->bindParam(':section_subject', $section_subject);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':description', $description);

        if ($stmt->execute()) {
            return ['success' => true, 'section_id' => $this->conn->lastInsertId()];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi tạo section'];
    }
}
?>
