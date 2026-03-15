<?php
// Common functions for ServiceLink
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Correctly load PHPMailer files from the includes/PHPMailer directory
require_once __DIR__ . '/PHPMailer/Exception.php';
require_once __DIR__ . '/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/PHPMailer/SMTP.php';

/**
 * Get user dashboard statistics
 */
function getUserDashboardStats($pdo, $user_id, $user_role, $department_id)
{
    $stats = [];

    try {
        if ($user_role == 'user') {
            // User statistics
            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_tickets
                FROM tickets 
                WHERE requester_id = ?
            ");
            $stmt->execute([$user_id]);
            $stats = $stmt->fetch();
        } else {
            // Staff/Admin statistics
            $where_clause = "";
            $params = [];

            if ($user_role == 'department_admin' || $user_role == 'staff') {
                $where_clause = "WHERE department_id = ?";
                $params[] = $department_id;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    COUNT(*) as total_tickets,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tickets,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tickets,
                    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_tickets,
                    SUM(CASE WHEN status = 'unresolved' THEN 1 ELSE 0 END) as unresolved_tickets,
                    SUM(CASE WHEN priority IN ('high', 'emergency') THEN 1 ELSE 0 END) as high_priority_tickets
                FROM tickets 
                $where_clause
            ");
            $stmt->execute($params);
            $stats = $stmt->fetch();

            // Get assigned tickets for staff
            if ($user_role == 'staff') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as assigned_tickets FROM tickets WHERE assigned_to = ?");
                $stmt->execute([$user_id]);
                $assigned = $stmt->fetch();
                $stats['assigned_tickets'] = $assigned['assigned_tickets'];
            }
        }
    } catch (PDOException $e) {
        // Return default stats on error
        $stats = [
            'total_tickets' => 0,
            'pending_tickets' => 0,
            'in_progress_tickets' => 0,
            'resolved_tickets' => 0,
            'unresolved_tickets' => 0,
            'high_priority_tickets' => 0
        ];
    }

    return $stats;
}

/**
 * Get recent tickets based on user role
 */
function getRecentTickets($pdo, $user_id, $user_role, $department_id, $limit = 10)
{
    try {
        $where_clause = "";
        $params = [];

        if ($user_role == 'user') {
            $where_clause = "WHERE t.requester_id = ?";
            $params[] = $user_id;
        } elseif ($user_role == 'department_admin' || $user_role == 'staff') {
            $where_clause = "WHERE t.department_id = ?";
            $params[] = $department_id;
        }

        $stmt = $pdo->prepare("
            SELECT t.*, 
                   CONCAT(u.first_name, ' ', u.last_name) as requester_name,
                   d.name as department_name
            FROM tickets t
            LEFT JOIN users u ON t.requester_id = u.id
            LEFT JOIN departments d ON t.department_id = d.id
            $where_clause
            ORDER BY t.created_at DESC
            LIMIT ?
        ");

        $params[] = $limit;
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get unread notifications for user
 */
function getUnreadNotifications($pdo, $user_id, $limit = 10)
{
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? AND is_read = 0 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        $stmt->execute([$user_id, $limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get priority color for badges
 */
function getPriorityColor($priority)
{
    switch ($priority) {
        case 'low':
            return 'success';
        case 'medium':
            return 'warning';
        case 'high':
            return 'danger';
        case 'emergency':
            return 'dark';
        default:
            return 'secondary';
    }
}

/**
 * Get status color for badges
 */
function getStatusColor($status)
{
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'in_progress':
            return 'info';
        case 'resolved':
            return 'success';
        case 'unresolved':
            return 'danger';
            // backward compatibility: map legacy statuses to current ones
        case 'new':
            return 'warning';
        case 'assigned':
        case 'on_hold':
            return 'info';
        case 'closed':
            return 'success';
        case 'reopen':
            return 'danger';
        default:
            return 'secondary';
    }
}

/**
 * Normalize legacy status values into the current set.
 */
function normalizeStatus($status)
{
    switch ($status) {
        case 'new':
        case 'assigned':
        case 'reopen':
        case 'on_hold':
        case 'open':
            return 'pending';
        case 'closed':
            return 'resolved';
        default:
            return $status;
    }
}

/**
 * Time ago function
 */
function timeAgo($datetime)
{
    $time = time() - strtotime($datetime);

    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time / 60) . ' min ago';
    if ($time < 86400) return floor($time / 3600) . ' hr ago';
    if ($time < 2592000) return floor($time / 86400) . ' days ago';
    if ($time < 31536000) return floor($time / 2592000) . ' months ago';

    return floor($time / 31536000) . ' years ago';
}

/**
 * Send notification to user
 */
function sendNotification($pdo, $user_id, $ticket_id, $title, $message, $type = 'system')
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, ticket_id, title, message, type) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $ticket_id, $title, $message, $type]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Upload file and return file info
 */
function uploadFile($file, $upload_dir = 'uploads/')
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Invalid parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            throw new RuntimeException('No file sent.');
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new RuntimeException('Exceeded filesize limit.');
        default:
            throw new RuntimeException('Unknown errors.');
    }

    if ($file['size'] > MAX_FILE_SIZE) {
        throw new RuntimeException('Exceeded filesize limit.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
        throw new RuntimeException('Invalid file format.');
    }

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $filename = sprintf(
        '%s_%s.%s',
        uniqid(),
        bin2hex(random_bytes(8)),
        $extension
    );

    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new RuntimeException('Failed to move uploaded file.');
    }

    return [
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_path' => $filepath,
        'file_size' => $file['size'],
        'mime_type' => $mime_type
    ];
}

/**
 * Check if user has permission
 */
function hasPermission($user_role, $required_role)
{
    $role_hierarchy = [
        'user' => 1,
        'staff' => 2,
        'department_admin' => 3,
        'admin' => 4
    ];

    return $role_hierarchy[$user_role] >= $role_hierarchy[$required_role];
}

/**
 * Sanitize input
 */
function sanitizeInput($input)
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate random password
 */
function generateRandomPassword($length = 12)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

/**
 * Log activity
 */
function logActivity($pdo, $user_id, $action, $details = null)
{
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $user_id,
            $action,
            $details,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Format file size
 */
function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

/**
 * Get user's full name
 */
function getUserFullName($pdo, $user_id)
{
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        return $result ? $result['full_name'] : 'Unknown User';
    } catch (PDOException $e) {
        return 'Unknown User';
    }
}

/**
 * Check if user can access ticket
 */
function canAccessTicket($pdo, $user_id, $user_role, $department_id, $ticket_id)
{
    try {
        $stmt = $pdo->prepare("SELECT requester_id, assigned_to, department_id FROM tickets WHERE id = ?");
        $stmt->execute([$ticket_id]);
        $ticket = $stmt->fetch();

        if (!$ticket) return false;

        // Admin can access all tickets
        if ($user_role == 'admin') return true;

        // Users can access their own tickets
        if ($user_role == 'user' && $ticket['requester_id'] == $user_id) return true;

        // Staff can access tickets in their department or assigned to them
        if (($user_role == 'staff' || $user_role == 'department_admin') &&
            ($ticket['department_id'] == $department_id || $ticket['assigned_to'] == $user_id)
        ) {
            return true;
        }

        return false;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get ticket priority options
 */
function getPriorityOptions()
{
    return [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'emergency' => 'Emergency'
    ];
}

/**
 * Get ticket status options
 */
function getStatusOptions()
{
    return [
        'pending' => 'Pending',
        'in_progress' => 'In Progress',
        'resolved' => 'Resolved',
        'unresolved' => 'Unresolved'
    ];
}

/**
 * Get smart troubleshooting recommendations based on ticket text
 */
function getSmartRecommendations($title, $description)
{
    $text = strtolower(trim(($title ?? '') . "\n" . ($description ?? '')));
    $recommendations = [
        'category' => 'general',
        'label' => 'General Tips',
        'steps' => [],
    ];

    if ($text === '') {
        return $recommendations;
    }

    // Network / Wi‑Fi issues
    if (preg_match('/wifi|wi-fi|internet|no connection|no network|cannot connect|can\'t connect/', $text)) {
        $recommendations['category'] = 'network';
        $recommendations['label'] = 'Network / Wi‑Fi';
        $recommendations['steps'] = [
            [
                'title' => 'Check other devices',
                'body'  => 'Confirm if other devices in the area can connect to the Wi‑Fi or internet. This helps identify if the issue is specific to one device or the whole network.',
            ],
            [
                'title' => 'Restart local network equipment',
                'body'  => 'If allowed, restart the router/access point and the affected device. Wait at least 30–60 seconds before turning equipment back on.',
            ],
            [
                'title' => 'Check physical connections',
                'body'  => 'Verify that LAN cables, power bricks, and switches for the network equipment are securely plugged in and powered.',
            ],
            [
                'title' => 'Check for campus / provider outage',
                'body'  => 'Check with the MIS/network status page or building admin to see if there is a known outage before escalating to the ISP.',
            ],
        ];

        return $recommendations;
    }

    // Printing issues
    if (preg_match('/printer|print\b|printing|cannot print|can\'t print/', $text)) {
        $recommendations['category'] = 'printer';
        $recommendations['label'] = 'Printing';
        $recommendations['steps'] = [
            [
                'title' => 'Check printer power and cables',
                'body'  => 'Make sure the printer is turned on, shows no hardware error lights, and USB/network cables are firmly connected.',
            ],
            [
                'title' => 'Restart printer and computer',
                'body'  => 'Restart the printer first, then restart the computer and try printing again.',
            ],
            [
                'title' => 'Verify correct printer selection',
                'body'  => 'Confirm that the correct printer is selected in the print dialog and that there are no paused or stuck jobs in the print queue.',
            ],
        ];

        return $recommendations;
    }

    // Performance / slow PC issues
    if (preg_match('/slow\b|lag|freeze|frozen|hangs|not responding|very slow/', $text)) {
        $recommendations['category'] = 'performance';
        $recommendations['label'] = 'Computer Performance';
        $recommendations['steps'] = [
            [
                'title' => 'Restart the device',
                'body'  => 'If possible, restart the affected PC or laptop to clear temporary issues.',
            ],
            [
                'title' => 'Close unused applications',
                'body'  => 'Close heavy or unused applications and browser tabs that may be consuming CPU or memory.',
            ],
            [
                'title' => 'Check for storage space',
                'body'  => 'Ensure the system drive has enough free space (ideally more than 10–15%).',
            ],
        ];

        return $recommendations;
    }

    // Default generic tips
    $recommendations['steps'] = [
        [
            'title' => 'Capture clear details',
            'body'  => 'Note the exact error message, when it started, and whether it happens on one device or multiple devices.',
        ],
        [
            'title' => 'Try a basic restart',
            'body'  => 'Restart the affected device or application if possible and confirm if the issue persists.',
        ],
    ];

    return $recommendations;
}

/**
 * Send email notification (Legacy)
 */
function sendEmailNotification($to, $subject, $message, $headers = null)
{
    if (!$headers) {
        $headers = "From: ServiceLink Support <servicelinknotif@gmail.com>\r\n";
        $headers .= "Reply-To: servicelinknotif@gmail.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    }

    return @mail($to, $subject, $message, $headers);
}

/**
 * Send client confirmation email
 */
function sendClientConfirmationEmail($client_email, $ticket_number, $tracking_code)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'servicelinknotif@gmail.com'; // Your Gmail address
        $mail->Password   = 'miqq nwhg hdmy pncf';      // The App Password you generated
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('servicelinknotif@gmail.com', 'ServiceLink Support');
        $mail->addAddress($client_email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "Service Request Received - Ticket #$ticket_number";

        // The email body
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6;'>
                <h2 style='color: #10b981;'>Service Request Received</h2>
                <p>Hello,</p>
                <p>We have successfully received your service request. Our technical team will review it shortly.</p>
                <div style='background-color: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #e9ecef;'>
                    <p style='margin: 0;'><strong>Ticket Number:</strong> $ticket_number</p>
                    <p style='margin: 5px 0 0 0;'><strong>Tracking Code:</strong> $tracking_code</p>
                </div>
                <p>You can track the real-time status of your request using the link below:</p>
                <p><a href='http://localhost/ticketai-main/track_ticket.php' style='background-color: #10b981; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Track My Ticket</a></p>
                <br>
                <p>Thank you,<br><strong>ServiceLink Support Team</strong></p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Send client completion/feedback email
 */
function sendClientCompletionEmail($client_email, $ticket_number, $status, $tracking_code, $title)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'servicelinknotif@gmail.com'; // Your Gmail address
        $mail->Password   = 'miqq nwhg hdmy pncf';      // The App Password you generated
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Recipients
        $mail->setFrom('servicelinknotif@gmail.com', 'ServiceLink Support');
        $mail->addAddress($client_email);

        // Content
        $mail->isHTML(true);
        $status_text = $status === 'resolved' ? 'Resolved' : 'Closed / Unresolved';
        $mail->Subject = "Ticket $status_text - Ticket #$ticket_number";

        // Build the URL for the feedback form
        $feedback_url = "http://localhost/ticketai-main/feedback.php?ticket=" . urlencode($ticket_number) . "&code=" . urlencode($tracking_code);
        $color = $status === 'resolved' ? '#10b981' : '#dc3545';

        // The email body
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; color: #333; line-height: 1.6; max-width: 600px; margin: 0 auto; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden;'>
                <div style='background-color: $color; color: white; padding: 20px; text-align: center;'>
                    <h2 style='margin: 0;'>Ticket $status_text</h2>
                </div>
                <div style='padding: 20px;'>
                    <p>Hello,</p>
                    <p>Your service request <strong>#$ticket_number</strong> ($title) has been marked as <strong>$status_text</strong> by our technical team.</p>
                    <p>We value your experience and are constantly trying to improve. Please take a quick moment to rate the service you received and leave any comments.</p>
                    <div style='text-align: center; margin: 35px 0;'>
                        <a href='$feedback_url' style='background-color: #10b981; color: white; padding: 14px 28px; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; display: inline-block;'>Submit Feedback & Rating</a>
                    </div>
                    <p style='font-size: 0.9em; color: #6b7280; margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 15px;'>If the issue persists, please reply directly to this email or submit a new request via our portal.</p>
                </div>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Completion email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

/**
 * Create breadcrumb navigation
 */
function createBreadcrumb($items)
{
    $breadcrumb = '<nav aria-label="breadcrumb"><ol class="breadcrumb">';

    $count = count($items);
    foreach ($items as $index => $item) {
        if ($index == $count - 1) {
            $breadcrumb .= '<li class="breadcrumb-item active" aria-current="page">' . htmlspecialchars($item['title']) . '</li>';
        } else {
            $breadcrumb .= '<li class="breadcrumb-item"><a href="' . htmlspecialchars($item['url']) . '">' . htmlspecialchars($item['title']) . '</a></li>';
        }
    }

    $breadcrumb .= '</ol></nav>';
    return $breadcrumb;
}

/**
 * Get display name for role (UI only; DB role unchanged)
 */
function getRoleDisplayName($role)
{
    switch ($role) {
        case 'department_admin':
            return 'Admin';
        case 'staff':
            return 'User';
        case 'superadmin':
            return 'Super Admin';
        case 'admin':
            return 'Admin';
        case 'user':
            return 'User';
        default:
            return ucfirst(str_replace('_', ' ', $role ?? ''));
    }
}

/**
 * Get role color for badges
 */
function getRoleColor($role)
{
    switch ($role) {
        case 'superadmin':
            return 'danger';
        case 'admin':
            return 'danger';
        case 'department_admin':
            return 'warning';
        case 'staff':
            return 'info';
        case 'user':
            return 'primary';
        default:
            return 'secondary';
    }
}

/**
 * Get ticket recommendations based on title, description, and category
 */
function getTicketRecommendations($title, $description, $category_id, $pdo)
{
    $recommendations = [];
    $text = strtolower(trim(($title ?? '') . ' ' . ($description ?? '')));

    if (empty($text)) {
        return $recommendations;
    }

    // WiFi/Network issues
    if (preg_match('/wifi|wi-fi|internet|network|connection|router|modem/', $text)) {
        $recommendations[] = 'Check if the router is powered on and all cables are connected properly';
        $recommendations[] = 'Restart the router by unplugging it for 30 seconds, then plugging it back in';
        $recommendations[] = 'Check if other devices can connect to the same network';
        $recommendations[] = 'Verify that you are connected to the correct Wi-Fi network';
    }

    // Computer/PC issues
    if (preg_match('/computer|pc|laptop|desktop|slow|freeze|crash|error/', $text)) {
        $recommendations[] = 'Restart the computer to clear temporary issues';
        $recommendations[] = 'Check if there are any error messages displayed';
        $recommendations[] = 'Close unnecessary applications and browser tabs';
        $recommendations[] = 'Check available disk space (should have at least 10-15% free)';
    }

    // Printer issues
    if (preg_match('/printer|print|printing/', $text)) {
        $recommendations[] = 'Check if the printer is powered on and shows no error lights';
        $recommendations[] = 'Verify that USB or network cables are securely connected';
        $recommendations[] = 'Restart both the printer and computer';
        $recommendations[] = 'Check if the correct printer is selected in print settings';
        $recommendations[] = 'Clear any stuck or paused print jobs from the queue';
    }

    // Email issues
    if (preg_match('/email|mail|outlook|gmail|cannot send|cannot receive/', $text)) {
        $recommendations[] = 'Verify your email address and password are correct';
        $recommendations[] = 'Check your internet connection';
        $recommendations[] = 'Check if emails are going to spam/junk folder';
        $recommendations[] = 'Try accessing email from a web browser';
    }

    // Password issues
    if (preg_match('/password|login|cannot log|forgot password|reset password/', $text)) {
        $recommendations[] = 'Try resetting your password using the "Forgot Password" link';
        $recommendations[] = 'Ensure Caps Lock is not enabled';
        $recommendations[] = 'Check if your account is locked due to multiple failed attempts';
        $recommendations[] = 'Clear browser cache and cookies, then try again';
    }

    // If no specific recommendations, provide generic ones
    if (empty($recommendations)) {
        $recommendations[] = 'Try restarting the affected device or application';
        $recommendations[] = 'Check for any error messages and note them down';
        $recommendations[] = 'Verify that all cables and connections are secure';
    }

    return $recommendations;
}

/**
 * Calculate estimated days to solve based on priority and category
 */
function calculateEstimatedDaysToSolve($priority, $category_id, $pdo)
{
    $base_estimates = [
        'emergency' => 1,
        'high' => 2,
        'medium' => 5,
        'low' => 7
    ];

    $estimated_days = $base_estimates[$priority] ?? 5;

    if ($category_id) {
        try {
            $stmt = $pdo->prepare("
                SELECT AVG(TIMESTAMPDIFF(DAY, created_at, resolved_at)) as avg_days
                FROM tickets
                WHERE category_id = ? AND resolved_at IS NOT NULL
                LIMIT 50
            ");
            $stmt->execute([$category_id]);
            $result = $stmt->fetch();

            if ($result && $result['avg_days'] !== null) {
                $historical_avg = round($result['avg_days']);
                $estimated_days = max($estimated_days, $historical_avg);
            }
        } catch (PDOException $e) {
        }
    }

    return $estimated_days;
}

/**
 * Generate unique ticket number
 */
function generateTicketNumber($pdo)
{
    $year = date('y');
    $prefix = 'TK' . $year;

    try {
        $stmt = $pdo->prepare("
            SELECT ticket_number
            FROM tickets
            WHERE ticket_number LIKE ?
            ORDER BY ticket_number DESC
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();

        if ($result) {
            $last_number = (int)substr($result['ticket_number'], strlen($prefix));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }

        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return $prefix . date('mdHis');
    }
}

/**
 * Generate unique client tracking code
 */
function generateClientTrackingCode($pdo)
{
    $year = date('Y');
    $prefix = 'SL-CLI-' . $year . '-';

    try {
        $stmt = $pdo->prepare("
            SELECT tracking_code
            FROM tickets
            WHERE tracking_code LIKE ?
            ORDER BY tracking_code DESC
            LIMIT 1
        ");
        $stmt->execute([$prefix . '%']);
        $result = $stmt->fetch();

        if ($result) {
            $last_number = (int)substr($result['tracking_code'], strlen($prefix));
            $new_number = $last_number + 1;
        } else {
            $new_number = 1;
        }

        return $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);
    } catch (PDOException $e) {
        return $prefix . strtoupper(substr(md5(uniqid()), 0, 4));
    }
}
