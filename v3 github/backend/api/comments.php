<?php
/**
 * API Comments Endpoints
 * Tạo bởi: MiniMax Agent
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

require_once '../classes/User.php';
require_once '../classes/Comment.php';
require_once '../classes/Section.php';

$user = new User();
$comment = new Comment();
$section = new Section();

$response = ['success' => false, 'message' => 'Invalid request'];

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Helper function để xác thực user
function authenticate($user) {
    $session_token = $_COOKIE['session_token'] ?? '';
    return $user->validateSession($session_token);
}

switch ($action) {
    case 'get_comments':
        $section_type = $_GET['section_type'] ?? '';
        $section_category = $_GET['section_category'] ?? '';
        $section_subject = $_GET['section_subject'] ?? null;
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = (int)($_GET['offset'] ?? 0);
        
        if (empty($section_type) || empty($section_category)) {
            $response = ['success' => false, 'message' => 'Thiếu thông tin section'];
            break;
        }

        if ($section_subject === '') {
            $section_subject = null;
        }

        $section_data = $section->getSection($section_type, $section_category, $section_subject);
        
        if ($section_data) {
            $comments = $comment->getCommentsBySection($section_data['id'], $limit, $offset);
            $stats = $comment->getCommentStats($section_data['id']);
            $section_reactions = $section->getSectionReactions($section_data['id']);
            
            // Lấy reaction của user hiện tại cho section nếu đã đăng nhập
            $user_section_reaction = null;
            $user_data = authenticate($user);
            if ($user_data) {
                $user_section_reaction = $section->getUserSectionReaction($user_data['id'], $section_data['id']);
                
                // Lấy reactions của user cho từng comment
                foreach ($comments as &$comment_item) {
                    $comment_item['user_reaction'] = $comment->getUserCommentReaction($user_data['id'], $comment_item['id']);
                    
                    foreach ($comment_item['replies'] as &$reply) {
                        $reply['user_reaction'] = $comment->getUserCommentReaction($user_data['id'], $reply['id']);
                    }
                }
            }
            
            $response = [
                'success' => true,
                'comments' => $comments,
                'stats' => $stats,
                'section' => $section_data,
                'section_reactions' => $section_reactions,
                'user_section_reaction' => $user_section_reaction
            ];
        } else {
            $response = ['success' => false, 'message' => 'Không tìm thấy section'];
        }
        break;

    case 'add_comment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_data = authenticate($user);
            
            if (!$user_data) {
                $response = ['success' => false, 'message' => 'Vui lòng đăng nhập để bình luận'];
                break;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $section_type = $data['section_type'] ?? '';
            $section_category = $data['section_category'] ?? '';
            $section_subject = $data['section_subject'] ?? null;
            $content = trim($data['content'] ?? '');
            $parent_comment_id = $data['parent_comment_id'] ?? null;

            if ($section_subject === '') {
                $section_subject = null;
            }
            
            if (empty($section_type) || empty($section_category) || empty($content)) {
                $response = ['success' => false, 'message' => 'Thiếu thông tin bắt buộc'];
                break;
            }

            $section_data = $section->getSection($section_type, $section_category, $section_subject);
            
            if ($section_data) {
                $response = $comment->addComment($user_data['id'], $section_data['id'], $content, $parent_comment_id);
            } else {
                $response = ['success' => false, 'message' => 'Không tìm thấy section'];
            }
        }
        break;

    case 'edit_comment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_data = authenticate($user);
            
            if (!$user_data) {
                $response = ['success' => false, 'message' => 'Vui lòng đăng nhập'];
                break;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $comment_id = $data['comment_id'] ?? 0;
            $new_content = trim($data['content'] ?? '');
            
            if (empty($comment_id) || empty($new_content)) {
                $response = ['success' => false, 'message' => 'Thiếu thông tin bắt buộc'];
                break;
            }

            $response = $comment->editComment($user_data['id'], $comment_id, $new_content);
        }
        break;

    case 'delete_comment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_data = authenticate($user);
            
            if (!$user_data) {
                $response = ['success' => false, 'message' => 'Vui lòng đăng nhập'];
                break;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            $comment_id = $data['comment_id'] ?? 0;
            
            if (empty($comment_id)) {
                $response = ['success' => false, 'message' => 'Thiếu ID bình luận'];
                break;
            }

            $is_admin = $user_data['is_admin'] == 1;
            $response = $comment->deleteComment($user_data['id'], $comment_id, $is_admin);
        }
        break;

    case 'react_comment':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_data = authenticate($user);
            
            if (!$user_data) {
                $response = ['success' => false, 'message' => 'Vui lòng đăng nhập để thả cảm xúc'];
                break;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $comment_id = $data['comment_id'] ?? 0;
            $reaction_type = $data['reaction_type'] ?? '';
            
            if (empty($comment_id) || empty($reaction_type)) {
                $response = ['success' => false, 'message' => 'Thiếu thông tin bắt buộc'];
                break;
            }

            $response = $comment->toggleCommentReaction($user_data['id'], $comment_id, $reaction_type);
        }
        break;

    case 'react_section':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $user_data = authenticate($user);
            
            if (!$user_data) {
                $response = ['success' => false, 'message' => 'Vui lòng đăng nhập để thả cảm xúc'];
                break;
            }

            $data = json_decode(file_get_contents('php://input'), true);
            
            $section_type = $data['section_type'] ?? '';
            $section_category = $data['section_category'] ?? '';
            $section_subject = $data['section_subject'] ?? null;
            $reaction_type = $data['reaction_type'] ?? '';

            if ($section_subject === '') {
                $section_subject = null;
            }
            
            if (empty($section_type) || empty($section_category) || empty($reaction_type)) {
                $response = ['success' => false, 'message' => 'Thiếu thông tin bắt buộc'];
                break;
            }

            $section_data = $section->getSection($section_type, $section_category, $section_subject);
            
            if ($section_data) {
                $response = $section->toggleSectionReaction($user_data['id'], $section_data['id'], $reaction_type);
            } else {
                $response = ['success' => false, 'message' => 'Không tìm thấy section'];
            }
        }
        break;

    case 'get_section_stats':
        $section_type = $_GET['section_type'] ?? '';
        $section_category = $_GET['section_category'] ?? '';
        $section_subject = $_GET['section_subject'] ?? null;

        if ($section_subject === '') {
            $section_subject = null;
        }
        
        if (empty($section_type) || empty($section_category)) {
            $response = ['success' => false, 'message' => 'Thiếu thông tin section'];
            break;
        }

        $section_data = $section->getSection($section_type, $section_category, $section_subject);
        
        if ($section_data) {
            $stats = $section->getSectionStats($section_data['id']);
            $reactions = $section->getSectionReactions($section_data['id']);
            
            $response = [
                'success' => true,
                'stats' => $stats,
                'reactions' => $reactions,
                'section' => $section_data
            ];
        } else {
            $response = ['success' => false, 'message' => 'Không tìm thấy section'];
        }
        break;

    default:
        $response = ['success' => false, 'message' => 'Action không hợp lệ'];
        break;
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>
