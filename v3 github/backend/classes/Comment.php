<?php
/**
 * Class Comment - Quản lý bình luận
 * Tạo bởi: MiniMax Agent
 */

require_once '../config/database.php';

class Comment {
    private $conn;
    private $table = 'comments';

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Thêm bình luận mới
    public function addComment($user_id, $section_id, $content, $parent_comment_id = null) {
        // Validate input
        if (empty(trim($content))) {
            return ['success' => false, 'message' => 'Nội dung bình luận không được để trống'];
        }

        if (strlen($content) > 2000) {
            return ['success' => false, 'message' => 'Bình luận không được quá 2000 ký tự'];
        }

        $query = "INSERT INTO " . $this->table . " (user_id, section_id, parent_comment_id, content) 
                  VALUES (:user_id, :section_id, :parent_comment_id, :content)";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->bindParam(':parent_comment_id', $parent_comment_id);
        $stmt->bindParam(':content', $content);

        if ($stmt->execute()) {
            $comment_id = $this->conn->lastInsertId();
            return [
                'success' => true, 
                'message' => 'Bình luận đã được thêm',
                'comment_id' => $comment_id
            ];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi thêm bình luận'];
    }

    // Lấy danh sách bình luận theo section
    public function getCommentsBySection($section_id, $limit = 50, $offset = 0) {
        $query = "SELECT c.id, c.user_id, c.content, c.created_at, c.updated_at, c.is_edited, 
                         c.parent_comment_id, u.username, u.display_name, u.avatar_icon, u.admin_badge,
                         (SELECT COUNT(*) FROM comment_reactions cr WHERE cr.comment_id = c.id) as total_reactions
                  FROM " . $this->table . " c
                  JOIN users u ON c.user_id = u.id
                  WHERE c.section_id = :section_id AND c.is_deleted = 0
                  ORDER BY c.created_at ASC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_id', $section_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $comments = $stmt->fetchAll();

        // Organize comments hierarchically
        return $this->organizeComments($comments);
    }

    // Tổ chức bình luận theo cấu trúc cha-con
    private function organizeComments($comments) {
        $organized = [];
        $replies = [];

        foreach ($comments as $comment) {
            if ($comment['parent_comment_id'] == null) {
                $comment['replies'] = [];
                $comment['reactions'] = $this->getCommentReactions($comment['id']);
                $organized[] = $comment;
            } else {
                $comment['reactions'] = $this->getCommentReactions($comment['id']);
                $replies[$comment['parent_comment_id']][] = $comment;
            }
        }

        // Gán replies cho các comment cha
        foreach ($organized as &$comment) {
            if (isset($replies[$comment['id']])) {
                $comment['replies'] = $replies[$comment['id']];
            }
        }

        return $organized;
    }

    // Lấy reactions của một comment
    private function getCommentReactions($comment_id) {
        $query = "SELECT reaction_type, COUNT(*) as count 
                  FROM comment_reactions 
                  WHERE comment_id = :comment_id 
                  GROUP BY reaction_type";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        $reactions = [];
        while ($row = $stmt->fetch()) {
            $reactions[$row['reaction_type']] = $row['count'];
        }

        return $reactions;
    }

    // Thêm/cập nhật reaction cho comment
    public function toggleCommentReaction($user_id, $comment_id, $reaction_type) {
        $valid_reactions = ['like', 'love', 'haha', 'wow', 'sad', 'angry'];
        
        if (!in_array($reaction_type, $valid_reactions)) {
            return ['success' => false, 'message' => 'Loại reaction không hợp lệ'];
        }

        // Kiểm tra xem đã reaction chưa
        $query = "SELECT reaction_type FROM comment_reactions 
                  WHERE user_id = :user_id AND comment_id = :comment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        $existing = $stmt->fetch();

        if ($existing) {
            if ($existing['reaction_type'] == $reaction_type) {
                // Xóa reaction nếu click vào cùng loại
                $query = "DELETE FROM comment_reactions 
                          WHERE user_id = :user_id AND comment_id = :comment_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':comment_id', $comment_id);
                
                if ($stmt->execute()) {
                    return ['success' => true, 'action' => 'removed', 'reaction' => $reaction_type];
                }
            } else {
                // Cập nhật reaction type
                $query = "UPDATE comment_reactions 
                          SET reaction_type = :reaction_type 
                          WHERE user_id = :user_id AND comment_id = :comment_id";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':reaction_type', $reaction_type);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':comment_id', $comment_id);
                
                if ($stmt->execute()) {
                    return ['success' => true, 'action' => 'updated', 'reaction' => $reaction_type];
                }
            }
        } else {
            // Thêm reaction mới
            $query = "INSERT INTO comment_reactions (user_id, comment_id, reaction_type) 
                      VALUES (:user_id, :comment_id, :reaction_type)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':comment_id', $comment_id);
            $stmt->bindParam(':reaction_type', $reaction_type);
            
            if ($stmt->execute()) {
                return ['success' => true, 'action' => 'added', 'reaction' => $reaction_type];
            }
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra'];
    }

    // Sửa bình luận
    public function editComment($user_id, $comment_id, $new_content) {
        // Kiểm tra quyền sở hữu comment
        $query = "SELECT user_id FROM " . $this->table . " WHERE id = :comment_id AND is_deleted = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        $comment = $stmt->fetch();
        if (!$comment || $comment['user_id'] != $user_id) {
            return ['success' => false, 'message' => 'Không có quyền sửa bình luận này'];
        }

        if (empty(trim($new_content))) {
            return ['success' => false, 'message' => 'Nội dung bình luận không được để trống'];
        }

        $query = "UPDATE " . $this->table . " 
                  SET content = :content, is_edited = 1 
                  WHERE id = :comment_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':content', $new_content);
        $stmt->bindParam(':comment_id', $comment_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Bình luận đã được cập nhật'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật bình luận'];
    }

    // Xóa bình luận
    public function deleteComment($user_id, $comment_id, $is_admin = false) {
        // Kiểm tra quyền
        if (!$is_admin) {
            $query = "SELECT user_id FROM " . $this->table . " WHERE id = :comment_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':comment_id', $comment_id);
            $stmt->execute();

            $comment = $stmt->fetch();
            if (!$comment || $comment['user_id'] != $user_id) {
                return ['success' => false, 'message' => 'Không có quyền xóa bình luận này'];
            }
        }

        $query = "UPDATE " . $this->table . " 
                  SET is_deleted = 1, deleted_at = NOW() 
                  WHERE id = :comment_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':comment_id', $comment_id);

        if ($stmt->execute()) {
            // Nếu là admin xóa thì log action
            if ($is_admin && $user_id) {
                $this->logAdminAction($user_id, 'delete_comment', null, $comment_id, 'Admin deleted comment');
            }
            
            return ['success' => true, 'message' => 'Bình luận đã được xóa'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi xóa bình luận'];
    }

    // Lấy reaction của user cho comment cụ thể
    public function getUserCommentReaction($user_id, $comment_id) {
        $query = "SELECT reaction_type FROM comment_reactions 
                  WHERE user_id = :user_id AND comment_id = :comment_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':comment_id', $comment_id);
        $stmt->execute();

        $result = $stmt->fetch();
        return $result ? $result['reaction_type'] : null;
    }

    // Thống kê bình luận
    public function getCommentStats($section_id) {
        $query = "SELECT COUNT(*) as total_comments,
                         COUNT(DISTINCT user_id) as unique_users
                  FROM " . $this->table . " 
                  WHERE section_id = :section_id AND is_deleted = 0";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':section_id', $section_id);
        $stmt->execute();

        return $stmt->fetch();
    }

    private function logAdminAction($admin_id, $action_type, $target_user_id = null, $target_comment_id = null, $reason = '') {
        $query = "INSERT INTO admin_actions (admin_user_id, action_type, target_user_id, target_comment_id, reason) 
                  VALUES (:admin_id, :action_type, :target_user_id, :target_comment_id, :reason)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':admin_id', $admin_id);
        $stmt->bindParam(':action_type', $action_type);
        $stmt->bindParam(':target_user_id', $target_user_id);
        $stmt->bindParam(':target_comment_id', $target_comment_id);
        $stmt->bindParam(':reason', $reason);
        
        $stmt->execute();
    }
}
?>
