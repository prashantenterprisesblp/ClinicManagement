<?php
require_once 'config/database.php';

$message = '';
$messageType = '';

// Handle form submission
if ($_POST) {
    $fullName = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $requestedDate = $_POST['requested_date'] ?? '';
    $requestedTime = $_POST['requested_time'] ?? '';
    $preferredDoctor = $_POST['preferred_doctor'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    
    // Validation
    $errors = [];
    if (empty($fullName)) $errors[] = 'Full name is required';
    if (empty($phone)) $errors[] = 'Phone number is required';
    if (empty($requestedDate)) $errors[] = 'Appointment date is required';
    if (empty($requestedTime)) $errors[] = 'Appointment time is required';
    
    // Validate date is not in the past
    if (!empty($requestedDate) && strtotime($requestedDate) < strtotime('today')) {
        $errors[] = 'Appointment date cannot be in the past';
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointment_requests 
                (full_name, phone, email, requested_date, requested_time, preferred_doctor_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $fullName, 
                $phone, 
                $email, 
                $requestedDate, 
                $requestedTime, 
                $preferredDoctor ?: null, 
                $notes
            ]);
            
            // Send notification email to clinic
            $clinicEmail = getSettingValue($pdo, 'clinic_email');
            if ($clinicEmail) {
                $subject = "New Appointment Request";
                $emailBody = "
                    New appointment request received:\n\n
                    Patient: $fullName\n
                    Phone: $phone\n
                    Email: $email\n
                    Requested Date: $requestedDate\n
                    Requested Time: $requestedTime\n
                    Preferred Doctor: " . ($preferredDoctor ? getDoctorName($pdo, $preferredDoctor) : 'Any available') . "\n
                    Notes: $notes\n\n
                    Please log in to the admin system to confirm this appointment.
                ";
                
                // Simple mail function (you may want to use PHPMailer for production)
                mail($clinicEmail, $subject, $emailBody);
            }
            
            $message = 'Your appointment request has been submitted successfully! We will contact you soon to confirm your appointment.';
            $messageType = 'success';
            
            // Clear form data
            $_POST = [];
            
        } catch (Exception $e) {
            $message = 'Sorry, there was an error submitting your request. Please try again.';
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
}

// Helper functions
function getSettingValue($pdo, $key) {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : null;
}

function getDoctorName($pdo, $doctorId) {
    $stmt = $pdo->prepare("SELECT full_name FROM doctors WHERE id = ?");
    $stmt->execute([$doctorId]);
    $result = $stmt->fetch();
    return $result ? $result['full_name'] : 'Unknown';
}

// Fetch doctors for dropdown
$stmt = $pdo->query("SELECT id, full_name FROM doctors WHERE is_active = 1 ORDER BY full_name");
$doctors = $stmt->fetchAll();

// Fetch clinic settings
$clinicName = getSettingValue($pdo, 'clinic_name') ?: 'Medical Clinic';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - <?php echo htmlspecialchars($clinicName); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .booking-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }

        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .form-container {
            padding: 2rem;
        }

        .message {
            padding: 1rem;
            border-radius: 5px;
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2c3e50;
        }

        .required::after {
            content: ' *';
            color: #e74c3c;
        }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="date"],
        input[type="time"],
        select,
        textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="tel"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .submit-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            margin-top: 1rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 1rem;
            display: inline-block;
            transition: color 0.3s;
        }

        .back-link:hover {
            color: #764ba2;
        }

        .back-link i {
            margin-right: 0.5rem;
        }

        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .time-slot {
            padding: 0.5rem;
            border: 2px solid #e9ecef;
            border-radius: 5px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
        }

        .time-slot:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }

        .time-slot.selected {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .form-note {
            font-size: 0.9rem;
            color: #666;
            margin-top: 0.5rem;
            font-style: italic;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .container {
                padding: 0 0.5rem;
            }

            .form-container {
                padding: 1.5rem;
            }

            .time-slots {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>

    <div class="container">
        <div class="booking-card">
            <div class="header">
                <h1><i class="fas fa-calendar-plus"></i> Book Appointment</h1>
                <p>Schedule your visit with our medical professionals</p>
            </div>
            
            <div class="form-container">
                <a href="index.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>

                <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <form method="POST" id="bookingForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="full_name" class="required">Full Name</label>
                            <input type="text" 
                                   id="full_name" 
                                   name="full_name" 
                                   value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone" class="required">Phone Number</label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                   required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        <div class="form-note">Optional - We'll send confirmation to this email if provided</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="requested_date" class="required">Preferred Date</label>
                            <input type="date" 
                                   id="requested_date" 
                                   name="requested_date" 
                                   value="<?php echo htmlspecialchars($_POST['requested_date'] ?? ''); ?>" 
                                   min="<?php echo date('Y-m-d'); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="requested_time" class="required">Preferred Time</label>
                            <select id="requested_time" name="requested_time" required>
                                <option value="">Select Time</option>
                                <?php
                                $times = [
                                    '09:00' => '9:00 AM',
                                    '09:30' => '9:30 AM',
                                    '10:00' => '10:00 AM',
                                    '10:30' => '10:30 AM',
                                    '11:00' => '11:00 AM',
                                    '11:30' => '11:30 AM',
                                    '14:00' => '2:00 PM',
                                    '14:30' => '2:30 PM',
                                    '15:00' => '3:00 PM',
                                    '15:30' => '3:30 PM',
                                    '16:00' => '4:00 PM',
                                    '16:30' => '4:30 PM',
                                    '17:00' => '5:00 PM'
                                ];
                                
                                foreach ($times as $value => $display) {
                                    $selected = (($_POST['requested_time'] ?? '') === $value) ? 'selected' : '';
                                    echo "<option value=\"$value\" $selected>$display</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="preferred_doctor">Preferred Doctor</label>
                        <select id="preferred_doctor" name="preferred_doctor">
                            <option value="">Any Available Doctor</option>
                            <?php foreach ($doctors as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                        <?php echo (($_POST['preferred_doctor'] ?? '') == $doctor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($doctor['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-note">Optional - Leave blank for any available doctor</div>
                    </div>

                    <div class="form-group">
                        <label for="notes">Additional Notes</label>
                        <textarea id="notes" 
                                  name="notes" 
                                  placeholder="Please describe your symptoms or reason for visit (optional)"><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        <div class="form-note">Optional - Help us prepare for your visit</div>
                    </div>

                    <button type="submit" class="submit-btn">
                        <i class="fas fa-paper-plane"></i> Submit Appointment Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Form validation and enhancement
        document.getElementById('bookingForm').addEventListener('submit', function(e) {
            const requiredFields = ['full_name', 'phone', 'requested_date', 'requested_time'];
            let hasErrors = false;

            requiredFields.forEach(field => {
                const input = document.getElementById(field);
                if (!input.value.trim()) {
                    input.style.borderColor = '#e74c3c';
                    hasErrors = true;
                } else {
                    input.style.borderColor = '#e9ecef';
                }
            });

            // Validate date is not in past
            const dateInput = document.getElementById('requested_date');
            const selectedDate = new Date(dateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);

            if (selectedDate < today) {
                dateInput.style.borderColor = '#e74c3c';
                alert('Please select a date that is today or in the future.');
                hasErrors = true;
            }

            // Validate phone number format
            const phoneInput = document.getElementById('phone');
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            if (phoneInput.value && !phoneRegex.test(phoneInput.value.replace(/[\s\-\(\)]/g, ''))) {
                phoneInput.style.borderColor = '#e74c3c';
                alert('Please enter a valid phone number.');
                hasErrors = true;
            }

            if (hasErrors) {
                e.preventDefault();
                return false;
            }

            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'block';
        });

        // Remove error styling on input
        document.querySelectorAll('input, select, textarea').forEach(input => {
            input.addEventListener('input', function() {
                this.style.borderColor = '#e9ecef';
            });
        });

        // Auto-format phone number
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{3})/, '($1) $2');
            }
            e.target.value = value;
        });

        // Hide loading on page load
        window.addEventListener('load', function() {
            document.getElementById('loadingOverlay').style.display = 'none';
        });

        // Prevent weekend selection for appointments
        document.getElementById('requested_date').addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const dayOfWeek = selectedDate.getDay();
            
            if (dayOfWeek === 0 || dayOfWeek === 6) { // Sunday or Saturday
                alert('Please select a weekday for your appointment. We are closed on weekends.');
                this.value = '';
            }
        });
    </script>
</body>
</html>
