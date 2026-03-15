<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    header('Location: ../login.php');
    exit;
}

$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $location = trim($_POST['location']);
                $address = trim($_POST['address']);
                $phone = trim($_POST['phone']);
                $email = trim($_POST['email']);
                
                if (!empty($name) && !empty($code) && !empty($location)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO campuses (name, code, location, address, phone, email, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, 1, NOW())");
                        $stmt->execute([$name, $code, $location, $address, $phone, $email]);
                        $message = "Campus created successfully!";
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $error = "Campus code already exists. Please use a different code.";
                        } else {
                            $error = "Error creating campus: " . $e->getMessage();
                        }
                    }
                } else {
                    $error = "Please fill in all required fields.";
                }
                break;
                
            case 'update':
                $id = $_POST['id'];
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $location = trim($_POST['location']);
                $address = trim($_POST['address']);
                $phone = trim($_POST['phone']);
                $email = trim($_POST['email']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                if (!empty($name) && !empty($code) && !empty($location)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE campuses SET name = ?, code = ?, location = ?, address = ?, phone = ?, email = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $code, $location, $address, $phone, $email, $is_active, $id]);
                        $message = "Campus updated successfully!";
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            $error = "Campus code already exists. Please use a different code.";
                        } else {
                            $error = "Error updating campus: " . $e->getMessage();
                        }
                    }
                } else {
                    $error = "Please fill in all required fields.";
                }
                break;
                
            case 'delete':
                $id = $_POST['id'];
                try {
                    // Check if campus has users
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE campus_id = ?");
                    $stmt->execute([$id]);
                    $user_count = $stmt->fetch()['count'];
                    
                    if ($user_count > 0) {
                        $error = "Cannot delete campus. There are {$user_count} users assigned to this campus.";
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM campuses WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = "Campus deleted successfully!";
                    }
                } catch (PDOException $e) {
                    $error = "Error deleting campus: " . $e->getMessage();
                }
                break;
        }
    }
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query with search and filters
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(name LIKE ? OR code LIKE ? OR location LIKE ? OR address LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = $status_filter;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get campuses with user count
try {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(u.id) as user_count
        FROM campuses c
        LEFT JOIN users u ON c.id = u.campus_id
        {$where_clause}
        GROUP BY c.id
        ORDER BY c.name ASC
    ");
    $stmt->execute($params);
    $campuses = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error loading campuses: " . $e->getMessage();
    $campuses = [];
}

// Get total count for results display
$total_count = count($campuses);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Management - ServiceLink Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/top_nav.php'; ?>
    
    <div class="container-fluid p-0">
        <!-- Sidebar -->
        <?php include '../includes/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="dashboard-content">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">
                    <i class="fas fa-university text-success me-2"></i>
                    Campus Management
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCampusModal">
                        <i class="fas fa-plus me-1"></i>
                        Add Campus
                    </button>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Search and Filters -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="search" class="form-label">Search Campuses</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Name, code, location, or address..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="1" <?php echo ($status_filter === '1') ? 'selected' : ''; ?>>Active</option>
                                <option value="0" <?php echo ($status_filter === '0') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>


            <!-- Campuses Table -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list text-success me-2"></i>
                        Campuses List
                    </h6>
                    <small class="text-muted">
                        <?php echo count($campuses); ?> campuses found
                        <?php if ($search || $status_filter !== ''): ?>
                            <a href="campuses.php" class="ms-2 text-decoration-none">
                                <i class="fas fa-times"></i> Clear filters
                            </a>
                        <?php endif; ?>
                    </small>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Campus</th>
                                    <th>Code</th>
                                    <th>Location</th>
                                    <th>Contact</th>
                                    <th>Users</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($campuses)): ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-4">
                                            <i class="fas fa-university fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">
                                                <?php if ($search || $status_filter !== ''): ?>
                                                    No campuses found matching your search criteria.
                                                    <br><a href="campuses.php" class="text-decoration-none">Clear filters</a> to see all campuses.
                                                <?php else: ?>
                                                    No campuses found. Create your first campus!
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($campuses as $campus): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="bg-success bg-opacity-10 rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 35px; height: 35px;">
                                                    <i class="fas fa-university text-success"></i>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars($campus['name']); ?></div>
                                                    <?php if ($campus['address']): ?>
                                                        <small class="text-muted"><?php echo htmlspecialchars($campus['address']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($campus['code']); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($campus['location']); ?></td>
                                        <td>
                                            <?php if ($campus['phone']): ?>
                                                <div><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($campus['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if ($campus['email']): ?>
                                                <div><i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($campus['email']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!$campus['phone'] && !$campus['email']): ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $campus['user_count']; ?> users</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $campus['is_active'] ? 'success' : 'secondary'; ?>">
                                                <?php echo $campus['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($campus['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editCampus(<?php echo htmlspecialchars(json_encode($campus)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteCampus(<?php echo $campus['id']; ?>, '<?php echo htmlspecialchars($campus['name']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Create Campus Modal -->
    <div class="modal fade" id="createCampusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>
                        Add New Campus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="create_name" class="form-label">Campus Name *</label>
                                    <input type="text" class="form-control" id="create_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="create_code" class="form-label">Code *</label>
                                    <input type="text" class="form-control" id="create_code" name="code" required maxlength="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="create_location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="create_location" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="create_address" class="form-label">Address</label>
                            <textarea class="form-control" id="create_address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="create_phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="create_phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="create_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="create_email" name="email">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>
                            Create Campus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Campus Modal -->
    <div class="modal fade" id="editCampusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Campus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="edit_name" class="form-label">Campus Name *</label>
                                    <input type="text" class="form-control" id="edit_name" name="name" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_code" class="form-label">Code *</label>
                                    <input type="text" class="form-control" id="edit_code" name="code" required maxlength="10">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_location" class="form-label">Location *</label>
                            <input type="text" class="form-control" id="edit_location" name="location" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_address" class="form-label">Address</label>
                            <textarea class="form-control" id="edit_address" name="address" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_phone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="edit_phone" name="phone">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="edit_email" name="email">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active Campus
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Update Campus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Campus Modal -->
    <div class="modal fade" id="deleteCampusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Delete Campus
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Are you sure you want to delete the campus "<strong id="delete_name"></strong>"?
                        </div>
                        
                        <p class="text-muted">This action cannot be undone. The campus will be permanently removed from the system.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-1"></i>
                            Delete Campus
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth page transition effect
        document.addEventListener('DOMContentLoaded', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';
            
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

        function editCampus(campus) {
            document.getElementById('edit_id').value = campus.id;
            document.getElementById('edit_name').value = campus.name || '';
            document.getElementById('edit_code').value = campus.code || '';
            document.getElementById('edit_location').value = campus.location || '';
            document.getElementById('edit_address').value = campus.address || '';
            document.getElementById('edit_phone').value = campus.phone || '';
            document.getElementById('edit_email').value = campus.email || '';
            document.getElementById('edit_is_active').checked = campus.is_active == 1;
            
            new bootstrap.Modal(document.getElementById('editCampusModal')).show();
        }

        function deleteCampus(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;
            
            new bootstrap.Modal(document.getElementById('deleteCampusModal')).show();
        }
    </script>
</body>
</html>