<?php
require __DIR__ . '/../includes/header.php';

// Check admin authentication
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';

// Upload directories
$upload_dir = __DIR__ . '/uploads/';
$profile_images_dir = $upload_dir . 'profile_images/';
$certificates_dir = $upload_dir . 'certificates/';

// Create upload directories if they don't exist

$upload_dir = realpath(__DIR__ . '/../') . DIRECTORY_SEPARATOR . 'uploads';
$profile_images_dir = $upload_dir . DIRECTORY_SEPARATOR . 'profile_images';
$certificates_dir = $upload_dir . DIRECTORY_SEPARATOR . 'certificates';

if (!file_exists($upload_dir)) {
    @mkdir($upload_dir, 0777, true);
}

if (!file_exists($profile_images_dir)) {
    @mkdir($profile_images_dir, 0777, true);
}

if (!file_exists($certificates_dir)) {
    @mkdir($certificates_dir, 0777, true);
}

$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Add/Edit Intern
        if ($action === 'save_intern') {
            $id = $_POST['id'] ?? null;
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $gender = $_POST['gender'] ?? '';
            $course = trim($_POST['course'] ?? '');
            
            // Validation
            if (empty($name) || !preg_match('/^[\p{L} .\'-]{2,100}$/u', $name)) {
                $errors[] = 'Please enter a valid full name (2-100 characters).';
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Please provide a valid email address.';
            }
            if (empty($id) && strlen($password) < 6) {
                $errors[] = 'Please provide a password of at least 6 characters.';
            }
            if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
                $errors[] = 'Please select a valid gender.';
            }
            if (empty($course) || mb_strlen($course) > 40) {
                $errors[] = 'Please enter a course (max 40 characters).';
            }
            
            if (empty($errors)) {
                $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$mysqli->connect_errno) {
                    // Check if email already exists (for new interns or different intern)
                    $check_stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
                    $check_id = $id ?: 0;
                    $check_stmt->bind_param('si', $email, $check_id);
                    $check_stmt->execute();
                    if ($check_stmt->get_result()->num_rows > 0) {
                        $errors[] = 'An account with that email already exists.';
                    }
                    $check_stmt->close();
                    
                    if (empty($errors)) {
                        if ($id) {
                            // Update existing intern
                            if (!empty($password)) {
                                $hashed = password_hash($password, PASSWORD_DEFAULT);
                                $stmt = $mysqli->prepare('UPDATE users SET name = ?, email = ?, password = ?, gender = ?, course = ? WHERE id = ?');
                                $stmt->bind_param('sssssi', $name, $email, $hashed, $gender, $course, $id);
                            } else {
                                $stmt = $mysqli->prepare('UPDATE users SET name = ?, email = ?, gender = ?, course = ? WHERE id = ?');
                                $stmt->bind_param('ssssi', $name, $email, $gender, $course, $id);
                            }
                        } else {
                            // Insert new intern
                            $hashed = password_hash($password, PASSWORD_DEFAULT);
                            $stmt = $mysqli->prepare('INSERT INTO users (name, email, password, gender, course) VALUES (?, ?, ?, ?, ?)');
                            $stmt->bind_param('sssss', $name, $email, $hashed, $gender, $course);
                        }
                        
                        if ($stmt->execute()) {
                            $success[] = $id ? 'Intern updated successfully.' : 'Intern added successfully.';
                        } else {
                            $errors[] = 'Failed to save intern.';
                        }
                        $stmt->close();
                    }
                    $mysqli->close();
                } else {
                    $errors[] = 'Database connection failed.';
                }
            }
        }
        
        // Upload Profile Image
        if ($action === 'upload_profile_image') {
            $intern_id = $_POST['intern_id'] ?? null;
            if ($intern_id && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['profile_image'];
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $errors[] = 'Invalid file type. Only JPEG, PNG, and GIF are allowed.';
                } elseif ($file['size'] > $max_size) {
                    $errors[] = 'File size exceeds 5MB limit.';
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'profile_' . $intern_id . '_' . time() . '.' . $ext;
                    $filepath = $profile_images_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $relative_path = 'uploads/profile_images/' . $filename;
                        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                        if (!$mysqli->connect_errno) {
                            // Check if column exists
                            $check_result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
                            if ($check_result && $check_result->num_rows > 0) {
                                // Delete old image if exists
                                $old_stmt = $mysqli->prepare('SELECT profile_image FROM users WHERE id = ?');
                                $old_stmt->bind_param('i', $intern_id);
                                $old_stmt->execute();
                                $old_result = $old_stmt->get_result();
                                if ($old_row = $old_result->fetch_assoc() && !empty($old_row['profile_image'])) {
                                    $old_file = __DIR__ . '/' . $old_row['profile_image'];
                                    if (file_exists($old_file)) unlink($old_file);
                                }
                                $old_stmt->close();
                                
                                // Update database
                                $stmt = $mysqli->prepare('UPDATE users SET profile_image = ? WHERE id = ?');
                                $stmt->bind_param('si', $relative_path, $intern_id);
                                if ($stmt->execute()) {
                                    $success[] = 'Profile image uploaded successfully.';
                                } else {
                                    $errors[] = 'Failed to update profile image.';
                                }
                                $stmt->close();
                            } else {
                                $errors[] = 'Profile image column does not exist. Please run the database migration first.';
                            }
                            $mysqli->close();
                        }
                    } else {
                        $errors[] = 'Failed to upload file.';
                    }
                }
            } else {
                $errors[] = 'Please select a valid image file.';
            }
        }
        
        // Upload Certificate
        if ($action === 'upload_certificate') {
            $intern_id = $_POST['intern_id'] ?? null;
            if ($intern_id && isset($_FILES['certificate']) && $_FILES['certificate']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['certificate'];
                $allowed_types = ['application/pdf'];
                $max_size = 10 * 1024 * 1024; // 10MB
                
                if (!in_array($file['type'], $allowed_types)) {
                    $errors[] = 'Invalid file type. Only PDF files are allowed.';
                } elseif ($file['size'] > $max_size) {
                    $errors[] = 'File size exceeds 10MB limit.';
                } else {
                    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'certificate_' . $intern_id . '_' . time() . '.' . $ext;
                    $filepath = $certificates_dir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $relative_path = 'uploads/certificates/' . $filename;
                        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                        if (!$mysqli->connect_errno) {
                            // Check if column exists
                            $check_result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'certificate_path'");
                            if ($check_result && $check_result->num_rows > 0) {
                                // Delete old certificate if exists
                                $old_stmt = $mysqli->prepare('SELECT certificate_path FROM users WHERE id = ?');
                                $old_stmt->bind_param('i', $intern_id);
                                $old_stmt->execute();
                                $old_result = $old_stmt->get_result();
                                if ($old_row = $old_result->fetch_assoc() && !empty($old_row['certificate_path'])) {
                                    $old_file = __DIR__ . '/' . $old_row['certificate_path'];
                                    if (file_exists($old_file)) unlink($old_file);
                                }
                                $old_stmt->close();
                                
                                // Update database
                                $stmt = $mysqli->prepare('UPDATE users SET certificate_path = ? WHERE id = ?');
                                $stmt->bind_param('si', $relative_path, $intern_id);
                                if ($stmt->execute()) {
                                    $success[] = 'Certificate uploaded successfully.';
                                } else {
                                    $errors[] = 'Failed to update certificate.';
                                }
                                $stmt->close();
                            } else {
                                $errors[] = 'Certificate path column does not exist. Please run the database migration first.';
                            }
                            $mysqli->close();
                        }
                    } else {
                        $errors[] = 'Failed to upload file.';
                    }
                }
            } else {
                $errors[] = 'Please select a valid PDF file.';
            }
        }
    }
}

// Handle delete action
if (($_GET['action'] ?? '') === 'delete' && !empty($_GET['id'])) {
    $id = (int)$_GET['id'];
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        // Check if columns exist before querying
        $check_result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
        $columns_exist = ($check_result && $check_result->num_rows > 0);
        
        if ($columns_exist) {
            // Get file paths before deleting
            $stmt = $mysqli->prepare('SELECT profile_image, certificate_path FROM users WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                // Delete files
                if (!empty($row['profile_image'])) {
                    $img_file = __DIR__ . '/' . $row['profile_image'];
                    if (file_exists($img_file)) unlink($img_file);
                }
                if (!empty($row['certificate_path'])) {
                    $cert_file = __DIR__ . '/' . $row['certificate_path'];
                    if (file_exists($cert_file)) unlink($cert_file);
                }
            }
            $stmt->close();
        }
        
        // Delete from database
        $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
        $stmt->bind_param('i', $id);
        if ($stmt->execute()) {
            $success[] = 'Intern deleted successfully.';
        } else {
            $errors[] = 'Failed to delete intern.';
        }
        $stmt->close();
        $mysqli->close();
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// Get total interns count
$total_interns = 0;
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    $result = $mysqli->query('SELECT COUNT(*) as total FROM users');
    if ($row = $result->fetch_assoc()) {
        $total_interns = $row['total'];
    }
    $mysqli->close();
}

// Get all interns
$interns = [];
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    // Check if profile_image and certificate_path columns exist
    $columns_exist = false;
    $check_result = $mysqli->query("SHOW COLUMNS FROM users LIKE 'profile_image'");
    if ($check_result && $check_result->num_rows > 0) {
        $columns_exist = true;
    }
    
    if ($columns_exist) {
        $result = $mysqli->query('SELECT id, name, email, gender, course, profile_image, certificate_path FROM users ORDER BY id DESC');
    } else {
        $result = $mysqli->query('SELECT id, name, email, gender, course FROM users ORDER BY id DESC');
    }
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            // Add default values if columns don't exist
            if (!isset($row['profile_image'])) {
                $row['profile_image'] = null;
            }
            if (!isset($row['certificate_path'])) {
                $row['certificate_path'] = null;
            }
            $interns[] = $row;
        }
    }
    $mysqli->close();
}

// Get intern for editing
$edit_intern = null;
if (!empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $stmt = $mysqli->prepare('SELECT id, name, email, gender, course FROM users WHERE id = ?');
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $edit_intern = $row;
        }
        $stmt->close();
        $mysqli->close();
    }
}
?>
<div class="card">
    <h1>Admin Dashboard</h1>
    <p>Welcome, <?php echo htmlspecialchars($_SESSION['admin_email'], ENT_QUOTES, 'UTF-8'); ?>!</p>
    <p style="margin-bottom: 20px;">
        <a href="admin_exams.php" style="color: #0078d4; margin-right: 15px;">Manage Exams</a> | 
        <a href="admin_exam_results.php" style="color: #0078d4; margin-right: 15px;">View Exam Results</a>
    </p>
    
    <?php if ($errors): ?>
        <div class="errors">
            <ul>
                <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 12px;">
            <ul style="margin: 0; padding-left: 20px;">
                <?php foreach ($success as $s): ?>
                    <li><?php echo htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <!-- Statistics -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <h2 style="margin-top: 0;">Statistics</h2>
        <p style="font-size: 24px; font-weight: bold; color: #0078d4;">
            Total Interns: <?php echo $total_interns; ?>
        </p>
    </div>
    
    <!-- Add/Edit Intern Form -->
    <h2><?php echo $edit_intern ? 'Edit Intern' : 'Add New Intern'; ?></h2>
    <form method="post" action="" style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="save_intern">
        <?php if ($edit_intern): ?>
            <input type="hidden" name="id" value="<?php echo $edit_intern['id']; ?>">
        <?php endif; ?>
        
        <label for="name">Full Name</label>
        <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($edit_intern['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($edit_intern['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        
        <label for="password">Password <?php echo $edit_intern ? '(leave blank to keep current)' : ''; ?></label>
        <input id="password" name="password" type="password" <?php echo $edit_intern ? '' : 'required'; ?>>
        
        <label for="gender">Gender</label>
        <select id="gender" name="gender" required>
            <option value="">-- Select --</option>
            <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                <option value="<?php echo $g; ?>" <?php echo ($edit_intern['gender'] ?? '') === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
            <?php endforeach; ?>
        </select>
        
        <label for="course">Course</label>
        <input id="course" name="course" type="text" value="<?php echo htmlspecialchars($edit_intern['course'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
        
        <div class="actions">
            <button type="submit"><?php echo $edit_intern ? 'Update Intern' : 'Add Intern'; ?></button>
            <?php if ($edit_intern): ?>
                <a href="admin_dashboard.php" style="margin-left: 10px; padding: 10px 16px; background: #6c757d; color: #fff; text-decoration: none; border-radius: 4px; display: inline-block;">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    
    <!-- Interns List -->
    <h2>All Interns</h2>
    <?php if (empty($interns)): ?>
        <p>No interns registered yet.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">ID</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Profile Image</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Name</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Email</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Gender</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Course</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Certificate</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($interns as $intern): ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $intern['id']; ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <?php if (!empty($intern['profile_image'])): ?>
                                <img src="<?php echo htmlspecialchars($intern['profile_image'], ENT_QUOTES, 'UTF-8'); ?>" 
                                     alt="Profile" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;">
                            <?php else: ?>
                                <span style="color: #999;">No image</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($intern['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($intern['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($intern['gender'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($intern['course'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <?php if (!empty($intern['certificate_path'])): ?>
                                <a href="<?php echo htmlspecialchars($intern['certificate_path'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" style="color: #0078d4;">View PDF</a>
                            <?php else: ?>
                                <span style="color: #999;">No certificate</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="admin_dashboard.php?edit=<?php echo $intern['id']; ?>" style="color: #0078d4; margin-right: 10px;">Edit</a>
                            <a href="admin_dashboard.php?action=delete&id=<?php echo $intern['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this intern?');" 
                               style="color: #dc3545; margin-right: 10px;">Delete</a>
                            <a href="#upload-image-<?php echo $intern['id']; ?>" style="color: #28a745; margin-right: 10px;">Upload Image</a>
                            <a href="#upload-cert-<?php echo $intern['id']; ?>" style="color: #ffc107;">Upload Certificate</a>
                        </td>
                    </tr>
                    <!-- Upload Profile Image Form (collapsible) -->
                    <tr id="upload-image-<?php echo $intern['id']; ?>" style="display: none;">
                        <td colspan="8" style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6;">
                            <h3>Upload Profile Image for <?php echo htmlspecialchars($intern['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <form method="post" action="" enctype="multipart/form-data" style="max-width: 500px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="upload_profile_image">
                                <input type="hidden" name="intern_id" value="<?php echo $intern['id']; ?>">
                                <label for="profile_image_<?php echo $intern['id']; ?>">Select Image (JPEG, PNG, GIF - Max 5MB)</label>
                                <input id="profile_image_<?php echo $intern['id']; ?>" name="profile_image" type="file" accept="image/jpeg,image/jpg,image/png,image/gif" required>
                                <div class="actions" style="margin-top: 10px;">
                                    <button type="submit">Upload Image</button>
                                    <button type="button" onclick="document.getElementById('upload-image-<?php echo $intern['id']; ?>').style.display='none';">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <!-- Upload Certificate Form (collapsible) -->
                    <tr id="upload-cert-<?php echo $intern['id']; ?>" style="display: none;">
                        <td colspan="8" style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6;">
                            <h3>Upload Certificate for <?php echo htmlspecialchars($intern['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                            <form method="post" action="" enctype="multipart/form-data" style="max-width: 500px;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                                <input type="hidden" name="action" value="upload_certificate">
                                <input type="hidden" name="intern_id" value="<?php echo $intern['id']; ?>">
                                <label for="certificate_<?php echo $intern['id']; ?>">Select PDF (Max 10MB)</label>
                                <input id="certificate_<?php echo $intern['id']; ?>" name="certificate" type="file" accept="application/pdf" required>
                                <div class="actions" style="margin-top: 10px;">
                                    <button type="submit">Upload Certificate</button>
                                    <button type="button" onclick="document.getElementById('upload-cert-<?php echo $intern['id']; ?>').style.display='none';">Cancel</button>
                                </div>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <p style="margin-top: 30px;">
        <a href="admin_logout.php">Logout</a>
    </p>
</div>

<script>
// Show/hide upload forms when clicking links
document.querySelectorAll('a[href^="#upload-"]').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        var targetId = this.getAttribute('href').substring(1);
        var targetRow = document.getElementById(targetId);
        if (targetRow) {
            // Hide all other upload forms
            document.querySelectorAll('tr[id^="upload-"]').forEach(function(row) {
                if (row.id !== targetId) {
                    row.style.display = 'none';
                }
            });
            // Toggle current form
            targetRow.style.display = targetRow.style.display === 'none' ? 'table-row' : 'none';
        }
    });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>

