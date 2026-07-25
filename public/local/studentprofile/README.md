# Local Student Profile Plugin

This Moodle plugin (`local_studentprofile`) forces students to complete their profiles upon their first login (typically via Google OAuth). It captures extended information such as College, Degree, Degree Type, and Admission Year, which is then used to automatically assign students to specific courses based on dynamic admin-defined rules.

## Features

1. **Mandatory Profile Completion:** Forces students to fill out missing profile fields before they can access the dashboard.
2. **College & Degree Management:** Admins can manage a centralized list of allowed Colleges and Degrees in the Site Administration.
3. **Roll Number Assignment:** Admins can assign permanent or temporary roll numbers to students who have completed their profiles.
4. **Course Assignment Rules Engine:** A powerful dynamic rules engine to auto-enroll students into courses based on their profile data.

## Course Assignment Rules Engine

The rules engine automatically evaluates a student's profile upon completion and enrolls them into designated courses if they meet specific criteria.

### Accessing the Rules Engine
Navigate to **Site Administration > Plugins > Local plugins > Student Profile Settings > Manage Rules** (`/local/studentprofile/admin_rules.php`).

### How It Works
1. **Trigger:** When a student successfully submits the profile completion form for the first time, a `profile_completed` event is fired.
2. **Evaluation:** The system fetches all *Active* rules and evaluates them in ascending order of their **Priority**.
3. **Action:** If a student's profile matches the conditions defined in a rule, they are automatically enrolled (via Manual Enrolment) into the courses selected for that rule.
4. **Stop Processing:** If a matched rule has the "Stop Processing" flag set to Yes, the engine immediately halts and will not evaluate any subsequent lower-priority rules.

### Building Rules
Rules are created using a dynamic **Visual Query Builder**. The builder supports nested logic groups (AND/OR) and allows conditions based on:
- **Text Fields** (First Name, Last Name): Supports Operators like `=`, `!=`, `Starts With`, `Ends With`, `Contains`.
- **Numeric Fields** (Admission Year): Supports Operators like `=`, `!=`, `<`, `<=`, `>`, `>=`, `Between`.
- **Dropdown/Selection Fields** (College Name, Degree, Degree Type): Supports Operators like `=`, `!=`, `IN`, `NOT IN`.

### Retroactive Execution
If you create new rules and wish to apply them to students who have *already* completed their profiles in the past, you can click the **Run Rules Engine on All Profiles** button on the Manage Rules page. This will iterate through all completed profiles and evaluate them against the current active rules.

## Development & Architecture

- **Schema:** 
  - `local_studentprofile_data`: Stores the submitted profile fields for each user.
  - `local_studentprofile_rules`: Stores the rule definitions, statuses, priorities, and conditions (as JSON).
  - `local_studentprofile_colleges` / `local_studentprofile_degrees`: Stores the master lists of valid options.
- **Rule Engine Core:** The evaluation logic resides in `\local_studentprofile\rule_engine` (`classes/rule_engine.php`), which recursively parses the JSON condition tree.
- **Frontend Builder:** The custom visual query builder is built using jQuery and AMD (`amd/src/rule_builder.js`) and serializes the DOM state into JSON upon form submission.
