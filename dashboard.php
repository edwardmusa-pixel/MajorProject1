<?php
session_start();

// Check if user is logged in and is admin
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
    
    // Get dynamic stats from database
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalLecturers = $pdo->query("SELECT COUNT(*) FROM lecturers")->fetchColumn();
    $totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
    $pendingCount = $pdo->query("SELECT COUNT(*) FROM attendance WHERE status='pending'")->fetchColumn();
    $totalAttendance = $pdo->query("SELECT COUNT(*) FROM attendance")->fetchColumn();
    $confirmedAttendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE status='confirmed'")->fetchColumn();
    
    // Get last 7 days attendance data for chart
    $labels = [];
    $attendanceData = [];
    for($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $labels[] = date('M d', strtotime($date));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE DATE(created_at) = ?");
        $stmt->execute([$date]);
        $attendanceData[] = (int)$stmt->fetchColumn();
    }
    
    // Get recent activities
    $recentActivities = $pdo->query("
        SELECT a.*, u.name as student_name, c.class_code 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN classes c ON a.class_id = c.id
        ORDER BY a.created_at DESC LIMIT 5
    ")->fetchAll();
    
} catch(PDOException $e) {
    $totalStudents = $totalLecturers = $totalClasses = $pendingCount = $totalAttendance = $confirmedAttendance = 0;
    $labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    $attendanceData = [0, 0, 0, 0, 0, 0, 0];
    $recentActivities = [];
}

$attendanceRate = $totalAttendance > 0 ? round(($confirmedAttendance / $totalAttendance) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | AttendPro</title>
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
        .circle-5 { width: 400px; height: 400px; top: 60%; left: -150px; animation-duration: 30s; }
        
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
        .shape-5 { bottom: 50%; left: 35%; animation-delay: 3s; animation-duration: 16s; }
        
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
        .dashboard-container {
            position: relative;
            z-index: 1;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: rgba(31, 41, 55, 0.9);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            color: white;
            position: fixed;
            height: 100vh;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-size: 1.5rem;
            font-weight: 800;
        }
        
        .logo-icon {
            font-size: 2rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .logo-highlight { color: #10B981; }
        
        .role-badge {
            background: linear-gradient(135deg, #059669, #10B981);
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            margin-top: 8px;
            font-weight: 600;
        }
        
        .sidebar-nav {
            display: flex;
            flex-direction: column;
            height: calc(100% - 120px);
            padding: 1rem 0;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            margin: 0 10px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        
        .sidebar-nav a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-nav a.active {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
            box-shadow: 0 4px 15px rgba(5, 150, 105, 0.3);
        }
        
        .sidebar-nav .password-link {
            margin-top: auto;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: 20px;
            padding-top: 12px;
        }
        
        .sidebar-footer {
            padding: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            margin-bottom: 1rem;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #059669, #10B981);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .logout-btn {
            display: block;
            background: rgba(239, 68, 68, 0.9);
            text-align: center;
            padding: 10px;
            border-radius: 12px;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }
        
        .logout-btn:hover {
            background: #DC2626;
            transform: translateY(-2px);
        }
        
        /* Main Content */
        .main-content {
            margin-left: 280px;
            flex: 1;
            padding: 1.5rem;
        }
        
        .top-bar {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 20px;
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .top-bar h1 { color: #065F46; font-size: 1.5rem; }
        
        #liveClock {
            font-family: monospace;
            font-size: 1rem;
            background: rgba(5, 150, 105, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 40px;
            color: #059669;
            font-weight: 600;
        }
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #059669, #10B981);
            padding: 1.5rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
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
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 24px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            background: rgba(255, 255, 255, 0.9);
            box-shadow: 0 20px 30px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #059669;
            margin-bottom: 0.5rem;
        }
        
        .stat-label { color: #374151; font-weight: 500; }
        
        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }
        
        .card:hover { background: rgba(255, 255, 255, 0.85); }
        .card h3 { color: #065F46; margin-bottom: 1rem; font-size: 1.2rem; }
        
        /* Chart Card */
        .chart-card {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(10px);
            padding: 1.5rem;
            border-radius: 24px;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Buttons */
        .btn {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 40px;
            text-decoration: none;
            margin-right: 12px;
            margin-bottom: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #059669, #10B981);
            color: white;
            box-shadow: 0 4px 10px rgba(5, 150, 105, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(5, 150, 105, 0.4);
        }
        
        .btn-secondary {
            background: rgba(107, 114, 128, 0.9);
            color: white;
        }
        
        .btn-secondary:hover {
            background: #6B7280;
            transform: translateY(-3px);
        }
        
        /* Recent Activity Table */
        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .activity-table th, .activity-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .activity-table th {
            color: #065F46;
            font-weight: 600;
        }
        
        .status-pending { color: #F59E0B; font-weight: bold; }
        .status-confirmed { color: #059669; font-weight: bold; }
        .status-rejected { color: #EF4444; font-weight: bold; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-nav a span:last-child { display: none; }
            .sidebar-header .logo-text { display: none; }
            .user-details { display: none; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            <div class="circle circle-5"></div>
        </div>
        <div class="floating-shapes">
            <div class="shape shape-1">🎓</div>
            <div class="shape shape-2">📍</div>
            <div class="shape shape-3">😀</div>
            <div class="shape shape-4">✅</div>
            <div class="shape shape-5">📊</div>
        </div>
        <div class="particles"></div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">🎓</div>
                    <div class="logo-text">Attend<span class="logo-highlight">Pro</span></div>
                </div>
                <div class="role-badge">ADMIN</div>
            </div>
            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">
                    <span>📊</span>
                    <span>Dashboard</span>
                </a>
                <a href="manage_lecturers.php">
                    <span>👨‍🏫</span>
                    <span>Lecturers</span>
                </a>
                <a href="manage_students.php">
                    <span>👨‍🎓</span>
                    <span>Students</span>
                </a>
                <a href="manage_classes.php">
                    <span>📚</span>
                    <span>Classes</span>
                </a>
                <a href="all_reports.php">
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
                    <div class="user-details">
                        <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br>
                        <small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small>
                    </div>
                </div>
                <a href="../logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="top-bar">
                <h1>Admin Dashboard</h1>
                <div id="liveClock"></div>
            </div>

            <!-- Welcome Section -->
            <div class="welcome-section">
                <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user']['name']); ?>! 👋</h2>
                <p>Here's what's happening with your attendance system today.</p>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalStudents; ?></div>
                    <div class="stat-label">Total Students</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalLecturers; ?></div>
                    <div class="stat-label">Total Lecturers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalClasses; ?></div>
                    <div class="stat-label">Total Classes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $pendingCount; ?></div>
                    <div class="stat-label">Pending Verifications</div>
                </div>
            </div>

            <!-- Second Row Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalAttendance; ?></div>
                    <div class="stat-label">Total Attendance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $confirmedAttendance; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $attendanceRate; ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo date('Y'); ?></div>
                    <div class="stat-label">Current Year</div>
                </div>
            </div>

            <!-- Chart Section -->
            <div class="chart-card">
                <h3>📊 Attendance Trend (Last 7 Days)</h3>
                <canvas id="attendanceChart" height="100"></canvas>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <h3>🔄 Recent Activity</h3>
                <?php if(count($recentActivities) > 0): ?>
                <table class="activity-table">
                    <thead>
                        <tr><th>Student</th><th>Class</th><th>Date</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($recentActivities as $activity): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($activity['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($activity['class_code']); ?></td>
                            <td><?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?></td>
                            <td class="status-<?php echo $activity['status']; ?>"><?php echo ucfirst($activity['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <p>No recent activity found.</p>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h3>⚡ Quick Actions</h3>
                <a href="manage_lecturers.php" class="btn btn-primary">👨‍🏫 Manage Lecturers</a>
                <a href="manage_students.php" class="btn btn-primary">👨‍🎓 Manage Students</a>
                <a href="manage_classes.php" class="btn btn-primary">📚 Manage Classes</a>
                <a href="all_reports.php" class="btn btn-secondary">📊 View Reports</a>
            </div>
        </main>
    </div>

    <script>
        // Live Clock
        function updateClock() {
            const clock = document.getElementById('liveClock');
            if (clock) {
                const now = new Date();
                clock.innerHTML = now.toLocaleString('en-US', {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
            }
        }
        setInterval(updateClock, 1000);
        updateClock();

        // Attendance Chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    label: 'Attendance Records',
                    data: <?php echo json_encode($attendanceData); ?>,
                    borderColor: '#059669',
                    backgroundColor: 'rgba(5, 150, 105, 0.1)',
                    tension: 0.4,
                    fill: true,
                    pointBackgroundColor: '#059669',
                    pointBorderColor: '#fff',
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { color: '#374151' }
                    },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: 'rgba(0,0,0,0.05)' },
                        ticks: { color: '#374151' }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { color: '#374151' }
                    }
                }
            }
        });
    </script>
</body>
</html>