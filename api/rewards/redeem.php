<?php
header("Content-Type: application/json");
require_once '../../includes/rewards.php';
require_once '../../includes/auth.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$rewardSystem = new RewardSystem();

$result = $rewardSystem->redeemReward(
    $_SESSION['user_id'],
    $input['reward_id']
);

echo json_encode($result);
?>
