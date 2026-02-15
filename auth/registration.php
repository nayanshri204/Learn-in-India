<?php
// 1. सबसे पहले Header शामिल करें (यह Session भी स्टार्ट कर देगा)
// हम __DIR__ का उपयोग कर रहे हैं ताकि पाथ की गलती न हो
require_once __DIR__ . '/../includes/header.php'; 

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'registration_db';

// JSON फाइल का पाथ अब एक फोल्डर बाहर 'data' में होगा
$usersFile = __DIR__ . '/../data/users.json';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$errors = [];
$values = ['name' => '', 'email' => '', 'password' => '', 'gender' => '', 'course' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... (आपका बाकी वैलिडेशन कोड यहाँ रहेगा) ...
    
    if (empty($errors)) {
        $hashed = password_hash($values['password'], PASSWORD_DEFAULT);
        $inserted = false;

        // डेटाबेस में सेव करना
        $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name);
        if (!$mysqli->connect_errno) {
            $stmt = $mysqli->prepare('INSERT INTO users (name, email, password, gender, course) VALUES (?, ?, ?, ?, ?)');
            if ($stmt) {
                $stmt->bind_param('sssss', $values['name'], $values['email'], $hashed, $values['gender'], $values['course']);
                if ($stmt->execute()) $inserted = true;
                $stmt->close();
            }
            $mysqli->close();
        }

        if ($inserted) {
            // JSON सिंक करने का लॉजिक (जैसा आपके पास था)
            // ...
            
            $_SESSION['intern_email'] = $values['email'];
            header('Location: ../dashboard.php'); 
            exit;
        } else {
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}
?>

<div class="container">
    <div class="card">
        <h1>Student Registration</h1>
        <?php if ($errors): ?>
            <div class="errors"><ul>
                <?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?>
            </ul></div>
        <?php endif; ?>

        <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <label>Full Name</label>
            <input name="name" type="text" value="<?= htmlspecialchars($values['name']) ?>" required>
            
            <label>Email</label>
            <input name="email" type="email" value="<?= htmlspecialchars($values['email']) ?>" required>
            
            <label>Password</label>
            <input name="password" type="password" required>

            <label>Gender</label>
            <select name="gender" required>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
            </select>

            <label>Course</label>
            <input name="course" type="text" value="<?= htmlspecialchars($values['course']) ?>" required>

            <div class="actions">
                <button type="submit">Register</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>