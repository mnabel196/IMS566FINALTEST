<?php

require_once 'config.php';
requireLogin();

require_once 'config.php';

// Initialize variables
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_category = isset($_GET['category']) ? $_GET['category'] : '';
$search_query = isset($_GET['search']) ? $_GET['search'] : '';

// Build base query
$query = "SELECT a.*, c.title AS category_title 
          FROM applications a 
          LEFT JOIN categories c ON a.category_id = c.id 
          WHERE 1=1";

// Add filters to query
$params = [];
if (!empty($filter_status)) {
    $query .= " AND a.status = ?";
    $params[] = $filter_status;
}

if (!empty($filter_category)) {
    $query .= " AND a.category_id = ?";
    $params[] = $filter_category;
}

if (!empty($search_query)) {
    $query .= " AND (a.title LIKE ? OR a.review LIKE ? OR a.author LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$query .= " ORDER BY a.posted_date DESC";

// Prepare and execute query
$stmt = mysqli_prepare($conn, $query);
if (!empty($params)) {
    $types = str_repeat('s', count($params));
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$applications = mysqli_fetch_all($result, MYSQLI_ASSOC);

// Get categories for filter dropdown
$categories = [];
$category_result = mysqli_query($conn, "SELECT * FROM categories");
while ($row = mysqli_fetch_assoc($category_result)) {
    $categories[] = $row;
}

// Calculate average ratings for each application
foreach ($applications as &$app) {
    $app_id = $app['id'];
    $rating_query = "SELECT AVG(rating) as avg_rating FROM comments WHERE application_id = $app_id";
    $rating_result = mysqli_query($conn, $rating_query);
    $rating_data = mysqli_fetch_assoc($rating_result);
    $app['avg_rating'] = round($rating_data['avg_rating'], 1);
}
unset($app); // Break the reference
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Reviews</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .filters {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        label {
            margin-bottom: 5px;
            font-weight: bold;
        }
        select, input[type="text"], input[type="search"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 150px;
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
        .btn-export {
            background-color: #2196F3;
        }
        .btn-export:hover {
            background-color: #0b7dda;
        }
        .btn-create {
            background-color: #ff9800;
        }
        .btn-create:hover {
            background-color: #e68a00;
        }
        .actions {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .app-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .app-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }
        .app-title {
            font-size: 20px;
            font-weight: bold;
            color: #333;
            margin: 0;
        }
        .app-meta {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
        }
        .app-image {
            max-width: 200px;
            max-height: 150px;
            margin-right: 15px;
            margin-bottom: 15px;
            float: left;
        }
        .app-review {
            margin-top: 10px;
            color: #444;
        }
        .rating {
            color: #ffc107;
            font-weight: bold;
        }
        .status {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }
        .status-active {
            background-color: #d4edda;
            color: #155724;
        }
        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .pagination a {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        .pagination a.active {
            background-color: #4CAF50;
            color: white;
            border: 1px solid #4CAF50;
        }
        .pagination a:hover:not(.active) {
            background-color: #ddd;
        }
        .no-results {
            text-align: center;
            padding: 20px;
            color: #666;
        }
    </style>
</head>
<div style="text-align: right; margin-bottom: 15px;">
    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?> | 
    <a href="logout.php">Logout</a>
</div>
<body>
    <div class="container">
        <h1>Application Reviews</h1>
        
        <div class="actions">
            <a href="create.php" class="btn btn-create">Create New Review</a>
            <a href="export.php" class="btn btn-export">Export to PDF</a>
        </div>
        
        <form method="GET" action="index.php">
            <div class="filters">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="search" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search...">
                </div>
                
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="">All Statuses</option>
                        <option value="Active" <?php echo $filter_status === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Inactive" <?php echo $filter_status === 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="category">Category</label>
                    <select id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $filter_category == $category['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['title']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group" style="align-self: flex-end;">
                    <button type="submit" class="btn">Apply Filters</button>
                    <a href="index.php" class="btn" style="background-color: #6c757d;">Reset</a>
                </div>
            </div>
        </form>
        
        <?php if (empty($applications)): ?>
            <div class="no-results">
                <p>No applications found matching your criteria.</p>
            </div>
        <?php else: ?>
            <?php foreach ($applications as $app): ?>
                <div class="app-card">
                    <div class="app-header">
                        <h2 class="app-title"><?php echo htmlspecialchars($app['title']); ?></h2>
                        <div>
                            <span class="status status-<?php echo strtolower($app['status']); ?>"><?php echo $app['status']; ?></span>
                        </div>
                    </div>
                    
                    <div class="app-meta">
                        <span>Category: <?php echo htmlspecialchars($app['category_title'] ?? 'Uncategorized'); ?></span> | 
                        <span>Author: <?php echo htmlspecialchars($app['author']); ?></span> | 
                        <span>Posted: <?php echo date('M d, Y H:i', strtotime($app['posted_date'])); ?></span> | 
                        <span class="rating">Rating: <?php echo $app['avg_rating']; ?> ★</span>
                    </div>
                    
                    <div class="clearfix">
                        <?php if (!empty($app['image'])): ?>
                            <img src="<?php echo htmlspecialchars($app['image_dir']); ?>" alt="<?php echo htmlspecialchars($app['title']); ?>" class="app-image">
                        <?php endif; ?>
                        
                        <div class="app-review">
                            <?php echo nl2br(htmlspecialchars($app['review'])); ?>
                        </div>
                    </div>
                    
                    <div style="margin-top: 15px;">
                        <a href="view.php?id=<?php echo $app['id']; ?>" class="btn">View Details</a>
                        <a href="edit.php?id=<?php echo $app['id']; ?>" class="btn" style="background-color: #ffc107;">Edit</a>
                        <a href="delete.php?id=<?php echo $app['id']; ?>" class="btn" style="background-color: #dc3545;" onclick="return confirm('Are you sure you want to delete this review?')">Delete</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>