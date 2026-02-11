<?php
require 'header.php';

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

$errors = [];
$success = [];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid form submission.';
    } else {
        $action = $_POST['action'] ?? '';
        
        // Save Exam
        if ($action === 'save_exam') {
            $exam_id = !empty($_POST['exam_id']) ? (int)$_POST['exam_id'] : null;
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
            $start_date = $_POST['start_date'] ?? '';
            $end_date = $_POST['end_date'] ?? '';
            $total_marks = (float)($_POST['total_marks'] ?? 0);
            $passing_marks = (float)($_POST['passing_marks'] ?? 0);
            
            // Convert datetime-local format (Y-m-d\TH:i) to MySQL datetime format (Y-m-d H:i:s)
            if (!empty($start_date)) {
                $start_date = str_replace('T', ' ', $start_date) . ':00';
            }
            if (!empty($end_date)) {
                $end_date = str_replace('T', ' ', $end_date) . ':00';
            }
            
            // Validation
            if (empty($title) || strlen($title) > 255) {
                $errors[] = 'Please enter a valid exam title (max 255 characters).';
            }
            if ($duration_minutes <= 0) {
                $errors[] = 'Duration must be greater than 0.';
            }
            if (empty($start_date) || empty($end_date)) {
                $errors[] = 'Please select start and end dates.';
            } elseif (strtotime($end_date) <= strtotime($start_date)) {
                $errors[] = 'End date must be after start date.';
            }
            if ($total_marks <= 0) {
                $errors[] = 'Total marks must be greater than 0.';
            }
            if ($passing_marks < 0 || $passing_marks > $total_marks) {
                $errors[] = 'Passing marks must be between 0 and total marks.';
            }
            
            if (empty($errors)) {
                $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$mysqli->connect_errno) {
                    if ($exam_id) {
                        $stmt = $mysqli->prepare('UPDATE exams SET title = ?, description = ?, duration_minutes = ?, start_date = ?, end_date = ?, total_marks = ?, passing_marks = ? WHERE id = ?');
                        $stmt->bind_param('ssissddi', $title, $description, $duration_minutes, $start_date, $end_date, $total_marks, $passing_marks, $exam_id);
                    } else {
                        $stmt = $mysqli->prepare('INSERT INTO exams (title, description, duration_minutes, start_date, end_date, total_marks, passing_marks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->bind_param('ssissddi', $title, $description, $duration_minutes, $start_date, $end_date, $total_marks, $passing_marks, $_SESSION['admin_id']);
                    }
                    
                    if ($stmt->execute()) {
                        $success[] = $exam_id ? 'Exam updated successfully.' : 'Exam created successfully.';
                        if (!$exam_id) {
                            $exam_id = $mysqli->insert_id;
                        }
                    } else {
                        $errors[] = 'Failed to save exam.';
                    }
                    $stmt->close();
                    $mysqli->close();
                }
            }
        }
        
        // Save Question
        if ($action === 'save_question' && !empty($_POST['exam_id'])) {
            $question_id = !empty($_POST['question_id']) ? (int)$_POST['question_id'] : null;
            $exam_id = (int)$_POST['exam_id'];
            $question_text = trim($_POST['question_text'] ?? '');
            $option_a = trim($_POST['option_a'] ?? '');
            $option_b = trim($_POST['option_b'] ?? '');
            $option_c = trim($_POST['option_c'] ?? '');
            $option_d = trim($_POST['option_d'] ?? '');
            $correct_answer = $_POST['correct_answer'] ?? '';
            $marks = (float)($_POST['marks'] ?? 1);
            $question_order = (int)($_POST['question_order'] ?? 0);
            
            // Validation
            if (empty($question_text)) {
                $errors[] = 'Question text is required.';
            }
            if (empty($option_a) || empty($option_b) || empty($option_c) || empty($option_d)) {
                $errors[] = 'All options (A, B, C, D) are required.';
            }
            if (!in_array($correct_answer, ['A', 'B', 'C', 'D'])) {
                $errors[] = 'Please select a correct answer.';
            }
            if ($marks <= 0) {
                $errors[] = 'Marks must be greater than 0.';
            }
            
            if (empty($errors)) {
                $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$mysqli->connect_errno) {
                    if ($question_id) {
                        $stmt = $mysqli->prepare('UPDATE exam_questions SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_answer = ?, marks = ?, question_order = ? WHERE id = ?');
                        $stmt->bind_param('ssssssdii', $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $question_order, $question_id);
                    } else {
                        $stmt = $mysqli->prepare('INSERT INTO exam_questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_answer, marks, question_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                        $stmt->bind_param('issssssdi', $exam_id, $question_text, $option_a, $option_b, $option_c, $option_d, $correct_answer, $marks, $question_order);
                    }
                    
                    if ($stmt->execute()) {
                        $success[] = $question_id ? 'Question updated successfully.' : 'Question added successfully.';
                    } else {
                        $errors[] = 'Failed to save question.';
                    }
                    $stmt->close();
                    $mysqli->close();
                }
            }
        }
        
        // Assign Exam
        if ($action === 'assign_exam' && !empty($_POST['exam_id'])) {
            $exam_id = (int)$_POST['exam_id'];
            $assign_type = $_POST['assign_type'] ?? '';
            $user_ids = $_POST['user_ids'] ?? [];
            $course = trim($_POST['course'] ?? '');
            
            if ($assign_type === 'user' && empty($user_ids)) {
                $errors[] = 'Please select at least one student.';
            } elseif ($assign_type === 'course' && empty($course)) {
                $errors[] = 'Please enter a course name.';
            }
            
            if (empty($errors)) {
                $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
                if (!$mysqli->connect_errno) {
                    if ($assign_type === 'user') {
                        $stmt = $mysqli->prepare('INSERT IGNORE INTO exam_assignments (exam_id, user_id) VALUES (?, ?)');
                        $count = 0;
                        foreach ($user_ids as $user_id) {
                            $user_id = (int)$user_id;
                            $stmt->bind_param('ii', $exam_id, $user_id);
                            if ($stmt->execute()) {
                                $count++;
                            }
                        }
                        $stmt->close();
                        $success[] = "Exam assigned to $count student(s) successfully.";
                    } elseif ($assign_type === 'course') {
                        $stmt = $mysqli->prepare('INSERT IGNORE INTO exam_assignments (exam_id, course) VALUES (?, ?)');
                        $stmt->bind_param('is', $exam_id, $course);
                        if ($stmt->execute()) {
                            $success[] = 'Exam assigned to course successfully.';
                        }
                        $stmt->close();
                    }
                    $mysqli->close();
                }
            }
        }
    }
}

// Handle deletions
if (!empty($_GET['action']) && $_GET['action'] === 'delete') {
    if (!empty($_GET['exam_id'])) {
        $exam_id = (int)$_GET['exam_id'];
        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$mysqli->connect_errno) {
            $stmt = $mysqli->prepare('DELETE FROM exams WHERE id = ?');
            $stmt->bind_param('i', $exam_id);
            if ($stmt->execute()) {
                $success[] = 'Exam deleted successfully.';
            }
            $stmt->close();
            $mysqli->close();
        }
    }
    if (!empty($_GET['question_id'])) {
        $question_id = (int)$_GET['question_id'];
        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$mysqli->connect_errno) {
            $stmt = $mysqli->prepare('DELETE FROM exam_questions WHERE id = ?');
            $stmt->bind_param('i', $question_id);
            if ($stmt->execute()) {
                $success[] = 'Question deleted successfully.';
            }
            $stmt->close();
            $mysqli->close();
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(24));
}

// Get all exams
$exams = [];
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    $result = $mysqli->query('SELECT * FROM exams ORDER BY created_at DESC');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $exams[] = $row;
        }
    }
    $mysqli->close();
}

// Get exam for editing
$edit_exam = null;
$exam_questions = [];
$all_users = [];
if (!empty($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $stmt = $mysqli->prepare('SELECT * FROM exams WHERE id = ?');
        $stmt->bind_param('i', $edit_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $edit_exam = $row;
            
            // Get questions for this exam
            $q_stmt = $mysqli->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY question_order, id');
            $q_stmt->bind_param('i', $edit_id);
            $q_stmt->execute();
            $q_result = $q_stmt->get_result();
            while ($q_row = $q_result->fetch_assoc()) {
                $exam_questions[] = $q_row;
            }
            $q_stmt->close();
        }
        $stmt->close();
        $mysqli->close();
    }
}

// Get all users for assignment
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    $result = $mysqli->query('SELECT id, name, email, course FROM users ORDER BY name');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $all_users[] = $row;
        }
    }
    $mysqli->close();
}

// Get question for editing
$edit_question = null;
if (!empty($_GET['edit_question'])) {
    $question_id = (int)$_GET['edit_question'];
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $stmt = $mysqli->prepare('SELECT * FROM exam_questions WHERE id = ?');
        $stmt->bind_param('i', $question_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $edit_question = $row;
            if (empty($edit_exam)) {
                $edit_exam_id = $row['exam_id'];
                $e_stmt = $mysqli->prepare('SELECT * FROM exams WHERE id = ?');
                $e_stmt->bind_param('i', $edit_exam_id);
                $e_stmt->execute();
                $e_result = $e_stmt->get_result();
                if ($e_row = $e_result->fetch_assoc()) {
                    $edit_exam = $e_row;
                }
                $e_stmt->close();
            }
        }
        $stmt->close();
        $mysqli->close();
    }
}
?>
<div class="card">
    <h1>Exam Management</h1>
    <p><a href="admin_dashboard.php">← Back to Admin Dashboard</a> | <a href="admin_exam_results.php">View Results</a></p>
    
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
    
    <!-- Exam Form -->
    <h2><?php echo $edit_exam ? 'Edit Exam' : 'Create New Exam'; ?></h2>
    <form method="post" action="" style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
        <input type="hidden" name="action" value="save_exam">
        <?php if ($edit_exam): ?>
            <input type="hidden" name="exam_id" value="<?php echo $edit_exam['id']; ?>">
        <?php endif; ?>
        
        <label for="title">Exam Title</label>
        <input id="title" name="title" type="text" value="<?php echo htmlspecialchars($edit_exam['title'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="255">
        
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="3" style="width: 100%; padding: 10px; margin-top: 6px; border: 1px solid #e0e6ea; border-radius: 6px;"><?php echo htmlspecialchars($edit_exam['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
        
        <label for="duration_minutes">Duration (minutes)</label>
        <input id="duration_minutes" name="duration_minutes" type="number" value="<?php echo htmlspecialchars($edit_exam['duration_minutes'] ?? 60, ENT_QUOTES, 'UTF-8'); ?>" required min="1">
        
        <label for="start_date">Start Date & Time</label>
        <input id="start_date" name="start_date" type="datetime-local" value="<?php echo $edit_exam ? date('Y-m-d\TH:i', strtotime($edit_exam['start_date'])) : ''; ?>" required>
        
        <label for="end_date">End Date & Time</label>
        <input id="end_date" name="end_date" type="datetime-local" value="<?php echo $edit_exam ? date('Y-m-d\TH:i', strtotime($edit_exam['end_date'])) : ''; ?>" required>
        
        <label for="total_marks">Total Marks</label>
        <input id="total_marks" name="total_marks" type="number" step="0.01" value="<?php echo htmlspecialchars($edit_exam['total_marks'] ?? 100, ENT_QUOTES, 'UTF-8'); ?>" required min="0.01">
        
        <label for="passing_marks">Passing Marks</label>
        <input id="passing_marks" name="passing_marks" type="number" step="0.01" value="<?php echo htmlspecialchars($edit_exam['passing_marks'] ?? 50, ENT_QUOTES, 'UTF-8'); ?>" required min="0">
        
        <div style="margin-top: 15px;">
            <button type="submit"><?php echo $edit_exam ? 'Update Exam' : 'Create Exam'; ?></button>
            <?php if ($edit_exam): ?>
                <a href="admin_exams.php" style="margin-left: 10px; color: #0078d4;">Cancel</a>
            <?php endif; ?>
        </div>
    </form>
    
    <?php if ($edit_exam): ?>
        <!-- Question Management -->
        <h2>Questions for: <?php echo htmlspecialchars($edit_exam['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
        
        <h3><?php echo $edit_question ? 'Edit Question' : 'Add Question'; ?></h3>
        <form method="post" action="" style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="save_question">
            <input type="hidden" name="exam_id" value="<?php echo $edit_exam['id']; ?>">
            <?php if ($edit_question): ?>
                <input type="hidden" name="question_id" value="<?php echo $edit_question['id']; ?>">
            <?php endif; ?>
            
            <label for="question_text">Question Text</label>
            <textarea id="question_text" name="question_text" rows="3" style="width: 100%; padding: 10px; margin-top: 6px; border: 1px solid #e0e6ea; border-radius: 6px;" required><?php echo htmlspecialchars($edit_question['question_text'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
            
            <label for="option_a">Option A</label>
            <input id="option_a" name="option_a" type="text" value="<?php echo htmlspecialchars($edit_question['option_a'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="500">
            
            <label for="option_b">Option B</label>
            <input id="option_b" name="option_b" type="text" value="<?php echo htmlspecialchars($edit_question['option_b'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="500">
            
            <label for="option_c">Option C</label>
            <input id="option_c" name="option_c" type="text" value="<?php echo htmlspecialchars($edit_question['option_c'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="500">
            
            <label for="option_d">Option D</label>
            <input id="option_d" name="option_d" type="text" value="<?php echo htmlspecialchars($edit_question['option_d'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required maxlength="500">
            
            <label for="correct_answer">Correct Answer</label>
            <select id="correct_answer" name="correct_answer" required>
                <option value="">-- Select --</option>
                <option value="A" <?php echo ($edit_question['correct_answer'] ?? '') === 'A' ? 'selected' : ''; ?>>A</option>
                <option value="B" <?php echo ($edit_question['correct_answer'] ?? '') === 'B' ? 'selected' : ''; ?>>B</option>
                <option value="C" <?php echo ($edit_question['correct_answer'] ?? '') === 'C' ? 'selected' : ''; ?>>C</option>
                <option value="D" <?php echo ($edit_question['correct_answer'] ?? '') === 'D' ? 'selected' : ''; ?>>D</option>
            </select>
            
            <label for="marks">Marks</label>
            <input id="marks" name="marks" type="number" step="0.01" value="<?php echo htmlspecialchars($edit_question['marks'] ?? 1, ENT_QUOTES, 'UTF-8'); ?>" required min="0.01">
            
            <label for="question_order">Order</label>
            <input id="question_order" name="question_order" type="number" value="<?php echo htmlspecialchars($edit_question['question_order'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>" min="0">
            
            <div style="margin-top: 15px;">
                <button type="submit"><?php echo $edit_question ? 'Update Question' : 'Add Question'; ?></button>
                <?php if ($edit_question): ?>
                    <a href="admin_exams.php?edit=<?php echo $edit_exam['id']; ?>" style="margin-left: 10px; color: #0078d4;">Cancel</a>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Questions List -->
        <h3>Questions List</h3>
        <?php if (empty($exam_questions)): ?>
            <p>No questions added yet.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Order</th>
                        <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Question</th>
                        <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Correct</th>
                        <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Marks</th>
                        <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($exam_questions as $q): ?>
                        <tr>
                            <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $q['question_order']; ?></td>
                            <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars(mb_substr($q['question_text'], 0, 80), ENT_QUOTES, 'UTF-8'); ?><?php echo mb_strlen($q['question_text']) > 80 ? '...' : ''; ?></td>
                            <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $q['correct_answer']; ?></td>
                            <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $q['marks']; ?></td>
                            <td style="padding: 12px; border: 1px solid #dee2e6;">
                                <a href="admin_exams.php?edit=<?php echo $edit_exam['id']; ?>&edit_question=<?php echo $q['id']; ?>" style="color: #0078d4; margin-right: 10px;">Edit</a>
                                <a href="admin_exams.php?edit=<?php echo $edit_exam['id']; ?>&action=delete&question_id=<?php echo $q['id']; ?>" 
                                   onclick="return confirm('Are you sure?');" style="color: #dc3545;">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <!-- Assign Exam -->
        <h3 style="margin-top: 30px;">Assign Exam</h3>
        <form method="post" action="" style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            <input type="hidden" name="action" value="assign_exam">
            <input type="hidden" name="exam_id" value="<?php echo $edit_exam['id']; ?>">
            
            <label>Assign Type</label>
            <select id="assign_type" name="assign_type" required onchange="toggleAssignType()">
                <option value="">-- Select --</option>
                <option value="user">Assign to Specific Students</option>
                <option value="course">Assign to Course (Batch)</option>
            </select>
            
            <div id="user_assign" style="display: none; margin-top: 15px;">
                <label>Select Students</label>
                <div style="max-height: 200px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px; border-radius: 4px;">
                    <?php foreach ($all_users as $user): ?>
                        <label style="display: block; padding: 5px;">
                            <input type="checkbox" name="user_ids[]" value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8'); ?>) - <?php echo htmlspecialchars($user['course'], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="course_assign" style="display: none; margin-top: 15px;">
                <label for="course">Course Name</label>
                <input id="course" name="course" type="text" placeholder="Enter course name">
            </div>
            
            <div style="margin-top: 15px;">
                <button type="submit">Assign Exam</button>
            </div>
        </form>
    <?php endif; ?>
    
    <!-- Exams List -->
    <h2 style="margin-top: 30px;">All Exams</h2>
    <?php if (empty($exams)): ?>
        <p>No exams created yet.</p>
    <?php else: ?>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Title</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Duration</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Start Date</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">End Date</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Total Marks</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exams as $exam): ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $exam['duration_minutes']; ?> min</td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($exam['start_date'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($exam['end_date'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $exam['total_marks']; ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="admin_exams.php?edit=<?php echo $exam['id']; ?>" style="color: #0078d4; margin-right: 10px;">Edit</a>
                            <a href="admin_exams.php?action=delete&exam_id=<?php echo $exam['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this exam?');" 
                               style="color: #dc3545;">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function toggleAssignType() {
    var assignType = document.getElementById('assign_type').value;
    document.getElementById('user_assign').style.display = assignType === 'user' ? 'block' : 'none';
    document.getElementById('course_assign').style.display = assignType === 'course' ? 'block' : 'none';
}
</script>

<?php require 'footer.php'; ?>
