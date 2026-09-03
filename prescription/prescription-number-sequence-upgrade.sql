-- Daily prescription number sequence for RXYYMMDDNN format.
-- Example: RX26071101, RX26071102.

CREATE TABLE IF NOT EXISTS prescription_daily_sequences (
    sequence_date DATE NOT NULL,
    last_number INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME DEFAULT NULL,
    PRIMARY KEY (sequence_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
