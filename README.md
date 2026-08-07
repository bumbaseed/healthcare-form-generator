A PHP and PostgreSQL web application that lets healthcare IT staff author clinical forms in the browser, complete them against a selected patient and export each submission as a FHIR R4 QuestionnaireResponse.

## Requirements

- PHP 8.1 or later with the `pdo_pgsql` extension enabled
- PostgreSQL 12 or later

The codebase uses the never return type, which requires PHP 8.1 or later. Earlier 8.0.x versions will fail to parse.

## Setup

1. Create the database:
   createdb -U postgres healthcare_generator

2. Load the schema and seed data:
   psql -U postgres -d healthcare_generator -f database/schema.sql
   psql -U postgres -d healthcare_generator -f database/seed.sql

3. Edit `config/database.local.php` and replace `password-here` with your local PostgreSQL password.
4. Start the built-in PHP server from the project root:

php -S localhost:8000

5. Open <http://localhost:8000> in a browser.

## Default credentials

Username Password Role

`admin` `admin123` admin
`staff` `staff123` staff

Once logged in, enter MRN `P001`, `P002` or `P003` on the patient entry screen to set the patient context.

## Project layout

- `code/includes/` - cross-cutting services such as authentication, CSRF tokens, the database connection wrapper and helper functions
- `code/models/` - domain classes covering form definitions, submissions, dynamic tables and the FHIR export
- `code/views/` - page templates and shared layout partials
- `forms/` - the self-contained PHP files produced by the form builder
- `database/` - schema definitions, seed data and the staff management CLI
