<?php
require_once 'config/database.php';

// Fetch clinic settings
function getClinicSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

// Fetch active doctors
function getDoctors($pdo) {
    $stmt = $pdo->query("
        SELECT d.id, d.full_name, d.profile_description, d.photo_path, s.name as specialty_name
        FROM doctors d 
        LEFT JOIN specialties s ON d.specialty_id = s.id 
        WHERE d.is_active = 1
        ORDER BY d.full_name
    ");
    return $stmt->fetchAll();
}

$settings = getClinicSettings($pdo);
$doctors = getDoctors($pdo);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['clinic_name'] ?? 'Medical Clinic'); ?></title>
    <meta name="description" content="Professional medical services with experienced doctors. Book your appointment online.">
    <meta name="keywords" content="medical clinic, doctors, healthcare, appointment booking">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            text-align: center;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .header h1 {
            font-size: 3rem;
            margin-bottom: 0.5rem;
            font-weight: 300;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .nav {
            background: rgba(255, 255, 255, 0.1);
            padding: 1rem 0;
            margin-top: 2rem;
        }

        .nav a {
            color: white;
            text-decoration: none;
            margin: 0 1rem;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            transition: background 0.3s;
        }

        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .section {
            padding: 4rem 0;
        }

        .section:nth-child(even) {
            background: #f8f9fa;
        }

        .section-title {
            text-align: center;
            font-size: 2.5rem;
            margin-bottom: 3rem;
            color: #2c3e50;
        }

        .doctors-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .doctor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .doctor-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .doctor-photo {
            width: 100%;
            height: 200px;
            background: linear-gradient(45deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4rem;
        }

        .doctor-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .doctor-info {
            padding: 1.5rem;
        }

        .doctor-name {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .doctor-specialty {
            color: #667eea;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .doctor-description {
            color: #666;
            line-height: 1.6;
        }

        .cta-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 4rem 0;
        }

        .cta-button {
            display: inline-block;
            background: white;
            color: #667eea;
            padding: 1rem 2rem;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 1rem;
            transition: all 0.3s;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .contact-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .contact-item {
            text-align: center;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .contact-item i {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 1rem;
        }

        .contact-item h3 {
            margin-bottom: 0.5rem;
            color: #2c3e50;
        }

        .footer {
            background: #2c3e50;
            color: white;
            text-align: center;
            padding: 2rem 0;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .doctors-grid {
                grid-template-columns: 1fr;
            }

            .nav a {
                display: block;
                margin: 0.5rem 0;
            }
        }

        .loading {
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
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
    </div>

    <header class="header">
        <div class="container">
            <h1><?php echo htmlspecialchars($settings['clinic_name'] ?? 'Medical Clinic'); ?></h1>
            <p>Professional Healthcare Services</p>
            <nav class="nav">
                <a href="#doctors">Our Doctors</a>
                <a href="#contact">Contact Us</a>
                <a href="booking.php" class="cta-button">Book Appointment</a>
            </nav>
        </div>
    </header>

    <section id="doctors" class="section">
        <div class="container">
            <h2 class="section-title">Our Medical Team</h2>
            <div class="doctors-grid">
                <?php foreach ($doctors as $doctor): ?>
                <div class="doctor-card">
                    <div class="doctor-photo">
                        <?php if ($doctor['photo_path'] && file_exists($doctor['photo_path'])): ?>
                            <img src="<?php echo htmlspecialchars($doctor['photo_path']); ?>" alt="<?php echo htmlspecialchars($doctor['full_name']); ?>">
                        <?php else: ?>
                            <i class="fas fa-user-md"></i>
                        <?php endif; ?>
                    </div>
                    <div class="doctor-info">
                        <h3 class="doctor-name"><?php echo htmlspecialchars($doctor['full_name']); ?></h3>
                        <p class="doctor-specialty"><?php echo htmlspecialchars($doctor['specialty_name'] ?? 'General Medicine'); ?></p>
                        <p class="doctor-description"><?php echo htmlspecialchars($doctor['profile_description'] ?? 'Experienced medical professional dedicated to providing quality healthcare.'); ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container">
            <h2>Ready to Schedule Your Appointment?</h2>
            <p>Book online now for a convenient and hassle-free experience</p>
            <a href="booking.php" class="cta-button">
                <i class="fas fa-calendar-plus"></i> Book Now
            </a>
        </div>
    </section>

    <section id="contact" class="section">
        <div class="container">
            <h2 class="section-title">Contact Information</h2>
            <div class="contact-info">
                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <h3>Address</h3>
                    <p><?php echo nl2br(htmlspecialchars($settings['clinic_address'] ?? 'Address not set')); ?></p>
                </div>
                <div class="contact-item">
                    <i class="fas fa-phone"></i>
                    <h3>Phone</h3>
                    <p><?php echo htmlspecialchars($settings['clinic_phone'] ?? 'Phone not set'); ?></p>
                </div>
                <?php if (!empty($settings['clinic_email'])): ?>
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <h3>Email</h3>
                    <p><?php echo htmlspecialchars($settings['clinic_email']); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['clinic_name'] ?? 'Medical Clinic'); ?>. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Loading animation for booking button
        document.querySelectorAll('a[href="booking.php"]').forEach(link => {
            link.addEventListener('click', function(e) {
                document.getElementById('loading').style.display = 'block';
            });
        });

        // Hide loading on page load
        window.addEventListener('load', function() {
            document.getElementById('loading').style.display = 'none';
        });
    </script>
</body>
</html>
