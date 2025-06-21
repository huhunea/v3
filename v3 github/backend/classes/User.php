<?php
/**
 * Class User - Quản lý người dùng
 * Tạo bởi: MiniMax Agent
 */

require_once '../config/database.php';

class User {
    private $conn;
    private $table = 'users';

    public $id;
    public $username;
    public $email;
    public $password_hash;
    public $avatar_icon;
    public $display_name;
    public $is_admin;
    public $admin_badge;
    public $created_at;
    public $last_login;
    public $is_active;
    public $profile_bio;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->connect();
    }

    // Đăng ký người dùng mới
    public function register($username, $email, $password, $display_name, $avatar_icon = 'user') {
        // Kiểm tra username và email đã tồn tại chưa
        if ($this->userExists($username, $email)) {
            return ['success' => false, 'message' => 'Tên đăng nhập hoặc email đã tồn tại'];
        }

        // Validate input
        if (strlen($username) < 3 || strlen($username) > 50) {
            return ['success' => false, 'message' => 'Tên đăng nhập phải từ 3-50 ký tự'];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email không hợp lệ'];
        }

        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Mật khẩu phải có ít nhất 6 ký tự'];
        }

        if (strlen($display_name) < 2 || strlen($display_name) > 100) {
            return ['success' => false, 'message' => 'Tên hiển thị phải từ 2-100 ký tự'];
        }

        // Hash password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        $query = "INSERT INTO " . $this->table . " 
                  (username, email, password_hash, display_name, avatar_icon) 
                  VALUES (:username, :email, :password_hash, :display_name, :avatar_icon)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':password_hash', $password_hash);
        $stmt->bindParam(':display_name', $display_name);
        $stmt->bindParam(':avatar_icon', $avatar_icon);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Đăng ký thành công'];
        }

        return ['success' => false, 'message' => 'Có lỗi xảy ra khi đăng ký'];
    }

    // Đăng nhập
    public function login($username, $password) {
        $query = "SELECT id, username, email, password_hash, avatar_icon, display_name, 
                         is_admin, admin_badge, is_active 
                  FROM " . $this->table . " 
                  WHERE (username = :username OR email = :username) AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch();
            
            if (password_verify($password, $row['password_hash'])) {
                // Cập nhật last_login
                $this->updateLastLogin($row['id']);
                
                // Tạo session
                $session_token = $this->createSession($row['id']);
                
                return [
                    'success' => true,
                    'user' => [
                        'id' => $row['id'],
                        'username' => $row['username'],
                        'email' => $row['email'],
                        'avatar_icon' => $row['avatar_icon'],
                        'display_name' => $row['display_name'],
                        'is_admin' => $row['is_admin'],
                        'admin_badge' => $row['admin_badge']
                    ],
                    'session_token' => $session_token
                ];
            }
        }

        return ['success' => false, 'message' => 'Tên đăng nhập hoặc mật khẩu không đúng'];
    }

    // Kiểm tra user đã tồn tại
    private function userExists($username, $email) {
        $query = "SELECT id FROM " . $this->table . " WHERE username = :username OR email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        return $stmt->rowCount() > 0;
    }

    // Cập nhật last_login
    private function updateLastLogin($user_id) {
        $query = "UPDATE " . $this->table . " SET last_login = NOW() WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
    }

    // Tạo session
    private function createSession($user_id) {
        // Xóa session cũ
        $this->cleanupExpiredSessions();
        
        $session_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $query = "INSERT INTO sessions (user_id, session_token, expires_at, ip_address, user_agent) 
                  VALUES (:user_id, :session_token, :expires_at, :ip_address, :user_agent)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':session_token', $session_token);
        $stmt->bindParam(':expires_at', $expires_at);
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->bindParam(':user_agent', $user_agent);
        
        if ($stmt->execute()) {
            return $session_token;
        }
        
        return false;
    }

    // Xác thực session
    public function validateSession($session_token) {
        $this->cleanupExpiredSessions();
        
        $query = "SELECT u.id, u.username, u.email, u.avatar_icon, u.display_name, 
                         u.is_admin, u.admin_badge, u.is_active 
                  FROM " . $this->table . " u 
                  JOIN sessions s ON u.id = s.user_id 
                  WHERE s.session_token = :session_token AND s.expires_at > NOW() AND u.is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':session_token', $session_token);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            return $stmt->fetch();
        }
        
        return false;
    }

    // Đăng xuất
    public function logout($session_token) {
        $query = "DELETE FROM sessions WHERE session_token = :session_token";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':session_token', $session_token);
        return $stmt->execute();
    }

    // Dọn dẹp session hết hạn
    private function cleanupExpiredSessions() {
        $query = "DELETE FROM sessions WHERE expires_at < NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
    }

    // Lấy thông tin user theo ID
    public function getUserById($user_id) {
        $query = "SELECT id, username, email, avatar_icon, display_name, 
                         is_admin, admin_badge, created_at, profile_bio 
                  FROM " . $this->table . " WHERE id = :user_id AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    // Cập nhật profile
    public function updateProfile($user_id, $display_name, $avatar_icon, $profile_bio = '') {
        $query = "UPDATE " . $this->table . " 
                  SET display_name = :display_name, avatar_icon = :avatar_icon, profile_bio = :profile_bio 
                  WHERE id = :user_id";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':display_name', $display_name);
        $stmt->bindParam(':avatar_icon', $avatar_icon);
        $stmt->bindParam(':profile_bio', $profile_bio);
        
        return $stmt->execute();
    }

    // Lấy danh sách avatar icons
    public function getAvatarIcons() {
        $query = "SELECT icon_name, icon_emoji, category FROM avatar_icons WHERE is_active = 1 ORDER BY category, icon_name";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }

    // Admin functions
    public function promoteUser($admin_id, $target_user_id, $badge) {
        if (!$this->isAdmin($admin_id)) {
            return ['success' => false, 'message' => 'Không có quyền admin'];
        }

        $query = "UPDATE " . $this->table . " SET admin_badge = :badge WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':badge', $badge);
        $stmt->bindParam(':user_id', $target_user_id);
        
        if ($stmt->execute()) {
            $this->logAdminAction($admin_id, 'promote_user', $target_user_id, null, "Promoted to: $badge");
            return ['success' => true, 'message' => 'Cập nhật quyền thành công'];
        }
        
        return ['success' => false, 'message' => 'Có lỗi xảy ra'];
    }

    public function banUser($admin_id, $target_user_id, $reason = '') {
        if (!$this->isAdmin($admin_id)) {
            return ['success' => false, 'message' => 'Không có quyền admin'];
        }

        $query = "UPDATE " . $this->table . " SET is_active = 0 WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $target_user_id);
        
        if ($stmt->execute()) {
            $this->logAdminAction($admin_id, 'ban_user', $target_user_id, null, $reason);
            return ['success' => true, 'message' => 'Đã cấm người dùng'];
        }
        
        return ['success' => false, 'message' => 'Có lỗi xảy ra'];
    }

    private function isAdmin($user_id) {
        $query = "SELECT is_admin FROM " . $this->table . " WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result && $result['is_admin'] == 1;
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
