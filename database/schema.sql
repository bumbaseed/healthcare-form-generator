-- Healthcare Form Generator - PostgreSQL schema.

-- Dynamically generated form tables live in their own schema so the public schema doesn't fill up with one table per form.
CREATE SCHEMA IF NOT EXISTS form_data;

-- Drop existing tables if they exist
DROP TABLE IF EXISTS audit_log CASCADE;
DROP TABLE IF EXISTS form_submissions CASCADE;
DROP TABLE IF EXISTS form_fields CASCADE;
DROP TABLE IF EXISTS form_definitions CASCADE;
DROP TABLE IF EXISTS patients CASCADE;
DROP TABLE IF EXISTS staff_users CASCADE;


-- Staff accounts. Used for login, audit attribution, and role-based access.
CREATE TABLE staff_users (
    user_id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(200) NOT NULL,
    email VARCHAR(200),
    role VARCHAR(50) NOT NULL DEFAULT 'staff',
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMP,
    failed_attempts INTEGER NOT NULL DEFAULT 0,
    locked_until TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CHECK (role IN ('admin', 'staff'))
);

CREATE INDEX idx_staff_users_username ON staff_users(username);
CREATE INDEX idx_staff_users_role ON staff_users(role);
CREATE INDEX idx_staff_users_active ON staff_users(is_active);


-- Patients. patient_identifier is what staff type into the MRN entry screen.
CREATE TABLE patients (
    patient_id SERIAL PRIMARY KEY,
    patient_identifier VARCHAR(100) UNIQUE NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    date_of_birth DATE,
    gender VARCHAR(20),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(100),
    address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT TRUE
);

CREATE INDEX idx_patients_identifier ON patients(patient_identifier);
CREATE INDEX idx_patients_name ON patients(last_name, first_name);
CREATE INDEX idx_patients_active ON patients(is_active);


-- Form templates. Each row drives the generation of one PHP file under /forms and one PostgreSQL table under form_data.
CREATE TABLE form_definitions (
    form_id SERIAL PRIMARY KEY,
    form_name VARCHAR(200) NOT NULL,
    form_description TEXT,
    form_version INTEGER DEFAULT 1,
    table_name VARCHAR(100) UNIQUE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INTEGER REFERENCES staff_users(user_id),
    field_count INTEGER DEFAULT 0
);

CREATE INDEX idx_form_definitions_active ON form_definitions(is_active);
CREATE INDEX idx_form_definitions_name ON form_definitions(form_name);


-- Field definitions for each form. Drives the generated form's HTML and the shape of the dynamic table created under form_data.
CREATE TABLE form_fields (
    field_id SERIAL PRIMARY KEY,
    form_id INTEGER NOT NULL REFERENCES form_definitions(form_id) ON DELETE CASCADE,
    field_order INTEGER NOT NULL,
    field_name VARCHAR(100) NOT NULL,
    field_label VARCHAR(200) NOT NULL,
    field_type VARCHAR(50) NOT NULL,
    data_type VARCHAR(50) NOT NULL,
    field_options TEXT,
    is_required BOOLEAN DEFAULT FALSE,
    default_value TEXT,
    validation_rules TEXT,
    max_length INTEGER,
    help_text TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(form_id, field_name),
    CHECK (field_type IN ('text', 'textarea', 'number', 'date', 'datetime', 'select', 'radio', 'checkbox', 'email', 'phone', 'boolean')),
    CHECK (data_type IN ('VARCHAR', 'TEXT', 'INTEGER', 'NUMERIC', 'DATE', 'TIMESTAMP', 'BOOLEAN'))
);

CREATE INDEX idx_form_fields_form ON form_fields(form_id);
CREATE INDEX idx_form_fields_order ON form_fields(form_id, field_order);


-- Metadata for each completed or in-progress submission. The actual field data lives in the dynamic table named by form_definitions.table_name; this row links the two and tracks status, signing, and audit columns. A patient's case file is just the set of submissions sharing patient_id.
CREATE TABLE form_submissions (
    submission_id SERIAL PRIMARY KEY,
    form_id INTEGER NOT NULL REFERENCES form_definitions(form_id),
    patient_id INTEGER REFERENCES patients(patient_id) ON DELETE CASCADE,
    submission_status VARCHAR(50) DEFAULT 'draft',
    data_table_id INTEGER,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,
    submitted_by INTEGER REFERENCES staff_users(user_id),
    notes TEXT,
    is_deleted BOOLEAN DEFAULT FALSE,
    -- Electronic signature captured at completion. signature_name is the typed full name; signature_confirmed_at is server-stamped when the attestation checkbox is ticked. Both stay NULL on drafts.
    signature_name VARCHAR(200),
    signature_confirmed_at TIMESTAMP,
    CHECK (submission_status IN ('draft', 'completed', 'reviewed'))
);

CREATE INDEX idx_form_submissions_form ON form_submissions(form_id);
CREATE INDEX idx_form_submissions_patient ON form_submissions(patient_id);
CREATE INDEX idx_form_submissions_status ON form_submissions(submission_status);
CREATE INDEX idx_form_submissions_deleted ON form_submissions(is_deleted);


-- Trigger to refresh updated_at on row change.
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_patients_updated_at BEFORE UPDATE ON patients
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_form_definitions_updated_at BEFORE UPDATE ON form_definitions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();


COMMENT ON TABLE patients IS 'Patient demographic and contact information';
COMMENT ON TABLE form_definitions IS 'Form templates that drive the generated PHP files and dynamic tables';
COMMENT ON TABLE form_fields IS 'Field definitions belonging to a form';
COMMENT ON TABLE form_submissions IS 'Submission metadata. Field data is held in the dynamic form_data table referenced by data_table_id.';
COMMENT ON TABLE staff_users IS 'Staff and admin accounts used for authentication';

COMMENT ON COLUMN form_definitions.table_name IS 'Name of the dynamic form_data table for this form, for example form_data.contact_form_v1';
COMMENT ON COLUMN form_submissions.data_table_id IS 'id of the row in the dynamic table that holds this submission''s field values';


-- Audit log. Records authentication events and any action a member of staff takes against a record, used for compliance and post-incident review.
CREATE TABLE audit_log (
    log_id SERIAL PRIMARY KEY,
    user_id INTEGER REFERENCES staff_users(user_id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INTEGER,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_audit_log_user ON audit_log(user_id);
CREATE INDEX idx_audit_log_action ON audit_log(action);
CREATE INDEX idx_audit_log_entity ON audit_log(entity_type, entity_id);
CREATE INDEX idx_audit_log_created ON audit_log(created_at);

COMMENT ON TABLE audit_log IS 'Audit trail of staff actions and authentication events';


CREATE TRIGGER update_staff_users_updated_at BEFORE UPDATE ON staff_users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();


-- After running this script, run database/seed.sql to load the default admin and demo staff accounts plus a small set of sample patients.
