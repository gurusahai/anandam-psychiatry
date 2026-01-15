<?php
// maintenance.php - System Status Monitor

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Check if maintenance mode is enabled
$maintenance_mode = false;
$maintenance_file = __DIR__ . '/../.maintenance';

if (file_exists($maintenance_file)) {
    $maintenance_mode = true;
    $maintenance_until = file_get_contents($maintenance_file);
}

// Get system status
$status = [
    'status' => $maintenance_mode ? 'maintenance' : 'online',
    'timestamp' => date('Y-m-d H:i:s'),
    'server' => [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown'
    ],
    'services' => [
        'website' => checkWebsiteStatus(),
        'database' => checkDatabaseStatus(),
        'email' => checkEmailService(),
        'calendly' => checkCalendlyService()
    ],
    'statistics' => [
        'contact_submissions' => countContactSubmissions(),
        'last_submission' => getLastSubmissionTime(),
        'uptime' => getSystemUptime()
    ]
];

if ($maintenance_mode) {
    $status['maintenance'] = [
        'enabled' => true,
        'until' => $maintenance_until,
        'message' => 'Website under maintenance. Please check back later.'
    ];
}

echo json_encode($status, JSON_PRETTY_PRINT);

function checkWebsiteStatus() {
    return 'online';
}

function checkDatabaseStatus() {
    try {
        $config = require __DIR__ . '/config/database.php';
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']}",
            $config['username'],
            $config['password']
        );
        return $pdo->query('SELECT 1') ? 'online' : 'offline';
    } catch (Exception $e) {
        return 'offline';
    }
}

function checkEmailService() {
    return function_exists('mail') ? 'configured' : 'not_configured';
}

function checkCalendlyService() {
    $url = 'https://calendly.com';
    $headers = @get_headers($url);
    return $headers && strpos($headers[0], '200') ? 'online' : 'unreachable';
}

function countContactSubmissions() {
    $log_file = __DIR__ . '/../backups/contact_submissions.log';
    if (file_exists($log_file)) {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return count($lines);
    }
    return 0;
}

function getLastSubmissionTime() {
    $log_file = __DIR__ . '/../backups/contact_submissions.log';
    if (file_exists($log_file)) {
        $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $last = end($lines);
        $data = json_decode($last, true);
        return $data['timestamp'] ?? 'never';
    }
    return 'never';
}

function getSystemUptime() {
    if (function_exists('sys_getloadavg')) {
        $load = sys_getloadavg();
        return [
            'load_1min' => $load[0],
            'load_5min' => $load[1],
            'load_15min' => $load[2]
        ];
    }
    return 'unavailable';
}
?>