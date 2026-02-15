<?php
require __DIR__ . '/../includes/header.php';

if (empty($_SESSION['intern_email'])) { header('Location: login.php'); exit; }

$db_host = 'localhost'; $db_user = 'root'; $db_pass = ''; $db_name = 'registration_db';

// Get user ID
$user_id = null;
$mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
if (!$mysqli->connect_errno) {
    $stmt = $mysqli->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->bind_param('s', $_SESSION['intern_email']);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) { $user_id = $row['id']; }
    $stmt->close();
    $mysqli->close();
}

if (!$user_id) { die('<div class="card"><h1>Error</h1><p>User not found.</p></div>'); }

$exam_id = (int)($_GET['exam_id'] ?? 0);
$attempt_id = (int)($_GET['attempt_id'] ?? 0);

// Handle auto-save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'auto_save') {
    header('Content-Type: application/json');
    if ($attempt_id && !empty($_POST['question_id']) && !empty($_POST['answer'])) {
        $question_id = (int)$_POST['question_id'];
        $answer = $_POST['answer'];
        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$mysqli->connect_errno) {
            $check = $mysqli->prepare('SELECT id FROM exam_answers WHERE attempt_id = ? AND question_id = ?');
            $check->bind_param('ii', $attempt_id, $question_id);
            $check->execute();
            $exists = $check->get_result()->num_rows > 0;
            $check->close();
            
            if ($exists) {
                $stmt = $mysqli->prepare('UPDATE exam_answers SET selected_answer = ?, answered_at = NOW() WHERE attempt_id = ? AND question_id = ?');
                $stmt->bind_param('sii', $answer, $attempt_id, $question_id);
            } else {
                $stmt = $mysqli->prepare('INSERT INTO exam_answers (attempt_id, question_id, selected_answer, answered_at) VALUES (?, ?, ?, NOW())');
                $stmt->bind_param('iis', $attempt_id, $question_id, $answer);
            }
            $stmt->execute();
            $stmt->close();
            $mysqli->close();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'DB error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid params']);
    }
    exit;
}

// Handle submit exam
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_exam' && $attempt_id) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $exam_stmt = $mysqli->prepare('SELECT e.id, e.total_marks FROM exams e INNER JOIN exam_attempts ea ON e.id = ea.exam_id WHERE ea.id = ?');
        $exam_stmt->bind_param('i', $attempt_id);
        $exam_stmt->execute();
        $exam_data = $exam_stmt->get_result()->fetch_assoc();
        $exam_stmt->close();
        
        if ($exam_data) {
            $exam_id = $exam_data['id'];
            $q_stmt = $mysqli->prepare('SELECT id, correct_answer, marks FROM exam_questions WHERE exam_id = ?');
            $q_stmt->bind_param('i', $exam_id);
            $q_stmt->execute();
            $questions_data = []; $obtained = 0; $total = 0;
            while ($row = $q_stmt->get_result()->fetch_assoc()) {
                $total += $row['marks'];
                $ans_stmt = $mysqli->prepare('SELECT selected_answer FROM exam_answers WHERE attempt_id = ? AND question_id = ?');
                $ans_stmt->bind_param('ii', $attempt_id, $row['id']);
                $ans_stmt->execute();
                $ans_row = $ans_stmt->get_result()->fetch_assoc();
                $ans_stmt->close();
                
                $is_correct = ($ans_row['selected_answer'] ?? null) === $row['correct_answer'] ? 1 : 0;
                $marks = $is_correct ? $row['marks'] : 0;
                $obtained += $marks;
                
                $upd = $mysqli->prepare('UPDATE exam_answers SET is_correct = ?, marks_obtained = ? WHERE attempt_id = ? AND question_id = ?');
                $upd->bind_param('idii', $is_correct, $marks, $attempt_id, $row['id']);
                $upd->execute();
                $upd->close();
            }
            $q_stmt->close();
            
            $time_spent = (int)($_POST['time_spent'] ?? 0);
            $status = 'submitted';
            $upd_attempt = $mysqli->prepare('UPDATE exam_attempts SET status = ?, submitted_at = NOW(), time_spent_seconds = ?, total_marks = ?, obtained_marks = ? WHERE id = ?');
            $upd_attempt->bind_param('siiddi', $status, $time_spent, $total, $obtained, $attempt_id);
            $upd_attempt->execute();
            $upd_attempt->close();
            
            $mysqli->close();
            header('Location: exam_result.php?exam_id=' . $exam_id);
            exit;
        }
        $mysqli->close();
    }
}

// Get exam details
$exam = null; $questions = []; $answers = []; $attempt = null;
if ($exam_id) {
    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
    if (!$mysqli->connect_errno) {
        $stmt = $mysqli->prepare('SELECT * FROM exams WHERE id = ?');
        $stmt->bind_param('i', $exam_id);
        $stmt->execute();
        $exam = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($exam) {
            $now = time(); $start = strtotime($exam['start_date']); $end = strtotime($exam['end_date']);
            if ($now < $start) { die("<div class='card'><h1>Not Available</h1><p>Starts: " . date('Y-m-d H:i', $start) . "</p></div>"); }
            if ($now > $end) { die("<div class='card'><h1>Not Available</h1><p>Ended: " . date('Y-m-d H:i', $end) . "</p></div>"); }
            
            if ($attempt_id) {
                $ast = $mysqli->prepare('SELECT * FROM exam_attempts WHERE id = ? AND user_id = ?');
                $ast->bind_param('ii', $attempt_id, $user_id);
                $ast->execute();
                $attempt = $ast->get_result()->fetch_assoc();
                $ast->close();
            }
            
            if (!$attempt) {
                $crst = $mysqli->prepare('INSERT INTO exam_attempts (exam_id, user_id, started_at, status) VALUES (?, ?, NOW(), "in_progress")');
                $crst->bind_param('ii', $exam_id, $user_id);
                $crst->execute();
                $attempt_id = $mysqli->insert_id;
                $crst->close();
                
                $ast = $mysqli->prepare('SELECT * FROM exam_attempts WHERE id = ?');
                $ast->bind_param('i', $attempt_id);
                $ast->execute();
                $attempt = $ast->get_result()->fetch_assoc();
                $ast->close();
            } else { $attempt_id = $attempt['id']; }
            
            if ($attempt && $attempt['status'] === 'submitted') { header('Location: exam_result.php?exam_id=' . $exam_id); exit; }
            
            $qst = $mysqli->prepare('SELECT * FROM exam_questions WHERE exam_id = ? ORDER BY question_order, id');
            $qst->bind_param('i', $exam_id);
            $qst->execute();
            while ($row = $qst->get_result()->fetch_assoc()) { $questions[] = $row; }
            $qst->close();
            
            $anst = $mysqli->prepare('SELECT question_id, selected_answer FROM exam_answers WHERE attempt_id = ?');
            $anst->bind_param('i', $attempt_id);
            $anst->execute();
            while ($row = $anst->get_result()->fetch_assoc()) { $answers[$row['question_id']] = $row['selected_answer']; }
            $anst->close();
        }
        $mysqli->close();
    }
}

if (!$exam || empty($questions)) { die('<div class="card"><h1>Error</h1><p>Exam not found.</p></div>'); }

$started = strtotime($attempt['started_at']);
$remaining = max(0, ($started + $exam['duration_minutes'] * 60) - time());
?>
<div class="card">
    <h1><?php echo htmlspecialchars($exam['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p><strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> min | <strong>Marks:</strong> <?php echo $exam['total_marks']; ?></p>
    <div style="background: #fff3cd; padding: 15px; border-radius: 6px; margin: 20px 0; text-align: center;">
        <div style="font-size: 24px; font-weight: bold;">Time: <span id="timer">--:--</span></div>
    </div>
    <form id="examForm" method="post">
        <input type="hidden" name="action" value="submit_exam">
        <input type="hidden" id="time_spent" name="time_spent" value="0">
        <?php foreach ($questions as $i => $q): ?>
            <div style="margin-bottom: 30px; padding: 20px; background: #f8f9fa; border-radius: 6px;">
                <h3>Q<?php echo $i + 1; ?> (<?php echo $q['marks']; ?> marks)</h3>
                <p><?php echo nl2br(htmlspecialchars($q['question_text'], ENT_QUOTES, 'UTF-8')); ?></p>
                <div style="margin-left: 20px;">
                    <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $key): ?>
                        <label style="display: block; padding: 8px;">
                            <input type="radio" name="answer_<?php echo $q['id']; ?>" value="<?php echo $letter; ?>" 
                                   <?php echo (isset($answers[$q['id']]) && $answers[$q['id']] === $letter) ? 'checked' : ''; ?>
                                   data-question-id="<?php echo $q['id']; ?>" class="answer-radio">
                            <strong><?php echo $letter; ?>)</strong> <?php echo htmlspecialchars($q[$key], ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <div style="text-align: center; margin: 30px 0;">
            <button type="submit" id="submitBtn" style="background: #28a745; padding: 15px 30px; font-size: 18px;" 
                    onclick="return confirm('Submit exam?');">Submit</button>
        </div>
    </form>
</div>

<script>
var rem = <?php echo $remaining; ?>, start = <?php echo $started; ?>, aid = <?php echo $attempt_id; ?>, ts = 0;

function tick() {
    var m = Math.floor(rem / 60), s = rem % 60;
    document.getElementById('timer').textContent = (m<10?'0':'') + m + ':' + (s<10?'0':'') + s;
    if (rem <= 0) { alert('Time up!'); document.getElementById('examForm').submit(); return; }
    rem--; ts = <?php echo time(); ?> - start + (<?php echo $remaining; ?> - rem);
    document.getElementById('time_spent').value = ts;
}

setInterval(tick, 1000);
tick();

document.querySelectorAll('.answer-radio').forEach(r => {
    r.addEventListener('change', function() {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'take_exam.php', true);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send('action=auto_save&attempt_id=' + aid + '&question_id=' + this.getAttribute('data-question-id') + '&answer=' + this.value);
    });
});

window.addEventListener('beforeunload', e => { e.preventDefault(); e.returnValue = 'Sure?'; });
history.pushState(null, null, location.href);
window.onpopstate = () => history.go(1);
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
