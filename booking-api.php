<?php
// booking-api.php - Simple backend for form submissions

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        $input = $_POST;
    }

    $response = [
        'success' => false,
        'message' => '',
        'data' => $input
    ];

    // Basic validation
    $required = ['name', 'email', 'phone', 'subject', 'message'];
    $missing = [];

    foreach ($required as $field) {
        if (empty($input[$field])) {
            $missing[] = $field;
        }
    }

    if (!empty($missing)) {
        $response['message'] = 'Missing required fields: ' . implode(', ', $missing);
        echo json_encode($response);
        exit;
    }

    // Validate email
    if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Invalid email address';
        echo json_encode($response);
        exit;
    }

    // In production, you would:
    // 1. Save to database
    // 2. Send email notifications
    // 3. Log the submission

    // For demo, we'll just simulate success
    $response['success'] = true;
    $response['message'] = 'Thank you for your message. We will contact you soon!';

    // Save to file (for demo purposes)
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'contact_form',
        'data' => $input
    ];

    file_put_contents('submissions.log', json_encode($logData) . PHP_EOL, FILE_APPEND);

    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?>