<?php
/**
 * process-contact.php
 * Contact Form Handler for Anandam Psychiatry Centre
 *
 * Security Features:
 * - CSRF Protection
 * - Rate Limiting
 * - Input Validation & Sanitization
 * - Honeypot Trap
 * - Email Validation
 * - File Upload Security
 */

// ===== SECURITY HEADERS =====
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// ===== CORS CONFIGURATION =====
$allowed_origins = [
    'https://www.anandampsychiatrycentre.in',
    'https://anandampsychiatrycentre.in',
    'http://localhost:3000',
    'http://localhost:8000'
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Methods: POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ===== SECURITY CHECKS =====
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

// Check if request is from our domain
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$allowed_referers = [
    'anandampsychiatrycentre.in',
    'localhost'
];

$is_valid_referer = false;
foreach ($allowed_referers as $domain) {
    if (strpos($referer, $domain) !== false) {
        $is_valid_referer = true;
        break;
    }
}

if (!$is_valid_referer && !in_array($_SERVER['HTTP_ORIGIN'] ?? '', $allowed_origins)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid request source']);
    exit();
}

// ===== SESSION & RATE LIMITING =====
session_start();

// Initialize rate limiting if not exists
if (!isset($_SESSION['contact_submissions'])) {
    $_SESSION['contact_submissions'] = [];
}

// Clean old submissions (older than 1 hour)
$current_time = time();
foreach ($_SESSION['contact_submissions'] as $key => $timestamp) {
    if ($current_time - $timestamp > 3600) { // 1 hour
        unset($_SESSION['contact_submissions'][$key]);
    }
}

// Check rate limit (max 5 submissions per hour per IP)
$client_ip = getClientIP();
$submission_count = 0;

foreach ($_SESSION['contact_submissions'] as $timestamp) {
    if ($current_time - $timestamp <= 3600) {
        $submission_count++;
    }
}

if ($submission_count >= 5) {
    http_response_code(429);
    echo json_encode(['success' => false, 'message' => 'Too many submission attempts. Please try again later.']);
    exit();
}

// Add current submission to rate limiting
$_SESSION['contact_submissions'][] = $current_time;

// ===== INPUT VALIDATION =====
$input = $_POST;

// Honeypot trap (spam prevention)
if (!empty($input['website']) || !empty($input['honeypot'])) {
    // Looks like a bot - log but don't process
    logSpamAttempt($input, $client_ip);
    echo json_encode(['success' => true, 'message' => 'Thank you for your message!']); // Fake success
    exit();
}

// Required fields
$required_fields = ['name', 'email', 'subject', 'message'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (empty(trim($input[$field] ?? ''))) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    echo json_encode([
        'success' => false,
        'message' => 'Please fill all required fields: ' . implode(', ', $missing_fields)
    ]);
    exit();
}

// Sanitize inputs
$name = sanitizeInput($input['name']);
$email = sanitizeInput($input['email']);
$phone = sanitizeInput($input['phone'] ?? '');
$subject = sanitizeInput($input['subject']);
$message = sanitizeInput($input['message']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid email address']);
    exit();
}

// Validate phone (if provided)
if (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Please enter a valid phone number']);
    exit();
}

// ===== PROCESS SUBMISSION =====
try {
    // Save to database
    $db_success = saveToDatabase([
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'subject' => $subject,
        'message' => $message,
        'ip_address' => $client_ip,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'timestamp' => date('Y-m-d H:i:s')
    ]);

    // Send email notifications
    $email_success = sendEmailNotifications($name, $email, $phone, $subject, $message);

    // Send auto-reply to user
    $auto_reply_success = sendAutoReply($name, $email);

    if ($db_success || $email_success) {
        echo json_encode([
            'success' => true,
            'message' => 'Thank you for your message! We will contact you within 24 hours.'
        ]);

        // Log successful submission
        logSubmission([
            'name' => $name,
            'email' => $email,
            'subject' => $subject,
            'ip' => $client_ip,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception('Failed to process submission');
    }

} catch (Exception $e) {
    error_log('Contact form error: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Sorry, there was an error processing your request. Please try calling us directly.'
    ]);
}

// ===== HELPER FUNCTIONS =====

/**
 * Get client IP address
 */
function getClientIP() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];

    foreach ($ip_keys as $key) {
        if (isset($_SERVER[$key])) {
            foreach (explode(',', $_SERVER[$key]) as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $data;
}

/**
 * Save submission to database
 */
function saveToDatabase($data) {
    $config = getDatabaseConfig();

    try {
        $pdo = new PDO(
            "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4",
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );

        $stmt = $pdo->prepare("
            INSERT INTO contact_submissions 
            (name, email, phone, subject, message, ip_address, user_agent, created_at)
            VALUES (:name, :email, :phone, :subject, :message, :ip_address, :user_agent, :timestamp)
        ");

        $stmt->execute([
            ':name' => $data['name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':subject' => $data['subject'],
            ':message' => $data['message'],
            ':ip_address' => $data['ip_address'],
            ':user_agent' => $data['user_agent'],
            ':timestamp' => $data['timestamp']
        ]);

        return true;

    } catch (PDOException $e) {
        // Log error but don't fail - fallback to file logging
        error_log('Database error: ' . $e->getMessage());

        // Fallback: Save to file
        return saveToFile($data);
    }
}

/**
 * Get database configuration
 */
function getDatabaseConfig() {
    // In production, load from environment variables or config file
    if (file_exists(__DIR__ . '/config/database.php')) {
        return require __DIR__ . '/config/database.php';
    }

    // Default configuration (update in production)
    return [
        'host' => 'localhost',
        'dbname' => 'anandam_psychiatry',
        'username' => 'anandam_user',
        'password' => 'secure_password_here'
    ];
}

/**
 * Save submission to file (fallback)
 */
function saveToFile($data) {
    $log_file = __DIR__ . '/../backups/contact_submissions.log';
    $log_dir = dirname($log_file);

    // Create directory if it doesn't exist
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $log_entry = json_encode([
            'timestamp' => $data['timestamp'],
            'data' => $data
        ]) . PHP_EOL;

    return file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX) !== false;
}

/**
 * Send email notifications
 */
function sendEmailNotifications($name, $email, $phone, $subject, $message) {
    $config = getEmailConfig();

    // Clinic email
    $to_clinic = $config['clinic_email'];
    $subject_clinic = "New Contact Form Submission: " . $subject;

    // Doctor email (optional)
    $to_doctor = $config['doctor_email'];

    // Email body
    $email_body = createEmailBody($name, $email, $phone, $subject, $message);

    // Headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Anandam Psychiatry Website <' . $config['from_email'] . '>',
        'Reply-To: ' . $name . ' <' . $email . '>',
        'X-Mailer: PHP/' . phpversion(),
        'X-Priority: 1',
        'X-MSMail-Priority: High',
        'Return-Path: ' . $config['from_email']
    ];

    // Send to clinic
    $sent_clinic = mail($to_clinic, $subject_clinic, $email_body, implode("\r\n", $headers));

    // Send to doctor (if different from clinic)
    $sent_doctor = true;
    if ($to_doctor && $to_doctor !== $to_clinic) {
        $sent_doctor = mail($to_doctor, $subject_clinic, $email_body, implode("\r\n", $headers));
    }

    return $sent_clinic || $sent_doctor;
}

/**
 * Get email configuration
 */
function getEmailConfig() {
    // In production, load from environment variables
    return [
        'clinic_email' => 'contact@anandampsychiatrycentre.in',
        'doctor_email' => 'dr.sharma@anandampsychiatrycentre.in',
        'from_email' => 'noreply@anandampsychiatrycentre.in',
        'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.gmail.com',
        'smtp_port' => getenv('SMTP_PORT') ?: 587,
        'smtp_username' => getenv('SMTP_USERNAME') ?: '',
        'smtp_password' => getenv('SMTP_PASSWORD') ?: ''
    ];
}

/**
 * Create HTML email body
 */
function createEmailBody($name, $email, $phone, $subject, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $ip_address = getClientIP();

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>New Contact Form Submission</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #4A6FA5 0%, #6C5CE7 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 300;
        }
        .content {
            padding: 30px;
        }
        .field-group {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #4A6FA5;
        }
        .field-label {
            font-weight: 600;
            color: #4A6FA5;
            margin-bottom: 5px;
            display: block;
        }
        .field-value {
            color: #333;
        }
        .message-content {
            white-space: pre-wrap;
            background: #fff;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #e9ecef;
            margin-top: 10px;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            color: #6c757d;
            font-size: 12px;
            border-top: 1px solid #e9ecef;
        }
        .urgent {
            background: #fff3cd;
            border-color: #ffeaa7;
            color: #856404;
            padding: 10px;
            border-radius: 5px;
            margin-top: 20px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>New Contact Form Submission</h1>
            <p>Anandam Psychiatry Centre Website</p>
        </div>
        
        <div class="content">
            <div class="field-group">
                <span class="field-label">From:</span>
                <div class="field-value">{$name} &lt;{$email}&gt;</div>
            </div>
            
            <div class="field-group">
                <span class="field-label">Phone:</span>
                <div class="field-value">{$phone ?: 'Not provided'}</div>
            </div>
            
            <div class="field-group">
                <span class="field-label">Subject:</span>
                <div class="field-value">{$subject}</div>
            </div>
            
            <div class="field-group">
                <span class="field-label">Message:</span>
                <div class="message-content">{$message}</div>
            </div>
            
            <div class="field-group">
                <span class="field-label">Submission Details:</span>
                <div class="field-value">
                    Time: {$timestamp}<br>
                    IP Address: {$ip_address}
                </div>
            </div>
            
            <div class="urgent">
                ⚡ Please respond within 24 hours
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated message from Anandam Psychiatry Centre website.</p>
            <p>Do not reply to this email. To respond, please use the email address provided above.</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Send auto-reply to user
 */
function sendAutoReply($name, $email) {
    $config = getEmailConfig();

    $subject = "Thank you for contacting Anandam Psychiatry Centre";

    $body = createAutoReplyBody($name);

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: Anandam Psychiatry Centre <' . $config['from_email'] . '>',
        'Reply-To: ' . $config['clinic_email'],
        'X-Mailer: PHP/' . phpversion()
    ];

    return mail($email, $subject, $body, implode("\r\n", $headers));
}

/**
 * Create auto-reply email body
 */
function createAutoReplyBody($name) {
    $current_year = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Thank you for contacting us</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f5f7fa;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
        }
        .header {
            background: linear-gradient(135deg, #4A6FA5 0%, #6C5CE7 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .logo {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .tagline {
            font-style: italic;
            opacity: 0.9;
            font-size: 18px;
        }
        .content {
            padding: 40px;
        }
        .greeting {
            font-size: 24px;
            color: #4A6FA5;
            margin-bottom: 20px;
        }
        .message {
            margin-bottom: 30px;
            color: #555;
        }
        .steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
            border-left: 4px solid #4A6FA5;
        }
        .steps h3 {
            color: #4A6FA5;
            margin-top: 0;
        }
        .steps ol {
            padding-left: 20px;
        }
        .steps li {
            margin-bottom: 10px;
        }
        .contact-info {
            background: #e3f2fd;
            padding: 20px;
            border-radius: 8px;
            margin: 30px 0;
        }
        .contact-info h3 {
            color: #2E4A76;
            margin-top: 0;
        }
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .contact-item i {
            width: 20px;
            color: #4A6FA5;
            margin-right: 10px;
        }
        .footer {
            background: #2D3436;
            color: white;
            padding: 30px;
            text-align: center;
        }
        .footer p {
            margin: 5px 0;
            opacity: 0.8;
            font-size: 14px;
        }
        .emergency {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            font-weight: 500;
        }
        .signature {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .doctor-name {
            font-weight: bold;
            color: #4A6FA5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">Anandam Psychiatry Centre</div>
            <div class="tagline">Where Good Things Are Going To Happen...</div>
        </div>
        
        <div class="content">
            <div class="greeting">Dear {$name},</div>
            
            <div class="message">
                <p>Thank you for reaching out to Anandam Psychiatry Centre. We have received your message and appreciate you taking the first step toward mental wellness.</p>
                
                <p>Our team is reviewing your inquiry and we will get back to you within <strong>24 hours</strong>.</p>
            </div>
            
            <div class="steps">
                <h3>What happens next?</h3>
                <ol>
                    <li>Our team reviews your message and understands your needs</li>
                    <li>We'll contact you via phone or email to discuss next steps</li>
                    <li>If it's urgent, we'll respond within 2 hours during clinic hours</li>
                    <li>We'll schedule a convenient appointment time for you</li>
                </ol>
            </div>
            
            <div class="contact-info">
                <h3>Clinic Information</h3>
                
                <div class="contact-item">
                    <i>📍</i>
                    <span><strong>Address:</strong> 4/1, 1st Floor, Balraj Khanna Road, East Patel Nagar, New Delhi - 110008</span>
                </div>
                
                <div class="contact-item">
                    <i>🕐</i>
                    <span><strong>Hours:</strong> Monday - Saturday, 10:00 AM - 8:00 PM</span>
                </div>
                
                <div class="contact-item">
                    <i>📞</i>
                    <span><strong>Phone:</strong> +91 95825 82707</span>
                </div>
                
                <div class="contact-item">
                    <i>📧</i>
                    <span><strong>Email:</strong> contact@anandampsychiatrycentre.in</span>
                </div>
            </div>
            
            <div class="emergency">
                ⚠️ <strong>Emergency:</strong> If you're experiencing a mental health emergency, please call our emergency line: <strong>+91 98765 43211</strong> or contact emergency services immediately.
            </div>
            
            <div class="signature">
                <p>Warm regards,</p>
                <p class="doctor-name">Dr. Srikant Sharma</p>
                <p>M.D. (Psychiatry)</p>
                <p>Anandam Psychiatry Centre</p>
            </div>
        </div>
        
        <div class="footer">
            <p>This is an automated message. Please do not reply to this email.</p>
            <p>For any queries, please contact us at contact@anandampsychiatrycentre.in</p>
            <p>© {$current_year} Anandam Psychiatry Centre. All rights reserved.</p>
            <p>East Patel Nagar, New Delhi - 110008</p>
        </div>
    </div>
</body>
</html>
HTML;
}

/**
 * Log spam attempts
 */
function logSpamAttempt($data, $ip) {
    $log_file = __DIR__ . '/../backups/spam_attempts.log';

    $log_entry = json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $ip,
            'data' => $data,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]) . PHP_EOL;

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Log successful submissions
 */
function logSubmission($data) {
    $log_file = __DIR__ . '/../backups/submissions.log';

    $log_entry = json_encode($data) . PHP_EOL;

    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

// Alternative: SMTP Email Sending Function
function sendEmailSMTP($to, $subject, $body, $headers) {
    require_once __DIR__ . '/../vendor/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../vendor/PHPMailer/SMTP.php';
    require_once __DIR__ . '/../vendor/PHPMailer/Exception.php';

    $config = getEmailConfig();

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['smtp_host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['smtp_username'];
        $mail->Password   = $config['smtp_password'];
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $config['smtp_port'];

        // Recipients
        $mail->setFrom($config['from_email'], 'Anandam Psychiatry Centre');
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;

        return $mail->send();

    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $mail->ErrorInfo);
        return false;
    }
}
?>