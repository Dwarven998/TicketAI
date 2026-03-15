<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is student or staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['user', 'staff'], true)) {
    header('Location: ../login.php');
    exit;
}

// Handle AJAX request for subcategories
if (isset($_GET['action']) && $_GET['action'] == 'get_subcategories' && isset($_GET['category_id'])) {
    header('Content-Type: application/json');
    try {
        $stmt = $pdo->prepare("
            SELECT id, name 
            FROM service_subcategories 
            WHERE category_id = ? AND is_active = 1 
            ORDER BY name
        ");
        $stmt->execute([$_GET['category_id']]);
        $subcategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($subcategories);
    } catch (PDOException $e) {
        echo json_encode([]);
    }
    exit;
}

// Handle AJAX request for recommendations
if (isset($_GET['action']) && $_GET['action'] == 'get_recommendations') {
    header('Content-Type: application/json');
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $category_id = $_POST['category_id'] ?? null;
    
    $recommendations = getTicketRecommendations($title, $description, $category_id, $pdo);
    echo json_encode(['recommendations' => $recommendations]);
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Get user information including campus
$user_info = [];
try {
    $stmt = $pdo->prepare("
        SELECT u.*, c.name as campus_name, d.name as department_name 
        FROM users u 
        LEFT JOIN campuses c ON u.campus_id = c.id 
        LEFT JOIN departments d ON u.department_id = d.id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user_info = $stmt->fetch();
} catch (PDOException $e) {
    $error = 'Failed to load user information.';
}

// Get departments, categories, and locations
$departments = [];
$categories = [];
$locations = [];

try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll();
    
    $stmt = $pdo->query("SELECT id, name, department_id FROM service_categories WHERE is_active = 1 ORDER BY name");
    $categories = $stmt->fetchAll();
    
    // Get locations filtered by user's campus
    if ($user_info['campus_id']) {
        $stmt = $pdo->prepare("SELECT id, name, description FROM locations WHERE (campus_id = ? OR campus_id IS NULL) AND is_active = 1 ORDER BY name");
        $stmt->execute([$user_info['campus_id']]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, description FROM locations WHERE is_active = 1 ORDER BY name");
        $stmt->execute();
    }
    $locations = $stmt->fetchAll();
} catch (PDOException $e) {
    $locations = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'];
    $subcategory_id = $_POST['subcategory_id'] ?? null;
    $location_id = $_POST['location_id'];
    
    // Validation
    if (empty($title) || empty($description) || empty($category_id) || empty($location_id)) {
        $error = 'Please fill in all required fields.';
    } else {
        try {
            // Generate unique ticket number
            $ticket_number = generateTicketNumber($pdo);
            
            // Get category department
            $stmt = $pdo->prepare("SELECT department_id FROM service_categories WHERE id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch();
            $department_id = $category['department_id'];
            
            // AI will determine priority automatically
            $priority = 'medium'; // Default, AI will update this
            
            // Get recommendations
            $recommendations_list = getTicketRecommendations($title, $description, $category_id, $pdo);
            $recommendations_text = implode("\n", $recommendations_list);
            
            // Calculate estimated days to solve
            $estimated_days = calculateEstimatedDaysToSolve($priority, $category_id, $pdo);
            
            // Get resolution type (default to online if not specified)
            $resolution_type = $_POST['resolution_type'] ?? 'online';
            
            // Create ticket
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_number, title, description, category_id, subcategory_id, 
                                   location_id, priority, requester_id, department_id, ai_analysis,
                                   resolution_type, estimated_days_to_solve, recommendations) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $ai_analysis = "Ticket submitted by student. AI analysis pending for priority and routing.";
            
            if ($stmt->execute([$ticket_number, $title, $description, $category_id, $subcategory_id, 
                              $location_id, $priority, $user_id, $department_id, $ai_analysis,
                              $resolution_type, $estimated_days, $recommendations_text])) {
                
                $ticket_id = $pdo->lastInsertId();
                
                // Handle file uploads for different types
                $upload_errors = [];
                $upload_dir = '../uploads/tickets/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Handle image attachments
                if (!empty($_FILES['image_attachments']['name'][0])) {
                    foreach ($_FILES['image_attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['image_attachments']['error'][$key] == 0) {
                            try {
                                $file_info = [
                                    'name' => $_FILES['image_attachments']['name'][$key],
                                    'tmp_name' => $tmp_name,
                                    'size' => $_FILES['image_attachments']['size'][$key],
                                    'error' => $_FILES['image_attachments']['error'][$key]
                                ];
                                
                                // Check file size (100MB max)
                                if ($file_info['size'] > 100 * 1024 * 1024) {
                                    $upload_errors[] = "Image file {$file_info['name']} exceeds 100MB limit";
                                    continue;
                                }
                                
                                $upload_result = uploadFile($file_info, $upload_dir, 'image');
                                
                                // Save to database
                                $stmt = $pdo->prepare("
                                    INSERT INTO ticket_attachments 
                                    (ticket_id, filename, original_filename, file_path, file_size, mime_type, attachment_type, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $ticket_id,
                                    $upload_result['filename'],
                                    $upload_result['original_filename'],
                                    $upload_result['file_path'],
                                    $upload_result['file_size'],
                                    $upload_result['mime_type'],
                                    'image',
                                    $user_id
                                ]);
                            } catch (Exception $e) {
                                $upload_errors[] = "Failed to upload image: {$file_info['name']}";
                            }
                        }
                    }
                }
                
                // Handle video attachments
                if (!empty($_FILES['video_attachments']['name'][0])) {
                    foreach ($_FILES['video_attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['video_attachments']['error'][$key] == 0) {
                            try {
                                $file_info = [
                                    'name' => $_FILES['video_attachments']['name'][$key],
                                    'tmp_name' => $tmp_name,
                                    'size' => $_FILES['video_attachments']['size'][$key],
                                    'error' => $_FILES['video_attachments']['error'][$key]
                                ];
                                
                                // Check file size (100MB max)
                                if ($file_info['size'] > 100 * 1024 * 1024) {
                                    $upload_errors[] = "Video file {$file_info['name']} exceeds 100MB limit";
                                    continue;
                                }
                                
                                $upload_result = uploadFile($file_info, $upload_dir, 'video');
                                
                                // Save to database
                                $stmt = $pdo->prepare("
                                    INSERT INTO ticket_attachments 
                                    (ticket_id, filename, original_filename, file_path, file_size, mime_type, attachment_type, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $ticket_id,
                                    $upload_result['filename'],
                                    $upload_result['original_filename'],
                                    $upload_result['file_path'],
                                    $upload_result['file_size'],
                                    $upload_result['mime_type'],
                                    'video',
                                    $user_id
                                ]);
                            } catch (Exception $e) {
                                $upload_errors[] = "Failed to upload video: {$file_info['name']}";
                            }
                        }
                    }
                }
                
                // Handle document attachments
                if (!empty($_FILES['document_attachments']['name'][0])) {
                    foreach ($_FILES['document_attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['document_attachments']['error'][$key] == 0) {
                            try {
                                $file_info = [
                                    'name' => $_FILES['document_attachments']['name'][$key],
                                    'tmp_name' => $tmp_name,
                                    'size' => $_FILES['document_attachments']['size'][$key],
                                    'error' => $_FILES['document_attachments']['error'][$key]
                                ];
                                
                                // Check file size (100MB max)
                                if ($file_info['size'] > 100 * 1024 * 1024) {
                                    $upload_errors[] = "Document file {$file_info['name']} exceeds 100MB limit";
                                    continue;
                                }
                                
                                $upload_result = uploadFile($file_info, $upload_dir, 'document');
                                
                                // Save to database
                                $stmt = $pdo->prepare("
                                    INSERT INTO ticket_attachments 
                                    (ticket_id, filename, original_filename, file_path, file_size, mime_type, attachment_type, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $ticket_id,
                                    $upload_result['filename'],
                                    $upload_result['original_filename'],
                                    $upload_result['file_path'],
                                    $upload_result['file_size'],
                                    $upload_result['mime_type'],
                                    'document',
                                    $user_id
                                ]);
                            } catch (Exception $e) {
                                $upload_errors[] = "Failed to upload document: {$file_info['name']}";
                            }
                        }
                    }
                }
                
                // Send notification to department staff
                $stmt = $pdo->prepare("
                    SELECT id FROM users 
                    WHERE department_id = ? AND role IN ('staff', 'department_admin') AND is_active = 1
                ");
                $stmt->execute([$department_id]);
                $staff_members = $stmt->fetchAll();
                
                foreach ($staff_members as $staff) {
                    sendNotification($pdo, $staff['id'], $ticket_id, 
                                   'New Ticket Assigned', 
                                   "A new ticket #{$ticket_number} has been submitted: {$title}",
                                   'ticket_created');
                }
                
                $success_message = "Ticket #{$ticket_number} has been created successfully!";
                if (!empty($upload_errors)) {
                    $success_message .= " Note: " . implode(", ", $upload_errors);
                }
                $success = $success_message;
                
                // Clear form
                $_POST = [];
            } else {
                $error = 'Failed to create ticket. Please try again.';
            }
        } catch (PDOException $e) {
            $error = 'Failed to create ticket. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Request - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/top_nav.php'; ?>
    
    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include 'includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="dashboard-content">
            <!-- Header -->
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-plus text-success me-2"></i>
                    Create New Service Request
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="tickets.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-1"></i>
                            Back to My Requests
                        </a>
                        <a href="chat-support.php" class="btn btn-outline-success">
                            <i class="fas fa-comments me-1"></i>
                            Chat Support
                        </a>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-2">
                        <a href="tickets.php" class="btn btn-success btn-sm">
                            <i class="fas fa-list me-1"></i>
                            View My Requests
                        </a>
                        <a href="chat-support.php" class="btn btn-outline-success btn-sm">
                            <i class="fas fa-comments me-1"></i>
                            Start Chat
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Create Request Form -->
            <div class="row">
                <div class="col-lg-8">
                    <!-- Personal Information Display -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-user me-2"></i>
                                Requester Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Name:</strong> <?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></p>
                                    <p class="mb-2"><strong>Student Number:</strong> <?php echo htmlspecialchars($user_info['user_number']); ?></p>
                                    <p class="mb-2"><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-2"><strong>Campus:</strong> <?php echo htmlspecialchars($user_info['campus_name'] ?? 'Not specified'); ?></p>
                                    <p class="mb-2"><strong>Year Level:</strong> <?php echo htmlspecialchars($user_info['year_level']); ?></p>
                                    <p class="mb-2"><strong>Request Date:</strong> <?php echo date('F j, Y - g:i A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-edit text-success me-2"></i>
                                Request Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST" enctype="multipart/form-data" id="ticketForm">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Request Title *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>" 
                                           placeholder="Brief description of your request" required>
                                    <div class="form-text">Provide a clear, concise title for your service request</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="category_id" class="form-label">Service Category *</label>
                                        <select class="form-select" id="category_id" name="category_id" required>
                                            <option value="">Select a service category</option>
                                            <?php 
                                            // Group categories by department
                                            $categories_by_dept = [];
                                            foreach ($categories as $category) {
                                                $dept_id = $category['department_id'];
                                                if (!isset($categories_by_dept[$dept_id])) {
                                                    $categories_by_dept[$dept_id] = [];
                                                }
                                                $categories_by_dept[$dept_id][] = $category;
                                            }
                                            
                                            // Display grouped categories
                                            foreach ($departments as $dept): 
                                                if (isset($categories_by_dept[$dept['id']])):
                                            ?>
                                                <optgroup label="<?php echo htmlspecialchars($dept['name']); ?>">
                                                    <?php foreach ($categories_by_dept[$dept['id']] as $category): ?>
                                                        <option value="<?php echo $category['id']; ?>" 
                                                                <?php echo (($_POST['category_id'] ?? '') == $category['id']) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($category['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="subcategory_id" class="form-label">Sub Category</label>
                                        <select class="form-select" id="subcategory_id" name="subcategory_id">
                                            <option value="">Select sub category</option>
                                        </select>
                                        <div class="form-text">Optional: More specific categorization</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="location_id" class="form-label">Location *</label>
                                        <select class="form-select" id="location_id" name="location_id" required>
                                            <option value="">Select location</option>
                                            <?php foreach ($locations as $location): ?>
                                                <option value="<?php echo $location['id']; ?>" 
                                                        <?php echo (($_POST['location_id'] ?? '') == $location['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($location['name']); ?>
                                                    <?php if ($location['description']): ?>
                                                        - <?php echo htmlspecialchars($location['description']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="form-text">Where is the issue located?</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Detailed Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="6" 
                                              placeholder="Please provide detailed information about your request..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    <div class="form-text">
                                        Include as much detail as possible: what happened, when it occurred, 
                                        steps you've already taken, error messages, etc.
                                    </div>
                                </div>
                                
                                <!-- Recommendations Section -->
                                <div class="alert alert-warning mb-3" id="recommendations_section" style="display: none;">
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-lightbulb me-2"></i>
                                        Recommended Steps Before Creating Ticket
                                    </h6>
                                    <div id="recommendations_list"></div>
                                    <hr>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="recommendations_tried" name="recommendations_tried">
                                        <label class="form-check-label" for="recommendations_tried">
                                            I have tried the recommended steps above and the issue persists
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Resolution Type -->
                                <div class="mb-3">
                                    <label class="form-label">Preferred Resolution Method *</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="resolution_type" id="resolution_online" value="online" checked>
                                        <label class="form-check-label" for="resolution_online">
                                            <i class="fas fa-laptop me-1"></i> Online (Remote assistance, email, phone)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="resolution_type" id="resolution_onsite" value="onsite">
                                        <label class="form-check-label" for="resolution_onsite">
                                            <i class="fas fa-tools me-1"></i> Onsite (In-person visit required)
                                        </label>
                                    </div>
                                    <div class="form-text">Select how you prefer this issue to be resolved</div>
                                </div>
                                
                                <!-- File Upload Instructions -->
                                <div class="alert alert-info mb-3">
                                    <h6 class="alert-heading mb-2">
                                        <i class="fas fa-info-circle me-2"></i>
                                        File Upload Instructions
                                    </h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <strong><i class="fas fa-image text-success me-1"></i> Images:</strong>
                                            <small class="d-block">Screenshots, photos, error messages</small>
                                        </div>
                                        <div class="col-md-4">
                                            <strong><i class="fas fa-video text-danger me-1"></i> Videos:</strong>
                                            <small class="d-block">Screen recordings, demonstrations</small>
                                        </div>
                                        <div class="col-md-4">
                                            <strong><i class="fas fa-file-alt text-success me-1"></i> Documents:</strong>
                                            <small class="d-block">PDFs, reports, spreadsheets</small>
                                        </div>
                                    </div>
                                    <hr class="my-2">
                                    <small class="text-muted">
                                        <i class="fas fa-lightbulb me-1"></i>
                                        <strong>Tip:</strong> You can select multiple files at once by holding Ctrl (Windows) or Cmd (Mac) while clicking files, 
                                        or drag and drop multiple files into each upload area.
                                    </small>
                                </div>
                                
                                <!-- Image Attachments -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-image text-success me-1"></i>
                                        Image Attachments (Screenshots, Photos)
                                    </label>
                                    <div id="image_attachments_container">
                                        <div class="image-attachment-item mb-2">
                                            <div class="input-group">
                                                <input type="file" class="form-control" name="image_attachments[]" 
                                                       accept=".jpg,.jpeg,.png,.gif,.bmp,.webp">
                                                <button type="button" class="btn btn-outline-success" onclick="addImageAttachment()">
                                                    <i class="fas fa-plus"></i> Add More
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        Upload screenshots, photos, or other images. Max 100MB per file. 
                                        Click "Add More" to upload additional images.
                                        Supported: JPG, PNG, GIF, BMP, WebP
                                    </div>
                                    <div id="image_preview" class="mt-2"></div>
                                </div>
                                
                                <!-- Video Attachments -->
                                <div class="mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-video text-danger me-1"></i>
                                        Video Attachments (Screen Recordings, Clips)
                                    </label>
                                    <div id="video_attachments_container">
                                        <div class="video-attachment-item mb-2">
                                            <div class="input-group">
                                                <input type="file" class="form-control" name="video_attachments[]" 
                                                       accept=".mp4,.avi,.mov,.wmv,.flv,.webm,.mkv">
                                                <button type="button" class="btn btn-outline-success" onclick="addVideoAttachment()">
                                                    <i class="fas fa-plus"></i> Add More
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        Upload screen recordings or video clips. Max 100MB per file. 
                                        Click "Add More" to upload additional videos.
                                        Supported: MP4, AVI, MOV, WMV, FLV, WebM, MKV
                                    </div>
                                    <div id="video_preview" class="mt-2"></div>
                                </div>
                                
                                <!-- Document Attachments -->
                                <div class="mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-file-alt text-success me-1"></i>
                                        Document Attachments (PDFs, Documents, Spreadsheets)
                                    </label>
                                    <div id="document_attachments_container">
                                        <div class="document-attachment-item mb-2">
                                            <div class="input-group">
                                                <input type="file" class="form-control" name="document_attachments[]" 
                                                       accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.rtf,.odt,.ods">
                                                <button type="button" class="btn btn-outline-success" onclick="addDocumentAttachment()">
                                                    <i class="fas fa-plus"></i> Add More
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="form-text">
                                        Upload documents, PDFs, spreadsheets, or text files. Max 100MB per file. 
                                        Click "Add More" to upload additional documents.
                                        Supported: PDF, DOC, DOCX, XLS, XLSX, TXT, RTF, ODT, ODS
                                    </div>
                                    <div id="document_preview" class="mt-2"></div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-success" id="previewBtn">
                                        <i class="fas fa-eye me-1"></i>
                                        Preview Request
                                    </button>
                                    <button type="button" class="btn btn-warning" id="clearAllBtn">
                                        <i class="fas fa-eraser me-1"></i>
                                        Clear All
                                    </button>
                                    <a href="tickets.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                        Cancel
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Help Sidebar -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-success text-white">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-question-circle me-2"></i>
                                Need Help?
                            </h6>
                        </div>
                        <div class="card-body">
                            <h6 class="fw-bold">Tips for Better Support:</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Be specific in your title
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Include error messages
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Attach relevant screenshots
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Mention steps you've tried
                                </li>
                            </ul>
                            
                            <hr>
                            
                            <h6 class="fw-bold">What We Handle:</h6>
                            <ul class="list-unstyled text-success small">
                                <li class="mb-1">• IT Support & Technical Issues</li>
                                <li class="mb-1">• Facility & Equipment Problems</li>
                                <li class="mb-1">• Academic Service Requests</li>
                                <li class="mb-1">• Student Service Inquiries</li>
                            </ul>
                            
                            <hr>
                            
                            <div class="text-center">
                                <a href="chat-support.php" class="btn btn-outline-success btn-sm">
                                    <i class="fas fa-comments me-1"></i>
                                    Live Chat Support
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Requests -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg-white border-0">
                            <h6 class="card-title mb-0">
                                <i class="fas fa-history me-2"></i>
                                Your Recent Requests
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php
                            try {
                                $stmt = $pdo->prepare("
                                    SELECT ticket_number, title, status, created_at 
                                    FROM tickets 
                                    WHERE requester_id = ? 
                                    ORDER BY created_at DESC 
                                    LIMIT 3
                                ");
                                $stmt->execute([$user_id]);
                                $recent_tickets = $stmt->fetchAll();
                                
                                if ($recent_tickets):
                                    foreach ($recent_tickets as $ticket):
                            ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div>
                                        <div class="fw-bold small"><?php echo htmlspecialchars($ticket['ticket_number']); ?></div>
                                        <div class="text-muted small">
                                            <?php echo htmlspecialchars(substr($ticket['title'], 0, 30)); ?>...
                                        </div>
                                    </div>
                                    <span class="badge bg-<?php echo getStatusColor($ticket['status']); ?> small">
                                        <?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?>
                                    </span>
                                </div>
                            <?php 
                                    endforeach;
                                else:
                            ?>
                                <p class="text-muted small mb-0">No previous requests</p>
                            <?php 
                                endif;
                            } catch (PDOException $e) {
                                echo '<p class="text-muted small mb-0">Unable to load recent requests</p>';
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Clear All Confirmation Modal -->
    <div class="modal fade" id="clearAllModal" tabindex="-1" aria-labelledby="clearAllModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="clearAllModalLabel">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Clear All Fields
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Are you sure you want to clear all fields?</strong>
                    </div>
                    <p>This action will:</p>
                    <ul class="mb-0">
                        <li>Clear all form inputs (title, description, category, etc.)</li>
                        <li>Remove all selected files and attachments</li>
                        <li>Reset all additional upload fields</li>
                        <li><strong>This cannot be undone!</strong></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>
                        Cancel
                    </button>
                    <button type="button" class="btn btn-warning" id="confirmClearAll">
                        <i class="fas fa-eraser me-1"></i>
                        Yes, Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="previewModalLabel">
                        <i class="fas fa-eye me-2"></i>
                        Review Your Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Please review all information carefully before submitting your request.
                    </div>
                    
                    <!-- Requester Info -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Requester Information</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($user_info['first_name'] . ' ' . $user_info['last_name']); ?></p>
                                    <p class="mb-1"><strong>Student Number:</strong> <?php echo htmlspecialchars($user_info['user_number']); ?></p>
                                    <p class="mb-1"><strong>Email:</strong> <?php echo htmlspecialchars($user_info['email']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p class="mb-1"><strong>Campus:</strong> <?php echo htmlspecialchars($user_info['campus_name'] ?? 'Not specified'); ?></p>
                                    <p class="mb-1"><strong>Year Level:</strong> <?php echo htmlspecialchars($user_info['year_level']); ?></p>
                                    <p class="mb-1"><strong>Request Date:</strong> <?php echo date('F j, Y - g:i A'); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Request Details -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Request Details</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Title:</strong> <span id="preview-title"></span></p>
                            <p class="mb-2"><strong>Category:</strong> <span id="preview-category"></span></p>
                            <p class="mb-2"><strong>Sub Category:</strong> <span id="preview-subcategory"></span></p>
                            <p class="mb-2"><strong>Location:</strong> <span id="preview-location"></span></p>
                            <p class="mb-2"><strong>Description:</strong></p>
                            <div class="border p-2 bg-light" id="preview-description"></div>
                        </div>
                    </div>
                    
                    <!-- Attachments -->
                    <div class="card mb-3">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-paperclip me-2"></i>Attachments</h6>
                        </div>
                        <div class="card-body">
                            <div id="preview-attachments">
                                <p class="text-muted">No attachments selected</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-edit me-1"></i>
                        Edit Request
                    </button>
                    <button type="button" class="btn btn-success" id="confirmSubmit">
                        <i class="fas fa-paper-plane me-1"></i>
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth page transition effect
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            
            setTimeout(function() {
                document.body.style.opacity = '1';
            }, 50);
        });
        
        // Smooth navigation effect for links
        document.querySelectorAll('a[href]').forEach(link => {
            link.addEventListener('click', function(e) {
                if (this.href && !this.href.includes('#') && !this.target) {
                    e.preventDefault();
                    document.body.style.opacity = '0.7';
                    setTimeout(() => {
                        window.location.href = this.href;
                    }, 150);
                }
            });
        });
        
        // File upload validation and preview
        function validateFileSize(files, maxSize = 100 * 1024 * 1024) {
            for (let file of files) {
                if (file.size > maxSize) {
                    alert(`File "${file.name}" is too large. Maximum size is 100MB.`);
                    return false;
                }
            }
            return true;
        }
        
        // Add more attachment functions
        function addImageAttachment() {
            const container = document.getElementById('image_attachments_container');
            const newItem = document.createElement('div');
            newItem.className = 'image-attachment-item mb-2';
            newItem.innerHTML = `
                <div class="input-group">
                    <input type="file" class="form-control" name="image_attachments[]" 
                           accept=".jpg,.jpeg,.png,.gif,.bmp,.webp">
                    <button type="button" class="btn btn-outline-danger" onclick="removeAttachment(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newItem);
            
            // Add file validation to new input
            const newInput = newItem.querySelector('input[type="file"]');
            newInput.addEventListener('change', function(e) {
                if (!validateFileSize(e.target.files)) {
                    e.target.value = '';
                }
            });
        }
        
        function addVideoAttachment() {
            const container = document.getElementById('video_attachments_container');
            const newItem = document.createElement('div');
            newItem.className = 'video-attachment-item mb-2';
            newItem.innerHTML = `
                <div class="input-group">
                    <input type="file" class="form-control" name="video_attachments[]" 
                           accept=".mp4,.avi,.mov,.wmv,.flv,.webm,.mkv">
                    <button type="button" class="btn btn-outline-danger" onclick="removeAttachment(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newItem);
            
            // Add file validation to new input
            const newInput = newItem.querySelector('input[type="file"]');
            newInput.addEventListener('change', function(e) {
                if (!validateFileSize(e.target.files)) {
                    e.target.value = '';
                }
            });
        }
        
        function addDocumentAttachment() {
            const container = document.getElementById('document_attachments_container');
            const newItem = document.createElement('div');
            newItem.className = 'document-attachment-item mb-2';
            newItem.innerHTML = `
                <div class="input-group">
                    <input type="file" class="form-control" name="document_attachments[]" 
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.rtf,.odt,.ods">
                    <button type="button" class="btn btn-outline-danger" onclick="removeAttachment(this)">
                        <i class="fas fa-times"></i> Remove
                    </button>
                </div>
            `;
            container.appendChild(newItem);
            
            // Add file validation to new input
            const newInput = newItem.querySelector('input[type="file"]');
            newInput.addEventListener('change', function(e) {
                if (!validateFileSize(e.target.files)) {
                    e.target.value = '';
                }
            });
        }
        
        function removeAttachment(button) {
            const item = button.closest('.image-attachment-item, .video-attachment-item, .document-attachment-item');
            item.remove();
        }
        
        // Initialize file validation for existing inputs
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('input[type="file"]').forEach(input => {
                input.addEventListener('change', function(e) {
                    if (!validateFileSize(e.target.files)) {
                        e.target.value = '';
                    }
                });
            });
        });
        
        // Function to update recommendations
        function updateRecommendations() {
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const categoryId = document.getElementById('category_id').value;
            
            if (title || description || categoryId) {
                // Show recommendations section
                const recommendationsSection = document.getElementById('recommendations_section');
                const recommendationsList = document.getElementById('recommendations_list');
                
                // Get recommendations via AJAX
                const formData = new URLSearchParams();
                formData.append('title', title);
                formData.append('description', description);
                formData.append('category_id', categoryId);
                
                fetch('?action=get_recommendations', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: formData.toString()
                })
                .then(response => response.json())
                .then(data => {
                    if (data.recommendations && data.recommendations.length > 0) {
                        recommendationsList.innerHTML = '<ul class="mb-0">' + 
                            data.recommendations.map(rec => `<li>${rec}</li>`).join('') + 
                            '</ul>';
                        recommendationsSection.style.display = 'block';
                    } else {
                        recommendationsSection.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error loading recommendations:', error);
                });
            } else {
                document.getElementById('recommendations_section').style.display = 'none';
            }
        }
        
        // Subcategory filtering with AJAX
        document.getElementById('category_id').addEventListener('change', function() {
            const categoryId = this.value;
            const subcategorySelect = document.getElementById('subcategory_id');
            
            // Clear subcategory options
            subcategorySelect.innerHTML = '<option value="">Select sub category</option>';
            
            if (categoryId) {
                // Fetch subcategories via AJAX
                fetch(`?action=get_subcategories&category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.id;
                            option.textContent = subcategory.name;
                            subcategorySelect.appendChild(option);
                        });
                    })
                    .catch(error => {
                        console.error('Error loading subcategories:', error);
                    });
            }
            
            // Update recommendations when category changes
            updateRecommendations();
        });
        
        // Update recommendations when title or description changes
        document.getElementById('title').addEventListener('input', updateRecommendations);
        document.getElementById('description').addEventListener('input', updateRecommendations);
        
        // Character counter for description
        const description = document.getElementById('description');
        const counter = document.createElement('div');
        counter.className = 'form-text text-end';
        counter.id = 'charCounter';
        description.parentNode.appendChild(counter);
        
        function updateCounter() {
            const length = description.value.length;
            counter.textContent = `${length} characters`;
            counter.className = length > 1000 ? 'form-text text-end text-warning' : 'form-text text-end text-muted';
        }
        
        description.addEventListener('input', updateCounter);
        updateCounter();

        // Auto-resize textarea
        description.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
        
        // Form submission validation
        document.getElementById('ticketForm').addEventListener('submit', function(e) {
            const recommendationsSection = document.getElementById('recommendations_section');
            const recommendationsTried = document.getElementById('recommendations_tried');
            
            // If recommendations are shown, require checkbox to be checked
            if (recommendationsSection.style.display !== 'none' && !recommendationsTried.checked) {
                e.preventDefault();
                alert('Please confirm that you have tried the recommended steps before submitting the ticket.');
                recommendationsTried.focus();
                return false;
            }
        });
        
        // Preview functionality
        document.getElementById('previewBtn').addEventListener('click', function() {
            // Validate required fields
            const form = document.getElementById('ticketForm');
            if (!form.checkValidity()) {
                form.reportValidity();
                return;
            }
            
            // Populate preview modal
            document.getElementById('preview-title').textContent = document.getElementById('title').value;
            
            const categorySelect = document.getElementById('category_id');
            document.getElementById('preview-category').textContent = 
                categorySelect.options[categorySelect.selectedIndex].text;
            
            const subcategorySelect = document.getElementById('subcategory_id');
            document.getElementById('preview-subcategory').textContent = 
                subcategorySelect.value ? subcategorySelect.options[subcategorySelect.selectedIndex].text : 'None';
            
            const locationSelect = document.getElementById('location_id');
            document.getElementById('preview-location').textContent = 
                locationSelect.options[locationSelect.selectedIndex].text;
            
            document.getElementById('preview-description').textContent = document.getElementById('description').value;
            
            // Show attachments with image previews
            const attachmentsContainer = document.getElementById('preview-attachments');
            attachmentsContainer.innerHTML = '';
            
            let hasAttachments = false;
            
            // Check each attachment type
            const attachmentTypes = [
                { container: 'image_attachments_container', label: 'Images', icon: 'fas fa-image text-success' },
                { container: 'video_attachments_container', label: 'Videos', icon: 'fas fa-video text-danger' },
                { container: 'document_attachments_container', label: 'Documents', icon: 'fas fa-file-alt text-success' }
            ];
            
            attachmentTypes.forEach(type => {
                const container = document.getElementById(type.container);
                const fileInputs = container.querySelectorAll('input[type="file"]');
                const files = [];
                
                // Collect all files from all inputs of this type
                fileInputs.forEach(input => {
                    if (input.files.length > 0) {
                        Array.from(input.files).forEach(file => files.push(file));
                    }
                });
                
                if (files.length > 0) {
                    hasAttachments = true;
                    const typeDiv = document.createElement('div');
                    typeDiv.className = 'mb-3';
                    typeDiv.innerHTML = `<h6 class="fw-bold text-success"><i class="${type.icon} me-2"></i>${type.label}:</h6>`;
                    
                    const filesContainer = document.createElement('div');
                    filesContainer.className = 'row';
                    
                    files.forEach(file => {
                        const fileDiv = document.createElement('div');
                        fileDiv.className = 'col-md-6 mb-2';
                        
                        if (type.container.includes('image')) {
                            // Show image preview
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                fileDiv.innerHTML = `
                                    <div class="card">
                                        <img src="${e.target.result}" class="card-img-top" style="height: 150px; object-fit: cover;" alt="Image preview">
                                        <div class="card-body p-2">
                                            <p class="card-text small mb-0">
                                                <i class="fas fa-image text-success me-1"></i>
                                                ${file.name}
                                            </p>
                                            <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                                        </div>
                                    </div>
                                `;
                            };
                            reader.readAsDataURL(file);
                        } else if (type.container.includes('video')) {
                            // Show video preview
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                fileDiv.innerHTML = `
                                    <div class="card">
                                        <video class="card-img-top" style="height: 150px; object-fit: cover;" controls>
                                            <source src="${e.target.result}" type="${file.type}">
                                            Your browser does not support the video tag.
                                        </video>
                                        <div class="card-body p-2">
                                            <p class="card-text small mb-0">
                                                <i class="fas fa-video text-danger me-1"></i>
                                                ${file.name}
                                            </p>
                                            <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                                        </div>
                                    </div>
                                `;
                            };
                            reader.readAsDataURL(file);
                        } else {
                            // Show document icon
                            let iconClass = 'fas fa-file-alt';
                            let iconColor = 'text-success';
                            
                            if (file.name.toLowerCase().includes('.pdf')) {
                                iconClass = 'fas fa-file-pdf';
                                iconColor = 'text-danger';
                            } else if (file.name.toLowerCase().includes('.doc') || file.name.toLowerCase().includes('.docx')) {
                                iconClass = 'fas fa-file-word';
                                iconColor = 'text-success';
                            } else if (file.name.toLowerCase().includes('.xls') || file.name.toLowerCase().includes('.xlsx')) {
                                iconClass = 'fas fa-file-excel';
                                iconColor = 'text-success';
                            }
                            
                            fileDiv.innerHTML = `
                                <div class="card">
                                    <div class="card-body text-center p-3">
                                        <i class="${iconClass} ${iconColor}" style="font-size: 3rem;"></i>
                                        <p class="card-text small mt-2 mb-0">${file.name}</p>
                                        <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                                    </div>
                                </div>
                            `;
                        }
                        
                        filesContainer.appendChild(fileDiv);
                    });
                    
                    typeDiv.appendChild(filesContainer);
                    attachmentsContainer.appendChild(typeDiv);
                }
            });
            
            if (!hasAttachments) {
                attachmentsContainer.innerHTML = '<p class="text-muted">No attachments selected</p>';
            }
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        });
        
        // Clear All functionality
        document.getElementById('clearAllBtn').addEventListener('click', function() {
            const modal = new bootstrap.Modal(document.getElementById('clearAllModal'));
            modal.show();
        });
        
        document.getElementById('confirmClearAll').addEventListener('click', function() {
            // Clear all form inputs
            document.getElementById('ticketForm').reset();
            
            // Clear all text inputs and textareas
            document.querySelectorAll('#ticketForm input[type="text"], #ticketForm textarea, #ticketForm select').forEach(input => {
                input.value = '';
            });
            
            // Reset dropdowns to default
            document.getElementById('category_id').selectedIndex = 0;
            document.getElementById('subcategory_id').innerHTML = '<option value="">Select sub category</option>';
            document.getElementById('location_id').selectedIndex = 0;
            
            // Clear all file inputs and reset attachment containers
            ['image', 'video', 'document'].forEach(type => {
                const container = document.getElementById(`${type}_attachments_container`);
                const firstItem = container.querySelector(`.${type}-attachment-item`);
                
                // Clear the first input
                const firstInput = firstItem.querySelector('input[type="file"]');
                firstInput.value = '';
                
                // Remove all additional attachment items (keep only the first one)
                const additionalItems = container.querySelectorAll(`.${type}-attachment-item:not(:first-child)`);
                additionalItems.forEach(item => item.remove());
                
                // Update the first item button to "Add More"
                const firstButton = firstItem.querySelector('button');
                firstButton.className = 'btn btn-outline-success';
                firstButton.innerHTML = '<i class="fas fa-plus"></i> Add More';
                firstButton.onclick = type === 'image' ? addImageAttachment : 
                                   type === 'video' ? addVideoAttachment : addDocumentAttachment;
            });
            
            // Clear preview areas
            document.getElementById('image_preview').innerHTML = '';
            document.getElementById('video_preview').innerHTML = '';
            document.getElementById('document_preview').innerHTML = '';
            
            // Close the modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('clearAllModal'));
            modal.hide();
            
            // Show success message
            const successAlert = document.createElement('div');
            successAlert.className = 'alert alert-success alert-dismissible fade show';
            successAlert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                All fields have been cleared successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Insert the alert at the top of the form
            const form = document.getElementById('ticketForm');
            form.parentNode.insertBefore(successAlert, form);
            
            // Auto-dismiss the alert after 3 seconds
            setTimeout(() => {
                if (successAlert.parentNode) {
                    successAlert.remove();
                }
            }, 3000);
            
            // Focus on the first input
            document.getElementById('title').focus();
        });
        
        // Confirm submit
        document.getElementById('confirmSubmit').addEventListener('click', function() {
            document.getElementById('ticketForm').submit();
        });
        
        // Auto-focus on first field
        document.getElementById('title').focus();
    </script>
</body>
</html>