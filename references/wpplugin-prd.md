# Product Requirements Document: GapNext WP Plugin

## 1. Product Overview
**Name:** GapNext WP
**Description:** A robust, multi-standard WordPress plugin designed to facilitate gap analysis audits directly from a WordPress website. It allows administrators to generate unique, shareable audit links for specific standards (e.g., EN9100, ISO9001), collect structured responses including evidence uploads, and export the results as CSV or automatically generated PDF reports.
**Primary User Persona:** Quality Consultants and Lead Auditors leveraging their WordPress site as a lead-generation and initial-assessment tool.
**End User Persona:** Company representatives completing the gap analysis checklist.

## 2. Goals & Objectives
* Provide a frictionless, responsive frontend experience for completing complex multi-part compliance checklists.
* Enable dynamic, data-driven checklist rendering so new standards can be added easily without altering core plugin logic.
* Ensure secure and organized collection of both structured data (answers/notes) and unstructured data (evidence files).
* Generate professional, branded PDF reports automatically upon completion.
* Provide a centralized WP Admin dashboard to manage links, track submissions, and export data.

## 3. Scope & Features

### 3.1. Standard Management Architecture
* **Data-driven Design:** Checklists are not hardcoded. The plugin scans an `includes/data/` directory for PHP arrays representing different standards.
* **Supported Standards (v1):** EN 9100:2018, ISO 9001:2015, ISO 13485:2016, ISO 27001:2022, ISO 45001:2018, ISO 42001:2023, ISO 22163:2023, IATF 16949:2016.
* **Data Extraction Pipeline:** The source of truth for standards are the original original PDF/DOCX files. A Node.js build script (`extract-checklists.js`) must be used to parse these documents into the static PHP arrays required by the plugin.

### 3.2. Audit Link Management
* **Unique Links:** Admins can generate a unique Audit Session (UUID).
* **Configuration:** When creating a link, the admin selects the Title, the Standard, and the Access Mode.
* **Access Control:** Global/Per-link toggle between `Public` (accessible to anyone with the link) and `Login Required` (forces WP authentication).

### 3.3. Frontend Audit Experience
The form is rendered via a shortcode (`[gapnext_checklist]`) which reads the `?audit={uuid}` parameter. It is a multi-step progressive form:

* **Step 1: Company Profile & Contacts**
  * Company Details: Name (Required), Address, VAT/Reg Number, Industry Sector.
  * Internal Contact: Name (Required), Role, Email (Required), Phone.
  * Consultant Details: Name (Required), Company, Email (Required), Phone.
* **Step 2: Checklist Execution**
  * Top-level clauses (e.g., 4, 5, 6) act as accordion sections.
  * Sub-clauses are indented.
  * Questions display Reference, Title, Description, and Help Text.
  * **Answer Toggle:** `Conforme` (Green), `Parzialmente` (Amber), `Non-Conforme` (Red).
  * **Notes:** Expandable text area per question.
  * **Evidence Uploads:** Multi-file input field per question. Accepts `.pdf, .doc, .docx, .xls, .xlsx, .txt, .jpg, .jpeg, .png`. Maximum file size enforced per WP settings.
* **Step 3: Submission & Review**
  * Live-updating completion progress bar.
  * AJAX-driven submission.
  * Success screen displaying the final computed "Percentage Conforme" score.

### 3.4. Backend Storage & Processing
* **Database:** Two custom tables: `{prefix}gapnext_audits` and `{prefix}gapnext_submissions`.
* **File Storage:** Uploaded evidence is routed through `wp_handle_upload()` and stored securely in `wp-content/uploads/gapnext-evidence/{audit_uuid}/q-{clause_id}/`.
* **Scoring Logic:** Server-side calculation where Conforme = 1.0, Parzialmente = 0.5, Non-Conforme = 0.0.

### 3.5. Admin Dashboard & Exports
* **Admin Menu:** "GapNext WP" with subpages for Audit Links, Submissions, and Settings.
* **Submissions Table:** View all completed audits, filter by standard/company.
* **CSV Export:** Bulk or single export. Columns include Standard Name, Company info, Clause References, Answers, and Evidence filenames.
* **PDF Export:** Bundled `TCPDF` library generates a standalone branded report. Includes a cover page (Standard Name, Overall Score, Company/Consultant details) and a color-coded table of all answers and notes.

## 4. Technical Requirements & Constraints
* **Platform:** WordPress 6.0+
* **PHP:** 7.4+ (8.x recommended).
* **Dependencies:** TCPDF must be bundled locally within the plugin (`vendor/tcpdf/`) to avoid Composer dependency clashes on shared hosting. No other external PHP libraries should be used.
* **Security:** Strict nonce checking on all AJAX calls. Sanitization (`sanitize_text_field`, `sanitize_textarea_field`) on all inputs. Capabilities check (`manage_options`) for all admin pages and export downloads. Careful validation of file mimes during evidence upload.

## 5. Future Roadmap (Post-MVP)
* Customizable scoring weights per question.
* Logic branching (hide/show questions based on previous answers).
* Automated email delivery of the PDF report to the respondent.
* Multi-language support (WPML/Polylang integration).
