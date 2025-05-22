<?php
require_once '../config/database.php';
require_login();

// Get dashboard statistics
function getDashboardStats($pdo) {
    $stats = [];
    
    // Pending appointment requests
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointment_requests WHERE status = 'pending'");
    $stats['pending_requests'] = $stmt->fetch()['count'];
    
    // Today's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = ? AND status = 'scheduled'");
    $stmt->execute([date('Y-m-d')]);
    $stats['today_appointments'] = $stmt->fetch()['count'];
    
    // Total doctors
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctors WHERE is_active = 1");
    $stats['total_doctors'] = $stmt->fetch()['count'];
    
    // This month's completed encounters
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM encounters WHERE status = 'closed' AND MONTH(encounter_date) = ? AND YEAR(encounter_date) = ?");
    $stmt->execute([date('n'), date('Y')]);
    $stats['month_encounters'] = $stmt->fetch()['count'];
    
    return $stats;
}

// Get recent activities
function getRecentActivities($pdo, $limit = 10) {
    $activities = [];
    
    // Recent appointment requests
    $stmt = $pdo->prepare("
        SELECT 'appointment_request' as type, full_name as name, created_at, status 
        FROM appointment_requests 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Recent appointments
    $stmt = $pdo->prepare("
        SELECT 'appointment' as type, patient_name as name, created_at, status 
        FROM appointments 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $activities = array_merge($activities, $stmt->fetchAll());
    
    // Sort by created_at
    usort($activities, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return array_slice($activities, 0, $limit);
}

$stats = getDashboardStats($pdo);
$recent_activities = getRecentActivities($pdo);
$user_role = get_user_role();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Clinic Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 300;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
        }

        .user-role {
            font-size: 0.85rem;
            opacity: 0.8;
            text-transform: capitalize;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .nav-menu {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            padding: 1rem;
        }

        .nav-items {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .nav-item {
            background: #f8f9fa;
            color: #333;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .nav-item:hover, .nav-item.active {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.pending { background: #ffc107; }
        .stat-icon.appointments { background: #28a745; }
        .stat-icon.doctors { background: #17a2b8; }
        .stat-icon.encounters { background: #6f42c1; }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
        }

        .stat-label {
            color: #666;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }

        .dashboard-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #2c3e50;
        }

        .card-content {
            padding: 1.5rem;
        }

        .activity-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f3f4;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-icon {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            color: white;
        }

        .activity-icon.request { background: #ffc107; }
        .activity-icon.appointment { background: #28a745; }

        .activity-details {
            flex: 1;
        }

        .activity-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .activity-meta {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            text-align: center;
            text-decoration: none;
            color: #333;
            transition: all 0.3s;
        }

        .quick-action:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
            color: #667eea;
        }

        .quick-action i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .nav-items {
                justify-content: center;
            }
        }

        .welcome-message {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }

        .welcome-message h2 {
            font-size: 1.8rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1 class="header-title">
                <i class="fas fa-clinic-medical"></i> Clinic Management
            </h1>
            <div class="header-user">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($_SESSION['full_name']); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
                </div>
                <a href="logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="welcome-message">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
            <p>Here's what's happening in your clinic today</p>
        </div>

        <nav class="nav-menu">
            <div class="nav-items">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                
                <?php if (in_array($user_role, ['administrator', 'receptionist'])): ?>
                <a href="appointment-requests.php" class="nav-item">
                    <i class="fas fa-clock"></i> Pending Requests
                    <?php if ($stats['pending_requests'] > 0): ?>
                        <span style="background: #dc3545; color: white; border-radius: 50%; padding: 0.2rem 0.5rem; font-size: 0.7rem; margin-left: 0.5rem;">
                            <?php echo $stats['pending_requests']; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                
                <?php if ($user_role === 'administrator'): ?>
                <a href="doctors.php" class="nav-item">
                    <i class="fas fa-user-md"></i> Doctors
                </a>
                <a href="settings.php" class="nav-item">
                    <i class="fas fa-cog"></i> Settings
                </a>
                <?php endif; ?>
                
                <a href="appointments.php" class="nav-item">
                    <i class="fas fa-calendar"></i> Appointments
                </a>
                
                <?php if ($user_role === 'doctor'): ?>
                <a href="my-requests.php" class="nav-item">
                    <i class="fas fa-inbox"></i> My Requests
                </a>
                <a href="encounters.php" class="nav-item">
                    <i class="fas fa-notes-medical"></i> Encounters
                </a>
                <?php endif; ?>
                
                <?php if (in_array($user_role, ['administrator', 'receptionist'])): ?>
                <a href="invoices.php" class="nav-item">
                    <i class="fas fa-file-invoice"></i> Invoices
                </a>
                <?php endif; ?>
            </div>
        </nav>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['pending_requests']; ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-icon pending">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['today_appointments']; ?></div>
                        <div class="stat-label">Today's Appointments</div>
                    </div>
                    <div class="stat-icon appointments">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['total_doctors']; ?></div>
                        <div class="stat-label">Active Doctors</div>
                    </div>
                    <div class="stat-icon doctors">
                        <i class="fas fa-user-md"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-number"><?php echo $stats['month_encounters']; ?></div>
                        <div class="stat-label">This Month's Encounters</div>
                    </div>
                    <div class="stat-icon encounters">
                        <i class="fas fa-notes-medical"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-history"></i> Recent Activity
                </div>
                <div class="card-content">
                    <?php if (empty($recent_activities)): ?>
                        <p style="text-align: center; color: #666; padding: 2rem;">No recent activity</p>
                    <?php else: ?>
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon <?php echo $activity['type'] === 'appointment_request' ? 'request' : 'appointment'; ?>">
                                <i class="fas fa-<?php echo $activity['type'] === 'appointment_request' ? 'clock' : 'calendar'; ?>"></i>
                            </div>
                            <div class="activity-details">
                                <div class="activity-name"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="activity-meta">
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['type'])); ?> - 
                                    <?php echo ucfirst($activity['status']); ?> - 
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <i class="fas fa-bolt"></i> Quick Actions
                </div>
                <div class="card-content">
                    <div class="quick-actions">
                        <?php if (in_array($user_role, ['administrator', 'receptionist'])): ?>
                        <a href="appointment-requests.php" class="quick-action">
                            <i class="fas fa-clock"></i>
                            <div>View Pending Requests</div>
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($user_role === 'administrator'): ?>
                        <a href="doctors.php?action=add" class="quick-action">
                            <i class="fas fa-user-plus"></i>
                            <div>Add Doctor</div>
                        </a>
                        <?php endif; ?>
                        
                        <a href="appointments.php" class="quick-action">
                            <i class="fas fa-calendar-plus"></i>
                            <div>Schedule Appointment</div>
                        </a>
                        
                        <?php if ($user_role === 'doctor'): ?>
                        <a href="encounters.php?action=new" class="quick-action">
                            <i class="fas fa-notes-medical"></i>
                            <div>New Encounter</div>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh dashboard every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 5 * 60 * 1000);

        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .dashboard-card, .quick-action');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
