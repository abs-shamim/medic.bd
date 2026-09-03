-- Prescription Module Database
-- Import this file once from phpMyAdmin or MySQL.
-- The module also uses CREATE TABLE IF NOT EXISTS as a safe fallback.

CREATE TABLE IF NOT EXISTS prescription_patients (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    patient_code VARCHAR(50) NOT NULL,
    name VARCHAR(150) NOT NULL,
    phone VARCHAR(50) DEFAULT NULL,
    phone_normalized VARCHAR(30) DEFAULT NULL,
    age VARCHAR(30) DEFAULT NULL,
    gender VARCHAR(20) DEFAULT NULL,
    blood_group VARCHAR(20) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    last_weight VARCHAR(30) DEFAULT NULL,
    last_height VARCHAR(30) DEFAULT NULL,
    last_blood_pressure VARCHAR(30) DEFAULT NULL,
    last_temperature VARCHAR(30) DEFAULT NULL,
    last_pulse VARCHAR(30) DEFAULT NULL,
    last_spo2 VARCHAR(30) DEFAULT NULL,
    medical_history TEXT DEFAULT NULL,
    last_visit_date DATE DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_patient_code_doctor (doctor_id, patient_code),
    KEY idx_patient_doctor (doctor_id),
    KEY idx_patient_phone (phone),
    KEY idx_patient_phone_normalized (phone_normalized),
    KEY idx_patient_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescriptions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prescription_no VARCHAR(60) NOT NULL,
    doctor_id BIGINT UNSIGNED NOT NULL,
    patient_id BIGINT UNSIGNED DEFAULT NULL,
    patient_name VARCHAR(150) NOT NULL,
    patient_age VARCHAR(30) DEFAULT NULL,
    patient_gender VARCHAR(20) DEFAULT NULL,
    patient_phone VARCHAR(50) DEFAULT NULL,
    patient_blood_group VARCHAR(20) DEFAULT NULL,
    patient_address TEXT DEFAULT NULL,
    visit_date DATE NOT NULL,
    weight VARCHAR(30) DEFAULT NULL,
    height VARCHAR(30) DEFAULT NULL,
    blood_pressure VARCHAR(30) DEFAULT NULL,
    temperature VARCHAR(30) DEFAULT NULL,
    pulse VARCHAR(30) DEFAULT NULL,
    spo2 VARCHAR(30) DEFAULT NULL,
    chief_complaints TEXT DEFAULT NULL,
    medical_history TEXT DEFAULT NULL,
    examination TEXT DEFAULT NULL,
    diagnosis TEXT DEFAULT NULL,
    advice TEXT DEFAULT NULL,
    follow_up_date DATE DEFAULT NULL,
    private_notes TEXT DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_prescription_no (prescription_no),
    KEY idx_prescription_doctor (doctor_id),
    KEY idx_prescription_patient (patient_id),
    KEY idx_prescription_visit_date (visit_date),
    KEY idx_prescription_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_daily_sequences (
    sequence_date DATE NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (sequence_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_medicines (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prescription_id BIGINT UNSIGNED NOT NULL,
    library_id BIGINT UNSIGNED DEFAULT NULL,
    medicine_form VARCHAR(100) DEFAULT NULL,
    generic_name VARCHAR(200) DEFAULT NULL,
    brand_name VARCHAR(200) DEFAULT NULL,
    medicine_name VARCHAR(200) NOT NULL,
    strength VARCHAR(100) DEFAULT NULL,
    dosage VARCHAR(100) DEFAULT NULL,
    frequency VARCHAR(100) DEFAULT NULL,
    duration VARCHAR(100) DEFAULT NULL,
    instruction VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_medicine_prescription (prescription_id),
    KEY idx_medicine_library (library_id),
    KEY idx_medicine_generic (generic_name),
    KEY idx_medicine_brand (brand_name),
    KEY idx_medicine_name (medicine_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE IF NOT EXISTS prescription_medicine_name_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    generic_name VARCHAR(200) NOT NULL,
    brand_name VARCHAR(200) NOT NULL DEFAULT '',
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_medicine_name_doctor (doctor_id, generic_name, brand_name),
    KEY idx_medicine_name_generic (generic_name),
    KEY idx_medicine_name_brand (brand_name),
    KEY idx_medicine_name_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_medicine_form_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_medicine_form_doctor (doctor_id, option_value),
    KEY idx_medicine_form_status (status),
    KEY idx_medicine_form_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_strength_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    option_value VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_strength_doctor (doctor_id, option_value),
    KEY idx_strength_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_dosage_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    option_value VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dosage_doctor (doctor_id, option_value),
    KEY idx_dosage_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_frequency_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    option_value VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_frequency_doctor (doctor_id, option_value),
    KEY idx_frequency_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_duration_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    option_value VARCHAR(100) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_duration_doctor (doctor_id, option_value),
    KEY idx_duration_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_instruction_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_instruction_doctor (doctor_id, option_value),
    KEY idx_instruction_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing installations are upgraded automatically by
-- prescription/includes/functions.php when the module is opened.

CREATE TABLE IF NOT EXISTS prescription_tests (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    prescription_id BIGINT UNSIGNED NOT NULL,
    test_name VARCHAR(200) NOT NULL,
    instruction VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_test_prescription (prescription_id),
    KEY idx_test_name (test_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- Complete Clinical Libraries
-- doctor_id = 0 means a shared demo item visible to every approved doctor.
-- Removing a shared demo from a doctor account is stored in
-- prescription_library_hidden and does not remove the demo for other doctors.
-- --------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS prescription_test_name_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(200) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_test_instruction_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_complaint_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_diagnosis_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_history_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_examination_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_advice_library (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    option_value VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active',
    usage_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_library_doctor_value (doctor_id, option_value),
    KEY idx_library_status (status),
    KEY idx_library_value (option_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS prescription_library_hidden (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    doctor_id BIGINT UNSIGNED NOT NULL,
    library_type VARCHAR(40) NOT NULL,
    item_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_hidden_library_item (doctor_id, library_type, item_id),
    KEY idx_hidden_doctor_type (doctor_id, library_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Shared demo medicine libraries.
INSERT IGNORE INTO prescription_medicine_name_library
    (doctor_id, generic_name, brand_name, status, usage_count, created_at)
VALUES
    (0, 'Paracetamol', '', 'active', 0, NOW()),
    (0, 'Omeprazole', '', 'active', 0, NOW()),
    (0, 'Oral Rehydration Salts', 'ORS', 'active', 0, NOW());

INSERT IGNORE INTO prescription_medicine_form_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Tab.', 'active', 0, NOW()),
    (0, 'Cap.', 'active', 0, NOW()),
    (0, 'Softgel', 'active', 0, NOW()),
    (0, 'Chew. Tab.', 'active', 0, NOW()),
    (0, 'Dispersible Tab.', 'active', 0, NOW()),
    (0, 'Eff. Tab.', 'active', 0, NOW()),
    (0, 'Sublingual Tab.', 'active', 0, NOW()),
    (0, 'Buccal Tab.', 'active', 0, NOW()),
    (0, 'Lozenge', 'active', 0, NOW()),
    (0, 'Troche', 'active', 0, NOW()),
    (0, 'Sachet', 'active', 0, NOW()),
    (0, 'Granules', 'active', 0, NOW()),
    (0, 'Powder', 'active', 0, NOW()),
    (0, 'Oral Powder', 'active', 0, NOW()),
    (0, 'Syr.', 'active', 0, NOW()),
    (0, 'Susp.', 'active', 0, NOW()),
    (0, 'Oral Susp.', 'active', 0, NOW()),
    (0, 'Soln.', 'active', 0, NOW()),
    (0, 'Oral Soln.', 'active', 0, NOW()),
    (0, 'Elixir', 'active', 0, NOW()),
    (0, 'Emulsion', 'active', 0, NOW()),
    (0, 'Drops', 'active', 0, NOW()),
    (0, 'Mixture', 'active', 0, NOW()),
    (0, 'Linctus', 'active', 0, NOW()),
    (0, 'Inj.', 'active', 0, NOW()),
    (0, 'IV Inj.', 'active', 0, NOW()),
    (0, 'IM Inj.', 'active', 0, NOW()),
    (0, 'SC Inj.', 'active', 0, NOW()),
    (0, 'ID Inj.', 'active', 0, NOW()),
    (0, 'Inf.', 'active', 0, NOW()),
    (0, 'IV Inf.', 'active', 0, NOW()),
    (0, 'Amp.', 'active', 0, NOW()),
    (0, 'Vial', 'active', 0, NOW()),
    (0, 'Prefilled Syringe', 'active', 0, NOW()),
    (0, 'Cream', 'active', 0, NOW()),
    (0, 'Oint.', 'active', 0, NOW()),
    (0, 'Gel', 'active', 0, NOW()),
    (0, 'Lotion', 'active', 0, NOW()),
    (0, 'Paste', 'active', 0, NOW()),
    (0, 'Paint', 'active', 0, NOW()),
    (0, 'Liniment', 'active', 0, NOW()),
    (0, 'Topical Soln.', 'active', 0, NOW()),
    (0, 'Topical Spray', 'active', 0, NOW()),
    (0, 'Foam', 'active', 0, NOW()),
    (0, 'Shampoo', 'active', 0, NOW()),
    (0, 'Soap', 'active', 0, NOW()),
    (0, 'Patch', 'active', 0, NOW()),
    (0, 'Eye Drop', 'active', 0, NOW()),
    (0, 'Eye Oint.', 'active', 0, NOW()),
    (0, 'Ophthalmic Soln.', 'active', 0, NOW()),
    (0, 'Ophthalmic Gel', 'active', 0, NOW()),
    (0, 'Eye Insert', 'active', 0, NOW()),
    (0, 'Ear Drop', 'active', 0, NOW()),
    (0, 'Otic Soln.', 'active', 0, NOW()),
    (0, 'Ear Spray', 'active', 0, NOW()),
    (0, 'Nasal Drop', 'active', 0, NOW()),
    (0, 'Nasal Spray', 'active', 0, NOW()),
    (0, 'Nasal Gel', 'active', 0, NOW()),
    (0, 'Nasal Wash', 'active', 0, NOW()),
    (0, 'Inhaler', 'active', 0, NOW()),
    (0, 'MDI', 'active', 0, NOW()),
    (0, 'DPI', 'active', 0, NOW()),
    (0, 'Neb. Soln.', 'active', 0, NOW()),
    (0, 'Respules', 'active', 0, NOW()),
    (0, 'Rotacap', 'active', 0, NOW()),
    (0, 'Transhaler', 'active', 0, NOW()),
    (0, 'Inhalation Cap.', 'active', 0, NOW()),
    (0, 'Nasal Inhaler', 'active', 0, NOW()),
    (0, 'Supp.', 'active', 0, NOW()),
    (0, 'Rectal Cream', 'active', 0, NOW()),
    (0, 'Rectal Oint.', 'active', 0, NOW()),
    (0, 'Enema', 'active', 0, NOW()),
    (0, 'Vag. Tab.', 'active', 0, NOW()),
    (0, 'Vag. Cap.', 'active', 0, NOW()),
    (0, 'Pessary', 'active', 0, NOW()),
    (0, 'Vag. Cream', 'active', 0, NOW()),
    (0, 'Vag. Gel', 'active', 0, NOW()),
    (0, 'Vag. Supp.', 'active', 0, NOW()),
    (0, 'Mouthwash', 'active', 0, NOW()),
    (0, 'Gargle', 'active', 0, NOW()),
    (0, 'Oral Gel', 'active', 0, NOW()),
    (0, 'Dental Gel', 'active', 0, NOW()),
    (0, 'Dental Paste', 'active', 0, NOW()),
    (0, 'Oral Spray', 'active', 0, NOW()),
    (0, 'Throat Spray', 'active', 0, NOW()),
    (0, 'Implant', 'active', 0, NOW()),
    (0, 'Pellet', 'active', 0, NOW()),
    (0, 'Ring', 'active', 0, NOW()),
    (0, 'Irrigation Soln.', 'active', 0, NOW()),
    (0, 'Dialysis Soln.', 'active', 0, NOW()),
    (0, 'Medical Gas', 'active', 0, NOW()),
    (0, 'Kit', 'active', 0, NOW()),
    (0, 'Device', 'active', 0, NOW()),
    (0, 'Other', 'active', 0, NOW());

INSERT IGNORE INTO prescription_strength_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, '500 mg', 'active', 0, NOW()),
    (0, '250 mg/5 ml', 'active', 0, NOW()),
    (0, '20 mg', 'active', 0, NOW()),
    (0, '5 mg', 'active', 0, NOW());

INSERT IGNORE INTO prescription_dosage_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, '1 tablet', 'active', 0, NOW()),
    (0, '1 capsule', 'active', 0, NOW()),
    (0, '5 ml', 'active', 0, NOW()),
    (0, 'As directed', 'active', 0, NOW());

INSERT IGNORE INTO prescription_frequency_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Once daily', 'active', 0, NOW()),
    (0, 'Twice daily', 'active', 0, NOW()),
    (0, 'Three times daily', 'active', 0, NOW()),
    (0, '1-0-1', 'active', 0, NOW()),
    (0, 'When required', 'active', 0, NOW());

INSERT IGNORE INTO prescription_duration_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, '3 days', 'active', 0, NOW()),
    (0, '5 days', 'active', 0, NOW()),
    (0, '7 days', 'active', 0, NOW()),
    (0, '1 month', 'active', 0, NOW()),
    (0, 'Continue', 'active', 0, NOW());

INSERT IGNORE INTO prescription_instruction_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'After meal', 'active', 0, NOW()),
    (0, 'Before meal', 'active', 0, NOW()),
    (0, 'At bedtime', 'active', 0, NOW()),
    (0, 'With water', 'active', 0, NOW()),
    (0, 'As directed', 'active', 0, NOW());

-- Shared demo clinical libraries.
INSERT IGNORE INTO prescription_test_name_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Complete Blood Count (CBC)', 'active', 0, NOW()),
    (0, 'Fasting Blood Glucose', 'active', 0, NOW()),
    (0, 'Serum Creatinine', 'active', 0, NOW()),
    (0, 'Urine Routine Examination', 'active', 0, NOW()),
    (0, 'Chest X-ray', 'active', 0, NOW());

INSERT IGNORE INTO prescription_test_instruction_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Fasting for 8 hours', 'active', 0, NOW()),
    (0, 'Bring previous reports', 'active', 0, NOW()),
    (0, 'Collect morning sample', 'active', 0, NOW()),
    (0, 'Complete before next visit', 'active', 0, NOW());

INSERT IGNORE INTO prescription_complaint_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Fever', 'active', 0, NOW()),
    (0, 'Cough', 'active', 0, NOW()),
    (0, 'Headache', 'active', 0, NOW()),
    (0, 'Abdominal pain', 'active', 0, NOW()),
    (0, 'Weakness', 'active', 0, NOW());

INSERT IGNORE INTO prescription_diagnosis_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Provisional diagnosis', 'active', 0, NOW()),
    (0, 'Viral fever', 'active', 0, NOW()),
    (0, 'Upper respiratory tract infection', 'active', 0, NOW()),
    (0, 'Hypertension', 'active', 0, NOW()),
    (0, 'Type 2 diabetes mellitus', 'active', 0, NOW());

INSERT IGNORE INTO prescription_history_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'No significant past medical history', 'active', 0, NOW()),
    (0, 'History of hypertension', 'active', 0, NOW()),
    (0, 'History of diabetes mellitus', 'active', 0, NOW()),
    (0, 'Known drug allergy: __________', 'active', 0, NOW());

INSERT IGNORE INTO prescription_examination_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'General condition stable', 'active', 0, NOW()),
    (0, 'Patient is afebrile', 'active', 0, NOW()),
    (0, 'Chest is clear', 'active', 0, NOW()),
    (0, 'Blood pressure: __________', 'active', 0, NOW()),
    (0, 'No peripheral oedema', 'active', 0, NOW());

INSERT IGNORE INTO prescription_advice_library
    (doctor_id, option_value, status, usage_count, created_at)
VALUES
    (0, 'Take medicines exactly as prescribed', 'active', 0, NOW()),
    (0, 'Drink adequate water', 'active', 0, NOW()),
    (0, 'Bring all investigation reports at follow-up', 'active', 0, NOW()),
    (0, 'Return earlier if symptoms worsen', 'active', 0, NOW()),
    (0, 'Follow up on the advised date', 'active', 0, NOW());

