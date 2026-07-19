<?php
// ============================================
// quenchook.cfd - User-facing HWID Reset API
// ============================================
// Called from index.php form (no admin API key needed)
// Usage: user_reset.php?key=XXXX-XXXX-XXXX
// ============================================

require_once __DIR__ . '/stdfax.php';
require_once __DIR__ . '/keys.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$key = $_GET['key'] ?? '';

if (empty($key)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No key provided.']);
    exit;
}

// Validate key exists
$pairs_json = getKeyAndHWIDPairs($AdminKey);
$pairs = json_decode($pairs_json, true);

if (!is_array($pairs) || isset($pairs['error'])) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Failed to load key data.']);
    exit;
}

$found = false;
$index = -1;
foreach ($pairs as $i => $pair) {
    if ($pair['key'] === $key) {
        $found = true;
        $index = $i;
        break;
    }
}

if (!$found) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Invalid license key.']);
    exit;
}

// Check cooldown
$cooldown_file = __DIR__ . '/reset_cooldowns.json';
$cooldowns = [];
if (file_exists($cooldown_file)) {
    $cooldowns = json_decode(file_get_contents($cooldown_file), true) ?? [];
}

$now = time();
if (isset($cooldowns[$key])) {
    $elapsed = $now - $cooldowns[$key];
    if ($elapsed < $HWID_RESET_COOLDOWN) {
        $remaining = $HWID_RESET_COOLDOWN - $elapsed;
        $days = ceil($remaining / 86400);
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => "HWID reset is on cooldown. Try again in ~{$days} day(s)."
        ]);
        exit;
    }
}

// Clear the HWID
$old_hwid = $pairs[$index]['hwid'] ?? '';
$pairs[$index]['hwid'] = '';

// Update keys.php
$updated_json = json_encode(array_values($pairs));
$keys_php_content = '<?php' . PHP_EOL . '
function getKeyAndHWIDPairs($api_key) {
    if ($api_key !== \'' . $AdminKey . '\') {
        return json_encode(array(\'error\' => \'Invalid API key\'));
    }
    
    return \'' . $updated_json . '\';
}
?>';

file_put_contents(__DIR__ . '/keys.php', $keys_php_content);

// Save cooldown
$cooldowns[$key] = $now;
file_put_contents($cooldown_file, json_encode($cooldowns));

// Log to Discord webhook if configured
if (!empty($WebhookUrl)) {
    $embed = [
        'embeds' => [[
            'title' => 'HWID Reset (User)',
            'description' => "Key: `{$key}`\nOld HWID: `{$old_hwid}`",
            'color' => hexdec('FFA500'),
            'timestamp' => date('c'),
            'fields' => [
                ['name' => 'IP', 'value' => $_SERVER['REMOTE_ADDR'], 'inline' => true]
            ]
        ]]
    ];
    $ch = curl_init($WebhookUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($embed));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}

echo json_encode(['status' => 'success', 'message' => 'HWID reset successfully! You can now use your key on a new PC.']);
?>
