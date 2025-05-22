-- Clinic Management System Database Schema

CREATE DATABASE clinic_management;
USE clinic_management;

-- Users table for authentication
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('administrator', 'receptionist', 'doctor') NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Login activity tracking
CREATE TABLE login_activity (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Medical specialties
CREATE TABLE specialties (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Doctors table
CREATE TABLE doctors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    full_name VARCHAR(100) NOT NULL,
    specialty_id INT,
    profile_description TEXT,
    photo_path VARCHAR(255),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (specialty_id) REFERENCES specialties(id)
);

-- System settings
CREATE TABLE system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Medicines catalog
CREATE TABLE medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    dosage_form VARCHAR(50),
    strength VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Appointment requests from public booking
CREATE TABLE appointment_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(100),
    requested_date DATE NOT NULL,
    requested_time TIME NOT NULL,
    preferred_doctor_id INT,
    notes TEXT,
    status ENUM('pending', 'confirmed', 'rejected', 'rescheduled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (preferred_doctor_id) REFERENCES doctors(id)
);

-- Confirmed appointments
CREATE TABLE appointments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    request_id INT,
    patient_name VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(20) NOT NULL,
    patient_email VARCHAR(100),
    doctor_id INT NOT NULL,
    appointment_date DATE NOT NULL,
    appointment_time TIME NOT NULL,
    status ENUM('scheduled', 'completed', 'cancelled', 'no_show') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (request_id) REFERENCES appointment_requests(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Patient encounters
CREATE TABLE encounters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    appointment_id INT NOT NULL,
    doctor_id INT NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    encounter_date DATE NOT NULL,
    encounter_time TIME NOT NULL,
    observations TEXT,
    notes TEXT,
    status ENUM('open', 'closed') DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (appointment_id) REFERENCES appointments(id),
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

-- Encounter files (local file references)
CREATE TABLE encounter_files (
    id INT PRIMARY KEY AUTO_INCREMENT,
    encounter_id INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encounter_id) REFERENCES encounters(id)
);

-- Services and charges for invoicing
CREATE TABLE services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Invoice items for encounters
CREATE TABLE encounter_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    encounter_id INT NOT NULL,
    service_id INT NOT NULL,
    quantity INT DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    total_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    FOREIGN KEY (service_id) REFERENCES services(id)
);

-- Prescribed medicines in encounters
CREATE TABLE encounter_medicines (
    id INT PRIMARY KEY AUTO_INCREMENT,
    encounter_id INT NOT NULL,
    medicine_id INT NOT NULL,
    dosage VARCHAR(100),
    frequency VARCHAR(100),
    duration VARCHAR(100),
    instructions TEXT,
    FOREIGN KEY (encounter_id) REFERENCES encounters(id),
    FOREIGN KEY (medicine_id) REFERENCES medicines(id)
);

-- Invoices
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    encounter_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    patient_name VARCHAR(100) NOT NULL,
    patient_phone VARCHAR(20),
    patient_email VARCHAR(100),
    doctor_name VARCHAR(100) NOT NULL,
    invoice_date DATE NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'cancelled') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (encounter_id) REFERENCES encounters(id)
);

-- Insert default data
INSERT INTO specialties (name, description) VALUES
('General Medicine', 'General medical practice'),
('Cardiology', 'Heart and cardiovascular system'),
('Dermatology', 'Skin, hair, and nail conditions'),
('Orthopedics', 'Bones, joints, and muscles'),
('Pediatrics', 'Medical care for children'),
('Gynecology', 'Women\'s reproductive health');

INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('clinic_name', 'Medical Clinic', 'Name of the clinic'),
('clinic_address', '123 Medical St, Health City, HC 12345', 'Clinic address'),
('clinic_phone', '+1-555-0123', 'Clinic phone number'),
('clinic_email', 'info@medicalclinic.com', 'Clinic email address'),
('invoice_header', 'Professional Medical Services', 'Invoice header text'),
('email_smtp_host', 'smtp.gmail.com', 'SMTP server for emails'),
('email_smtp_port', '587', 'SMTP port'),
('email_username', '', 'Email username'),
('email_password', '', 'Email password');

INSERT INTO services (name, description, price) VALUES
('Consultation', 'General medical consultation', 100.00),
('Follow-up Visit', 'Follow-up consultation', 75.00),
('Basic Lab Tests', 'Basic laboratory tests', 50.00),
('X-Ray', 'X-Ray examination', 80.00),
('ECG', 'Electrocardiogram', 60.00);

INSERT INTO medicines (name, dosage_form, strength) VALUES
('Paracetamol', 'Tablet', '500mg'),
('Amoxicillin', 'Capsule', '250mg'),
('Ibuprofen', 'Tablet', '400mg'),
('Omeprazole', 'Capsule', '20mg'),
('Vitamin D3', 'Tablet', '1000IU');

-- Create default admin user (password: admin123)
INSERT INTO users (username, password, role, email, full_name) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'administrator', 'admin@clinic.com', 'System Administrator');
