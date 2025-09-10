<?php
// Enable Xdebug profiling and tracing
if (function_exists('xdebug_enable')) {
    xdebug_enable();
}

// Set Xdebug to break at the first line
if (function_exists('xdebug_break')) {
    xdebug_break();
}

require_once dirname(__DIR__, 4) . '/wp-load.php';

// Simulate the test scenario
echo "Starting debug script...\n";

// Clear rank cache first
echo "Clearing rank cache...\n";
delete_transient('canna_rank_structure_dtos');

// Create a test user
echo "Creating test user...\n";
$uniqueEmail = 'debug_rank_test_' . time() . '@example.com';
$user_id = wp_create_user('debug_rank_user', 'test-password', $uniqueEmail);

if (is_wp_error($user_id)) {
    echo "Error creating user: " . $user_id->get_error_message() . "\n";
    exit(1);
}

echo "User created with ID: " . $user_id . "\n";

// Set user points to 4800
update_user_meta($user_id, '_canna_points_balance', 100);
update_user_meta($user_id, '_canna_lifetime_points', 4800);

echo "User points set to 4800 lifetime points\n";

// Get user rank before claiming points
echo "Checking user rank before claiming points...\n";
$userRepo = new \CannaRewards\Repositories\UserRepository();
$rankService = new \CannaRewards\Services\RankService($userRepo);

try {
    $user_rank_dto = $rankService->getUserRank($user_id);
    echo "User rank before: " . $user_rank_dto->key . "\n";
} catch (Exception $e) {
    echo "Error getting user rank: " . $e->getMessage() . "\n";
    exit(1);
}

// Simulate the claim action
echo "Simulating product scan claim...\n";
try {
    // This is where the timeout is likely occurring
    $rewardCodeRepo = new \CannaRewards\Repositories\RewardCodeRepository();
    $productRepo = new \CannaRewards\Repositories\ProductRepository();
    $logRepo = new \CannaRewards\Repositories\ActionLogRepository();
    $actionLogService = new \CannaRewards\Services\ActionLogService($logRepo);
    $economyService = new \CannaRewards\Services\EconomyService();
    $redeemHandler = new \CannaRewards\Commands\RedeemRewardCommandHandler(/* dependencies */);
    
    $handler = new \CannaRewards\Commands\ProcessProductScanCommandHandler(
        $rewardCodeRepo,
        $productRepo,
        $logRepo,
        $userRepo,
        $economyService,
        $actionLogService,
        $redeemHandler
    );
    
    // Create test QR code first
    global $wpdb;
    $testCode = 'PWT-RANKUP-AUDIT';
    $wpdb->delete($wpdb->prefix . 'canna_reward_codes', ['code' => $testCode]);
    $wpdb->insert($wpdb->prefix . 'canna_reward_codes', [
        'code' => $testCode,
        'sku'  => 'PWT-001',
    ]);
    
    // Process the scan
    $command = new \CannaRewards\Commands\ProcessProductScanCommand($user_id, $testCode);
    $result = $handler->handle($command);
    
    echo "Claim result: " . print_r($result, true) . "\n";
    
} catch (Exception $e) {
    echo "Error during claim: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

echo "Script completed successfully.\n";
?>