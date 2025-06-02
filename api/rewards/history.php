<?php
header("Content-Type: application/json");
require_once '../../includes/rewards.php';
require_once '../../includes/auth.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$rewardSystem = new RewardSystem();
echo json_encode([
    'success' => true,
    'history' => $rewardSystem->getUserHistory($_SESSION['user_id'])
]);
?>
