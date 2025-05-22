<?php
require_once '../config/database.php';
require_role(['administrator', 'receptionist']);

$message = '';
$messageType = '';

// Handle actions
if ($_POST && isset($_POST['action'])) {
    $requestId = (int)$_POST['request_id'];
    $action = $_POST['action'];
    
    switch ($action) {
        case 'confirm':
            $doctorId = (int)$_POST['doctor_id'];
            $appointmentDate = $_POST['appointment_date'];
            $appointmentTime = $_POST['appointment_time'];
            
            try {
                $pdo->beginTransaction();
                
                // Get request details
                $stmt = $pdo->prepare("SELECT * FROM appointment_requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
                
                if ($request) {
                    // Create confirmed appointment
                    $stmt = $pdo->prepare("
                        INSERT INTO appointments 
                        (request_id, patient_name, patient_phone, patient_email, doctor_id, appointment_date, appointment_time, notes) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $requestId,
                        $request['full_name'],
                        $request['phone'],
                        $request['email'],
                        $doctorId,
                        $appointmentDate,
                        $appointmentTime,
                        $request['notes']
                    ]);
                    
                    // Update request status
                    $stmt = $pdo->prepare("UPDATE appointment_requests SET status = 'confirmed' WHERE id = ?");
                    $stmt->execute([$requestId]);
                    
                    // Send confirmation email
                    if ($request['email']) {
                        $doctorStmt = $pdo->prepare("SELECT full_name FROM doctors WHERE id = ?");
                        $doctorStmt->execute([$doctorId]);
                        $doctor = $doctorStmt->fetch();
                        
                        $subject = "Appointment Confirmed";
                        $emailBody = "
Dear {$request['full_name']},

Your appointment has been confirmed!

Details:
- Date: " . date('F j, Y', strtotime($appointmentDate)) . "
- Time: " . date('g:i A', strtotime($appointmentTime)) . "
- Doctor: " . ($doctor ? $doctor['full_name'] : 'TBD') . "

Please arrive 15 minutes before your scheduled time.

Thank you for choosing our clinic.

Best regards,
Clinic Team
                        ";
                        
                        send_notification_email($request['email'], $subject, $emailBody);
                    }
                    
                    $pdo->commit();
                    $message = 'Appointment confirmed successfully!';
                    $messageType = 'success';
                } else {
                    throw new Exception('Request not found');
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error confirming appointment: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
            
        case 'reject':
            $rejectionReason = $_POST['rejection_reason'] ?? '';
            
            try {
                // Get request details for email
                $stmt = $pdo->prepare("SELECT * FROM appointment_requests WHERE id = ?");
                $stmt->execute([$requestId]);
                $request = $stmt->fetch();
                
                // Update request status
                $stmt = $pdo->prepare("UPDATE appointment_requests SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$requestId]);
                
                // Send rejection email
                if ($request && $request['email']) {
                    $subject = "Appointment Request Update";
                    $emailBody = "
Dear {$request['full_name']},

We regret to inform you that we cannot accommodate your appointment request for " . date('F j, Y', strtotime($request['requested_date'])) . " at " . date('g:i A', strtotime($request['requested_time'])) . ".

" . ($rejectionReason ? "Reason: $rejectionReason" : "") . "

Please feel free to submit a new request with alternative dates, or contact us directly to discuss other available options.

Thank you for your understanding.

Best regards,
Clinic Team
                    ";
                    
                    send_notification_email($request['email'], $subject, $emailBody);
                }
                
                $message = 'Appointment request rejected.';
                $messageType = 'success';
                
            } catch (Exception $e) {
                $message = 'Error rejecting appointment: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
    }
}

// Get pending requests with pagination
$page = (int)($_GET['page'] ?? 1);
$per_page = 10;
$status_filter = $_GET['status'] ?? 'pending';

$where_clause = "WHERE 1=1";
$params = [];

if ($status_filter && $status_filter !== 'all') {
    $where_clause .= " AND status = ?";
    $params[] = $status_filter;
}

$query = "
    SELECT ar.*, d.full_name as preferred_doctor_name 
    FROM appointment_requests ar 
    LEFT JOIN doctors d ON ar.preferred_doctor_id = d.id 
    $where_clause 
    ORDER BY ar.created_at DESC
";

$result = paginate($pdo, $query, $params, $page, $per_page);
$requests = $result['data'];

// Get doctors for assignment
$doctorsStmt = $pdo->query("SELECT id, full_name FROM doctors WHERE is_active = 1 ORDER BY full_name");
$doctors = $doctorsStmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests - Clinic Management</title>
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

        .back-btn {
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

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .page-title {
            margin-bottom: 2rem;
        }

        .page-title h1 {
            font-size: 2rem;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .filter-row {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-group select {
            padding: 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        .requests-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .requests-header {
            background: #f8f9fa;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
            color: #2c3e50;
        }

        .request-item {
            padding: 1.5rem;
            border-bottom: 1px solid #f1f3f4;
            transition: background 0.3s;
        }

        .request-item:hover {
            background: #f8f9fb;
        }

        .request-item:last-child {
            border-bottom: none;
        }

        .request-header {
            display: flex;
            justify-content: between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .patient-info {
            flex: 1;
        }

        .patient-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.25rem;
        }

        .patient-contact {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }

        .request-status {
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #666;
            font-size: 0.9rem;
        }

        .detail-item i {
            color: #667eea;
            width: 16px;
        }

        .request-notes {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            font-style: italic;
            color: #666;
        }

        .request-actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-confirm {
            background: #28a745;
            color: white;
        }

        .btn-confirm:hover {
            background: #218838;
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
        }

        .btn-view {
            background: #17a2b8;
            color: white;
        }

        .btn-view:hover {
            background: #138496;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 2rem;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #dee2e6;
        }

        .modal-header h3 {
            color: #2c3e50;
            margin: 0;
        }

        .close {
            color: #aaa;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
        }

        .close:hover {
            color: #000;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            font-size: 1rem;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a,
        .pagination span {
            padding: 0.5rem 1rem;
            border: 1px solid #dee2e6;
            color: #667eea;
            text-decoration: none;
            border-radius: 5px;
            transition: all 0.3s;
        }

        .pagination a:hover {
            background: #667eea;
            color: white;
        }

        .pagination .current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .request-details {
                grid-template-columns: 1fr;
            }

            .request-actions {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1><i class="fas fa-clock"></i> Appointment Requests</h1>
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </header>

    <div class="container">
        <?php if ($message): ?>
        <div class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <div class="filters">
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label for="status">Status:</label>
                    <select name="status" id="status" onchange="this.form.submit()">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
            </form>
        </div>

        <div class="requests-container">
            <div class="requests-header">
                <i class="fas fa-list"></i> Appointment Requests (<?php echo $result['total']; ?> total)
            </div>

            <?php if (empty($requests)): ?>
                <div class="request-item" style="text-align: center; padding: 3rem; color: #666;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No appointment requests found.</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                <div class="request-item">
                    <div class="request-header">
                        <div class="patient-info">
                            <div class="patient-name"><?php echo htmlspecialchars($request['full_name']); ?></div>
                            <div class="patient-contact">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['phone']); ?>
                                <?php if ($request['email']): ?>
                                    | <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['email']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="request-status status-<?php echo $request['status']; ?>">
                            <?php echo ucfirst($request['status']); ?>
                        </div>
                    </div>

                    <div class="request-details">
                        <div class="detail-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('F j, Y', strtotime($request['requested_date'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($request['requested_time'])); ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-user-md"></i>
                            <span><?php echo $request['preferred_doctor_name'] ?: 'Any available'; ?></span>
                        </div>
                        <div class="detail-item">
                            <i class="fas fa-calendar-plus"></i>
                            <span>Requested: <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></span>
                        </div>
                    </div>

                    <?php if ($request['notes']): ?>
                    <div class="request-notes">
                        <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($request['notes'])); ?>
                    </div>
                    <?php endif; ?>

                    <?php if ($request['status'] === 'pending'): ?>
                    <div class="request-actions">
                        <button class="btn btn-confirm" onclick="openConfirmModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['full_name']); ?>', '<?php echo $request['requested_date']; ?>', '<?php echo $request['requested_time']; ?>', <?php echo $request['preferred_doctor_id'] ?: 'null'; ?>)">
                            <i class="fas fa-check"></i> Confirm
                        </button>
                        <button class="btn btn-reject" onclick="openRejectModal(<?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['full_name']); ?>')">
                            <i class="fas fa-times"></i> Reject
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($result['total_pages'] > 1): ?>
        <div class="pagination">
            <?php if ($result['page'] > 1): ?>
                <a href="?page=<?php echo $result['page'] - 1; ?>&status=<?php echo $status_filter; ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $result['total_pages']; $i++): ?>
                <?php if ($i == $result['page']): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php else: ?>
                    <a href="?page=<?php echo $i; ?>&status=<?php echo $status_filter; ?>"><?php echo $i; ?></a>
                <?php endif; ?>
            <?php endfor; ?>

            <?php if ($result['page'] < $result['total_pages']): ?>
                <a href="?page=<?php echo $result['page'] + 1; ?>&status=<?php echo $status_filter; ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Confirm Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirm Appointment</h3>
                <button class="close" onclick="closeModal('confirmModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="confirm">
                <input type="hidden" name="request_id" id="confirmRequestId">
                
                <div class="form-group">
                    <label>Patient:</label>
                    <input type="text" id="confirmPatientName" readonly>
                </div>

                <div class="form-group">
                    <label for="doctor_id">Assign Doctor:</label>
                    <select name="doctor_id" id="doctor_id" required>
                        <option value="">Select Doctor</option>
                        <?php foreach ($doctors as $doctor): ?>
                        <option value="<?php echo $doctor['id']; ?>"><?php echo htmlspecialchars($doctor['full_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="appointment_date">Appointment Date:</label>
                    <input type="date" name="appointment_date" id="appointment_date" required min="<?php echo date('Y-m-d'); ?>">
                </div>

                <div class="form-group">
                    <label for="appointment_time">Appointment Time:</label>
                    <select name="appointment_time" id="appointment_time" required>
                        <option value="">Select Time</option>
                        <option value="09:00">9:00 AM</option>
                        <option value="09:30">9:30 AM</option>
                        <option value="10:00">10:00 AM</option>
                        <option value="10:30">10:30 AM</option>
                        <option value="11:00">11:00 AM</option>
                        <option value="11:30">11:30 AM</option>
                        <option value="14:00">2:00 PM</option>
                        <option value="14:30">2:30 PM</option>
                        <option value="15:00">3:00 PM</option>
                        <option value="15:30">3:30 PM</option>
                        <option value="16:00">4:00 PM</option>
                        <option value="16:30">4:30 PM</option>
                        <option value="17:00">5:00 PM</option>
                    </select>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-confirm" style="flex: 1;">
                        <i class="fas fa-check"></i> Confirm Appointment
                    </button>
                    <button type="button" class="btn" onclick="closeModal('confirmModal')" style="background: #6c757d; color: white; flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Appointment</h3>
                <button class="close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="rejectRequestId">
                
                <div class="form-group">
                    <label>Patient:</label>
                    <input type="text" id="rejectPatientName" readonly>
                </div>

                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection (Optional):</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="3" placeholder="Explain why the appointment cannot be accommodated..."></textarea>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-reject" style="flex: 1;">
                        <i class="fas fa-times"></i> Reject Request
                    </button>
                    <button type="button" class="btn" onclick="closeModal('rejectModal')" style="background: #6c757d; color: white; flex: 1;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openConfirmModal(requestId, patientName, requestedDate, requestedTime, preferredDoctorId) {
            document.getElementById('confirmRequestId').value = requestId;
            document.getElementById('confirmPatientName').value = patientName;
            document.getElementById('appointment_date').value = requestedDate;
            document.getElementById('appointment_time').value = requestedTime;
            
            if (preferredDoctorId) {
                document.getElementById('doctor_id').value = preferredDoctorId;
            }
            
            document.getElementById('confirmModal').style.display = 'block';
        }

        function openRejectModal(requestId, patientName) {
            document.getElementById('rejectRequestId').value = requestId;
            document.getElementById('rejectPatientName').value = patientName;
            document.getElementById('rejectModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['confirmModal', 'rejectModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }

        // Prevent weekend selection for appointments
        document.getElementById('appointment_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const dayOfWeek = selectedDate.getDay();
            
            if (dayOfWeek === 0 || dayOfWeek === 6) { // Sunday or Saturday
                alert('Please select a weekday for the appointment. The clinic is closed on weekends.');
                this.value = '';
            }
        });

        // Auto-refresh every 2 minutes for pending requests
        <?php if ($status_filter === 'pending'): ?>
        setTimeout(function() {
            if (document.querySelectorAll('.status-pending').length > 0) {
                location.reload();
            }
        }, 2 * 60 * 1000);
        <?php endif; ?>

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // ESC to close modals
            if (e.keyCode === 27) {
                closeModal('confirmModal');
                closeModal('rejectModal');
            }
        });

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('input[name="action"]').value;
                
                if (action === 'confirm') {
                    const doctorId = this.querySelector('select[name="doctor_id"]').value;
                    const appointmentDate = this.querySelector('input[name="appointment_date"]').value;
                    const appointmentTime = this.querySelector('select[name="appointment_time"]').value;
                    
                    if (!doctorId || !appointmentDate || !appointmentTime) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                        return false;
                    }
                    
                    // Confirm action
                    if (!confirm('Are you sure you want to confirm this appointment?')) {
                        e.preventDefault();
                        return false;
                    }
                } else if (action === 'reject') {
                    if (!confirm('Are you sure you want to reject this appointment request?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });

        // Add loading state to buttons
        document.querySelectorAll('button[type="submit"]').forEach(button => {
            button.addEventListener('click', function() {
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                setTimeout(() => {
                    this.disabled = false;
                }, 3000);
            });
        });

        // Highlight urgent requests (requested for today or tomorrow)
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const tomorrow = new Date(today);
            tomorrow.setDate(tomorrow.getDate() + 1);
            
            document.querySelectorAll('.request-item').forEach(item => {
                const dateElements = item.querySelectorAll('.detail-item');
                dateElements.forEach(element => {
                    if (element.innerHTML.includes('fas fa-calendar')) {
                        const dateText = element.textContent.trim();
                        const requestDate = new Date(dateText);
                        
                        if (requestDate.toDateString() === today.toDateString() || 
                            requestDate.toDateString() === tomorrow.toDateString()) {
                            item.style.borderLeft = '4px solid #ffc107';
                            item.style.backgroundColor = '#fffbf0';
                        }
                    }
                });
            });
        });

        // Print functionality
        function printRequests() {
            window.print();
        }

        // Export functionality (basic CSV)
        function exportRequests() {
            const requests = <?php echo json_encode($requests); ?>;
            let csv = 'Patient Name,Phone,Email,Requested Date,Requested Time,Preferred Doctor,Status,Notes,Created At\n';
            
            requests.forEach(request => {
                csv += `"${request.full_name}","${request.phone}","${request.email || ''}","${request.requested_date}","${request.requested_time}","${request.preferred_doctor_name || 'Any'}","${request.status}","${(request.notes || '').replace(/"/g, '""')}","${request.created_at}"\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'appointment_requests_' + new Date().toISOString().split('T')[0] + '.csv';
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // Add export button to page
        document.addEventListener('DOMContentLoaded', function() {
            const header = document.querySelector('.requests-header');
            if (header && <?php echo count($requests); ?> > 0) {
                const exportBtn = document.createElement('button');
                exportBtn.innerHTML = '<i class="fas fa-download"></i> Export CSV';
                exportBtn.className = 'btn';
                exportBtn.style.cssText = 'background: #17a2b8; color: white; float: right; margin-top: -5px;';
                exportBtn.onclick = exportRequests;
                header.appendChild(exportBtn);
            }
        });
    </script>

    <style media="print">
        .header, .filters, .request-actions, .pagination, .modal {
            display: none !important;
        }
        
        .container {
            max-width: none;
            margin: 0;
            padding: 1rem;
        }
        
        .request-item {
            page-break-inside: avoid;
            border: 1px solid #ddd;
            margin-bottom: 1rem;
        }
        
        .requests-container {
            box-shadow: none;
        }
    </style>
</body>
</html>
