<?php
require_once 'config.php';
requireLogin();

// Initialize variables
$errors = [];
$category_title = '';
$category_status = 'Active';
$edit_mode = false;
$edit_id = 0;

// Fetch all categories
$categories = [];
$query = "SELECT * FROM categories ORDER BY title ASC";
$result = mysqli_query($conn, $query);
while ($row = mysqli_fetch_assoc($result)) {
    $categories[] = $row;
}

// Handle form submission for adding/editing categories
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category_title = trim($_POST['title']);
    $category_status = $_POST['status'];
    
    // Validate inputs
    if (empty($category_title)) {
        $errors['title'] = 'Category title is required';
    } elseif (strlen($category_title) < 3) {
        $errors['title'] = 'Category title must be at least 3 characters';
    }
    
    if (empty($errors)) {
        // Check if we're in edit mode
        if (isset($_POST['edit_id']) && is_numeric($_POST['edit_id'])) {
            $edit_id = (int)$_POST['edit_id'];
            $current_time = date('Y-m-d H:i:s');
            
            // Update existing category
            $query = "UPDATE categories SET title = ?, status = ?, modified = ? WHERE id = ?";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'sssi', $category_title, $category_status, $current_time, $edit_id);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = 'Category updated successfully';
                header('Location: categories.php');
                exit();
            } else {
                $errors['database'] = 'Error updating category: ' . mysqli_error($conn);
            }
        } else {
            // Add new category
            $current_time = date('Y-m-d H:i:s');
            $query = "INSERT INTO categories (title, status, created, modified) VALUES (?, ?, ?, ?)";
            $stmt = mysqli_prepare($conn, $query);
            mysqli_stmt_bind_param($stmt, 'ssss', $category_title, $category_status, $current_time, $current_time);
            
            if (mysqli_stmt_execute($stmt)) {
                $_SESSION['success_message'] = 'Category added successfully';
                header('Location: categories.php');
                exit();
            } else {
                $errors['database'] = 'Error adding category: ' . mysqli_error($conn);
            }
        }
    }
}

// Handle edit request
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $query = "SELECT * FROM categories WHERE id = ? LIMIT 1";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, 'i', $edit_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($category = mysqli_fetch_assoc($result)) {
        $category_title = $category['title'];
        $category_status = $category['status'];
        $edit_mode = true;
    } else {
        $_SESSION['error_message'] = 'Category not found';
        header('Location: categories.php');
        exit();
    }
}

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Check if category is being used by any applications
    $check_query = "SELECT COUNT(*) as count FROM applications WHERE category_id = ?";
    $check_stmt = mysqli_prepare($conn, $check_query);
    mysqli_stmt_bind_param($check_stmt, 'i', $delete_id);
    mysqli_stmt_execute($check_stmt);
    $check_result = mysqli_stmt_get_result($check_stmt);
    $usage = mysqli_fetch_assoc($check_result);
    
    if ($usage['count'] > 0) {
        $_SESSION['error_message'] = 'Cannot delete category - it is being used by one or more applications';
    } else {
        $query = "DELETE FROM categories WHERE id = ?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, 'i', $delete_id);
        
        if (mysqli_stmt_execute($stmt)) {
            $_SESSION['success_message'] = 'Category deleted successfully';
        } else {
            $_SESSION['error_message'] = 'Error deleting category: ' . mysqli_error($conn);
        }
    }
    
    header('Location: categories.php');
    exit();
}

// Display success/error messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message']);
unset($_SESSION['error_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="text"], select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background-color: #4CAF50;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
        }
        .btn:hover {
            background-color: #45a049;
        }
        .btn-edit {
            background-color: #2196F3;
        }
        .btn-edit:hover {
            background-color: #0b7dda;
        }
        .btn-delete {
            background-color: #f44336;
        }
        .btn-delete:hover {
            background-color: #d32f2f;
        }
        .btn-cancel {
            background-color: #6c757d;
        }
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        .error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 5px;
        }
        .success {
            color: #28a745;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #d4edda;
            border-radius: 4px;
        }
        .error-message {
            color: #721c24;
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f8d7da;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        .actions {
            display: flex;
            gap: 5px;
        }
        .form-container {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .logout-link {
            text-align: right;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logout-link">
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> | 
            <a href="logout.php">Logout</a>
        </div>
        
        <h1>Manage Categories</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($errors['database'])): ?>
            <div class="error-message"><?php echo $errors['database']; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <h2><?php echo $edit_mode ? 'Edit Category' : 'Add New Category'; ?></h2>
            
            <form method="POST" action="categories.php">
                <?php if ($edit_mode): ?>
                    <input type="hidden" name="edit_id" value="<?php echo $edit_id; ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label for="title">Category Title</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($category_title); ?>">
                    <?php if (!empty($errors['title'])): ?>
                        <div class="error"><?php echo $errors['title']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="Active" <?php echo $category_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $category_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn"><?php echo $edit_mode ? 'Update Category' : 'Add Category'; ?></button>
                    
                    <?php if ($edit_mode): ?>
                        <a href="categories.php" class="btn btn-cancel">Cancel</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <h2>Existing Categories</h2>
        
        <?php if (empty($categories)): ?>
            <p>No categories found.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Modified</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $category): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($category['title']); ?></td>
                            <td class="status-<?php echo strtolower($category['status']); ?>">
                                <?php echo $category['status']; ?>
                            </td>
                            <td><?php echo date('M d, Y H:i', strtotime($category['created'])); ?></td>
                            <td><?php echo date('M d, Y H:i', strtotime($category['modified'])); ?></td>
                            <td class="actions">
                                <a href="categories.php?edit=<?php echo $category['id']; ?>" class="btn btn-edit">Edit</a>
                                <a href="categories.php?delete=<?php echo $category['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this category?')">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>