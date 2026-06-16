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

// Get lecturers for filter dropdown
$lecturers = $pdo->query("
    SELECT l.*, u.name 
    FROM lecturers l 
    JOIN users u ON l.user_id = u.id 
    ORDER BY u.name
")->fetchAll();

// Get classes for filter dropdown
$classes = $pdo->query("
    SELECT c.*, u.name as lecturer_name 
    FROM classes c 
    JOIN lecturers l ON c.lecturer_id = l.id 
    JOIN users u ON l.user_id = u.id 
    ORDER BY c.class_code
")->fetchAll();

// Handle filters
$classId = $_GET['class_id'] ?? '';
$lecturerId = $_GET['lecturer_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';

// Build query
$sql = "SELECT a.*, s.student_id, u.name as student_name, c.class_code, c.class_name, 
               lec.lecturer_id, lec_u.name as lecturer_name
        FROM attendance a 
        JOIN students s ON a.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        JOIN classes c ON a.class_id = c.id
        JOIN lecturers lec ON c.lecturer_id = lec.id
        JOIN users lec_u ON lec.user_id = lec_u.id
        WHERE 1=1";
$params = [];

if($classId) {
    $sql .= " AND a.class_id = ?";
    $params[] = $classId;
}
if($lecturerId) {
    $sql .= " AND c.lecturer_id = ?";
    $params[] = $lecturerId;
}
if($startDate) {
    $sql .= " AND DATE(a.session_time) >= ?";
    $params[] = $startDate;
}
if($endDate) {
    $sql .= " AND DATE(a.session_time) <= ?";
    $params[] = $endDate;
}
if($status) {
    $sql .= " AND a.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY a.session_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Calculate summary
$total = count($records);
$confirmed = 0;
$pending = 0;
$rejected = 0;
foreach($records as $r) {
    if($r['status'] == 'confirmed') $confirmed++;
    elseif($r['status'] == 'pending') $pending++;
    elseif($r['status'] == 'rejected') $rejected++;
}

// Handle CSV Export
if(isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Student Name', 'Class', 'Lecturer', 'Session Date', 'Status', 'Face Verified', 'Location Verified', 'Distance (m)', 'Method', 'Submitted At']);
    foreach($records as $r) {
        fputcsv($output, [
            $r['student_id'],
            $r['student_name'],
            $r['class_code'] . ' - ' . $r['class_name'],
            $r['lecturer_name'],
            $r['session_time'],
            $r['status'],
            $r['face_verified'] ? 'Yes' : 'No',
            $r['location_verified'] ? 'Yes' : 'No',
            $r['distance_from_class'] ?? '-',
            $r['method'],
            $r['created_at']
        ]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Reports | AttendPro</title>
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
            background: radial-gradient(circle at 30% 40%, rgba(5,150,105,0.15) 0%, rgba(16,185,129,0.05) 50%, transparent 100%);
            animation: rotateGradient 20s ease infinite;
        }
        @keyframes rotateGradient { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .moving-circles { position: absolute; width: 100%; height: 100%; }
        .circle { position: absolute; border-radius: 50%; background: rgba(5,150,105,0.08); animation: floatCircle linear infinite; }
        .circle-1 { width: 300px; height: 300px; top: 10%; left: -100px; animation-duration: 25s; }
        .circle-2 { width: 500px; height: 500px; bottom: -200px; right: -150px; animation-duration: 35s; animation-direction: reverse; }
        .circle-3 { width: 200px; height: 200px; top: 50%; right: 20%; animation-duration: 20s; }
        .circle-4 { width: 150px; height: 150px; bottom: 30%; left: 15%; animation-duration: 18s; }
        @keyframes floatCircle { 0% { transform: translate(0,0) rotate(0deg); } 50% { transform: translate(50px,30px) rotate(180deg); } 100% { transform: translate(0,0) rotate(360deg); } }
        .floating-shapes { position: absolute; width: 100%; height: 100%; pointer-events: none; }
        .shape { position: absolute; font-size: 2rem; opacity: 0.2; animation: floatShape 15s ease-in-out infinite; }
        .shape-1 { top: 15%; left: 10%; animation-delay: 0s; }
        .shape-2 { top: 70%; right: 15%; animation-delay: 2s; }
        .shape-3 { bottom: 20%; left: 20%; animation-delay: 4s; }
        .shape-4 { top: 40%; right: 25%; animation-delay: 1s; }
        @keyframes floatShape { 0%,100% { transform: translateY(0) rotate(0deg); opacity: 0.15; } 50% { transform: translateY(-30px) rotate(10deg); opacity: 0.3; } }
        .particles { position: absolute; width: 100%; height: 100%; background-image: radial-gradient(circle at 20% 40%, rgba(5,150,105,0.15) 1px, transparent 1px); background-size: 50px 50px; animation: moveParticles 30s linear infinite; }
        @keyframes moveParticles { 0% { background-position: 0 0; } 100% { background-position: 100px 100px; } }
        
        .dashboard { display: flex; position: relative; z-index: 1; }
        .sidebar { width: 280px; background: rgba(31,41,55,0.9); backdrop-filter: blur(12px); color: white; position: fixed; height: 100vh; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .logo-highlight { color: #10B981; }
        .role-badge { background: #059669; display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-top: 8px; }
        .sidebar-nav { display: flex; flex-direction: column; height: calc(100% - 120px); padding: 1rem 0; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; margin: 0 10px; color: rgba(255,255,255,0.7); text-decoration: none; border-radius: 12px; transition: all 0.3s; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: white; transform: translateX(5px); }
        .sidebar-nav .password-link { margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; padding-top: 12px; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); }
        .user-info { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 1rem; }
        .user-avatar { width: 40px; height: 40px; background: #059669; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .logout-btn { display: block; background: #EF4444; text-align: center; padding: 10px; border-radius: 12px; text-decoration: none; color: white; transition: all 0.3s; }
        .logout-btn:hover { background: #DC2626; transform: translateY(-2px); }
        .main-content { margin-left: 280px; flex: 1; padding: 1.5rem; }
        .top-bar { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1rem 1.5rem; border-radius: 20px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .top-bar h1 { color: #065F46; }
        #liveClock { font-family: monospace; background: rgba(5,150,105,0.1); padding: 0.5rem 1rem; border-radius: 40px; color: #059669; }
        
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; margin-bottom: 1.5rem; border: 1px solid rgba(255,255,255,0.3); transition: all 0.3s; }
        .card:hover { background: rgba(255,255,255,0.85); }
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; }
        select, input { padding: 12px; border: 1px solid rgba(5,150,105,0.2); border-radius: 12px; background: rgba(255,255,255,0.9); min-width: 150px; }
        .btn { background: linear-gradient(135deg, #059669, #10B981); color: white; border: none; padding: 12px 24px; border-radius: 40px; cursor: pointer; text-decoration: none; display: inline-block; font-weight: 600; transition: all 0.3s; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(5,150,105,0.3); }
        .btn-gray { background: #6B7280; }
        .btn-gray:hover { box-shadow: 0 4px 12px rgba(107,114,128,0.3); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 1rem; margin-bottom: 1.5rem; }
        .stat-card { background: rgba(255,255,255,0.5); padding: 1rem; border-radius: 16px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #059669; }
        .stat-label { color: #374151; font-weight: 500; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; overflow-x: auto; display: block; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.1); white-space: nowrap; }
        th { background: #059669; color: white; position: sticky; top: 0; }
        .status-pending { color: #F59E0B; font-weight: bold; }
        .status-confirmed { color: #059669; font-weight: bold; }
        .status-rejected { color: #EF4444; font-weight: bold; }
        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-pending { background: #FEF3C7; color: #92400E; }
        .badge-confirmed { background: #D1FAE5; color: #065F46; }
        .badge-rejected { background: #FEE2E2; color: #991B1B; }
        
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-nav a span:last-child { display: none; }
            .main-content { margin-left: 70px; }
            .stats-grid { grid-template-columns: repeat(2,1fr); }
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
            <div class="shape shape-4">📊</div>
        </div>
        <div class="particles"></div>
    </div>

    <div class="dashboard">
        <aside class="sidebar">
            <div class="sidebar-header"><div class="logo">🎓 Attend<span class="logo-highlight">Pro</span></div><div class="role-badge">ADMIN</div></div>
            <nav class="sidebar-nav">
                <a href="dashboard.php">📊 Dashboard</a>
                <a href="manage_lecturers.php">👨‍🏫 Lecturers</a>
                <a href="manage_students.php">👨‍🎓 Students</a>
                <a href="manage_classes.php">📚 Classes</a>
                <a href="all_reports.php" class="active">📈 Reports</a>
                <a href="../change_password.php" class="password-link">🔐 Change Password</a>
            </nav>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div><div><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br><small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small></div></div>
                <a href="../logout.php" class="logout-btn">🚪 Logout</a>
            </div>
        </aside>

        <main class="main-content">
            <div class="top-bar"><h1>📊 Attendance Reports</h1><div id="liveClock"></div></div>
            
            <!-- Filter Section -->
            <div class="card">
                <h3>🔍 Filter Reports</h3>
                <form method="GET" class="filters">
                    <select name="class_id">
                        <option value="">All Classes</option>
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($c['class_code']); ?> - <?php echo htmlspecialchars($c['class_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="lecturer_id">
                        <option value="">All Lecturers</option>
                        <?php foreach($lecturers as $l): ?>
                            <option value="<?php echo $l['id']; ?>" <?php echo $lecturerId == $l['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($l['name']); ?> (<?php echo $l['lecturer_id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" placeholder="Start Date">
                    <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" placeholder="End Date">
                    
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                    
                    <button type="submit" class="btn">🔍 Generate</button>
                    <a href="all_reports.php" class="btn btn-gray">Reset</a>
                    <?php if(count($records) > 0): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 1])); ?>" class="btn btn-gray">📥 Export CSV</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if(count($records) > 0): ?>
                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><div class="stat-label">Total Records</div></div>
                    <div class="stat-card"><div class="stat-number" style="color:#059669;"><?php echo $confirmed; ?></div><div class="stat-label">Confirmed</div></div>
                    <div class="stat-card"><div class="stat-number" style="color:#F59E0B;"><?php echo $pending; ?></div><div class="stat-label">Pending</div></div>
                    <div class="stat-card"><div class="stat-number" style="color:#EF4444;"><?php echo $rejected; ?></div><div class="stat-label">Rejected</div></div>
                </div>
                
                <!-- Records Table -->
                <div class="card">
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Class</th>
                                    <th>Lecturer</th>
                                    <th>Session Date</th>
                                    <th>Face</th>
                                    <th>Location</th>
                                    <th>Distance</th>
                                    <th>Status</th>
                                    <th>Method</th>
                                    <th>Submitted</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($records as $r): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($r['student_id']); ?></td>
                                    <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['class_code']); ?></td>
                                    <td><?php echo htmlspecialchars($r['lecturer_name']); ?></td>
                                    <td><?php echo date('M d, Y g:i A', strtotime($r['session_time'])); ?></td>
                                    <td><?php echo $r['face_verified'] ? '✅' : '❌'; ?></td>
                                    <td><?php echo $r['location_verified'] ? '✅' : '❌'; ?></td>
                                    <td><?php echo $r['distance_from_class'] ? $r['distance_from_class'] . 'm' : '-'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $r['status']; ?>">
                                            <?php echo ucfirst($r['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo str_replace('_', ' ', $r['method']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($r['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <p style="text-align: center; padding: 2rem;">📭 No attendance records found. Try adjusting your filters.</p>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        function updateClock() {
            const clock = document.getElementById('liveClock');
            if (clock) {
                const now = new Date();
                clock.innerHTML = now.toLocaleString('en-US', {
                    weekday: 'short', year: 'numeric', month: 'short', day: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit'
                });
            }
        }
        setInterval(updateClock, 1000);
        updateClock();
    </script>
</body>
</html>