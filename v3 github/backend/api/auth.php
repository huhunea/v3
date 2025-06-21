<?php
/**
 * API Authentication Endpoints
 * Tạo bởi: MiniMax Agent
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../classes/User.php';

$user = new User();
$response = ['success' => false, 'message' => 'Invalid request'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'register':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = $data['password'] ?? '';
            $display_name = trim($data['display_name'] ?? '');
            $avatar_icon = $data['avatar_icon'] ?? 'user';
            
            if (empty($username) || empty($email) || empty($password) || empty($display_name)) {
                $response = ['success' => false, 'message' => 'Vui lòng điền đầy đủ thông tin'];
            } else {
                $response = $user->register($username, $email, $password, $display_name, $avatar_icon);
            }
        }
        break;

    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $username = trim($data['username'] ?? '');
            $password = $data['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $response = ['success' => false, 'message' => 'Vui lòng điền tên đăng nhập và mật khẩu'];
            } else {
                $response = $user->login($username, $password);
                
                if ($response['success']) {
                    // Set cookie
                    setcookie('session_token', $response['session_token'], time() + (30 * 24 * 60 * 60), '/', '', false, true);
                }
            }
        }
        break;

    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $session_token = $_COOKIE['session_token'] ?? '';
            
            if ($session_token) {
                $user->logout($session_token);
                setcookie('session_token', '', time() - 3600, '/', '', false, true);
            }
            
            $response = ['success' => true, 'message' => 'Đã đăng xuất'];
        }
        break;

    case 'verify':
        $session_token = $_COOKIE['session_token'] ?? $_GET['token'] ?? '';
        
        if ($session_token) {
            $user_data = $user->validateSession($session_token);
            
            if ($user_data) {
                $response = [
                    'success' => true,
                    'user' => $user_data
                ];
            } else {
                $response = ['success' => false, 'message' => 'Session không hợp lệ'];
            }
        } else {
            $response = ['success' => false, 'message' => 'Không tìm thấy session'];
        }
        break;

    case 'get_avatars':
        $avatars = $user->getAvatarIcons();
        $response = [
            'success' => true,
            'avatars' => $avatars
        ];
        break;

    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $session_token = $_COOKIE['session_token'] ?? '';
            $user_data = $user->validateSession($session_token);
            
            if ($user_data) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $display_name = trim($data['display_name'] ?? '');
                $avatar_icon = $data['avatar_icon'] ?? 'user';
                $profile_bio = trim($data['profile_bio'] ?? '');
                
                if (empty($display_name)) {
                    $response = ['success' => false, 'message' => 'Tên hiển thị không được để trống'];
                } else {
                    $result = $user->updateProfile($user_data['id'], $display_name, $avatar_icon, $profile_bio);
                    
                    if ($result) {
                        $response = ['success' => true, 'message' => 'Cập nhật profile thành công'];
                    } else {
                        $response = ['success' => false, 'message' => 'Có lỗi xảy ra khi cập nhật profile'];
                    }
                }
            } else {
                $response = ['success' => false, 'message' => 'Vui lòng đăng nhập'];
            }
        }
        break;

    case 'get_profile':
        $session_token = $_COOKIE['session_token'] ?? '';
        $user_data = $user->validateSession($session_token);
        
        if ($user_data) {
            $profile = $user->getUserById($user_data['id']);
            $response = [
                'success' => true,
                'profile' => $profile
            ];
        } else {
            $response = ['success' => false, 'message' => 'Vui lòng đăng nhập'];
        }
        break;

    // Admin functions
    case 'promote_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $session_token = $_COOKIE['session_token'] ?? '';
            $admin_data = $user->validateSession($session_token);
            
            if ($admin_data && $admin_data['is_admin']) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $target_user_id = $data['user_id'] ?? 0;
                $badge = $data['badge'] ?? '';
                
                $response = $user->promoteUser($admin_data['id'], $target_user_id, $badge);
            } else {
                $response = ['success' => false, 'message' => 'Không có quyền admin'];
            }
        }
        break;

    case 'ban_user':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $session_token = $_COOKIE['session_token'] ?? '';
            $admin_data = $user->validateSession($session_token);
            
            if ($admin_data && $admin_data['is_admin']) {
                $data = json_decode(file_get_contents('php://input'), true);
                
                $target_user_id = $data['user_id'] ?? 0;
                $reason = $data['reason'] ?? '';
                
                $response = $user->banUser($admin_data['id'], $target_user_id, $reason);
            } else {
                $response = ['success' => false, 'message' => 'Không có quyền admin'];
            }
        }
        break;

    default:
        $response = ['success' => false, 'message' => 'Action không hợp lệ'];
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
