<?php
require_once '../config/session.php';
require_once '../config/database.php';

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
                $description = trim($_POST['description']);
                $head_user_id = $_POST['head_user_id'] ?: null;

                if (!empty($name) && !empty($code)) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO departments (name, code, description, head_user_id, is_active, created_at) VALUES (?, ?, ?, ?, 1, NOW())");
                        $stmt->execute([$name, $code, $description, $head_user_id]);
                        $message = "Department created successfully!";
                    } catch (PDOException $e) {
                        $error = "Error creating department: " . $e->getMessage();
                    }
                } else {
                    $error = "Please fill in the department name and code.";
                }
                break;

            case 'update':
                $id = $_POST['id'];
                $name = trim($_POST['name']);
                $code = trim($_POST['code']);
                $description = trim($_POST['description']);
                $head_user_id = $_POST['head_user_id'] ?: null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;

                if (!empty($name) && !empty($code)) {
                    try {
                        $stmt = $pdo->prepare("UPDATE departments SET name = ?, code = ?, description = ?, head_user_id = ?, is_active = ?, updated_at = NOW() WHERE id = ?");
                        $stmt->execute([$name, $code, $description, $head_user_id, $is_active, $id]);
                        $message = "Department updated successfully!";
                    } catch (PDOException $e) {
                        $error = "Error updating department: " . $e->getMessage();
                    }
                } else {
                    $error = "Please fill in the department name.";
                }
                break;

            case 'delete':
                $id = $_POST['id'];
                try {
                    $stmt = $pdo->prepare("DELETE FROM departments WHERE id = ?");
                    $stmt->execute([$id]);
                    $message = "Department deleted successfully!";
                } catch (PDOException $e) {
                    $error = "Error deleting department: " . $e->getMessage();
                }
                break;
        }
    }
}
// Get search and filter parameters
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build query conditions
$where_conditions = [];
$params = [];

if ($search) {
    $where_conditions[] = "(d.name LIKE ? OR d.code LIKE ? OR d.description LIKE ? OR CONCAT(h.first_name, ' ', h.last_name) LIKE ? OR h.email LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
}

if ($status_filter) {
    $where_conditions[] = "d.is_active = ?";
    $params[] = ($status_filter == 'active') ? 1 : 0;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get departments with filters and head information
$departments = [];
try {
    $stmt = $pdo->prepare("
        SELECT d.*, d.head_user_id, CONCAT(h.first_name, ' ', h.last_name) as head_name, h.email as head_email 
        FROM departments d 
        LEFT JOIN users h ON d.head_user_id = h.id 
        $where_clause 
        ORDER BY d.name
    ");
    $stmt->execute($params);
    $departments = $stmt->fetchAll();
} catch (PDOException $e) {
    $departments = [];
}

// Get users who can be department heads (staff, department_admin, admin)
$potential_heads = [];
try {
    $stmt = $pdo->query("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, email 
        FROM users 
        WHERE role IN ('admin', 'department_admin', 'staff') AND is_active = 1 
        ORDER BY first_name, last_name
    ");
    $potential_heads = $stmt->fetchAll();
} catch (PDOException $e) {
    $potential_heads = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Departments - ServiceLink</title>
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
                    <i class="fas fa-building text-success me-2"></i>
                    Manage Departments
                </h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                        <i class="fas fa-plus me-1"></i>
                        Add Department
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
                        <div class="col-md-8">
                            <label for="search" class="form-label">Search Departments</label>
                            <input type="text" class="form-control" id="search" name="search"
                                placeholder="Department name, code, description, head name, or email..."
                                value="<?php echo htmlspecialchars($search); ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-list text-success me-2"></i>
                        Departments List
                    </h6>
                    <small class="text-muted">
                        <?php echo count($departments); ?> departments found
                        <?php if ($search || $status_filter): ?>
                            <a href="departments.php" class="ms-2 text-decoration-none">
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
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Description</th>
                                    <th>Department Head</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($departments)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4">
                                            <i class="fas fa-building fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">
                                                <?php if ($search || $status_filter): ?>
                                                    No departments found matching your search criteria.
                                                    <br><a href="departments.php" class="text-decoration-none">Clear filters</a> to see all departments.
                                                <?php else: ?>
                                                    No departments found. Create your first department!
                                                <?php endif; ?>
                                            </p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($departments as $department): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($department['name']); ?></td>
                                            <td><code><?php echo htmlspecialchars($department['code']); ?></code></td>
                                            <td><?php echo htmlspecialchars($department['description']); ?></td>
                                            <td>
                                                <?php if ($department['head_name']): ?>
                                                    <?php echo htmlspecialchars($department['head_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($department['head_email']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">No head assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $department['is_active'] ? 'success' : 'secondary'; ?>">
                                                    <?php echo $department['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('M j, Y', strtotime($department['created_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-primary" onclick="editDepartment(<?php echo htmlspecialchars(json_encode($department)); ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" onclick="deleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')">
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
    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        <div class="mb-3">
                            <label for="name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="code" name="code" maxlength="10" required
                                placeholder="e.g., IT, HR, CS">
                            <small class="text-muted">Short code for the department (max 10 characters)</small>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="head_user_id" class="form-label">Department Head</label>
                            <select class="form-select" id="head_user_id" name="head_user_id">
                                <option value="">Select Department Head</option>
                                <?php foreach ($potential_heads as $head): ?>
                                    <option value="<?php echo $head['id']; ?>">
                                        <?php echo htmlspecialchars($head['name']); ?> (<?php echo htmlspecialchars($head['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Create Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" id="edit_id">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_code" class="form-label">Department Code *</label>
                            <input type="text" class="form-control" id="edit_code" name="code" maxlength="10" required>
                            <small class="text-muted">Short code for the department (max 10 characters)</small>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="edit_head_user_id" class="form-label">Department Head</label>
                            <select class="form-select" id="edit_head_user_id" name="head_user_id">
                                <option value="">Select Department Head</option>
                                <?php foreach ($potential_heads as $head): ?>
                                    <option value="<?php echo $head['id']; ?>">
                                        <?php echo htmlspecialchars($head['name']); ?> (<?php echo htmlspecialchars($head['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                <label class="form-check-label" for="edit_is_active">
                                    Active
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Department</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteDepartmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" id="delete_id">
                        <p>Are you sure you want to delete the department "<span id="delete_name"></span>"?</p>
                        <p class="text-danger small">This action cannot be undone and may affect existing tickets and users.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete</button>
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

        function editDepartment(department) {
            document.getElementById('edit_id').value = department.id;
            document.getElementById('edit_name').value = department.name || '';
            document.getElementById('edit_code').value = department.code || '';
            document.getElementById('edit_description').value = department.description || '';
            document.getElementById('edit_head_user_id').value = department.head_user_id || '';
            document.getElementById('edit_is_active').checked = department.is_active == 1;

            new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
        }

        function deleteDepartment(id, name) {
            document.getElementById('delete_id').value = id;
            document.getElementById('delete_name').textContent = name;

            new bootstrap.Modal(document.getElementById('deleteDepartmentModal')).show();
        }
    </script>
</body>

</html>