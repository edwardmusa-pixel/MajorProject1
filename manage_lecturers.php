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

// Function to generate unique lecturer ID
function generateLecturerId($pdo) {
    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(lecturer_id, 2) AS UNSIGNED)) as max_id FROM lecturers");
    $result = $stmt->fetch();
    $nextId = ($result['max_id'] ?? 0) + 1;
    return 'L' . str_pad($nextId, 3, '0', STR_PAD_LEFT);
}

$message = '';
$error = '';

// Handle Add Lecturer
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_lecturer'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $office = trim($_POST['office']);
    $specialization = trim($_POST['specialization']);
    
    // Generate random password (10 characters)
    $plainPassword = substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 10);
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);
    
    // Generate unique lecturer ID
    $lecturerId = generateLecturerId($pdo);
    
    try {
        $pdo->beginTransaction();
        
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if($stmt->fetch()) {
            throw new Exception("Email already exists!");
        }
        
        $stmt = $pdo->prepare("INSERT INTO users (email, password, name, role, is_active) VALUES (?, ?, ?, 'lecturer', 1)");
        $stmt->execute([$email, $passwordHash, $name]);
        $userId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO lecturers (user_id, lecturer_id, department, phone, office_room, specialization) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $lecturerId, $department, $phone, $office, $specialization]);
        
        $pdo->commit();
        
        $message = '<div class="success">
            <strong>✅ Lecturer Added Successfully!</strong><br><br>
            <div style="background: white; padding: 15px; border-radius: 12px; margin-top: 10px;">
                <p><strong>📧 Email:</strong> ' . htmlspecialchars($email) . '</p>
                <p><strong>🔑 Password:</strong> <code style="background: #e5e7eb; padding: 4px 8px; border-radius: 6px; font-size: 16px;">' . $plainPassword . '</code></p>
                <p><strong>🆔 Lecturer ID:</strong> ' . $lecturerId . '</p>
            </div>
            <br>
            <p>⚠️ Please provide these credentials to the lecturer.</p>
        </div>';
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = '<div class="error">❌ Error: ' . $e->getMessage() . '</div>';
    }
}

// Handle Delete
if(isset($_GET['delete'])) {
    $stmt = $pdo->prepare("SELECT user_id FROM lecturers WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $lecturer = $stmt->fetch();
    if($lecturer) {
        $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$lecturer['user_id']]);
        $message = '<div class="success">✅ Lecturer deleted successfully!</div>';
    }
}

$lecturers = $pdo->query("SELECT l.*, u.name, u.email FROM lecturers l JOIN users u ON l.user_id = u.id ORDER BY l.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Lecturers | AttendPro</title>
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
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; }
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
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.1); }
        th { background: #059669; color: white; }
        .delete-btn { background: #EF4444; padding: 5px 10px; border-radius: 6px; text-decoration: none; color: white; font-size: 12px; display: inline-block; }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .main-content { margin-left: 70px; } .sidebar-nav a span:last-child { display: none; } }
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-header"><div class="logo">🎓 Attend<span class="logo-highlight">Pro</span></div><div class="role-badge">ADMIN</div></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="manage_lecturers.php" class="active">👨‍🏫 Lecturers</a>
            <a href="manage_students.php">👨‍🎓 Students</a>
            <a href="manage_classes.php">📚 Classes</a>
            <a href="all_reports.php">📈 Reports</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div><div><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br><small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small></div></div>
            <a href="../logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>👨‍🏫 Manage Lecturers</h1><div id="clock"></div></div>
        
        <?php echo $message; ?>
        <?php echo $error; ?>
        
        <div class="card">
            <h3>➕ Add New Lecturer</h3>
            <form method="POST">
                <div class="form-grid">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email Address" required>
                    <input type="text" name="department" placeholder="Department">
                    <input type="text" name="phone" placeholder="Phone">
                    <input type="text" name="office" placeholder="Office Room">
                    <input type="text" name="specialization" placeholder="Specialization">
                </div>
                <button type="submit" name="add_lecturer" class="btn">➕ Add Lecturer</button>
            </form>
        </div>
        
        <div class="card">
            <h3>📋 All Lecturers</h3>
            <?php if(count($lecturers) > 0): ?>
            <div style="overflow-x: auto;">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Department</th><th>Phone</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($lecturers as $l): ?>
                        <tr>
                            <td><?php echo $l['lecturer_id']; ?></td>
                            <td><strong><?php echo htmlspecialchars($l['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($l['email']); ?></td>
                            <td><?php echo htmlspecialchars($l['department'] ?? '-'); ?></td>
                            <td><?php echo htmlspecialchars($l['phone'] ?? '-'); ?></td>
                            <td><a href="?delete=<?php echo $l['id']; ?>" class="delete-btn" onclick="return confirm('Delete this lecturer?')">Delete</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p style="text-align: center; padding: 2rem;">No lecturers found.</p>
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