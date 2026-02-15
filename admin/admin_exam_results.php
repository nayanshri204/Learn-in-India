<?php
require __DIR__ . '/../includes/header.php';

// Check admin authentication
if (empty($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';

$exam_id = !empty($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

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

// Get results for selected exam or all exams
$results = [];
$selected_exam = null;

if ($exam_id) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        // Get exam
        $stmt = $mysqli->prepare('SELECT * FROM exams WHERE id = ?');
        $stmt->bind_param('i', $exam_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $selected_exam = $result->fetch_assoc();
        $stmt->close();
        
        if ($selected_exam) {
            // Get all attempts for this exam
            $query = "SELECT ea.*, u.name as student_name, u.email as student_email, u.course
                      FROM exam_attempts ea
                      INNER JOIN users u ON ea.user_id = u.id
                      WHERE ea.exam_id = ? AND ea.status = 'submitted'
                      ORDER BY ea.submitted_at DESC";
            $stmt = $mysqli->prepare($query);
            $stmt->bind_param('i', $exam_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
            $stmt->close();
        }
        $mysqli->close();
    }
} else {
    // Get all results from all exams
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $query = "SELECT ea.*, u.name as student_name, u.email as student_email, u.course, e.title as exam_title
                  FROM exam_attempts ea
                  INNER JOIN users u ON ea.user_id = u.id
                  INNER JOIN exams e ON ea.exam_id = e.id
                  WHERE ea.status = 'submitted'
                  ORDER BY ea.submitted_at DESC
                  LIMIT 100";
        $result = $mysqli->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $results[] = $row;
            }
        }
        $mysqli->close();
    }
}
?>
<div class="card">
    <h1>Exam Results</h1>
    <p><a href="../admin/admin_dashboard.php">← Back to Admin Dashboard</a> | <a href="../admin/admin_exams.php">Manage Exams</a></p>
    
    <!-- Exam Filter -->
    <h2>Select Exam</h2>
    <form method="get" action="" style="margin-bottom: 20px;">
        <label for="exam_id">Filter by Exam:</label>
        <select id="exam_id" name="exam_id" onchange="this.form.submit()">
            <option value="">All Exams</option>
            <?php foreach ($exams as $exam): ?>
                <option value="<?php echo $exam['id']; ?>" <?php echo $exam_id == $exam['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    
    <!-- Results Summary -->
    <?php if ($selected_exam): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
            <h2><?php echo htmlspecialchars($selected_exam['title'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><strong>Total Marks:</strong> <?php echo $selected_exam['total_marks']; ?> | 
               <strong>Passing Marks:</strong> <?php echo $selected_exam['passing_marks']; ?> | 
               <strong>Total Attempts:</strong> <?php echo count($results); ?></p>
        </div>
    <?php endif; ?>
    
    <!-- Results Table -->
    <?php if (empty($results)): ?>
        <p>No results found.</p>
    <?php else: ?>
        <h2>Results</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <?php if (!$selected_exam): ?>
                        <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Exam</th>
                    <?php endif; ?>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Student Name</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Email</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Course</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Marks Obtained</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Total Marks</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Percentage</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Status</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Submitted At</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $result): ?>
                    <?php 
                    $percentage = $result['total_marks'] > 0 ? ($result['obtained_marks'] / $result['total_marks']) * 100 : 0;
                    $passed = $result['obtained_marks'] >= ($selected_exam ? $selected_exam['passing_marks'] : 0);
                    ?>
                    <tr>
                        <?php if (!$selected_exam): ?>
                            <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($result['exam_title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endif; ?>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($result['student_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($result['student_email'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($result['course'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo number_format($result['obtained_marks'], 2); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo number_format($result['total_marks'], 2); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo number_format($percentage, 2); ?>%</td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <?php if ($passed): ?>
                                <span style="color: #28a745; font-weight: bold;">PASSED</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: bold;">FAILED</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($result['submitted_at'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="admin_exam_detail.php?exam_id=<?php echo $result['exam_id']; ?>&attempt_id=<?php echo $result['id']; ?>" style="color: #0078d4;">View Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
