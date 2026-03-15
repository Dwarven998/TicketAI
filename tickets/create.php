<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Only students can create tickets
if ($_SESSION['user_role'] != 'user') {
    header('Location: ../dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Get user's campus for location filtering
$user_campus_id = $_SESSION['campus_id'] ?? null;

// Get departments for categorization
$departments = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name");
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Get locations for user's campus
$locations = [];
if ($user_campus_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, name, building, floor, room FROM locations WHERE campus_id = ? AND is_active = 1 ORDER BY name");
        $stmt->execute([$user_campus_id]);
        $locations = $stmt->fetchAll();
    } catch (PDOException $e) {
        $locations = [];
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category_id = $_POST['category_id'] ?: null;
    $subcategory_id = $_POST['subcategory_id'] ?: null;
    $location_id = $_POST['location_id'] ?: null;
    $department_id = $_POST['department_id'] ?: null;

    if (!empty($title) && !empty($description)) {
        try {
            // Generate unique ticket number
            $ticket_number = generateTicketNumber($pdo);

            // Determine priority automatically (AI-like logic)
            $priority = 'medium'; // Default
            $title_lower = strtolower($title);
            $desc_lower = strtolower($description);

            if (
                strpos($title_lower, 'urgent') !== false || strpos($desc_lower, 'urgent') !== false ||
                strpos($title_lower, 'emergency') !== false || strpos($desc_lower, 'emergency') !== false
            ) {
                $priority = 'emergency';
            } elseif (
                strpos($title_lower, 'important') !== false || strpos($desc_lower, 'important') !== false ||
                strpos($title_lower, 'asap') !== false || strpos($desc_lower, 'asap') !== false
            ) {
                $priority = 'high';
            } elseif (
                strpos($title_lower, 'minor') !== false || strpos($desc_lower, 'minor') !== false ||
                strpos($title_lower, 'small') !== false || strpos($desc_lower, 'small') !== false
            ) {
                $priority = 'low';
            }

            // Insert ticket
            $stmt = $pdo->prepare("
                INSERT INTO tickets (ticket_number, title, description, category_id, subcategory_id, location_id, 
                                   priority, status, requester_id, department_id, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, NOW())
            ");
            $stmt->execute([
                $ticket_number,
                $title,
                $description,
                $category_id,
                $subcategory_id,
                $location_id,
                $priority,
                $user_id,
                $department_id
            ]);

            $ticket_id = $pdo->lastInsertId();

            // Handle file uploads for different types
            $upload_errors = [];
            $upload_dir = '../uploads/tickets/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            // Process each attachment type
            $attachment_types = ['images', 'videos', 'documents'];

            foreach ($attachment_types as $type) {
                if (isset($_FILES[$type]) && !empty($_FILES[$type]['name'][0])) {
                    $files = $_FILES[$type];
                    $file_count = count($files['name']);

                    for ($i = 0; $i < $file_count; $i++) {
                        if ($files['error'][$i] == UPLOAD_ERR_OK) {
                            $file_name = $files['name'][$i];
                            $file_tmp = $files['tmp_name'][$i];
                            $file_size = $files['size'][$i];
                            $file_type = $files['type'][$i];

                            // Validate file size (100MB limit)
                            if ($file_size > 104857600) {
                                $upload_errors[] = "File $file_name exceeds 100MB limit";
                                continue;
                            }

                            // Generate unique filename
                            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
                            $unique_filename = uniqid() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
                            $file_path = $upload_dir . $unique_filename;

                            if (move_uploaded_file($file_tmp, $file_path)) {
                                // Insert attachment record
                                $attachment_type = ($type == 'images') ? 'image' : (($type == 'videos') ? 'video' : 'document');
                                $stmt = $pdo->prepare("
                                    INSERT INTO ticket_attachments (ticket_id, file_name, original_name, file_path, 
                                                                   file_size, file_type, attachment_type, uploaded_by) 
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                                ");
                                $stmt->execute([
                                    $ticket_id,
                                    $unique_filename,
                                    $file_name,
                                    $file_path,
                                    $file_size,
                                    $file_type,
                                    $attachment_type,
                                    $user_id
                                ]);
                            } else {
                                $upload_errors[] = "Failed to upload $file_name";
                            }
                        }
                    }
                }
            }

            $success = "Ticket created successfully! Ticket Number: $ticket_number";

            // Clear form data on success
            $_POST = [];
        } catch (PDOException $e) {
            $error = "Error creating ticket: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Service Request - ServiceLink</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <?php include '../includes/sidebar.php'; ?>

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 dashboard-content">
                <!-- Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="fas fa-plus text-success me-2"></i>
                        Create Service Request
                    </h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="../student/tickets.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-1"></i>
                                Back to My Requests
                            </a>
                        </div>
                    </div>
                </div>

                <?php if (isset($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($upload_errors)): ?>
                    <div class="alert alert-warning alert-dismissible fade show">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Upload Issues:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($upload_errors as $upload_error): ?>
                                <li><?php echo htmlspecialchars($upload_error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Create Ticket Form -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="ticketForm">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Request Title <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="title" name="title"
                                            value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                            placeholder="Brief description of your request" required>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Detailed Description <span class="text-danger">*</span></label>
                                        <textarea class="form-control" id="description" name="description" rows="5"
                                            placeholder="Please provide detailed information about your request..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="department_id" class="form-label">Department</label>
                                        <select class="form-select" id="department_id" name="department_id" required>
                                            <option value="">Select Department</option>
                                            <?php foreach ($departments as $dept): ?>
                                                <option value="<?php echo $dept['id']; ?>"
                                                    <?php echo (($_POST['department_id'] ?? '') == $dept['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($dept['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="category_id" class="form-label">Category</label>
                                        <select class="form-select" id="category_id" name="category_id">
                                            <option value="">Select Category</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="subcategory_id" class="form-label">Subcategory</label>
                                        <select class="form-select" id="subcategory_id" name="subcategory_id">
                                            <option value="">Select Subcategory</option>
                                        </select>
                                    </div>

                                    <div class="mb-3">
                                        <label for="location_id" class="form-label">Location</label>
                                        <select class="form-select" id="location_id" name="location_id">
                                            <option value="">Select Location</option>
                                            <?php foreach ($locations as $location): ?>
                                                <option value="<?php echo $location['id']; ?>"
                                                    <?php echo (($_POST['location_id'] ?? '') == $location['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($location['name']); ?>
                                                    <?php if ($location['building']): ?>
                                                        - <?php echo htmlspecialchars($location['building']); ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- File Attachments -->
                            <div class="row mt-4">
                                <div class="col-12">
                                    <h5 class="mb-3">
                                        <i class="fas fa-paperclip text-success me-2"></i>
                                        Attachments (Optional)
                                    </h5>
                                    <p class="text-muted small">Maximum file size: 100MB per file</p>
                                </div>

                                <!-- Images -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-image text-success me-1"></i>
                                        Images
                                    </label>
                                    <div id="image-uploads">
                                        <input type="file" class="form-control mb-2" name="images[]" accept="image/*" multiple>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addFileInput('image-uploads', 'images[]', 'image/*')">
                                        <i class="fas fa-plus me-1"></i>Add More
                                    </button>
                                </div>

                                <!-- Videos -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-video text-success me-1"></i>
                                        Videos
                                    </label>
                                    <div id="video-uploads">
                                        <input type="file" class="form-control mb-2" name="videos[]" accept="video/*" multiple>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addFileInput('video-uploads', 'videos[]', 'video/*')">
                                        <i class="fas fa-plus me-1"></i>Add More
                                    </button>
                                </div>

                                <!-- Documents -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">
                                        <i class="fas fa-file text-success me-1"></i>
                                        Documents
                                    </label>
                                    <div id="document-uploads">
                                        <input type="file" class="form-control mb-2" name="documents[]" accept=".pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx" multiple>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-success" onclick="addFileInput('document-uploads', 'documents[]', '.pdf,.doc,.docx,.txt,.xls,.xlsx,.ppt,.pptx')">
                                        <i class="fas fa-plus me-1"></i>Add More
                                    </button>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                                <div>
                                    <button type="button" class="btn btn-outline-danger" onclick="clearForm()">
                                        <i class="fas fa-trash me-1"></i>
                                        Clear All
                                    </button>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-success me-2" onclick="previewTicket()">
                                        <i class="fas fa-eye me-1"></i>
                                        Preview
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-paper-plane me-1"></i>
                                        Submit Request
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Preview Modal -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye text-success me-2"></i>
                        Request Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="previewContent">
                    <!-- Preview content will be inserted here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-success" onclick="submitForm()">
                        <i class="fas fa-paper-plane me-1"></i>
                        Submit Request
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Clear Confirmation Modal -->
    <div class="modal fade" id="clearModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Clear Form
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to clear all form data? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="confirmClear()">
                        <i class="fas fa-trash me-1"></i>
                        Clear All
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Load categories when department changes
        document.getElementById('department_id').addEventListener('change', function() {
            const departmentId = this.value;
            const categorySelect = document.getElementById('category_id');
            const subcategorySelect = document.getElementById('subcategory_id');

            // Clear existing options
            categorySelect.innerHTML = '<option value="">Select Category</option>';
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

            if (departmentId) {
                fetch(`../api/get_categories.php?department_id=${departmentId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(category => {
                            const option = document.createElement('option');
                            option.value = category.id;
                            option.textContent = category.name;
                            categorySelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading categories:', error));
            }
        });

        // Load subcategories when category changes
        document.getElementById('category_id').addEventListener('change', function() {
            const categoryId = this.value;
            const subcategorySelect = document.getElementById('subcategory_id');

            // Clear existing options
            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';

            if (categoryId) {
                fetch(`../api/get_subcategories.php?category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(subcategory => {
                            const option = document.createElement('option');
                            option.value = subcategory.id;
                            option.textContent = subcategory.name;
                            subcategorySelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('Error loading subcategories:', error));
            }
        });

        // Add file input function
        function addFileInput(containerId, inputName, accept) {
            const container = document.getElementById(containerId);
            const div = document.createElement('div');
            div.className = 'input-group mb-2';
            div.innerHTML = `
                <input type="file" class="form-control" name="${inputName}" accept="${accept}" multiple>
                <button type="button" class="btn btn-outline-danger" onclick="removeFileInput(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(div);
        }

        // Remove file input function
        function removeFileInput(button) {
            button.parentElement.remove();
        }

        // Clear form function
        function clearForm() {
            const modal = new bootstrap.Modal(document.getElementById('clearModal'));
            modal.show();
        }

        function confirmClear() {
            document.getElementById('ticketForm').reset();

            // Clear additional file inputs
            ['image-uploads', 'video-uploads', 'document-uploads'].forEach(containerId => {
                const container = document.getElementById(containerId);
                const inputs = container.querySelectorAll('.input-group');
                inputs.forEach(input => input.remove());
            });

            // Clear category and subcategory dropdowns
            document.getElementById('category_id').innerHTML = '<option value="">Select Category</option>';
            document.getElementById('subcategory_id').innerHTML = '<option value="">Select Subcategory</option>';

            const modal = bootstrap.Modal.getInstance(document.getElementById('clearModal'));
            modal.hide();
        }

        // Preview function
        function previewTicket() {
            const title = document.getElementById('title').value;
            const description = document.getElementById('description').value;
            const department = document.getElementById('department_id').selectedOptions[0]?.text || 'Not selected';
            const category = document.getElementById('category_id').selectedOptions[0]?.text || 'Not selected';
            const subcategory = document.getElementById('subcategory_id').selectedOptions[0]?.text || 'Not selected';
            const location = document.getElementById('location_id').selectedOptions[0]?.text || 'Not selected';

            // Get file information
            const imageFiles = Array.from(document.querySelectorAll('input[name="images[]"]')).flatMap(input => Array.from(input.files));
            const videoFiles = Array.from(document.querySelectorAll('input[name="videos[]"]')).flatMap(input => Array.from(input.files));
            const documentFiles = Array.from(document.querySelectorAll('input[name="documents[]"]')).flatMap(input => Array.from(input.files));

            let previewContent = `
                <div class="mb-3">
                    <h6>Title:</h6>
                    <p>${title || 'Not provided'}</p>
                </div>
                <div class="mb-3">
                    <h6>Description:</h6>
                    <p>${description || 'Not provided'}</p>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Department:</h6>
                        <p>${department}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Category:</h6>
                        <p>${category}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6>Subcategory:</h6>
                        <p>${subcategory}</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Location:</h6>
                        <p>${location}</p>
                    </div>
                </div>
            `;

            if (imageFiles.length > 0 || videoFiles.length > 0 || documentFiles.length > 0) {
                previewContent += '<h6>Attachments:</h6>';

                if (imageFiles.length > 0) {
                    previewContent += `<p><i class="fas fa-image text-success me-1"></i> Images: ${imageFiles.length} file(s)</p>`;
                    imageFiles.forEach(file => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.className = 'img-thumbnail me-2 mb-2';
                                img.style.maxWidth = '100px';
                                img.style.maxHeight = '100px';
                                document.getElementById('previewContent').appendChild(img);
                            };
                            reader.readAsDataURL(file);
                        }
                    });
                }

                if (videoFiles.length > 0) {
                    previewContent += `<p><i class="fas fa-video text-success me-1"></i> Videos: ${videoFiles.length} file(s)</p>`;
                }

                if (documentFiles.length > 0) {
                    previewContent += `<p><i class="fas fa-file text-success me-1"></i> Documents: ${documentFiles.length} file(s)</p>`;
                }
            }

            document.getElementById('previewContent').innerHTML = previewContent;

            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            modal.show();
        }

        function submitForm() {
            document.getElementById('ticketForm').submit();
        }
    </script>
</body>

</html>