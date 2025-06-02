<?php
session_start();
require_once '../../includes/db_connect.php';
require_once '../../includes/admin_auth.php';

// Set JSON header
header('Content-Type: application/json');

// Check authentication and admin status
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

// Verify admin status
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE user_id = ? AND is_active = 1");
    $stmt->execute([$_SESSION['user_id']]);
    if ($stmt->fetchColumn() == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        exit;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'list':
            $rewards = getRewards($pdo);
            echo json_encode(['success' => true, 'data' => $rewards]);
            break;
            
        case 'get':
            $rewardId = $_GET['id'] ?? 0;
            if (!$rewardId) {
                echo json_encode(['success' => false, 'message' => 'Reward ID required']);
                break;
            }
            
            $reward = getRewardById($pdo, $rewardId);
            if ($reward) {
                echo json_encode(['success' => true, 'data' => $reward]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Reward not found']);
            }
            break;
            
        case 'add':
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                echo json_encode(['success' => false, 'message' => 'Invalid input data']);
                break;
            }
            
            $result = addReward($input, $pdo);
            echo json_encode($result);
            break;
            
        case 'update':
            $rewardId = $_GET['id'] ?? 0;
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$rewardId || !$input) {
                echo json_encode(['success' => false, 'message' => 'Reward ID and data required']);
                break;
            }
            
            $input['reward_id'] = $rewardId;
            $result = updateReward($input, $pdo);
            echo json_encode($result);
            break;
            
        case 'toggle':
            $rewardId = $_GET['id'] ?? 0;
            $isActive = filter_var($_GET['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
            
            if (!$rewardId) {
                echo json_encode(['success' => false, 'message' => 'Reward ID required']);
                break;
            }
            
            $result = toggleReward($pdo, $rewardId, $isActive);
            echo json_encode($result);
            break;
            
        case 'delete':
            $rewardId = $_GET['id'] ?? 0;
            if (!$rewardId) {
                echo json_encode(['success' => false, 'message' => 'Reward ID required']);
                break;
            }
            
            $result = deleteReward($pdo, $rewardId);
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Rewards API error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

function getRewards($pdo) {
    try {
        // Check if rewards table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'rewards'");
        $stmt->execute();
        if (!$stmt->fetch()) {
            return [];
        }
        
        // Check if user_rewards table exists for redemption count
        $stmt = $pdo->prepare("SHOW TABLES LIKE 'user_rewards'");
        $stmt->execute();
        $hasUserRewards = $stmt->fetch();
        
        if ($hasUserRewards) {
            $sql = "
                SELECT 
                    r.reward_id,
                    r.name,
                    r.description,
                    r.points_cost,
                    r.category,
                    r.inventory,
                    r.is_active,
                    COUNT(ur.redemption_id) as total_redemptions
                FROM rewards r
                LEFT JOIN user_rewards ur ON r.reward_id = ur.reward_id
                GROUP BY r.reward_id
                ORDER BY r.created_at DESC
            ";
        } else {
            $sql = "
                SELECT 
                    r.reward_id,
                    r.name,
                    r.description,
                    r.points_cost,
                    r.category,
                    r.inventory,
                    r.is_active,
                    0 as total_redemptions
                FROM rewards r
                ORDER BY r.created_at DESC
            ";
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting rewards: " . $e->getMessage());
        return [];
    }
}

function getRewardById($pdo, $rewardId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM rewards WHERE reward_id = ?");
        $stmt->execute([$rewardId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting reward by ID: " . $e->getMessage());
        return null;
    }
}

function addReward($data, $pdo) {
    try {
        if (empty($data['name']) || empty($data['description']) || !isset($data['points_cost'])) {
            return ['success' => false, 'message' => 'Name, description, and points cost are required'];
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO rewards (name, description, points_cost, category, inventory, is_active, created_at) 
            VALUES (?, ?, ?, ?, ?, TRUE, NOW())
        ");
        $stmt->execute([
            $data['name'],
            $data['description'],
            (int)$data['points_cost'],
            $data['category'] ?: null,
            $data['inventory'] ? (int)$data['inventory'] : null
        ]);
        
        return ['success' => true, 'message' => 'Reward created successfully'];
    } catch (Exception $e) {
        error_log("Error adding reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create reward'];
    }
}

function updateReward($data, $pdo) {
    try {
        $stmt = $pdo->prepare("
            UPDATE rewards SET 
                name = ?, 
                description = ?, 
                points_cost = ?, 
                category = ?, 
                inventory = ?
            WHERE reward_id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['description'],
            (int)$data['points_cost'],
            $data['category'] ?: null,
            $data['inventory'] ? (int)$data['inventory'] : null,
            $data['reward_id']
        ]);
        
        return ['success' => true, 'message' => 'Reward updated successfully'];
    } catch (Exception $e) {
        error_log("Error updating reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update reward'];
    }
}

function toggleReward($pdo, $rewardId, $isActive) {
    try {
        $stmt = $pdo->prepare("UPDATE rewards SET is_active = ? WHERE reward_id = ?");
        $stmt->execute([$isActive ? 1 : 0, $rewardId]);
        
        $status = $isActive ? 'activated' : 'deactivated';
        return ['success' => true, 'message' => "Reward $status successfully"];
    } catch (Exception $e) {
        error_log("Error toggling reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to update reward status'];
    }
}

function deleteReward($pdo, $rewardId) {
    try {
        $stmt = $pdo->prepare("DELETE FROM rewards WHERE reward_id = ?");
        $stmt->execute([$rewardId]);
        
        return ['success' => true, 'message' => 'Reward deleted successfully'];
    } catch (Exception $e) {
        error_log("Error deleting reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to delete reward'];
    }
}
?>
