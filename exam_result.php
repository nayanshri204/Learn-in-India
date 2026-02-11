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

// Get current user ID
$user_id = null;
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
    if ($stmt) {
        $stmt->bind_param('s', $_SESSION['intern_email']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $user_id = $row['id'];
        }
        $stmt->close();
    }
    $mysqli->close();
}

if (!$user_id) {
    echo '<div class="card"><h1>Error</h1><p>User not found.</p></div>';
    require 'footer.php';
    exit;
}

$exam_id = !empty($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

if (!$exam_id) {
    echo '<div class="card"><h1>Error</h1><p>Exam ID not provided.</p><p><a href="student_exams.php">Back to Exams</a></p></div>';
    require 'footer.php';
    exit;
}

// Get exam and attempt details
$exam = null;
$attempt = null;
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
        // Get attempt for this user
        $attempt_stmt = $mysqli->prepare('SELECT * FROM exam_attempts WHERE exam_id = ? AND user_id = ? AND status = ? ORDER BY submitted_at DESC LIMIT 1');
        $status = 'submitted';
        $attempt_stmt->bind_param('iis', $exam_id, $user_id, $status);
        $attempt_stmt->execute();
        $attempt_result = $attempt_stmt->get_result();
        $attempt = $attempt_result->fetch_assoc();
        $attempt_stmt->close();
        
        if ($attempt) {
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
            $ans_stmt->bind_param('i', $attempt['id']);
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

if (!$exam || !$attempt) {
    echo '<div class="card"><h1>Error</h1><p>Exam or attempt not found.</p><p><a href="student_exams.php">Back to Exams</a></p></div>';
    require 'footer.php';
    exit;
}

$percentage = $attempt['total_marks'] > 0 ? ($attempt['obtained_marks'] / $attempt['total_marks']) * 100 : 0;
$passed = $attempt['obtained_marks'] >= $exam['passing_marks'];
?>
<div class="card">
    <h1>Exam Result: <?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><a href="student_exams.php">← Back to My Exams</a></p>
    
    <!-- Summary -->
    <div style="background: <?php echo $passed ? '#d4edda' : '#f8d7da'; ?>; border: 1px solid <?php echo $passed ? '#c3e6cb' : '#f5c6cb'; ?>; padding: 20px; border-radius: 6px; margin: 20px 0;">
        <h2 style="margin-top: 0;">Result Summary</h2>
        <div style="font-size: 18px;">
            <p><strong>Marks Obtained:</strong> <?php echo number_format($attempt['obtained_marks'], 2); ?> / <?php echo number_format($attempt['total_marks'], 2); ?></p>
            <p><strong>Percentage:</strong> <?php echo number_format($percentage, 2); ?>%</p>
            <p><strong>Passing Marks:</strong> <?php echo number_format($exam['passing_marks'], 2); ?></p>
            <p><strong>Status:</strong> 
                <span style="color: <?php echo $passed ? '#155724' : '#721c24'; ?>; font-weight: bold; font-size: 20px;">
                    <?php echo $passed ? 'PASSED' : 'FAILED'; ?>
                </span>
            </p>
            <p><strong>Submitted At:</strong> <?php echo date('Y-m-d H:i:s', strtotime($attempt['submitted_at'])); ?></p>
            <?php if ($attempt['time_spent_seconds']): ?>
                <p><strong>Time Spent:</strong> <?php echo gmdate('H:i:s', $attempt['time_spent_seconds']); ?></p>
            <?php endif; ?>
        </div>
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
                    <?php if ($selected === 'A' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Your Answer (Wrong)</span><?php endif; ?>
                </div>
                <div style="padding: 8px; <?php echo ($selected === 'B') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>B)</strong> <?php echo htmlspecialchars($question['option_b'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'B'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'B' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Your Answer (Wrong)</span><?php endif; ?>
                </div>
                <div style="padding: 8px; <?php echo ($selected === 'C') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>C)</strong> <?php echo htmlspecialchars($question['option_c'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'C'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'C' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Your Answer (Wrong)</span><?php endif; ?>
                </div>
                <div style="padding: 8px; <?php echo ($selected === 'D') ? 'background: ' . ($is_correct ? '#d4edda' : '#f8d7da') . ';' : ''; ?>">
                    <strong>D)</strong> <?php echo htmlspecialchars($question['option_d'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php if ($question['correct_answer'] === 'D'): ?><span style="color: #28a745; font-weight: bold;">✓ Correct</span><?php endif; ?>
                    <?php if ($selected === 'D' && !$is_correct): ?><span style="color: #dc3545; font-weight: bold;">✗ Your Answer (Wrong)</span><?php endif; ?>
                </div>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: <?php echo $is_correct ? '#d4edda' : '#f8d7da'; ?>; border-radius: 4px;">
                <strong>Your Answer:</strong> <?php echo $selected ? $selected : 'Not Answered'; ?> | 
                <strong>Correct Answer:</strong> <?php echo $question['correct_answer']; ?> | 
                <strong>Marks:</strong> <?php echo number_format($marks_obtained, 2); ?> / <?php echo $question['marks']; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require 'footer.php'; ?>
