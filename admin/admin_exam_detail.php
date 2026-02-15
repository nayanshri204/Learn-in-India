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
$attempt_id = !empty($_GET['attempt_id']) ? (int)$_GET['attempt_id'] : 0;

if (!$exam_id || !$attempt_id) {
    echo '<div class="card"><h1>Error</h1><p>Invalid parameters.</p><p><a href="admin_exam_results.php">Back to Results</a></p></div>';
    require '../includes/footer.php';
    exit;
}

// Get exam, attempt, and student details
$exam = null;
$attempt = null;
$student = null;
$questions = [];
$answers = [];

$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    // Get exam
    $stmt = $mysqli->prepare('SELECT * FROM exams WHERE id = ?');
    $stmt->bind_param('i', $exam_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $exam = $result->fetch_assoc();
    $stmt->close();
    
    if ($exam) {
        // Get attempt
        $attempt_stmt = $mysqli->prepare('SELECT * FROM exam_attempts WHERE id = ? AND exam_id = ?');
        $attempt_stmt->bind_param('ii', $attempt_id, $exam_id);
        $attempt_stmt->execute();
        $attempt_result = $attempt_stmt->get_result();
        $attempt = $attempt_result->fetch_assoc();
        $attempt_stmt->close();
        
        if ($attempt) {
            // Get student
            $student_stmt = $mysqli->prepare('SELECT * FROM users WHERE id = ?');
            $student_stmt->bind_param('i', $attempt['user_id']);
            $student_stmt->execute();
            $student_result = $student_stmt->get_result();
            $student = $student_result->fetch_assoc();
            $student_stmt->close();
            
            // Get questions
            $q_stmt = $mysqli->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY question_order, id');
            $q_stmt->bind_param('i', $exam_id);
            $q_stmt->execute();
            $q_result = $q_stmt->get_result();
            while ($q_row = $q_result->fetch_assoc()) {
                $questions[] = $q_row;
            }
            $q_stmt->close();
            
            // Get answers
            $ans_stmt = $mysqli->prepare('SELECT * FROM exam_answers WHERE attempt_id = ?');
            $ans_stmt->bind_param('i', $attempt_id);
            $ans_stmt->execute();
            $ans_result = $ans_stmt->get_result();
            while ($ans_row = $ans_result->fetch_assoc()) {
                $answers[$ans_row['question_id']] = $ans_row;
            }
            $ans_stmt->close();
        }
    }
    $mysqli->close();
}

if (!$exam || !$attempt || !$student) {
    echo '<div class="card"><h1>Error</h1><p>Exam, attempt, or student not found.</p><p><a href="admin_exam_results.php">Back to Results</a></p></div>';
    require '../includes/footer.php';
    exit;
}

$percentage = $attempt['total_marks'] > 0 ? ($attempt['obtained_marks'] / $attempt['total_marks']) * 100 : 0;
$passed = $attempt['obtained_marks'] >= $exam['passing_marks'];
?>
<div class="card">
    <h1>Exam Result Details</h1>
    <p><a href="admin_exam_results.php?exam_id=<?php echo $exam_id; ?>">← Back to Results</a></p>
    
    <!-- Student Info -->
    <div style="background: #f8f9fa; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
        <h2>Student Information</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Course:</strong> <?php echo htmlspecialchars($student['course'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    
    <!-- Summary -->
    <div style="background: <?php echo $passed ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $passed ? '#c3e6cb' : '#f5c6cb'; ?>; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h2 style="margin-top: 0;">Result Summary</h2>
        <p><strong>Exam:</strong> <?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></p>
        <p><strong>Marks Obtained:</strong> <?php echo number_format($attempt['obtained_marks'], 2); ?> / <?php echo number_format($attempt['total_marks'], 2); ?></p>
        <p><strong>Percentage:</strong> <?php echo number_format($percentage, 2); ?>%</p>
        <p><strong>Passing Marks:</strong> <?php echo number_format($exam['passing_marks'], 2); ?></p>
        <p><strong>Status:</strong> 
            <span style="color: <?php echo $passed ? '#155724' : '#721c24'; ?>; font-weight: bold; font-size: 20px;">
                <?php echo $passed ? 'PASSED' : 'FAILED'; ?>
            </span>
        </p>
        <p><strong>Started At:</strong> <?php echo date('Y-m-d H:i:s', strtotime($attempt['started_at'])); ?></p>
        <p><strong>Submitted At:</strong> <?php echo date('Y-m-d H:i:s', strtotime($attempt['submitted_at'])); ?></p>
        <?php if ($attempt['time_spent_seconds']): ?>
            <p><strong>Time Spent:</strong> <?php echo gmdate('H:i:s', $attempt['time_spent_seconds']); ?></p>
        <?php endif; ?>
    </div>
    
    <!-- Detailed Results -->
    <h2>Question-wise Results</h2>
    <?php foreach ($questions as $index => $question): ?>
        <?php 
        $answer = $answers[$question['id']] ?? null;
        $selected = $answer ? $answer['selected_answer'] : null;
        $is_correct = $answer ? $answer['is_correct'] : 0;
        $marks_obtained = $answer ? $answer['marks_obtained'] : 0;
        ?>
        <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-left: 4px solid <?php echo $is_correct ? '#28a745' : '#dc3545'; ?>; border-radius: 6px;">
            <h3>Question <?php echo $index + 1; ?> (<?php echo $question['marks']; ?> marks)</h3>
            <p style="font-size: 16px; margin-bottom: 15px;"><?php echo nl2br(htmlspecialchars($question['question_text'], ENT_QUOTES, 'UTF-8')); ?></p>
            
            <div style="margin-left: 20px; margin-bottom: 15px;">
                <div style="padding: 8px; <?php echo ($selected === 'A') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>A)</strong> <?php echo htmlspecialchars($question['option_a'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'A'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'A' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Student Answer (Wrong)</span><?php endif; ?>
                </div>
                <div style="padding: 8px; <?php echo ($selected === 'B') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>B)</strong> <?php echo htmlspecialchars($question['option_b'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'B'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'B' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Student Answer (Wrong)</span><?php endif; ?>
                </div>
                <div style="padding: 8px; <?php echo ($selected === 'C') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>C)</strong> <?php echo htmlspecialchars($question['option_c'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'C'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'C' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Student Answer (Wrong)</span><?php endif; ?>
                </div>
                <div style="padding: 8px; <?php echo ($selected === 'D') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>D)</strong> <?php echo htmlspecialchars($question['option_d'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'D'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'D' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Student Answer (Wrong)</span><?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: <?php echo $is_correct ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px;">
                <strong>Student Answer:</strong> <?php echo $selected ? $selected : 'Not Answered'; ?> | 
                <strong>Correct Answer:</strong> <?php echo $question['correct_answer']; ?> | 
                <strong>Marks:</strong> <?php echo number_format($marks_obtained, 2); ?> / <?php echo $question['marks']; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
