# Local Student Profile Plugin (`local_studentprofile`)

The `local_studentprofile` plugin for Moodle enforces mandatory extended profile completion for students upon login (especially useful with Google OAuth2 login), provides centralized master management for Colleges and Degrees, automates course enrolments using a visual dynamic rules engine, manages roll number verification workflows, replaces the standard Moodle participants list with an advanced filterable directory, and supports bulk CSV import/export operations.

---

## 🌟 Key Features

1. **Mandatory Profile Completion & Session Redirection**
   - Intercepts student logins via the `user_loggedin` event observer.
   - Redirects uncompleted profiles to `/local/studentprofile/complete.php` via the `after_config` hook.
   - Collects personal details (First Name, Middle Name, Last Name, Phone Number), Degree Type (UG/PG), Degree Name, College Name, Roll Number, and Admission Year.
   - Synchronizes basic profile fields (First/Last name, Phone Number) back to Moodle's core `mdl_user` table.

2. **Auto-Saving & Draft Recovery**
   - Periodically auto-saves unsubmitted form data via AJAX (`local_studentprofile_save_draft`).
   - Displays a warning banner on the student Dashboard (`/my/`) if an unfinalized draft exists.

3. **Dynamic Course Assignment Rules Engine**
   - Admins define automated course enrolment rules using a visual hierarchical query builder (`admin_rule_edit.php`).
   - Supports `AND`/`OR` logic groups and operators across text fields, numeric fields, selection options, and computed temporal fields (`reporting_week`).
   - Automatically enrols matching students into designated courses via Moodle's `manual` enrolment plugin upon profile completion.
   - Includes priority-based evaluation and a **Stop Processing** flag.
   - Supports **Retroactive Rule Execution** across all existing completed profiles from `admin_rules.php`.

4. **College & Degree Master Data Management**
   - **College Management (`admin_colleges.php`):** Centralized CRUD with support for full names, acronym shortnames, long descriptions, and linked degrees. Automatically generates acronym shortnames on-the-fly when students input custom college names.
   - **Degree Management (`admin_degrees.php`):** Master degree list with UG/PG classification.
   - **Automated Installation Seeder & CLI (`db/install.php` & `cli_seed.php`):** Automatically seeds all master colleges (98) and degrees (58) from `seed_data.json` upon fresh plugin installation on any domain or manually via CLI.

5. **Roll Number Verification Workflow**
   - Administrative review table at `admin_rollnumbers.php`.
   - Tracks approval status (`0 = Pending`, `1 = Approved`, `2 = Disapproved`).
   - Allows inline AJAX editing of roll numbers and statuses via `rollno_updater.js`.

6. **Custom Course Participants Directory**
   - Overrides standard Moodle course participants navigation (`/user/index.php`) and redirects to `/local/studentprofile/participants.php`.
   - Provides real-time AJAX filtering by **College**, **Degree**, **Admission Year**, **Search Keywords**, and **Alphabetical Initials**.

7. **Bulk Data Operations (CSV Import & Export)**
   - **CSV Export (`admin_export_profiles.php`):** Export completed student records with academic metadata, email, and roll number statuses.
   - **CSV Import (`admin_import_profiles.php`):** Bulk update or create student profiles from CSV uploads matched by email address.

8. **OAuth2 Integration & Admin Settings**
   - Enabling mandatory completion automatically configures Google OAuth2 issuers (`requireconfirmation = 0`) so students seamlessly transition from Google login directly to profile completion.

---

## 🛠️ Architecture & Database Schema

### Database Tables
- `local_studentprofile_data`: Stores student extended profile records, draft state JSON, and roll number statuses.
- `local_studentprofile_rules`: Stores rule conditions (JSON tree), target course IDs, priority, and status.
- `local_studentprofile_colleges`: Stores master list of colleges, shortname acronyms, and associated degrees.
- `local_studentprofile_degrees`: Stores master list of degrees and degree types (UG/PG).

### Key Classes & Entry Points
- `\local_studentprofile\hook\after_config`: Navigation interception hook for redirection.
- `\local_studentprofile\observer`: Event listeners for `user_loggedin` and `profile_completed`.
- `\local_studentprofile\rule_engine`: Recursive rule evaluation core logic.
- `\local_studentprofile\external`: Web service endpoints for participant table rendering, draft saving, and roll number updates.
- `db/install.php`: Post-install hook that populates all 98 colleges and 58 degrees from `seed_data.json` automatically.

---

## 📜 License
GNU GPL v3 or later
