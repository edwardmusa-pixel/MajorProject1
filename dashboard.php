<?php
session_start();
if(!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'lecturer') {
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

// Get lecturer ID
$stmt = $pdo->prepare("SELECT id FROM lecturers WHERE user_id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$lecturer = $stmt->fetch();
$lecturerId = $lecturer['id'];

// Get lecturer's classes
$classes = $pdo->prepare("SELECT * FROM classes WHERE lecturer_id = ?");
$classes->execute([$lecturerId]);
$myClasses = $classes->fetchAll();
$classIds = array_column($myClasses, 'id');

// Statistics
$totalStudents = 0;
$pendingCount = 0;
$todayCount = 0;
$totalClasses = count($myClasses);
$attendanceRate = 0;

if(!empty($classIds)) {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    
    // Total students enrolled in my classes
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM student_enrollments WHERE class_id IN ($placeholders)");
    $stmt->execute($classIds);
    $totalStudents = $stmt->fetchColumn();
    
    // Pending verifications
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE class_id IN ($placeholders) AND status = 'pending'");
    $stmt->execute($classIds);
    $pendingCount = $stmt->fetchColumn();
    
    // Today's attendance
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE class_id IN ($placeholders) AND DATE(created_at) = ?");
    $stmt->execute(array_merge($classIds, [$today]));
    $todayCount = $stmt->fetchColumn();
    
    // Total confirmed attendance
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE class_id IN ($placeholders) AND status = 'confirmed'");
    $stmt->execute($classIds);
    $totalConfirmed = $stmt->fetchColumn();
    
    $totalAttendance = $totalConfirmed + $pendingCount;
    $attendanceRate = $totalAttendance > 0 ? round(($totalConfirmed / $totalAttendance) * 100) : 0;
}

// Get recent pending for quick view
$recentPending = [];
if($pendingCount > 0) {
    $stmt = $pdo->prepare("
        SELECT a.*, s.student_id, u.name as student_name, c.class_code 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON a.class_id = c.id
        WHERE c.lecturer_id = ? AND a.status = 'pending'
        ORDER BY a.created_at DESC LIMIT 5
    ");
    $stmt->execute([$lecturerId]);
    $recentPending = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Dashboard | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%); min-height: 100vh; }
        
        /* Animated Background */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
        }
        
        .gradient-bg {
            position: absolute;
            width: 200%;
            height: 200%;
            top: -50%;
            left: -50%;
            background: radial-gradient(circle at 30% 40%, rgba(5, 150, 105, 0.15) 0%, rgba(16, 185, 129, 0.05) 50%, transparent 100%);
            animation: rotateGradient 20s ease infinite;
        }
        
        @keyframes rotateGradient {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .moving-circles {
            position: absolute;
            width: 100%;
            height: 100%;
        }
        
        .circle {
            position: absolute;
            border-radius: 50%;
            background: rgba(5, 150, 105, 0.08);
            animation: floatCircle linear infinite;
        }
        
        .circle-1 { width: 300px; height: 300px; top: 10%; left: -100px; animation-duration: 25s; }
        .circle-2 { width: 500px; height: 500px; bottom: -200px; right: -150px; animation-duration: 35s; animation-direction: reverse; }
        .circle-3 { width: 200px; height: 200px; top: 50%; right: 20%; animation-duration: 20s; }
        .circle-4 { width: 150px; height: 150px; bottom: 30%; left: 15%; animation-duration: 18s; }
        
        @keyframes floatCircle {
            0% { transform: translate(0, 0) rotate(0deg); }
            50% { transform: translate(50px, 30px) rotate(180deg); }
            100% { transform: translate(0, 0) rotate(360deg); }
        }
        
        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        
        .shape {
            position: absolute;
            font-size: 2rem;
            opacity: 0.2;
            animation: floatShape 15s ease-in-out infinite;
        }
        
        .shape-1 { top: 15%; left: 10%; animation-delay: 0s; }
        .shape-2 { top: 70%; right: 15%; animation-delay: 2s; animation-duration: 18s; }
        .shape-3 { bottom: 20%; left: 20%; animation-delay: 4s; animation-duration: 20s; }
        .shape-4 { top: 40%; right: 25%; animation-delay: 1s; animation-duration: 22s; }
        
        @keyframes floatShape {
            0%, 100% { transform: translateY(0) rotate(0deg); opacity: 0.15; }
            50% { transform: translateY(-30px) rotate(10deg); opacity: 0.3; }
        }
        
        .particles {
            position: absolute;
            width: 100%;
            height: 100%;
            background-image: radial-gradient(circle at 20% 40%, rgba(5, 150, 105, 0.15) 1px, transparent 1px);
            background-size: 50px 50px;
            animation: moveParticles 30s linear infinite;
        }
        
        @keyframes moveParticles {
            0% { background-position: 0 0; }
            100% { background-position: 100px 100px; }
        }
        
        /* Dashboard Layout */
        .dashboard { display: flex; position: relative; z-index: 1; }
        .sidebar { width: 280px; background: rgba(31, 41, 55, 0.9); backdrop-filter: blur(12px); color: white; position: fixed; height: 100vh; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .logo-highlight { color: #10B981; }
        .role-badge { background: #3B82F6; display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-top: 8px; }
        .sidebar-nav { display: flex; flex-direction: column; height: calc(100% - 120px); }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; transition: all 0.3s; border-radius: 12px; margin: 0 10px; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .sidebar-nav .password-link { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 12px; margin-top: 20px; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .user-info { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 1rem; }
        .user-avatar { width: 40px; height: 40px; background: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .logout-btn { display: block; background: #EF4444; text-align: center; padding: 10px; border-radius: 12px; text-decoration: none; color: white; transition: all 0.3s; }
        .logout-btn:hover { background: #DC2626; transform: translateY(-2px); }
        .main-content { margin-left: 280px; flex: 1; padding: 1.5rem; }
        .top-bar { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1rem 1.5rem; border-radius: 20px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { color: #065F46; }
        #clock { font-family: monospace; background: rgba(5,150,105,0.1); padding: 0.5rem 1rem; border-radius: 40px; color: #059669; font-weight: 600; }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #3B82F6, #2563EB);
            padding: 1.5rem;
            border-radius: 24px;
            margin-bottom: 2rem;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
            background-size: 20px 20px;
            animation: shimmer 20s linear infinite;
        }
        
        @keyframes shimmer {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }
        
        .welcome-section h2, .welcome-section p { position: relative; z-index: 1; }
        .welcome-section p { opacity: 0.9; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; text-align: center; transition: all 0.3s; border: 1px solid rgba(255,255,255,0.3); }
        .stat-card:hover { transform: translateY(-5px); background: rgba(255,255,255,0.9); }
        .stat-number { font-size: 2.5rem; font-weight: bold; color: #3B82F6; }
        .stat-label { color: #374151; font-weight: 500; }
        
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.3); transition: all 0.3s; }
        .card:hover { background: rgba(255,255,255,0.85); }
        .card h3 { color: #065F46; margin-bottom: 1rem; font-size: 1.2rem; }
        
        .btn { background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; border: none; padding: 10px 20px; border-radius: 40px; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 10px; margin-bottom: 8px; font-weight: 500; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,0.3); }
        .btn-warning { background: linear-gradient(135deg, #F59E0B, #D97706); }
        .btn-warning:hover { box-shadow: 0 4px 12px rgba(245,158,11,0.3); }
        .btn-password { background: linear-gradient(135deg, #6B7280, #4B5563); }
        
        .alert { background: #FEF3C7; border-left: 4px solid #F59E0B; padding: 1rem; border-radius: 12px; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; }
        
        .my-classes { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px,1fr)); gap: 1rem; margin-top: 1rem; }
        .class-item { background: rgba(255,255,255,0.5); padding: 1rem; border-radius: 16px; border-left: 4px solid #3B82F6; transition: all 0.3s; }
        .class-item:hover { transform: translateX(5px); background: rgba(255,255,255,0.8); }
        
        .pending-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .pending-table th, .pending-table td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .pending-table th { background: #3B82F6; color: white; border-radius: 12px 12px 0 0; }
        .status-pending { color: #F59E0B; font-weight: bold; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-nav a span:last-child { display: none; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="animated-bg">
        <div class="gradient-bg"></div>
        <div class="moving-circles">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
            <div class="circle circle-4"></div>
        </div>
        <div class="floating-shapes">
            <div class="shape shape-1">🎓</div>
            <div class="shape shape-2">📍</div>
            <div class="shape shape-3">😀</div>
            <div class="shape shape-4">✅</div>
        </div>
        <div class="particles"></div>
    </div>

    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">🎓 Attend<span class="logo-highlight">Pro</span></div>
                <div class="role-badge">LECTURER</div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">
                    <span>📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="generate_qr.php">
                    <span>🎫</span>
                    <span>Generate QR</span>
                </a>
                <a href="verify_pending.php">
                    <span>✅</span>
                    <span>Verify Pending</span>
                </a>
                <a href="reports.php">
                    <span>📈</span>
                    <span>Reports</span>
                </a>
                <a href="../change_password.php" class="password-link">
                    <span>🔐</span>
                    <span>Change Password</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div>
                    <div>
                        <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </aside>
        <main class="main-content">
            <div class="top-bar">
                <h1>📊 Lecturer Dashboard</h1>
                <div id="clock"></div>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user']['name']); ?>! 👋</h2>
                <p>Here's an overview of your classes and attendance.</p>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalClasses; ?></div>
                    <div class="stat-label">My Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalStudents; ?></div>
                    <div class="stat-label">My Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pendingCount; ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attendanceRate; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </div>
            
            <?php if($pendingCount > 0): ?>
            <div class="alert">
                <span>⚠️ You have <strong><?php echo $pendingCount; ?> pending attendance requests</strong> waiting for your verification.</span>
                <a href="verify_pending.php" style="color: #D97706; text-decoration: none; font-weight: bold;">Go to Verify →</a>
            </div>
            <?php endif; ?>
            
            <?php if(count($recentPending) > 0): ?>
            <div class="card">
                <h3>⏳ Recent Pending Requests</h3>
                <table class="pending-table">
                    <thead>
                        <tr><th>Student</th><th>Class</th><th>Submitted</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentPending as $pending): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($pending['student_name']); ?> (<?php echo $pending['student_id']; ?>)</td>
                            <td><?php echo htmlspecialchars($pending['class_code']); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($pending['created_at'])); ?></td>
                            <td class="status-pending">Pending</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if($pendingCount > 5): ?>
                <a href="verify_pending.php" style="display: inline-block; margin-top: 1rem; color: #3B82F6;">View all <?php echo $pendingCount; ?> requests →</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="card">
                <h3>📚 My Classes</h3>
                <div class="my-classes">
                    <?php foreach($myClasses as $class): ?>
                    <div class="class-item">
                        <strong><?php echo htmlspecialchars($class['class_code']); ?></strong><br>
                        <?php echo htmlspecialchars($class['class_name']); ?><br>
                        📅 <?php echo $class['day_of_week']; ?> | <?php echo date('g:i A', strtotime($class['start_time'])); ?> - <?php echo date('g:i A', strtotime($class['end_time'])); ?><br>
                        📍 <?php echo htmlspecialchars($class['room']); ?>, <?php echo htmlspecialchars($class['building']); ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if(count($myClasses) == 0): ?>
                        <p>No classes assigned yet. Contact admin.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <a href="generate_qr.php" class="btn">🎫 Generate QR Code</a>
                <a href="verify_pending.php" class="btn btn-warning">✅ Verify Pending</a>
                <a href="reports.php" class="btn">📊 View Reports</a>
                <a href="../change_password.php" class="btn btn-password">🔐 Change Password</a>
            </div>
        </main>
    </div>

    <script>
        function updateClock() { 
            const now = new Date();
            document.getElementById('clock').innerHTML = now.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>