-- Medicine Form Library Upgrade
-- Adds a reusable medicine form library and saves the selected form per medicine.

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

SET @medicine_form_column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'prescription_medicines'
      AND COLUMN_NAME = 'medicine_form'
);

SET @medicine_form_alter_sql = IF(
    @medicine_form_column_exists = 0,
    'ALTER TABLE prescription_medicines ADD COLUMN medicine_form VARCHAR(100) DEFAULT NULL AFTER library_id',
    'SELECT 1'
);

PREPARE medicine_form_stmt FROM @medicine_form_alter_sql;
EXECUTE medicine_form_stmt;
DEALLOCATE PREPARE medicine_form_stmt;

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
