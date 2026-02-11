<?php require 'header.php';

// Sample course catalog (would normally come from a DB)
$courses = [
    ['title' => 'Full-Stack Web Development', 'category' => 'Web', 'level' => 'Intermediate'],
    ['title' => 'Data Science with Python', 'category' => 'Data', 'level' => 'Advanced'],
    ['title' => 'Digital Marketing Basics', 'category' => 'Marketing', 'level' => 'Beginner'],
    ['title' => 'Graphic Design Essentials', 'category' => 'Design', 'level' => 'Beginner'],
    ['title' => 'Cybersecurity Fundamentals', 'category' => 'Security', 'level' => 'Intermediate'],
];

$q = trim($_GET['q'] ?? '');
$filtered = [];
if ($q !== '') {
    $q_l = mb_strtolower($q);
    foreach ($courses as $c) {
        if (mb_stripos($c['title'], $q) !== false || mb_stripos($c['category'], $q) !== false || mb_stripos($c['level'], $q) !== false) {
            $filtered[] = $c;
        }
    }
} else {
    $filtered = $courses;
}
?>

<div class="card">
    <h1>Courses</h1>
    <p>Search results for: <strong><?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?></strong></p>

    <div>
        <input id="clientSearch" type="text" placeholder="Filter courses on this page..." style="padding:8px;width:100%;max-width:360px;margin-bottom:12px">
    </div>

    <div id="courseList">
        <?php if (empty($filtered)): ?>
            <p>No courses found.</p>
        <?php else: ?>
            <ul>
                <?php foreach ($filtered as $c): ?>
                    <li class="course-item card" style="margin-bottom:8px;padding:12px">
                        <strong><?php echo htmlspecialchars($c['title'], ENT_QUOTES, 'UTF-8'); ?></strong>
                        <div style="color:#6b7280;font-size:0.95rem">Category: <?php echo htmlspecialchars($c['category'], ENT_QUOTES, 'UTF-8'); ?> — Level: <?php echo htmlspecialchars($c['level'], ENT_QUOTES, 'UTF-8'); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>

<script>
// client-side simple filter
const input = document.getElementById('clientSearch');
const list = document.getElementById('courseList');
input.addEventListener('input', function(){
    const q = this.value.toLowerCase();
    const items = list.querySelectorAll('.course-item');
    items.forEach(it => {
        const text = it.textContent.toLowerCase();
        it.style.display = text.includes(q) ? '' : 'none';
    });
});
</script>

<?php require 'footer.php'; ?>