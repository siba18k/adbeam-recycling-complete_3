<?php
require_once 'db_connect.php';

class RewardSystem {
    
    public function getAvailableRewards() {
        global $pdo;
        
        $stmt = $pdo->prepare("
            SELECT reward_id, name, description, points_cost, image_url, 
                   is_active, category, inventory, created_at, updated_at
            FROM rewards 
            ORDER BY created_at DESC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function addReward($name, $description, $pointsCost, $category, $inventory = null) {
        global $pdo;
        
        $stmt = $pdo->prepare("
            INSERT INTO rewards (name, description, points_cost, category, inventory, is_active, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([$name, $description, $pointsCost, $category, $inventory]);
        
        return $pdo->lastInsertId();
    }
    
    public function updateReward($rewardId, $data) {
        global $pdo;
        
        $updateFields = [];
        $params = [];
        
        if (isset($data['name'])) {
            $updateFields[] = "name = ?";
            $params[] = $data['name'];
        }
        if (isset($data['description'])) {
            $updateFields[] = "description = ?";
            $params[] = $data['description'];
        }
        if (isset($data['points_cost'])) {
            $updateFields[] = "points_cost = ?";
            $params[] = (int)$data['points_cost'];
        }
        if (isset($data['category'])) {
            $updateFields[] = "category = ?";
            $params[] = $data['category'];
        }
        if (isset($data['inventory'])) {
            $updateFields[] = "inventory = ?";
            $params[] = $data['inventory'] ? (int)$data['inventory'] : null;
        }
        if (isset($data['is_active'])) {
            $updateFields[] = "is_active = ?";
            $params[] = $data['is_active'] ? 1 : 0;
        }
        
        if (!empty($updateFields)) {
            $updateFields[] = "updated_at = NOW()";
            $params[] = $rewardId;
            $sql = "UPDATE rewards SET " . implode(', ', $updateFields) . " WHERE reward_id = ?";
            $stmt = $pdo->prepare($sql);
            return $stmt->execute($params);
        }
        
        return false;
    }
    
    public function toggleRewardStatus($rewardId, $isActive) {
        global $pdo;
        
        $stmt = $pdo->prepare("UPDATE rewards SET is_active = ?, updated_at = NOW() WHERE reward_id = ?");
        return $stmt->execute([$isActive ? 1 : 0, $rewardId]);
    }
    
    public function deleteReward($rewardId) {
        global $pdo;
        
        // Check if reward has been redeemed
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_rewards WHERE reward_id = ?");
        $stmt->execute([$rewardId]);
        $redemptionCount = $stmt->fetchColumn();
        
        if ($redemptionCount > 0) {
            // Don't delete, just deactivate
            return $this->toggleRewardStatus($rewardId, false);
        } else {
            // Safe to delete
            $stmt = $pdo->prepare("DELETE FROM rewards WHERE reward_id = ?");
            return $stmt->execute([$rewardId]);
        }
    }
    
    public function redeemReward($userId, $rewardId) {
        global $pdo;
        
        $pdo->beginTransaction();
        try {
            // Check if reward exists and is active
            $stmt = $pdo->prepare("SELECT points_cost, inventory FROM rewards WHERE reward_id = ? AND is_active = 1");
            $stmt->execute([$rewardId]);
            $reward = $stmt->fetch();
            
            if (!$reward) {
                throw new Exception("Reward not found or inactive");
            }
            
            // Check inventory
            if ($reward['inventory'] !== null && $reward['inventory'] <= 0) {
                throw new Exception("Reward out of stock");
            }
            
            // Check user points
            $stmt = $pdo->prepare("SELECT points_balance FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $userPoints = $stmt->fetchColumn();
            
            if ($userPoints < $reward['points_cost']) {
                throw new Exception("Insufficient points");
            }
            
            // Generate redemption code
            $redemptionCode = strtoupper(substr(md5(uniqid()), 0, 8));
            
            // Create redemption record
            $stmt = $pdo->prepare("
                INSERT INTO user_rewards (user_id, reward_id, redemption_code, redeemed_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$userId, $rewardId, $redemptionCode]);
            
            // Deduct points from user
            $stmt = $pdo->prepare("UPDATE users SET points_balance = points_balance - ? WHERE user_id = ?");
            $stmt->execute([$reward['points_cost'], $userId]);
            
            // Update inventory if applicable
            if ($reward['inventory'] !== null) {
                $stmt = $pdo->prepare("UPDATE rewards SET inventory = inventory - 1 WHERE reward_id = ?");
                $stmt->execute([$rewardId]);
            }
            
            $pdo->commit();
            return $redemptionCode;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
?>
