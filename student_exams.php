<?php
require 'header.php';

if (empty($_SESSION['intern_email'])) {
    header('Location: login.php');
    exit;
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';

// Get current user info
$user_id = null;
$user_course = null;
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    $stmt = $mysqli->prepare('SELECT id, course FROM users WHERE email = ?');
    if ($stmt) {
        $stmt->bind_param('s', $_SESSION['intern_email']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
            $user_course = $row['course'];
        }
        $stmt->close();
    }
    $mysqli->close();
}

// Get assigned exams for this user
$available_exams = [];
if ($user_id) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        // Get exams assigned to user directly or by course
        $query = "SELECT DISTINCT e.*, 
                  (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND user_id = ?) as attempt_count
                  FROM exams e
                  INNER JOIN exam_assignments ea ON e.id = ea.exam_id
                  WHERE (ea.user_id = ? OR ea.course = ?)
                  AND e.start_date <= NOW()
                  AND e.end_date >= NOW()
                  ORDER BY e.start_date ASC";
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            $stmt->bind_param('iis', $user_id, $user_id, $user_course);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $available_exams[] = $row;
            }
            $stmt->close();
        }
        $mysqli->close();
    }
}

// Get completed exams
$completed_exams = [];
if ($user_id) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $query = "SELECT e.*, ea.obtained_marks, ea.total_marks, ea.status, ea.submitted_at
                  FROM exams e
                  INNER JOIN exam_attempts ea ON e.id = ea.exam_id
                  WHERE ea.user_id = ? AND ea.status = 'submitted'
                  ORDER BY ea.submitted_at DESC";
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $completed_exams[] = $row;
            }
            $stmt->close();
        }
        $mysqli->close();
    }
}

// Get in-progress exams
$in_progress_exams = [];
if ($user_id) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $query = "SELECT e.*, ea.id as attempt_id, ea.started_at
                  FROM exams e
                  INNER JOIN exam_attempts ea ON e.id = ea.exam_id
                  WHERE ea.user_id = ? AND ea.status = 'in_progress'
                  ORDER BY ea.started_at DESC";
        $stmt = $mysqli->prepare($query);
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $in_progress_exams[] = $row;
            }
            $stmt->close();
        }
        $mysqli->close();
    }
}
?>
<div class="card">
    <h1>My Exams</h1>
    <p><a href="dashboard.php">← Back to Dashboard</a></p>
    
    <!-- In Progress Exams -->
    <?php if (!empty($in_progress_exams)): ?>
        <h2>Exams In Progress</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Title</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Duration</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Started At</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($in_progress_exams as $exam): ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $exam['duration_minutes']; ?> minutes</td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($exam['started_at'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>&attempt_id=<?php echo $exam['attempt_id']; ?>" style="color: #0078d4;">Continue Exam</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- Available Exams -->
    <h2>Available Exams</h2>
    <?php if (empty($available_exams)): ?>
        <p>No exams available at this time.</p>
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
                <?php foreach ($available_exams as $exam): ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $exam['duration_minutes']; ?> minutes</td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($exam['start_date'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($exam['end_date'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo $exam['total_marks']; ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <?php if ($exam['attempt_count'] > 0): ?>
                                <a href="exam_result.php?exam_id=<?php echo $exam['id']; ?>" style="color: #28a745;">View Result</a>
                            <?php else: ?>
                                <a href="take_exam.php?exam_id=<?php echo $exam['id']; ?>" style="color: #0078d4;">Start Exam</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    
    <!-- Completed Exams -->
    <?php if (!empty($completed_exams)): ?>
        <h2 style="margin-top: 30px;">Completed Exams</h2>
        <table style="width: 100%; border-collapse: collapse; margin-top: 20px;">
            <thead>
                <tr style="background: #f8f9fa;">
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Title</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Marks Obtained</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Total Marks</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Percentage</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Submitted At</th>
                    <th style="text-align: left; padding: 12px; border: 1px solid #dee2e6;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($completed_exams as $exam): ?>
                    <?php 
                    $percentage = $exam['total_marks'] > 0 ? ($exam['obtained_marks'] / $exam['total_marks']) * 100 : 0;
                    $passed = $exam['obtained_marks'] >= $exam['passing_marks'];
                    ?>
                    <tr>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo number_format($exam['obtained_marks'], 2); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo number_format($exam['total_marks'], 2); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <?php echo number_format($percentage, 2); ?>%
                            <?php if ($passed): ?>
                                <span style="color: #28a745; font-weight: bold;">(Passed)</span>
                            <?php else: ?>
                                <span style="color: #dc3545; font-weight: bold;">(Failed)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;"><?php echo date('Y-m-d H:i', strtotime($exam['submitted_at'])); ?></td>
                        <td style="padding: 12px; border: 1px solid #dee2e6;">
                            <a href="exam_result.php?exam_id=<?php echo $exam['id']; ?>" style="color: #0078d4;">View Result</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require 'footer.php'; ?>
