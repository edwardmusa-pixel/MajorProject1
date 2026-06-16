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

// Get lecturer's classes for filter
$classes = $pdo->prepare("SELECT * FROM classes WHERE lecturer_id = ?");
$classes->execute([$lecturerId]);
$myClasses = $classes->fetchAll();

// Handle filters
$classId = $_GET['class_id'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';

// Build query targeting attendance_records
$sql = "SELECT a.*, s.student_id, u.name as student_name, c.class_code, c.class_name 
        FROM attendance_records a 
        JOIN students s ON a.student_id = s.id 
        JOIN users u ON s.user_id = u.id 
        JOIN classes c ON a.class_id = c.id 
        WHERE c.lecturer_id = ?";
$params = [$lecturerId];

if($classId) {
    $sql .= " AND a.class_id = ?";
    $params[] = $classId;
}
if($startDate) {
    $sql .= " AND DATE(a.status_time) >= ?";
    $params[] = $startDate;
}
if($endDate) {
    $sql .= " AND DATE(a.status_time) <= ?";
    $params[] = $endDate;
}
if($status) {
    $sql .= " AND LOWER(a.status) = LOWER(?)";
    $params[] = $status;
}

$sql .= " ORDER BY a.status_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$records = $stmt->fetchAll();

// Calculate summary metrics
$total = count($records);
$confirmed = 0;
$pending = 0;
$rejected = 0;
foreach($records as $r) {
    $currentStatus = strtolower($r['status']);
    if($currentStatus == 'confirmed' || $currentStatus == 'present') $confirmed++;
    elseif($currentStatus == 'pending') $pending++;
    elseif($currentStatus == 'rejected' || $currentStatus == 'absent') $rejected++;
}

// Get student count per class for performance summaries
$classStats = [];
foreach($myClasses as $class) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM student_enrollments WHERE class_id = ?");
    $stmt->execute([$class['id']]);
    $enrolled = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_records WHERE class_id = ? AND LOWER(status) = 'confirmed'");
    $stmt->execute([$class['id']]);
    $attended = $stmt->fetchColumn();
    
    $classStats[$class['id']] = [
        'name' => $class['class_name'],
        'code' => $class['class_code'],
        'enrolled' => $enrolled,
        'attended' => $attended,
        'rate' => $enrolled > 0 ? round(($attended / $enrolled) * 100) : 0
    ];
}

// Handle CSV Export
if(isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="lecturer_attendance_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Student ID', 'Student Name', 'Class', 'Session Date', 'Status', 'Face Verified', 'Location Verified', 'Distance (m)', 'Method', 'Submitted At']);
    foreach($records as $r) {
        fputcsv($output, [
            $r['student_id'],
            $r['student_name'],
            $r['class_code'] . ' - ' . $r['class_name'],
            $r['status_time'],
            $r['status'],
            isset($r['face_verified']) && $r['face_verified'] ? 'Yes' : 'No',
            isset($r['location_verified']) && $r['location_verified'] ? 'Yes' : 'No',
            $r['distance_from_class'] ?? '-',
            $r['method'] ?? 'QR Scan',
            $r['status_time']
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
    <title>My Reports | AttendPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 50%, #A7F3D0 100%); min-height: 100vh; }
        
        .dashboard { display: flex; }
        .sidebar { width: 280px; background: rgba(31, 41, 55, 0.9); backdrop-filter: blur(12px); color: white; position: fixed; height: 100vh; }
        .sidebar-header { padding: 1.5rem; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .logo { font-size: 1.5rem; font-weight: bold; }
        .logo-highlight { color: #10B981; }
        .role-badge { background: #3B82F6; display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; margin-top: 8px; }
        .sidebar-nav a { display: flex; align-items: center; gap: 12px; padding: 12px 20px; color: rgba(255,255,255,0.7); text-decoration: none; }
        .sidebar-nav a:hover, .sidebar-nav a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-footer { padding: 1rem; border-top: 1px solid rgba(255,255,255,0.1); position: absolute; bottom: 0; width: 100%; }
        .user-info { display: flex; align-items: center; gap: 10px; padding: 10px; background: rgba(255,255,255,0.1); border-radius: 12px; margin-bottom: 1rem; }
        .user-avatar { width: 40px; height: 40px; background: #3B82F6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold;}
        .logout-btn { display: block; background: #EF4444; text-align: center; padding: 10px; border-radius: 12px; text-decoration: none; color: white; }
        
        .main-content { margin-left: 280px; flex: 1; padding: 1.5rem; }
        .top-bar { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1rem 1.5rem; border-radius: 20px; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; }
        .card { background: rgba(255,255,255,0.7); backdrop-filter: blur(10px); padding: 1.5rem; border-radius: 24px; margin-bottom: 1.5rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); }
        
        .filters { display: flex; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem; align-items: center; }
        select, input { padding: 12px; border: 1px solid rgba(5,150,105,0.2); border-radius: 12px; background: rgba(255,255,255,0.9); outline: none; }
        
        .btn { background: linear-gradient(135deg, #3B82F6, #2563EB); color: white; border: none; padding: 12px 24px; border-radius: 40px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 0.9rem; font-weight: 500; text-align: center; }
        .btn:hover { opacity: 0.95; transform: translateY(-0.5px); }
        .btn-gray { background: #6B7280; }
        .btn-success { background: linear-gradient(135deg, #10B981, #059669); }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3,1fr); gap: 1rem; margin-bottom: 0.5rem; }
        .stat-card { background: rgba(255,255,255,0.5); padding: 1rem; border-radius: 16px; text-align: center; }
        .stat-number { font-size: 2rem; font-weight: bold; color: #3B82F6; }
        
        .class-stats { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px,1fr)); gap: 1rem; margin-top: 1rem; }
        .class-stat-card { background: rgba(255,255,255,0.5); padding: 1rem; border-radius: 16px; text-align: center; border: 1px solid rgba(255,255,255,0.5); }
        .class-rate { font-size: 1.5rem; font-weight: bold; color: #059669; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 14px 12px; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.06); }
        th { background: #3B82F6; color: white; font-weight: 600; }
        tr:hover { background: rgba(255,255,255,0.4); }
        
        .badge { display: inline-block; padding: 6px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; text-transform: capitalize; }
        .badge-pending { background: #FEF3C7; color: #92400E; }
        .badge-confirmed, .badge-present { background: #D1FAE5; color: #065F46; }
        .badge-rejected, .badge-absent { background: #FEE2E2; color: #991B1B; }
        
        .chart-container { max-width: 450px; margin: 0 auto; padding: 10px 0; }
        
        /* Dedicated Attendance List Segment Block */
        .generated-report-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #E5E7EB; padding-bottom: 12px; margin-bottom: 15px; }
        .meta-tag { background: rgba(59, 130, 246, 0.1); color: #1E40AF; padding: 4px 12px; border-radius: 8px; font-size: 0.85rem; font-weight: 600; }

        @media (max-width: 768px) { 
            .sidebar { width: 70px; } 
            .main-content { margin-left: 70px; } 
            .sidebar-nav a span:last-child { display: none; } 
        }

        /* Printable Sheet Custom Layout rules overrides handles window tracking views cleanly */
        @media print {
            body * { visibility: hidden; }
            #printableAttendanceList, #printableAttendanceList * { visibility: visible; }
            #printableAttendanceList { position: absolute; left: 0; top: 0; width: 100%; background: white; color: black; padding: 20px; }
            .print-btn-hide { display: none !important; }
            th { background: #1F2937 !important; color: white !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="dashboard">
    <aside class="sidebar">
        <div class="sidebar-header"><div class="logo">🎓 Attend<span class="logo-highlight">Pro</span></div><div class="role-badge">LECTURER</div></div>
        <nav class="sidebar-nav">
            <a href="dashboard.php">📊 Dashboard</a>
            <a href="generate_qr.php">🎫 Generate QR</a>
            <a href="verify_pending.php">✅ Verify Pending</a>
            <a href="reports.php" class="active">📈 Reports</a>
        </nav>
        <div class="sidebar-footer">
            <div class="user-info"><div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div><div><strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong><br><small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small></div></div>
            <a href="../logout.php" class="logout-btn">🚪 Logout</a>
        </div>
    </aside>
    <main class="main-content">
        <div class="top-bar"><h1>📊 My Class Reports</h1><div id="clock"></div></div>
        
        <div class="card">
            <h3>📈 Class Attendance Performance</h3>
            <div class="chart-container">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
        
        <div class="card">
            <h3>📋 Class Statistics</h3>
            <div class="class-stats">
                <?php foreach($classStats as $stats): ?>
                <div class="class-stat-card">
                    <strong><?php echo htmlspecialchars($stats['code']); ?></strong><br>
                    <?php echo htmlspecialchars($stats['name']); ?><br>
                    <span class="class-rate"><?php echo $stats['rate']; ?>%</span><br>
                    <small><?php echo $stats['attended']; ?>/<?php echo $stats['enrolled']; ?> students attended</small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="card">
            <h3>🔍 Filter & Generate Attendance Sheets</h3>
            <form method="GET" class="filters">
                <select name="class_id" required>
                    <option value="">Select Target Class...</option>
                    <?php foreach($myClasses as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['class_code'] . ' - ' . $c['class_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" placeholder="Date">
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                <select name="status">
                    <option value="">All Verification Statuses</option>
                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo $status == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="rejected" <?php echo $status == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                </select>
                <button type="submit" class="btn">📋 Generate Roster</button>
                <a href="reports.php" class="btn btn-gray">Reset</a>
                <?php if(count($records) > 0): ?>
                    <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 1])); ?>" class="btn btn-gray">📥 Export CSV</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if(!empty($classId)): ?>
        <div class="card" id="printableAttendanceList">
            <div class="generated-report-header">
                <div>
                    <h2 style="color: #1F2937; font-size: 1.35rem; font-weight:700;">📋 Generated Attendance Sheet</h2>
                    <p style="font-size:0.85rem; color:#4B5563; margin-top:4px;">
                        Class: <strong><?php echo !empty($records) ? htmlspecialchars($records[0]['class_code'] . ' - ' . $records[0]['class_name']) : 'Selected Class'; ?></strong>
                    </p>
                </div>
                <div style="text-align: right; display:flex; gap:10px; align-items:center;">
                    <?php if(!empty($startDate)): ?>
                        <span class="meta-tag">📅 Date: <?php echo htmlspecialchars($startDate); ?></span>
                    <?php else: ?>
                        <span class="meta-tag">📅 Range: All Records</span>
                    <?php endif; ?>
                    <button onclick="window.print();" class="btn btn-success print-btn-hide" style="padding: 8px 16px; font-size:0.85rem;">🖨️ Print Sheet</button>
                </div>
            </div>

            <div style="overflow-x: auto; width: 100%;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 15%;">Student ID</th>
                            <th style="width: 35%;">Student Name</th>
                            <th style="width: 25%;">Logged Timestamp</th>
                            <th style="width: 15%;">Method</th>
                            <th style="width: 10%;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($records) > 0): ?>
                            <?php foreach($records as $r): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($r['student_id']); ?></strong></td>
                                <td><?php echo htmlspecialchars($r['student_name']); ?></td>
                                <td><?php echo date('M d, Y g:i A', strtotime($r['status_time'])); ?></td>
                                <td><?php echo htmlspecialchars($r['method'] ?? 'QR Scan'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo strtolower($r['status']); ?>">
                                        <?php echo htmlspecialchars($r['status']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem; color: #4B5563;">📭 No data records match this date parameter.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if(count($records) > 0 && empty($classId)): ?>
        <div class="card">
            <h3>📊 Global System Summaries</h3>
            <div class="stats-grid" style="margin-top:12px;">
                <div class="stat-card"><div class="stat-number"><?php echo $total; ?></div><div>Total Records</div></div>
                <div class="stat-card"><div class="stat-number" style="color:#059669;"><?php echo $confirmed; ?></div><div>Confirmed</div></div>
                <div class="stat-card"><div class="stat-number" style="color:#F59E0B;"><?php echo $pending; ?></div><div>Pending</div></div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>
<script>
    function updateClock() { document.getElementById('clock').innerHTML = new Date().toLocaleString(); }
    setInterval(updateClock, 1000); updateClock();
    
    // Chart for class performance
    const classLabels = <?php echo json_encode(array_column($classStats, 'code')); ?>;
    const classRates = <?php echo json_encode(array_column($classStats, 'rate')); ?>;
    
    new Chart(document.getElementById('attendanceChart'), {
        type: 'bar',
        data: {
            labels: classLabels,
            datasets: [{
                label: 'Attendance Rate (%)',
                data: classRates,
                backgroundColor: '#3B82F6',
                borderRadius: 8,
                borderColor: '#2563EB',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    title: { display: true, text: 'Percentage (%)' }
                }
            },
            plugins: {
                legend: { position: 'top' },
                tooltip: { callbacks: { label: function(context) { return context.raw + '%'; } } }
            }
        }
    });
</script>
</body>
</html>