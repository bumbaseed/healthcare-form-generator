
-- Usage:
-- psql -U postgres -d healthcare_generator -f database/schema.sql
-- psql -U postgres -d healthcare_generator -f database/seed.sql
--
-- Default credentials seeded by this script:
-- Admin: username = admin / password = admin123
-- Staff: username = staff / password = staff123
--
-- Both passwords are bcrypt hashes generated with PHP password_hash() and PASSWORD_DEFAULT. Bcrypt is salt-portable so these hashes verify  on any host running PHP >= 7.4.

-- Staff accounts. 
INSERT INTO staff_users (username, password_hash, full_name, role, is_active)
VALUES
    ('admin', '$2y$12$XjA.5VaPJeZeJcQUo4f3sexvsjaom4aMBJSplVrFAWzepYS0MRC/O', 'System Administrator', 'admin', TRUE),
    ('staff', '$2y$12$dgtPkfWftdISSPgS1fzvcOmWosz1l2qjIV7CYZRs3Ud.DGEcdE.LK', 'Demo Staff User',       'staff', TRUE)
ON CONFLICT (username) DO NOTHING;

-- Sample patients. 
INSERT INTO patients (patient_identifier, first_name, last_name, date_of_birth, gender, contact_phone, contact_email)
VALUES
    ('P001', 'Alice',  'Walker', '1985-03-12', 'female', '07700 900001', 'alice.walker@example.test'),
    ('P002', 'Brendan', 'Hill',  '1972-11-04', 'male',   '07700 900002', 'brendan.hill@example.test'),
    ('P003', 'Chen',    'Liu',   '1990-07-22', 'other',  '07700 900003', 'chen.liu@example.test')
ON CONFLICT (patient_identifier) DO NOTHING;
