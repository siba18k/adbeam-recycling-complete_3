<?php
header("Content-Type: application/json");
require_once '../../includes/rewards.php';

$rewardSystem = new RewardSystem();
echo json_encode([
    'success' => true,
    'categories' => $rewardSystem->getRewardCategories()
]);
?>
