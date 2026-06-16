<?php
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$host = 'localhost';
$dbname = 'attendpro_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed");
}

// Function to generate unique student ID
function generateStudentId($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(student_id, 2) AS UNSIGNED)) as max_id FROM students");
    $result = $stmt->fetch();
    $nextId = ($result['max_id'] ?? 0) + 1;
    return 'S' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
}

// Get classes for dropdown
$classes = $pdo->query("SELECT * FROM classes ORDER BY class_code")->fetchAll();

$message = '';
$error = '';

// Handle Add Student
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $program = trim($_POST['program']);
    $year = trim($_POST['year']);
    $classIds = $_POST['class_ids'] ?? [];
    
    // Generate random password (10 characters)
    $plainPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    // Generate unique student ID
    $studentId = generateStudentId($pdo);
    
    try {
        $pdo->beginTransaction();
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->fetch()) {
            throw new Exception("Email already exists!");
        }
        
        $stmt = $pdo->prepare("INSERT INTO users (email, password, name, role, is_active) VALUES (?, ?, ?, 'student', 1)");
        $stmt->execute([$email, $passwordHash, $name]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO students (user_id, student_id, phone, program, year_of_study) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $studentId, $phone, $program, $year]);
        $studentDbId = $pdo->lastInsertId();
        
        // Enroll in classes
        foreach($classIds as $classId) {
            $stmt = $pdo->prepare("INSERT INTO student_enrollments (student_id, class_id) VALUES (?, ?)");
            $stmt->execute([$studentDbId, $classId]);
        }
        
        $pdo->commit();
        
        $message = '<div class="success">
            <strong>✅ Student Registered Successfully!</strong><br><br>
            <div style="background: white; padding: 15px; border-radius: 12px; margin-top: 10px;">
                <p><strong>📧 Email:</strong> ' . htmlspecialchars($email) . '</p>
                <p><strong>🔑 Password:</strong> <code style="background: #e5e7eb; padding: 4px 8px; border-radius: 6px; font-size: 16px;">' . $plainPassword . '</code></p>
                <p><strong>🆔 Student ID:</strong> ' . $studentId . '</p>
                <p><strong>📚 Enrolled Classes:</strong> ' . count($classIds) . ' class(es)</p>
            </div>
            <br>
            <p>⚠️ Please provide these credentials to the student.</p>
        </div>';
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = '<div class="error">❌ Error: ' . $e->getMessage() . '</div>';
    }
}

// Handle Delete
if(isset($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT user_id FROM students WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $student = $stmt->fetch();
    if($student) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$student['user_id']]);
        $message = '<div class="success">✅ Student deleted successfully!</div>';
    }
}

// Search
$search = $_GET['search'] ?? '';
$sql = "SELECT s.*, u.name, u.email FROM students s JOIN users u ON s.user_id = u.id";
if($search) {
    $sql .= " WHERE u.name LIKE '%$search%' OR s.student_id LIKE '%$search%'";
}
$sql .= " ORDER BY s.id DESC";
$students = $pdo->query($sql)->fetchAll();

// Get enrollments for each student
$enrollments = [];
foreach($students as $s) {
    $stmt = $pdo->prepare("SELECT c.class_code, c.class_name FROM student_enrollments e JOIN classes c ON e.class_id = c.id WHERE e.student_id = ?");
    $stmt->execute([$s['id']]);
    $enrollments[$s['id']] = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Students | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%); min-height: 100vh; }
        
        .dashboard { display: flex; }
        .sidebar { width: 280px; background: rgba(31, 41, 55, 0.9); backdrop-filter: blur(12px); color: white; position: fixed; height: 100vh; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .logo-highlight { color: #10B981; }
        .role-badge { background: #059669; display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-top: 8px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); position: absolute; bottom: 0; width: 100%; }
        .user-info { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 1rem; }
        .user-avatar { width: 40px; height: 40px; background: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .logout-btn { display: block; background: #EF4444; text-align: center; padding: 10px; border-radius: 12px; text-decoration: none; color: white; }
        .main-content { margin-left: 280px; flex: 1; padding: 1.5rem; }
        .top-bar { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1rem 1.5rem; border-radius: 20px; margin-bottom: 2rem; display: flex; justify-content: space-between; }
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; margin-bottom: 1.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-top: 1rem; }
        input, select { width: 100%; padding: 12px; border: 1px solid rgba(5,150,105,0.2); border-radius: 12px; background: rgba(255,255,255,0.9); }
        .btn { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 12px 24px; border-radius: 40px; cursor: pointer; margin-top: 1rem; }
        .btn-gray { background: #6B7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.1); }
        th { background: #059669; color: white; }
        .delete-btn { background: #EF4444; padding: 5px 10px; border-radius: 6px; text-decoration: none; color: white; font-size: 12px; display: inline-block; }
        .search-box { padding: 10px; width: 300px; margin-bottom: 1rem; }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .class-checkboxes { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 10px; }
        .class-checkboxes label { display: flex; align-items: center; gap: 5px; background: #f3f4f6; padding: 5px 10px; border-radius: 20px; cursor: pointer; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .main-content { margin-left: 70px; } .sidebar-nav a span:last-child { display: none; } }
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-header"><div class="logo">🎓 Attend<span class="logo-highlight">Pro</span></div><div class="role-badge">ADMIN</div></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="manage_lecturers.php">👨‍🏫 Lecturers</a>
            <a href="manage_students.php" class="active">👨‍🎓 Students</a>
            <a href="manage_classes.php">📚 Classes</a>
            <a href="all_reports.php">📈 Reports</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div><div><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br><small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small></div></div>
            <a href="../logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>👨‍🎓 Manage Students</h1><div id="clock"></div></div>
        
        <?php echo $message; ?>
        <?php echo $error; ?>
        
        <div class="card">
            <h3>➕ Register New Student</h3>
            <form method="POST">
                <div class="form-grid">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="text" name="phone" placeholder="Phone Number">
                    <input type="text" name="program" placeholder="Program (e.g., Computer Science)">
                    <select name="year">
                        <option>Year 1</option><option>Year 2</option><option>Year 3</option><option>Year 4</option>
                    </select>
                </div>
                <div class="class-checkboxes">
                    <strong>📚 Enroll in Classes:</strong>
                    <?php foreach($classes as $c): ?>
                    <label><input type="checkbox" name="class_ids[]" value="<?php echo $c['id']; ?>"> <?php echo $c['class_code']; ?> - <?php echo $c['class_name']; ?></label>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="add_student" class="btn">➕ Register Student</button>
            </form>
        </div>
        
        <div class="card">
            <h3>🔍 Search Students</h3>
            <form method="GET">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" class="search-box" placeholder="Search by name or ID...">
                <button type="submit" class="btn">Search</button>
                <a href="manage_students.php" class="btn btn-gray">Reset</a>
            </form>
        </div>
        
        <div class="card">
            <h3>📋 All Students</h3>
            <?php if(count($students) > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Program</th><th>Year</th><th>Enrolled Classes</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $s): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['student_id']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['email']); ?></td>
                            <td><?php echo htmlspecialchars($s['program'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($s['year_of_study'] ?? '-'); ?></td>
                            <td>
                                <?php 
                                $classesList = [];
                                foreach($enrollments[$s['id']] ?? [] as $e) {
                                    $classesList[] = $e['class_code'];
                                }
                                echo implode(', ', $classesList) ?: '-';
                                ?>
                            </td>
                            <td><a href="?delete=<?php echo $s['id']; ?>" class="delete-btn" onclick="return confirm('Delete this student?')">Delete</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem;">No students found.</p>
            <?php endif; ?>
        </div>
    </main>
</div>
<script>
    function updateClock() { document.getElementById('clock').innerHTML = new Date().toLocaleString(); }
    setInterval(updateClock, 1000); updateClock();
</script>
</body>
</html>