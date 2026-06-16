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

// Get lecturers for dropdown
$lecturers = $pdo->query("SELECT l.*, u.name FROM lecturers l JOIN users u ON l.user_id = u.id")->fetchAll();

// Handle Add Class
$message = '';
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    try {
        $stmt = $pdo->prepare("INSERT INTO classes (class_code, class_name, lecturer_id, day_of_week, start_time, end_time, room, building, latitude, longitude) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['class_code'], 
            $_POST['class_name'], 
            $_POST['lecturer_id'],
            $_POST['day'], 
            $_POST['start_time'], 
            $_POST['end_time'],
            $_POST['room'], 
            $_POST['building'], 
            $_POST['lat'], 
            $_POST['lng']
        ]);
        $message = '<div class="success">✅ Class created successfully!</div>';
    } catch(Exception $e) {
        $message = '<div class="error">❌ Error: ' . $e->getMessage() . '</div>';
    }
}

// Handle Delete
if(isset($_GET['delete'])) {
    try {
        $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$_GET['delete']]);
        $message = '<div class="success">✅ Class deleted successfully!</div>';
    } catch(Exception $e) {
        $message = '<div class="error">❌ Cannot delete: Class has attendance records</div>';
    }
}

// Get all classes with lecturer names
$classes = $pdo->query("
    SELECT c.*, u.name as lecturer_name 
    FROM classes c 
    JOIN lecturers l ON c.lecturer_id = l.id 
    JOIN users u ON l.user_id = u.id 
    ORDER BY c.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Classes | AttendPro</title>
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
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); position: absolute; bottom: 0; width: 100%; }
        .user-info { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 1rem; }
        .user-avatar { width: 40px; height: 40px; background: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
        .logout-btn { display: block; background: #EF4444; text-align: center; padding: 10px; border-radius: 12px; text-decoration: none; color: white; }
        .main-content { margin-left: 280px; flex: 1; padding: 1.5rem; }
        .top-bar { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1rem 1.5rem; border-radius: 20px; margin-bottom: 2rem; display: flex; justify-content: space-between; }
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; margin-bottom: 1.5rem; }
        .form-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-top: 1rem; }
        input, select { width: 100%; padding: 12px; border: 1px solid rgba(5,150,105,0.2); border-radius: 12px; background: rgba(255,255,255,0.9); }
        .btn { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 12px 24px; border-radius: 40px; cursor: pointer; margin-top: 1rem; }
        .location-box { margin-top: 1rem; padding: 1rem; background: rgba(5,150,105,0.1); border-radius: 16px; }
        .classes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px,1fr)); gap: 1rem; margin-top: 1rem; }
        .class-card { background: rgba(255,255,255,0.8); padding: 1rem; border-radius: 16px; border-left: 4px solid #059669; }
        .delete-btn { background: #EF4444; padding: 5px 10px; border-radius: 6px; text-decoration: none; color: white; font-size: 12px; display: inline-block; margin-top: 8px; }
        .success { background: #D1FAE5; color: #065F46; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .error { background: #FEE2E2; color: #991B1B; padding: 12px; border-radius: 12px; margin-bottom: 1rem; }
        .geo-note { font-size: 12px; color: #059669; margin-top: 5px; }
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
            <a href="manage_students.php">👨‍🎓 Students</a>
            <a href="manage_classes.php" class="active">📚 Classes</a>
            <a href="all_reports.php">📈 Reports</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div><div><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br><small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small></div></div>
            <a href="../logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>📚 Manage Classes (5m Geo-Fence)</h1><div id="clock"></div></div>
        
        <?php echo $message; ?>
        
        <div class="card">
            <h3>➕ Create New Class</h3>
            <form method="POST">
                <div class="form-grid">
                    <input type="text" name="class_code" placeholder="Course Code (e.g., CS301)" required>
                    <input type="text" name="class_name" placeholder="Class Name" required>
                    <select name="lecturer_id" required>
                        <option value="">Select Lecturer</option>
                        <?php foreach($lecturers as $l): ?>
                            <option value="<?php echo $l['id']; ?>"><?php echo htmlspecialchars($l['name']); ?> (<?php echo $l['lecturer_id']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <select name="day" required>
                        <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
                        <option>Thursday</option><option>Friday</option>
                    </select>
                    <input type="time" name="start_time" value="09:00" required>
                    <input type="time" name="end_time" value="11:00" required>
                    <input type="text" name="room" placeholder="Room Number">
                    <input type="text" name="building" placeholder="Building Name">
                </div>
                <div class="location-box">
                    <strong>📍 Class Location (5 meter Geo-Fence)</strong>
                    <div class="form-grid" style="margin-top: 10px;">
                        <input type="text" name="lat" placeholder="Latitude (e.g., 37.7749)" required>
                        <input type="text" name="lng" placeholder="Longitude (e.g., -122.4194)" required>
                    </div>
                    <div class="geo-note">⚠️ Students must be within 5 meters of this location to mark attendance</div>
                </div>
                <button type="submit" name="add_class" class="btn">➕ Create Class</button>
            </form>
        </div>
        
        <div class="card">
            <h3>📋 All Classes</h3>
            <div class="classes-grid">
                <?php foreach($classes as $c): ?>
                <div class="class-card">
                    <strong><?php echo htmlspecialchars($c['class_code']); ?></strong><br>
                    <strong style="font-size: 1.1rem;"><?php echo htmlspecialchars($c['class_name']); ?></strong><br>
                    👨‍🏫 <?php echo htmlspecialchars($c['lecturer_name']); ?><br>
                    📅 <?php echo $c['day_of_week']; ?> | <?php echo date('g:i A', strtotime($c['start_time'])); ?> - <?php echo date('g:i A', strtotime($c['end_time'])); ?><br>
                    📍 <?php echo htmlspecialchars($c['room']); ?>, <?php echo htmlspecialchars($c['building']); ?><br>
                    🗺️ GPS: <?php echo $c['latitude']; ?>, <?php echo $c['longitude']; ?><br>
                    <a href="?delete=<?php echo $c['id']; ?>" class="delete-btn" onclick="return confirm('⚠️ Delete this class? This will remove all attendance records.')">Delete Class</a>
                </div>
                <?php endforeach; ?>
                <?php if(count($classes) == 0): ?>
                    <p>No classes found. Create your first class above.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>
<script>
    function updateClock() { document.getElementById('clock').innerHTML = new Date().toLocaleString(); }
    setInterval(updateClock, 1000); updateClock();
</script>
</body>
</html>