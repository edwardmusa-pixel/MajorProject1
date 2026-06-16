<?php
$current_page = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['user']['role'] ?? 'admin';
?>

<div class="sidebar-header">
    <div class="logo-animated">
        <div class="logo-icon">🎓</div>
        <div class="logo-text">Attend<span class="logo-highlight">Pro</span></div>
    </div>
    <div class="role-badge <?php echo $role; ?>"><?php echo strtoupper($role); ?></div>
</div>

<nav class="sidebar-nav">
    <?php if($role == 'admin'): ?>
        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span>Dashboard</span>
        </a>
        <a href="manage_lecturers.php" class="nav-link <?php echo $current_page == 'manage_lecturers.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👨‍🏫</span>
            <span>Lecturers</span>
        </a>
        <a href="manage_students.php" class="nav-link <?php echo $current_page == 'manage_students.php' ? 'active' : ''; ?>">
            <span class="nav-icon">👨‍🎓</span>
            <span>Students</span>
        </a>
        <a href="manage_classes.php" class="nav-link <?php echo $current_page == 'manage_classes.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📚</span>
            <span>Classes</span>
        </a>
        <a href="all_reports.php" class="nav-link <?php echo $current_page == 'all_reports.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📈</span>
            <span>Reports</span>
        </a>
    <?php elseif($role == 'lecturer'): ?>
        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📊</span>
            <span>Dashboard</span>
        </a>
        <a href="generate_qr.php" class="nav-link <?php echo $current_page == 'generate_qr.php' ? 'active' : ''; ?>">
            <span class="nav-icon">🎫</span>
            <span>Generate QR</span>
        </a>
        <a href="verify_pending.php" class="nav-link <?php echo $current_page == 'verify_pending.php' ? 'active' : ''; ?>">
            <span class="nav-icon">✅</span>
            <span>Verify Pending</span>
        </a>
        <a href="reports.php" class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📈</span>
            <span>Reports</span>
        </a>
    <?php elseif($role == 'student'): ?>
        <a href="dashboard.php" class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📱</span>
            <span>Self Check-in</span>
        </a>
        <a href="history.php" class="nav-link <?php echo $current_page == 'history.php' ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span>
            <span>My History</span>
        </a>
    <?php endif; ?>
</nav>

<div class="sidebar-footer">
    <div class="user-info">
        <div class="user-avatar"><?php echo strtoupper(substr($_SESSION['user']['name'], 0, 2)); ?></div>
        <div class="user-details">
            <strong><?php echo htmlspecialchars($_SESSION['user']['name']); ?></strong>
            <small><?php echo htmlspecialchars($_SESSION['user']['email']); ?></small>
        </div>
    </div>
    <a href="../logout.php" class="logout-btn">
        <span>🚪</span>
        <span>Logout</span>
    </a>
</div>