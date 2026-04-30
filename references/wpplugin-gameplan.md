# Execution Gameplan: GapNext WP Plugin

This document outlines the systematic, production-ready development path for building the GapNext WP plugin. It translates the requirements from `wpplugin-prd.md` into actionable technical steps for a WordPress PHP developer.

## Phase 1: Foundation & Data Preparation

### 1.1 Data Extraction Script (Node.js)
Before writing any PHP, the core checklist data must be extracted from the original PDF/DOCX files.
1. Create a `scripts/` folder outside the main plugin directory.
2. Write `extract-checklists.js` using `mammoth` (for DOCX) or `pdf-parse` (for PDF) to read the 8 provided standard files.
3. Parse the documents to extract the hierarchical structure: Top-level clauses (e.g., 4. Context of the Organization) and sub-clauses (e.g., 4.1, 4.2).
4. For every question, extract the `Reference`, `Title`, `Question Text`, and `Help Text` (if available in the document).
5. Output the parsed data as PHP associative arrays and save them directly into the plugin's `includes/data/` folder (e.g., `en-9100-2018.php`, `iso-9001-2015.php`).

### 1.2 Plugin Scaffolding
1. Create the plugin directory: `wp-content/plugins/gapnext-wp/`.
2. Create the main bootstrap file: `gapnext-wp.php` with standard WordPress plugin header comments.
3. Scaffold the directory structure: `includes/`, `includes/data/`, `assets/`, `vendor/`.
4. Define core constants in the bootstrap file (e.g., `GAPNEXT_WP_VERSION`, `GAPNEXT_WP_DIR`, `GAPNEXT_WP_URL`).

## Phase 2: Database & Core Architecture

### 2.1 Database Schema (Activation Hook)
1. In `includes/class-installer.php`, hook into `register_activation_hook`.
2. Use `dbDelta` to create two custom tables:
   * `{prefix}gapnext_audits`: `id` (BIGINT), `uuid` (VARCHAR 36), `title` (VARCHAR 255), `standard_id` (VARCHAR 100), `created_by` (BIGINT), `created_at` (DATETIME), `access_mode` (VARCHAR 20), `status` (VARCHAR 20).
   * `{prefix}gapnext_submissions`: `id` (BIGINT), `audit_uuid` (VARCHAR 36), `standard_id` (VARCHAR 100), `submitted_at` (DATETIME), `company_name` (VARCHAR), `company_address` (VARCHAR), `company_vat` (VARCHAR), `company_sector` (VARCHAR), `contact_name` (VARCHAR), `contact_role` (VARCHAR), `contact_email` (VARCHAR), `contact_phone` (VARCHAR), `consultant_name` (VARCHAR), `consultant_company` (VARCHAR), `consultant_email` (VARCHAR), `consultant_phone` (VARCHAR), `answers` (LONGTEXT - JSON format), `evidence_paths` (LONGTEXT - JSON format), `score` (FLOAT).

### 2.2 Standard Registry Loader
1. Create `includes/class-standard-registry.php`.
2. Build a mechanism that scans the `includes/data/` directory for `.php` files and builds an internal cache of available standards.
3. Provide methods: `get_available_standards()` and `get_standard_data($standard_id)`.

## Phase 3: Backend Admin Management

### 3.1 Admin Menu & Pages
1. Create `includes/class-admin.php` hooking into `admin_menu`.
2. Register the main menu "GapNext WP".
3. Add submenus: **Audit Links**, **Submissions**, **Settings**.

### 3.2 Audit Links Manager
1. In `includes/class-audit-manager.php`, build the UI for the **Audit Links** page.
2. Implement a form to create a new Audit Session: Input `Title`, select `Standard` (from Registry), and select `Access Mode` (Public/Login Required).
3. On submission, generate a unique UUID (e.g., using `wp_generate_uuid4()`) and save the record to `{prefix}gapnext_audits`.
4. Display a table showing all created audits with a "Copy Link" button pointing to `site.url/?audit={uuid}`.

### 3.3 Submissions View
1. Build the **Submissions** admin page using `WP_List_Table` or a standard HTML table to display records from `{prefix}gapnext_submissions`.
2. Add row actions for "Export CSV" and "Export PDF".

## Phase 4: Frontend Shortcode & Form Logic

### 4.1 Shortcode Controller
1. Create `includes/class-checklist.php` and register the shortcode `[gapnext_checklist]`.
2. The shortcode callback must:
   * Read `$_GET['audit']`.
   * Look up the `uuid` in the database to get the `standard_id`.
   * Load the corresponding PHP array using the Standard Registry.
   * Verify the `access_mode`. If `login_required` and `!is_user_logged_in()`, redirect to `wp_login_url()`.

### 4.2 Form Rendering (HTML/CSS/JS)
1. Render a multi-step HTML form.
2. **Step 1:** Render the Company and Consultant input fields.
3. **Step 2:** Iterate over the loaded standard's clauses. Render top-level clauses as accordions. Render sub-clauses with:
   * Reference & Text.
   * Radio inputs: `Conforme` (value: 1), `Parzialmente` (value: 0.5), `Non-Conforme` (value: 0).
   * Textarea for Notes.
   * `<input type="file" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png">` for Evidence.
4. Keep the UI clean; use `assets/gapnext-wp.css` for styling and `assets/gapnext-wp.js` for step navigation and client-side validation.

## Phase 5: Submission Handling & Security

### 5.1 AJAX Processing
1. In `includes/class-ajax.php`, register `wp_ajax_gapnext_submit` and `wp_ajax_nopriv_gapnext_submit` (public).
2. **Security:** Verify WordPress nonces before processing. Sanitize all incoming `POST` data (`sanitize_text_field`, etc.).
3. **File Uploads:** Iterate through `$_FILES`. Use `wp_handle_upload()` to securely move files to `wp-content/uploads/gapnext-evidence/{uuid}/q-{clause_id}/`. Save the returned paths in an array map.
4. **Scoring:** Calculate total score based on the answers submitted. (Total Score / Total Questions).
5. **Database Insert:** JSON-encode the answers map and the evidence paths map. Insert the full record into `{prefix}gapnext_submissions`.
6. Return a JSON success response with the calculated `score`.

## Phase 6: Exporters (CSV & PDF)

### 6.1 CSV Exporter
1. Create `includes/class-export-csv.php` triggered via an admin POST request from the Submissions table.
2. Query the requested submission ID.
3. Parse the JSON `answers` and `evidence_paths`.
4. Output HTTP headers for CSV download. Stream the data row by row, ensuring special characters are escaped. Include Standard Name, Company Info, Clause References, Answers, Notes, and a comma-separated list of Evidence filenames.

### 6.2 PDF Exporter (TCPDF)
1. Download TCPDF and place it in `vendor/tcpdf/`. Add a `.htaccess` file denying web access to this folder.
2. Create `includes/class-export-pdf.php`. Include `vendor/tcpdf/tcpdf.php`.
3. Construct the PDF:
   * Add a Cover Page with robust styling: Logo, "Gap Audit Report", Standard Title, Date, Score.
   * Add a summary block showing Company and Consultant details.
   * Build the results table: Loop through the parsed JSON answers. Color-code the answer cell based on the value (Green/Amber/Red). Append Notes to the row. List the filenames of any Evidence uploaded for that clause.
4. Output the PDF directly to the browser via `Output('gapnext-report.pdf', 'D')`.

## Phase 7: Testing & Hardening
1. Install the plugin on a fresh, local WordPress instance.
2. Run the Node.js extraction script to populate `includes/data/`.
3. Test Audit link creation with all 8 standards.
4. Complete End-to-End submissions for multiple standards, actively uploading test files to ensure `wp_handle_upload()` handles permissions cleanly.
5. Generate and review CSV and PDF outputs for edge cases (e.g., extremely long notes text flowing across PDF pages).
6. Verify access controls by attempting to load a `login_required` audit form in an incognito window.
