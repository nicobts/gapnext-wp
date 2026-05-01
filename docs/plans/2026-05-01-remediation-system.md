# Remediation System — Implementation Plan (Phase 1 + 2)

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add plugin hardening (uninstall, auth, client role) and an event-sourced remediation log engine that lets consultants create corrective action plans and track per-question remediation progress from the WP admin.

**Architecture:** Event-sourced `gapnext_remediation_log` table stores every change as an immutable event. Current state is derived by replaying events per question. The initial gap analysis submission is never modified. A new `gapnext_client` WP role and `gapnext_client_access` table control which users can view which audits. Phase 3 (client frontend dashboard) will be a separate plan.

**Tech Stack:** WordPress 6.0+, PHP 7.4+, jQuery (admin), vanilla JS where possible, `$wpdb` for DB access, WordPress AJAX API.

**Reference docs:**
- `references/architecture-scaling-notes.md` — scaling decisions and data model rationale
- `memory/MEMORY.md` — project overview and design decisions

---

## File Structure

### New Files

| File | Responsibility |
|------|---------------|
| `uninstall.php` | Clean up all plugin data (tables, options) on uninstall |
| `includes/class-client-role.php` | Register/remove `gapnext_client` WP role, login redirect |
| `includes/class-remediation-log.php` | Write immutable events to `gapnext_remediation_log` |
| `includes/class-remediation-state.php` | Derive current state by replaying events from the log |
| `includes/class-remediation-ajax.php` | AJAX endpoints for remediation actions (admin-side) |
| `includes/views/admin-remediation.php` | Admin UI: remediation plan tab within submission detail |
| `includes/views/admin-client-access.php` | Admin UI: client user assignment panel |
| `assets/gapnext-admin-remediation.js` | JS for remediation admin interactions |
| `assets/gapnext-admin-remediation.css` | Styles for remediation admin UI |

### Modified Files

| File | Changes |
|------|---------|
| `gapnext-wp.php` | Version bump 1.1.0 → 1.2.0, boot new classes |
| `includes/class-installer.php` | Add new DB tables, add columns to `gapnext_audits` |
| `includes/class-ajax.php` | Fix download auth for `login_required` audits |
| `includes/class-audit-manager.php` | Add remediation tab + client access tab to submission detail view |
| `includes/class-admin.php` | Register remediation assets on admin pages |

---

## Chunk 1: Foundation & Hardening

### Task 1: Database Migration — New Tables and Columns

**Files:**
- Modify: `includes/class-installer.php`

- [ ] **Step 1: Add `gapnext_remediation_log` table to `create_tables()`**

Add after the existing `$sql_generations` block in `class-installer.php`:

```php
$sql_remediation_log = "CREATE TABLE {$wpdb->prefix}gapnext_remediation_log (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    submission_id BIGINT(20) UNSIGNED NOT NULL,
    question_ref VARCHAR(50) NOT NULL DEFAULT '',
    event_type VARCHAR(30) NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    user_role VARCHAR(20) NOT NULL DEFAULT '',
    data LONGTEXT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY submission_question (submission_id, question_ref, created_at),
    KEY submission_type (submission_id, event_type),
    KEY user_events (user_id, created_at)
) $charset;";

$sql_client_access = "CREATE TABLE {$wpdb->prefix}gapnext_client_access (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    audit_uuid VARCHAR(36) NOT NULL,
    granted_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
    granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY user_audit (user_id, audit_uuid),
    KEY audit_uuid (audit_uuid)
) $charset;";
```

Also add the `dbDelta` calls:

```php
dbDelta( $sql_remediation_log );
dbDelta( $sql_client_access );
```

- [ ] **Step 2: Add new columns to `gapnext_audits` table**

Modify the existing `$sql_audits` string — add these two columns before the `PRIMARY KEY` line:

```php
phase VARCHAR(20) NOT NULL DEFAULT 'gap_analysis',
client_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
```

Note: `dbDelta` handles `ALTER TABLE` for existing installs — adding columns to the CREATE TABLE string is sufficient.

- [ ] **Step 3: Add `remediation_score` column to `gapnext_submissions` table**

Add this column to the existing `$sql_submissions` string, after the `ai_report_uuid` line:

```php
remediation_score FLOAT NULL DEFAULT NULL,
```

- [ ] **Step 4: Verify migration works**

Run: Deactivate and reactivate the plugin in WP Admin → Plugins.
Expected: No errors. Check phpMyAdmin/Adminer to confirm:
- `gapnext_remediation_log` table exists with correct columns and indexes
- `gapnext_client_access` table exists with correct columns and indexes
- `gapnext_audits` has `phase` and `client_user_id` columns
- `gapnext_submissions` has `remediation_score` column

- [ ] **Step 5: Commit**

```bash
git add includes/class-installer.php
git commit -m "feat: add remediation_log, client_access tables and new columns for remediation phase"
```

---

### Task 2: Uninstall Hook

**Files:**
- Create: `uninstall.php`

- [ ] **Step 1: Create `uninstall.php`**

```php
<?php
/**
 * GapNext WP — Uninstall
 *
 * Fired when the plugin is deleted via WP Admin → Plugins → Delete.
 * Removes all custom database tables and plugin options.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Drop custom tables
$tables = [
    $wpdb->prefix . 'gapnext_remediation_log',
    $wpdb->prefix . 'gapnext_client_access',
    $wpdb->prefix . 'gapnext_ai_report_generations',
    $wpdb->prefix . 'gapnext_submissions',
    $wpdb->prefix . 'gapnext_audits',
];

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Delete plugin options
$options = [
    'gapnext_wp_db_version',
    'gapnext_default_language',
    'gapnext_default_access_mode',
    'gapnext_consultant_logo_id',
    'gapnext_checklist_page_id',
    'gapnext_results_page_id',
    'gapnext_notification_email',
    'gapnext_notify_consultant',
    'gapnext_pdf_footer_text',
    'gapnext_ai_api_url',
    'gapnext_ai_api_key',
    'gapnext_ai_branding_primary_color',
    'gapnext_ai_branding_secondary_color',
    'gapnext_ai_branding_header_title',
    'gapnext_ai_branding_footer_text',
    'gapnext_ai_branding_prepared_by',
    'gapnext_ai_branding_company_website',
    'gapnext_ai_branding_company_phone',
];

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove gapnext_client role
remove_role( 'gapnext_client' );

// Clean up transients (connection status per user)
$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_gapnext_%' OR option_name LIKE '_transient_timeout_gapnext_%'"
);
```

- [ ] **Step 2: Verify uninstall does not fire on deactivation**

Run: Deactivate the plugin in WP Admin → Plugins.
Expected: All tables and options still exist. `uninstall.php` only runs on Delete.

- [ ] **Step 3: Commit**

```bash
git add uninstall.php
git commit -m "feat: add uninstall.php to clean up tables, options, and roles on plugin delete"
```

---

### Task 3: Client Role

**Files:**
- Create: `includes/class-client-role.php`
- Modify: `gapnext-wp.php` (boot the class)
- Modify: `includes/class-installer.php` (register role on activation)

- [ ] **Step 1: Create `class-client-role.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class GapNext_Client_Role {

    public function __construct() {
        add_filter( 'login_redirect', [ $this, 'redirect_client_after_login' ], 10, 3 );
        add_filter( 'show_admin_bar', [ $this, 'hide_admin_bar_for_clients' ] );
        add_action( 'admin_init', [ $this, 'block_admin_for_clients' ] );
    }

    /**
     * Register the gapnext_client role. Safe to call multiple times —
     * WordPress ignores add_role() if the role already exists.
     */
    public static function register_role() {
        add_role( 'gapnext_client', __( 'GapNext Client', 'gapnext-wp' ), [
            'read' => true,
        ] );
    }

    /**
     * After login, redirect gapnext_client users to the dashboard page
     * instead of wp-admin.
     */
    public function redirect_client_after_login( $redirect_to, $requested_redirect_to, $user ) {
        if ( ! is_wp_error( $user ) && in_array( 'gapnext_client', (array) $user->roles, true ) ) {
            $dashboard_pid = (int) get_option( 'gapnext_dashboard_page_id', 0 );
            if ( $dashboard_pid ) {
                return get_permalink( $dashboard_pid );
            }
            return home_url();
        }
        return $redirect_to;
    }

    /**
     * Hide the WP admin bar for gapnext_client users.
     */
    public function hide_admin_bar_for_clients( $show ) {
        if ( is_user_logged_in() && current_user_can( 'gapnext_client' ) && ! current_user_can( 'manage_options' ) ) {
            return false;
        }
        return $show;
    }

    /**
     * Block wp-admin access for gapnext_client users (redirect to home).
     * AJAX requests are still allowed.
     */
    public function block_admin_for_clients() {
        if ( wp_doing_ajax() ) return;
        if ( current_user_can( 'gapnext_client' ) && ! current_user_can( 'manage_options' ) ) {
            $dashboard_pid = (int) get_option( 'gapnext_dashboard_page_id', 0 );
            wp_safe_redirect( $dashboard_pid ? get_permalink( $dashboard_pid ) : home_url() );
            exit;
        }
    }

    /**
     * Create a new WP user with gapnext_client role and grant access to an audit.
     *
     * @param string $email    Client email
     * @param string $name     Display name
     * @param string $audit_uuid  Audit to grant access to
     * @return int|WP_Error    User ID on success, WP_Error on failure
     */
    public static function create_client_user( string $email, string $name, string $audit_uuid ) {
        $email = sanitize_email( $email );
        if ( ! is_email( $email ) ) {
            return new \WP_Error( 'invalid_email', __( 'Invalid email address.', 'gapnext-wp' ) );
        }

        $existing = get_user_by( 'email', $email );
        if ( $existing ) {
            // User exists — just grant access
            self::grant_access( $existing->ID, $audit_uuid );
            return $existing->ID;
        }

        $password = wp_generate_password( 16, true, true );
        $username = sanitize_user( strtok( $email, '@' ), true );

        // Ensure unique username
        $base = $username;
        $i = 1;
        while ( username_exists( $username ) ) {
            $username = $base . $i;
            $i++;
        }

        $user_id = wp_insert_user( [
            'user_login'   => $username,
            'user_email'   => $email,
            'user_pass'    => $password,
            'display_name' => $name,
            'role'         => 'gapnext_client',
        ] );

        if ( is_wp_error( $user_id ) ) {
            return $user_id;
        }

        self::grant_access( $user_id, $audit_uuid );

        return $user_id;
    }

    /**
     * Grant a user access to a specific audit.
     */
    public static function grant_access( int $user_id, string $audit_uuid ) {
        global $wpdb;
        $wpdb->replace(
            $wpdb->prefix . 'gapnext_client_access',
            [
                'user_id'    => $user_id,
                'audit_uuid' => $audit_uuid,
                'granted_by' => get_current_user_id(),
                'granted_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%d', '%s' ]
        );
    }

    /**
     * Revoke a user's access to a specific audit.
     */
    public static function revoke_access( int $user_id, string $audit_uuid ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'gapnext_client_access',
            [ 'user_id' => $user_id, 'audit_uuid' => $audit_uuid ],
            [ '%d', '%s' ]
        );
    }

    /**
     * Get all audit UUIDs a user has access to.
     *
     * @return string[]
     */
    public static function get_user_audits( int $user_id ): array {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT audit_uuid FROM {$wpdb->prefix}gapnext_client_access WHERE user_id = %d",
            $user_id
        ) );
    }

    /**
     * Get all users who have access to a specific audit.
     *
     * @return array[] Each element: ['user_id' => int, 'email' => string, 'display_name' => string]
     */
    public static function get_audit_clients( string $audit_uuid ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT ca.user_id, u.user_email, u.display_name
             FROM {$wpdb->prefix}gapnext_client_access ca
             JOIN {$wpdb->users} u ON u.ID = ca.user_id
             WHERE ca.audit_uuid = %s
             ORDER BY u.display_name",
            $audit_uuid
        ), ARRAY_A );
        return $rows ?: [];
    }
}
```

- [ ] **Step 2: Register role in `class-installer.php` `activate()` method**

Add `GapNext_Client_Role::register_role();` as the first line inside `activate()`:

```php
public static function activate() {
    GapNext_Client_Role::register_role();
    self::create_tables();
    self::set_defaults();
    flush_rewrite_rules();
}
```

- [ ] **Step 3: Boot the class in `gapnext-wp.php`**

Add inside the `plugins_loaded` callback, after `new GapNext_Audit_Manager();`:

```php
new GapNext_Client_Role();
```

- [ ] **Step 4: Verify role registration**

Run: Deactivate and reactivate the plugin.
Expected: In WP Admin → Users → Add New, "GapNext Client" appears in the Role dropdown. Creating a user with that role and logging in redirects to the home page (not wp-admin).

- [ ] **Step 5: Commit**

```bash
git add includes/class-client-role.php includes/class-installer.php gapnext-wp.php
git commit -m "feat: add gapnext_client WP role with login redirect and admin blocking"
```

---

### Task 4: Download Auth Fix

**Files:**
- Modify: `includes/class-ajax.php` (the `handle_download` method, lines ~301-339)

- [ ] **Step 1: Add access check to `handle_download()`**

Replace the current `handle_download()` method body with auth-aware logic. After looking up the submission and validating it, check if the audit has `access_mode = 'login_required'`:

Find the method starting at line 301 and add this block after `$sub = GapNext_Audit_Manager::get_submission( $sub_id );` and the not-found check:

```php
// Enforce access control for login_required audits
$audit = GapNext_Audit_Manager::get_audit_by_uuid( $uuid );
if ( $audit && $audit->access_mode === 'login_required' ) {
    if ( ! is_user_logged_in() ) {
        wp_die( esc_html__( 'Login required.', 'gapnext-wp' ), '', [ 'response' => 403 ] );
    }
    // Allow admins and the assigned client user
    if ( ! current_user_can( 'manage_options' ) ) {
        $user_audits = GapNext_Client_Role::get_user_audits( get_current_user_id() );
        if ( ! in_array( $uuid, $user_audits, true ) ) {
            wp_die( esc_html__( 'Access denied.', 'gapnext-wp' ), '', [ 'response' => 403 ] );
        }
    }
}
```

- [ ] **Step 2: Verify public audits still work without login**

Run: Open an incognito browser. Navigate to a download URL for a public audit.
Expected: Download starts without login prompt.

- [ ] **Step 3: Verify login_required audits block anonymous downloads**

Run: Open an incognito browser. Navigate to a download URL for a `login_required` audit.
Expected: "Login required." error with 403 status.

- [ ] **Step 4: Commit**

```bash
git add includes/class-ajax.php
git commit -m "fix: enforce auth on download handler for login_required audits"
```

---

### Task 5: Version Bump

**Files:**
- Modify: `gapnext-wp.php`

- [ ] **Step 1: Update version constants and header**

In `gapnext-wp.php`, change:
- Line 6: `* Version:     1.1.0` → `* Version:     1.2.0`
- Line 18: `define( 'GAPNEXT_WP_VERSION', '1.1.0' );` → `define( 'GAPNEXT_WP_VERSION', '1.2.0' );`

- [ ] **Step 2: Verify auto-migration triggers**

Run: Load any WP admin page.
Expected: The `plugins_loaded` callback detects version mismatch and runs `GapNext_Installer::activate()` automatically, applying any pending DB changes.

- [ ] **Step 3: Commit**

```bash
git add gapnext-wp.php
git commit -m "chore: bump version to 1.2.0"
```

---

## Chunk 2: Remediation Log Engine

### Task 6: Remediation Log — Event Writer

**Files:**
- Create: `includes/class-remediation-log.php`

- [ ] **Step 1: Create `class-remediation-log.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Writes immutable events to the gapnext_remediation_log table.
 *
 * Each event represents a single atomic change to a remediation item.
 * Events are never updated or deleted — current state is derived
 * by replaying events in chronological order.
 *
 * Event types:
 *   action_set       — corrective action text defined
 *   action_updated   — corrective action text modified
 *   priority_set     — priority assigned or changed
 *   deadline_set     — deadline assigned or changed
 *   responsible_set  — responsible person assigned or changed
 *   status_changed   — remediation status transition
 *   answer_changed   — client proposes a new answer value
 *   answer_approved  — consultant approves proposed answer
 *   answer_rejected  — consultant rejects proposed answer
 *   evidence_added   — file uploaded as remediation evidence
 *   comment          — free-text note/discussion
 *   verified         — consultant marks item as verified/closed
 */
class GapNext_Remediation_Log {

    /**
     * Valid event types.
     */
    private const EVENT_TYPES = [
        'action_set',
        'action_updated',
        'priority_set',
        'deadline_set',
        'responsible_set',
        'status_changed',
        'answer_changed',
        'answer_approved',
        'answer_rejected',
        'evidence_added',
        'comment',
        'verified',
    ];

    /**
     * Valid remediation statuses.
     */
    public const STATUSES = [
        'open',
        'in_progress',
        'pending_review',
        'verified',
        'rejected',
    ];

    /**
     * Valid priorities.
     */
    public const PRIORITIES = [ 'high', 'medium', 'low' ];

    /**
     * Insert a single event into the log.
     *
     * @param int    $submission_id
     * @param string $question_ref   Question reference (e.g. "4_1"), or '' for submission-level events
     * @param string $event_type     One of self::EVENT_TYPES
     * @param array  $data           Event-specific payload (will be JSON-encoded)
     * @param int    $user_id        WP user ID (0 = system)
     * @param string $user_role      'consultant' or 'client'
     * @return int|false             Inserted row ID or false on failure
     */
    public static function insert(
        int $submission_id,
        string $question_ref,
        string $event_type,
        array $data,
        int $user_id = 0,
        string $user_role = ''
    ) {
        if ( ! in_array( $event_type, self::EVENT_TYPES, true ) ) {
            return false;
        }

        if ( $user_id === 0 ) {
            $user_id = get_current_user_id();
        }
        if ( $user_role === '' ) {
            $user_role = current_user_can( 'manage_options' ) ? 'consultant' : 'client';
        }

        global $wpdb;
        $result = $wpdb->insert(
            $wpdb->prefix . 'gapnext_remediation_log',
            [
                'submission_id' => $submission_id,
                'question_ref'  => $question_ref,
                'event_type'    => $event_type,
                'user_id'       => $user_id,
                'user_role'     => $user_role,
                'data'          => wp_json_encode( $data ),
                'created_at'    => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Get all events for a submission, ordered chronologically.
     *
     * @param int         $submission_id
     * @param string|null $question_ref  Filter to a specific question (null = all)
     * @return array      Array of event objects with decoded 'data' field
     */
    public static function get_events( int $submission_id, ?string $question_ref = null ): array {
        global $wpdb;

        if ( $question_ref !== null ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gapnext_remediation_log
                 WHERE submission_id = %d AND question_ref = %s
                 ORDER BY created_at ASC, id ASC",
                $submission_id,
                $question_ref
            ) );
        } else {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}gapnext_remediation_log
                 WHERE submission_id = %d
                 ORDER BY created_at ASC, id ASC",
                $submission_id
            ) );
        }

        // Decode JSON data field
        foreach ( $rows as $row ) {
            $row->data = json_decode( $row->data, true ) ?: [];
        }

        return $rows ?: [];
    }

    /**
     * Get recent events across all submissions (for admin dashboard).
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent( int $limit = 20 ): array {
        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, s.company_name, s.standard_id
             FROM {$wpdb->prefix}gapnext_remediation_log l
             JOIN {$wpdb->prefix}gapnext_submissions s ON s.id = l.submission_id
             ORDER BY l.created_at DESC
             LIMIT %d",
            $limit
        ) );

        foreach ( $rows as $row ) {
            $row->data = json_decode( $row->data, true ) ?: [];
        }

        return $rows ?: [];
    }

    /**
     * Initialize remediation for a submission — creates 'action_set' events
     * for all questions that currently have a non-conforme or partial answer,
     * and sets their status to 'open'.
     *
     * Also creates placeholder entries for conforme/na/unanswered questions
     * with status 'open' (can be edited later, per design requirement).
     *
     * @param int   $submission_id
     * @param array $answers  Decoded JSON from submission (ref => {value, note})
     * @param array $clauses  Standard clause data array
     * @return int  Number of questions initialized
     */
    public static function initialize_remediation( int $submission_id, array $answers, array $clauses ): int {
        $count = 0;
        $user_id = get_current_user_id();

        foreach ( $clauses as $clause ) {
            if ( (int) ( $clause['level'] ?? 2 ) === 1 ) continue;

            $ref = $clause['reference'];
            $answer_row = $answers[ $ref ] ?? [];
            $value = is_array( $answer_row ) ? ( $answer_row['value'] ?? null ) : $answer_row;

            self::insert( $submission_id, $ref, 'status_changed', [
                'old_status' => '',
                'new_status' => 'open',
                'original_answer' => $value,
            ], $user_id, 'consultant' );

            $count++;
        }

        return $count;
    }

    /**
     * Check if remediation has been initialized for a submission.
     */
    public static function is_initialized( int $submission_id ): bool {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}gapnext_remediation_log
             WHERE submission_id = %d AND event_type = 'status_changed'
             LIMIT 1",
            $submission_id
        ) );
    }
}
```

- [ ] **Step 2: Verify class autoloads**

The existing SPL autoloader in `gapnext-wp.php` maps `GapNext_Remediation_Log` → `class-remediation-log.php`. No changes needed.

Run: Add `GapNext_Remediation_Log::STATUSES;` to a temporary test in any loaded file. No errors = autoload works. Remove the test line after.

- [ ] **Step 3: Commit**

```bash
git add includes/class-remediation-log.php
git commit -m "feat: add GapNext_Remediation_Log — event-sourced immutable event writer"
```

---

### Task 7: Remediation State — Deriving Current State

**Files:**
- Create: `includes/class-remediation-state.php`

- [ ] **Step 1: Create `class-remediation-state.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Derives current remediation state by replaying events from GapNext_Remediation_Log.
 *
 * This is a read-only class — it never writes to the database.
 */
class GapNext_Remediation_State {

    /**
     * Get the current state of a single question.
     *
     * Replays all events for this question and returns the derived state.
     *
     * @param int    $submission_id
     * @param string $question_ref
     * @return array {
     *     original_answer: float|string|null,
     *     current_answer: float|string|null,
     *     proposed_answer: float|string|null,
     *     status: string,
     *     corrective_action: string,
     *     priority: string,
     *     deadline: string|null,
     *     responsible: string,
     *     evidence: string[],
     *     pending_approval: bool,
     *     events: object[],
     * }
     */
    public static function for_question( int $submission_id, string $question_ref ): array {
        $events = GapNext_Remediation_Log::get_events( $submission_id, $question_ref );
        return self::replay( $events );
    }

    /**
     * Get the current state of ALL questions in a submission.
     *
     * @param int $submission_id
     * @return array<string, array>  Keyed by question_ref
     */
    public static function for_submission( int $submission_id ): array {
        $events = GapNext_Remediation_Log::get_events( $submission_id );

        // Group events by question_ref
        $grouped = [];
        foreach ( $events as $event ) {
            $grouped[ $event->question_ref ][] = $event;
        }

        $states = [];
        foreach ( $grouped as $ref => $ref_events ) {
            $states[ $ref ] = self::replay( $ref_events );
        }

        return $states;
    }

    /**
     * Aggregate stats across all questions in a submission.
     *
     * @param int        $submission_id
     * @param array|null $states  Pre-computed states (optional, avoids double query)
     * @return array {
     *     total: int,
     *     open: int,
     *     in_progress: int,
     *     pending_review: int,
     *     verified: int,
     *     rejected: int,
     *     unchanged: int,
     *     current_score: float,
     *     overdue: int,
     * }
     */
    public static function aggregate( int $submission_id, ?array $states = null ): array {
        if ( $states === null ) {
            $states = self::for_submission( $submission_id );
        }

        $agg = [
            'total'          => count( $states ),
            'open'           => 0,
            'in_progress'    => 0,
            'pending_review' => 0,
            'verified'       => 0,
            'rejected'       => 0,
            'unchanged'      => 0,
            'overdue'        => 0,
        ];

        $compliant_count = 0;
        $applicable_count = 0;
        $today = current_time( 'Y-m-d' );

        foreach ( $states as $state ) {
            $status = $state['status'];
            if ( isset( $agg[ $status ] ) ) {
                $agg[ $status ]++;
            }

            // Count as unchanged if no corrective action and original answer was compliant/na
            $orig = $state['original_answer'];
            if ( $state['corrective_action'] === '' && ( $orig === 1.0 || $orig === 1 || $orig === 'na' ) ) {
                $agg['unchanged']++;
            }

            // Overdue: has a deadline, deadline has passed, not verified
            if ( $state['deadline'] && $state['deadline'] < $today && $status !== 'verified' ) {
                $agg['overdue']++;
            }

            // Current score calculation
            $current = $state['current_answer'] ?? $state['original_answer'];
            if ( $current !== 'na' && $current !== null ) {
                $applicable_count++;
                if ( $current === 1.0 || $current === 1 ) {
                    $compliant_count++;
                }
            }
        }

        $agg['current_score'] = $applicable_count > 0
            ? round( $compliant_count / $applicable_count, 4 )
            : 0.0;

        return $agg;
    }

    /**
     * Replay a set of events (for one question) and derive current state.
     */
    private static function replay( array $events ): array {
        $state = [
            'original_answer'   => null,
            'current_answer'    => null,
            'proposed_answer'   => null,
            'status'            => 'open',
            'corrective_action' => '',
            'priority'          => 'medium',
            'deadline'          => null,
            'responsible'       => '',
            'evidence'          => [],
            'pending_approval'  => false,
            'events'            => $events,
        ];

        foreach ( $events as $event ) {
            $d = $event->data;

            switch ( $event->event_type ) {
                case 'status_changed':
                    $state['status'] = $d['new_status'] ?? $state['status'];
                    if ( isset( $d['original_answer'] ) && $state['original_answer'] === null ) {
                        $state['original_answer'] = $d['original_answer'];
                    }
                    $state['pending_approval'] = ( $state['status'] === 'pending_review' );
                    break;

                case 'action_set':
                case 'action_updated':
                    $state['corrective_action'] = $d['action'] ?? $state['corrective_action'];
                    break;

                case 'priority_set':
                    $state['priority'] = $d['priority'] ?? $state['priority'];
                    break;

                case 'deadline_set':
                    $state['deadline'] = $d['deadline'] ?? $state['deadline'];
                    break;

                case 'responsible_set':
                    $state['responsible'] = $d['responsible'] ?? $state['responsible'];
                    break;

                case 'answer_changed':
                    $state['proposed_answer'] = $d['new_value'] ?? null;
                    $state['pending_approval'] = true;
                    break;

                case 'answer_approved':
                    $state['current_answer'] = $d['approved_value'] ?? $state['proposed_answer'];
                    $state['proposed_answer'] = null;
                    $state['pending_approval'] = false;
                    if ( isset( $d['new_status'] ) ) {
                        $state['status'] = $d['new_status'];
                    }
                    break;

                case 'answer_rejected':
                    $state['proposed_answer'] = null;
                    $state['pending_approval'] = false;
                    if ( isset( $d['new_status'] ) ) {
                        $state['status'] = $d['new_status'];
                    }
                    break;

                case 'evidence_added':
                    if ( isset( $d['file_path'] ) ) {
                        $state['evidence'][] = $d['file_path'];
                    }
                    break;

                case 'verified':
                    $state['status'] = 'verified';
                    $state['pending_approval'] = false;
                    if ( isset( $d['verified_answer'] ) ) {
                        $state['current_answer'] = $d['verified_answer'];
                    }
                    break;

                case 'comment':
                    // Comments don't change state — they're in the event timeline
                    break;
            }
        }

        // If current_answer was never set, it equals original
        if ( $state['current_answer'] === null ) {
            $state['current_answer'] = $state['original_answer'];
        }

        return $state;
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add includes/class-remediation-state.php
git commit -m "feat: add GapNext_Remediation_State — derives current state from event log"
```

---

### Task 8: Remediation AJAX Endpoints

**Files:**
- Create: `includes/class-remediation-ajax.php`
- Modify: `gapnext-wp.php` (boot the class)

- [ ] **Step 1: Create `class-remediation-ajax.php`**

```php
<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * AJAX handlers for remediation actions (admin/consultant side).
 *
 * All handlers require manage_options capability.
 * Client-side AJAX handlers will be added in Phase 3.
 */
class GapNext_Remediation_Ajax {

    public function __construct() {
        // Admin-side (consultant) actions
        add_action( 'wp_ajax_gapnext_remediation_init',       [ $this, 'handle_init' ] );
        add_action( 'wp_ajax_gapnext_remediation_update',     [ $this, 'handle_update' ] );
        add_action( 'wp_ajax_gapnext_remediation_bulk',       [ $this, 'handle_bulk' ] );
        add_action( 'wp_ajax_gapnext_remediation_review',     [ $this, 'handle_review' ] );
        add_action( 'wp_ajax_gapnext_remediation_comment',    [ $this, 'handle_comment' ] );
        add_action( 'wp_ajax_gapnext_create_client',          [ $this, 'handle_create_client' ] );
    }

    /**
     * Initialize remediation plan for a submission.
     * Sets audit phase to 'remediation'.
     */
    public function handle_init() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id = (int) ( $_POST['submission_id'] ?? 0 );
        if ( ! $sub_id ) wp_send_json_error( __( 'Invalid submission.', 'gapnext-wp' ) );

        if ( GapNext_Remediation_Log::is_initialized( $sub_id ) ) {
            wp_send_json_error( __( 'Remediation already initialized.', 'gapnext-wp' ) );
        }

        $sub = GapNext_Audit_Manager::get_submission( $sub_id );
        if ( ! $sub ) wp_send_json_error( __( 'Submission not found.', 'gapnext-wp' ) );

        $standard = GapNext_Standard_Registry::get_standard_data( $sub->standard_id, $sub->language );
        if ( ! $standard ) wp_send_json_error( __( 'Standard not found.', 'gapnext-wp' ) );

        $answers = json_decode( $sub->answers, true ) ?: [];
        $count = GapNext_Remediation_Log::initialize_remediation( $sub_id, $answers, $standard['clauses'] );

        // Update audit phase
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'gapnext_audits',
            [ 'phase' => 'remediation' ],
            [ 'uuid' => $sub->audit_uuid ],
            [ '%s' ],
            [ '%s' ]
        );

        wp_send_json_success( [
            'message' => sprintf(
                /* translators: %d: number of questions */
                __( 'Remediation initialized for %d questions.', 'gapnext-wp' ),
                $count
            ),
            'count' => $count,
        ] );
    }

    /**
     * Update a single remediation item (action, priority, deadline, responsible, status).
     */
    public function handle_update() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id = (int) ( $_POST['submission_id'] ?? 0 );
        $ref    = sanitize_text_field( $_POST['question_ref'] ?? '' );
        $field  = sanitize_key( $_POST['field'] ?? '' );
        $value  = wp_unslash( $_POST['value'] ?? '' );

        if ( ! $sub_id || ! $ref || ! $field ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        switch ( $field ) {
            case 'action':
                $current = GapNext_Remediation_State::for_question( $sub_id, $ref );
                $type = $current['corrective_action'] === '' ? 'action_set' : 'action_updated';
                GapNext_Remediation_Log::insert( $sub_id, $ref, $type, [
                    'action' => sanitize_textarea_field( $value ),
                ] );
                break;

            case 'priority':
                $value = sanitize_key( $value );
                if ( ! in_array( $value, GapNext_Remediation_Log::PRIORITIES, true ) ) {
                    wp_send_json_error( __( 'Invalid priority.', 'gapnext-wp' ) );
                }
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'priority_set', [
                    'priority' => $value,
                ] );
                break;

            case 'deadline':
                $value = sanitize_text_field( $value );
                if ( $value && ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
                    wp_send_json_error( __( 'Invalid date format.', 'gapnext-wp' ) );
                }
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'deadline_set', [
                    'deadline' => $value ?: null,
                ] );
                break;

            case 'responsible':
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'responsible_set', [
                    'responsible' => sanitize_text_field( $value ),
                ] );
                break;

            case 'status':
                $value = sanitize_key( $value );
                if ( ! in_array( $value, GapNext_Remediation_Log::STATUSES, true ) ) {
                    wp_send_json_error( __( 'Invalid status.', 'gapnext-wp' ) );
                }
                $current = GapNext_Remediation_State::for_question( $sub_id, $ref );
                GapNext_Remediation_Log::insert( $sub_id, $ref, 'status_changed', [
                    'old_status' => $current['status'],
                    'new_status' => $value,
                ] );
                break;

            default:
                wp_send_json_error( __( 'Unknown field.', 'gapnext-wp' ) );
        }

        // Return updated state
        $state = GapNext_Remediation_State::for_question( $sub_id, $ref );
        unset( $state['events'] ); // don't send full event list in response
        wp_send_json_success( $state );
    }

    /**
     * Bulk update: set priority or deadline for multiple questions at once.
     */
    public function handle_bulk() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id = (int) ( $_POST['submission_id'] ?? 0 );
        $field  = sanitize_key( $_POST['field'] ?? '' );
        $value  = sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) );
        $refs   = isset( $_POST['refs'] ) && is_array( $_POST['refs'] ) ? $_POST['refs'] : [];

        if ( ! $sub_id || ! $field || empty( $refs ) ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        $updated = 0;
        foreach ( $refs as $ref ) {
            $ref = sanitize_text_field( $ref );
            if ( ! $ref ) continue;

            switch ( $field ) {
                case 'priority':
                    if ( in_array( $value, GapNext_Remediation_Log::PRIORITIES, true ) ) {
                        GapNext_Remediation_Log::insert( $sub_id, $ref, 'priority_set', [
                            'priority' => $value,
                        ] );
                        $updated++;
                    }
                    break;
                case 'deadline':
                    GapNext_Remediation_Log::insert( $sub_id, $ref, 'deadline_set', [
                        'deadline' => $value ?: null,
                    ] );
                    $updated++;
                    break;
            }
        }

        wp_send_json_success( [ 'updated' => $updated ] );
    }

    /**
     * Review: approve or reject a client's proposed answer change.
     */
    public function handle_review() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id   = (int) ( $_POST['submission_id'] ?? 0 );
        $ref      = sanitize_text_field( $_POST['question_ref'] ?? '' );
        $decision = sanitize_key( $_POST['decision'] ?? '' ); // 'approve' or 'reject'
        $feedback = sanitize_textarea_field( wp_unslash( $_POST['feedback'] ?? '' ) );

        if ( ! $sub_id || ! $ref || ! in_array( $decision, [ 'approve', 'reject' ], true ) ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        $current = GapNext_Remediation_State::for_question( $sub_id, $ref );

        if ( $decision === 'approve' ) {
            GapNext_Remediation_Log::insert( $sub_id, $ref, 'answer_approved', [
                'approved_value' => $current['proposed_answer'],
                'feedback'       => $feedback,
                'new_status'     => 'verified',
            ] );
        } else {
            GapNext_Remediation_Log::insert( $sub_id, $ref, 'answer_rejected', [
                'rejected_value' => $current['proposed_answer'],
                'feedback'       => $feedback,
                'new_status'     => 'in_progress',
            ] );
        }

        $state = GapNext_Remediation_State::for_question( $sub_id, $ref );
        unset( $state['events'] );
        wp_send_json_success( $state );
    }

    /**
     * Add a comment to a remediation item.
     */
    public function handle_comment() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $sub_id  = (int) ( $_POST['submission_id'] ?? 0 );
        $ref     = sanitize_text_field( $_POST['question_ref'] ?? '' );
        $comment = sanitize_textarea_field( wp_unslash( $_POST['comment'] ?? '' ) );

        if ( ! $sub_id || ! $ref || ! $comment ) {
            wp_send_json_error( __( 'Missing parameters.', 'gapnext-wp' ) );
        }

        GapNext_Remediation_Log::insert( $sub_id, $ref, 'comment', [
            'comment' => $comment,
        ] );

        wp_send_json_success( [ 'saved' => true ] );
    }

    /**
     * Create a WP client user and grant access to an audit.
     */
    public function handle_create_client() {
        check_ajax_referer( 'gapnext_remediation', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Unauthorized', 403 );

        $email      = sanitize_email( $_POST['email'] ?? '' );
        $name       = sanitize_text_field( $_POST['name'] ?? '' );
        $audit_uuid = sanitize_text_field( $_POST['audit_uuid'] ?? '' );

        if ( ! $email || ! $name || ! $audit_uuid ) {
            wp_send_json_error( __( 'Email, name, and audit are required.', 'gapnext-wp' ) );
        }

        $result = GapNext_Client_Role::create_client_user( $email, $name, $audit_uuid );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( $result->get_error_message() );
        }

        $user = get_user_by( 'ID', $result );
        wp_send_json_success( [
            'user_id'      => $result,
            'email'        => $user->user_email,
            'display_name' => $user->display_name,
        ] );
    }
}
```

- [ ] **Step 2: Boot the class in `gapnext-wp.php`**

Add inside the `plugins_loaded` callback, after `new GapNext_Client_Role();`:

```php
new GapNext_Remediation_Ajax();
```

- [ ] **Step 3: Commit**

```bash
git add includes/class-remediation-ajax.php gapnext-wp.php
git commit -m "feat: add remediation AJAX endpoints for init, update, review, comment, and client creation"
```

---

### Task 9: Admin UI — Remediation Tab in Submission Detail

**Files:**
- Create: `includes/views/admin-remediation.php`
- Create: `includes/views/admin-client-access.php`
- Create: `assets/gapnext-admin-remediation.css`
- Create: `assets/gapnext-admin-remediation.js`
- Modify: `includes/class-audit-manager.php` (add tabs to submission view)
- Modify: `includes/class-admin.php` (enqueue new assets)

This is the largest task. It adds a tabbed interface to the submission detail page with:
1. **Gap Analysis** tab (existing content, unchanged)
2. **Remediation Plan** tab (new — item list with inline editing)
3. **Client Access** tab (new — user assignment)

- [ ] **Step 1: Create `assets/gapnext-admin-remediation.css`**

```css
/* GapNext WP — Admin Remediation Styles */

.gapnext-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid #c3c4c7;
    margin: 16px 0 0;
}
.gapnext-tab {
    padding: 10px 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: #646970;
    border: 1px solid transparent;
    border-bottom: none;
    background: transparent;
    margin-bottom: -2px;
    border-radius: 4px 4px 0 0;
    transition: color .15s, background .15s;
}
.gapnext-tab:hover { color: #1d2327; background: #f6f7f7; }
.gapnext-tab.active {
    color: #1d2327;
    background: #fff;
    border-color: #c3c4c7;
    border-bottom-color: #fff;
}
.gapnext-tab-badge {
    display: inline-block;
    background: #d63638;
    color: #fff;
    font-size: 11px;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 10px;
    margin-left: 6px;
    min-width: 16px;
    text-align: center;
}
.gapnext-tab-panel { display: none; padding: 20px 0 0; }
.gapnext-tab-panel.active { display: block; }

/* Remediation item card */
.gnr-item {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 6px;
    padding: 16px 18px;
    margin-bottom: 12px;
    transition: border-color .15s;
}
.gnr-item:hover { border-color: #2271b1; }
.gnr-item-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
}
.gnr-item-ref {
    font-weight: 700;
    font-size: 13px;
    color: #1d2327;
    min-width: 50px;
}
.gnr-item-title {
    flex: 1;
    font-size: 13px;
    color: #3c434a;
}
.gnr-item-original {
    font-size: 12px;
    font-weight: 600;
    padding: 2px 8px;
    border-radius: 10px;
}
.gnr-item-original.conforme     { background: #d1fae5; color: #065f46; }
.gnr-item-original.parziale     { background: #fef3c7; color: #92400e; }
.gnr-item-original.non-conforme { background: #fee2e2; color: #991b1b; }
.gnr-item-original.na           { background: #e5e7eb; color: #6b7280; }
.gnr-item-original.unanswered   { background: #f3f4f6; color: #9ca3af; }

/* Status badge */
.gnr-status {
    font-size: 11px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.gnr-status-open           { background: #e5e7eb; color: #374151; }
.gnr-status-in_progress    { background: #dbeafe; color: #1e40af; }
.gnr-status-pending_review { background: #fef3c7; color: #92400e; }
.gnr-status-verified       { background: #d1fae5; color: #065f46; }
.gnr-status-rejected       { background: #fee2e2; color: #991b1b; }

/* Item fields grid */
.gnr-item-fields {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
    margin-top: 10px;
}
.gnr-item-field label {
    display: block;
    font-size: 11px;
    color: #646970;
    margin-bottom: 3px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
.gnr-item-field input,
.gnr-item-field select,
.gnr-item-field textarea {
    width: 100%;
    font-size: 13px;
    padding: 5px 8px;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    box-sizing: border-box;
}
.gnr-item-field textarea {
    resize: vertical;
    min-height: 60px;
}
.gnr-item-action-field {
    grid-column: 1 / -1;
}

/* Review pending indicator */
.gnr-pending-banner {
    background: #fffbeb;
    border: 1px solid #fcd34d;
    border-radius: 6px;
    padding: 10px 14px;
    margin-top: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
}
.gnr-pending-banner .gnr-btn-approve {
    background: #16a34a;
    color: #fff;
    border: none;
    padding: 4px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}
.gnr-pending-banner .gnr-btn-reject {
    background: #dc2626;
    color: #fff;
    border: none;
    padding: 4px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}

/* Event timeline within item */
.gnr-timeline {
    margin-top: 12px;
    border-top: 1px solid #f0f0f1;
    padding-top: 10px;
}
.gnr-timeline-toggle {
    font-size: 12px;
    color: #2271b1;
    cursor: pointer;
    background: none;
    border: none;
    padding: 0;
    font-weight: 600;
}
.gnr-timeline-list {
    margin-top: 8px;
    list-style: none;
    padding: 0;
}
.gnr-timeline-list li {
    font-size: 12px;
    color: #646970;
    padding: 3px 0 3px 18px;
    position: relative;
}
.gnr-timeline-list li::before {
    content: '';
    width: 6px;
    height: 6px;
    background: #c3c4c7;
    border-radius: 50%;
    position: absolute;
    left: 0;
    top: 8px;
}
.gnr-timeline-date { color: #9ca3af; margin-right: 6px; }
.gnr-timeline-actor { font-weight: 600; color: #3c434a; }

/* Init remediation CTA */
.gnr-init-panel {
    background: #f0f6fc;
    border: 1px solid #c3c4c7;
    border-left: 4px solid #2271b1;
    border-radius: 4px;
    padding: 20px 24px;
    margin: 16px 0;
    max-width: 700px;
}
.gnr-init-panel h3 { margin: 0 0 8px; font-size: 14px; }
.gnr-init-panel p { margin: 0 0 14px; font-size: 13px; color: #3c434a; }

/* Filter bar */
.gnr-filters {
    display: flex;
    gap: 8px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.gnr-filter-btn {
    padding: 4px 12px;
    border-radius: 14px;
    font-size: 12px;
    font-weight: 600;
    border: 1px solid #dcdcde;
    background: #fff;
    cursor: pointer;
    color: #3c434a;
    transition: all .15s;
}
.gnr-filter-btn:hover { border-color: #2271b1; color: #2271b1; }
.gnr-filter-btn.active { background: #2271b1; color: #fff; border-color: #2271b1; }

/* Aggregate stats bar */
.gnr-agg-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}
.gnr-agg-stat {
    background: #f6f7f7;
    border-radius: 6px;
    padding: 10px 14px;
    text-align: center;
    min-width: 80px;
    border-left: 3px solid;
}
.gnr-agg-stat-num {
    display: block;
    font-size: 20px;
    font-weight: 700;
    line-height: 1;
}
.gnr-agg-stat-label {
    display: block;
    font-size: 10px;
    color: #646970;
    margin-top: 3px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}
```

- [ ] **Step 2: Create `assets/gapnext-admin-remediation.js`**

```js
/* GapNext WP — Admin Remediation JS */
(function($) {
    'use strict';

    var nonce = window.gapnextRemediation ? gapnextRemediation.nonce : '';
    var ajaxUrl = window.gapnextRemediation ? gapnextRemediation.ajaxUrl : '';
    var subId = window.gapnextRemediation ? gapnextRemediation.submissionId : 0;
    var auditUuid = window.gapnextRemediation ? gapnextRemediation.auditUuid : '';

    // Tab switching
    $(document).on('click', '.gapnext-tab', function() {
        var target = $(this).data('tab');
        $('.gapnext-tab').removeClass('active');
        $(this).addClass('active');
        $('.gapnext-tab-panel').removeClass('active');
        $('#gapnext-panel-' + target).addClass('active');
    });

    // Initialize remediation
    $(document).on('click', '#gnr-init-btn', function() {
        var $btn = $(this);
        if (!confirm(gapnextRemediation.i18n.confirmInit)) return;
        $btn.prop('disabled', true).text(gapnextRemediation.i18n.initializing);

        $.post(ajaxUrl, {
            action: 'gapnext_remediation_init',
            nonce: nonce,
            submission_id: subId
        }).done(function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error');
                $btn.prop('disabled', false).text(gapnextRemediation.i18n.initBtn);
            }
        }).fail(function() {
            alert('Network error');
            $btn.prop('disabled', false).text(gapnextRemediation.i18n.initBtn);
        });
    });

    // Inline field save (debounced)
    var saveTimers = {};
    $(document).on('change', '.gnr-field-save', function() {
        var $el = $(this);
        var ref = $el.closest('.gnr-item').data('ref');
        var field = $el.data('field');
        var value = $el.val();

        // Debounce per ref+field
        var key = ref + '_' + field;
        if (saveTimers[key]) clearTimeout(saveTimers[key]);

        saveTimers[key] = setTimeout(function() {
            $.post(ajaxUrl, {
                action: 'gapnext_remediation_update',
                nonce: nonce,
                submission_id: subId,
                question_ref: ref,
                field: field,
                value: value
            }).done(function(resp) {
                if (resp.success) {
                    // Update status badge if status changed
                    if (field === 'status') {
                        var $badge = $el.closest('.gnr-item').find('.gnr-status');
                        $badge.attr('class', 'gnr-status gnr-status-' + resp.data.status)
                              .text(resp.data.status.replace('_', ' '));
                    }
                    $el.css('border-color', '#16a34a');
                    setTimeout(function() { $el.css('border-color', ''); }, 1500);
                }
            });
        }, field === 'action' ? 1000 : 300); // longer debounce for text areas
    });

    // Textarea save on blur (for corrective action)
    $(document).on('blur', '.gnr-field-save[data-field="action"]', function() {
        $(this).trigger('change');
    });

    // Review: approve/reject
    $(document).on('click', '.gnr-btn-approve, .gnr-btn-reject', function() {
        var $btn = $(this);
        var $item = $btn.closest('.gnr-item');
        var ref = $item.data('ref');
        var decision = $btn.hasClass('gnr-btn-approve') ? 'approve' : 'reject';
        var feedback = '';

        if (decision === 'reject') {
            feedback = prompt(gapnextRemediation.i18n.rejectReason || 'Reason for rejection:');
            if (feedback === null) return; // cancelled
        }

        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'gapnext_remediation_review',
            nonce: nonce,
            submission_id: subId,
            question_ref: ref,
            decision: decision,
            feedback: feedback
        }).done(function(resp) {
            if (resp.success) {
                location.reload(); // simplest way to update all UI state
            } else {
                alert(resp.data || 'Error');
                $btn.prop('disabled', false);
            }
        });
    });

    // Add comment
    $(document).on('click', '.gnr-add-comment-btn', function() {
        var $item = $(this).closest('.gnr-item');
        var ref = $item.data('ref');
        var $input = $item.find('.gnr-comment-input');
        var comment = $input.val().trim();
        if (!comment) return;

        $.post(ajaxUrl, {
            action: 'gapnext_remediation_comment',
            nonce: nonce,
            submission_id: subId,
            question_ref: ref,
            comment: comment
        }).done(function(resp) {
            if (resp.success) {
                $input.val('');
                // Append to timeline
                var $list = $item.find('.gnr-timeline-list');
                var now = new Date().toLocaleDateString();
                $list.append('<li><span class="gnr-timeline-date">' + now + '</span> ' +
                    '<span class="gnr-timeline-actor">You:</span> ' + $('<span/>').text(comment).html() + '</li>');
            }
        });
    });

    // Toggle timeline visibility
    $(document).on('click', '.gnr-timeline-toggle', function() {
        $(this).closest('.gnr-timeline').find('.gnr-timeline-list').slideToggle(200);
    });

    // Filter items by status
    $(document).on('click', '.gnr-filter-btn', function() {
        var filter = $(this).data('filter');
        $('.gnr-filter-btn').removeClass('active');
        $(this).addClass('active');

        if (filter === 'all') {
            $('.gnr-item').show();
        } else if (filter === 'needs_action') {
            $('.gnr-item').each(function() {
                var status = $(this).data('status');
                $(this).toggle(status !== 'verified' && status !== 'open');
            });
        } else {
            $('.gnr-item').each(function() {
                $(this).toggle($(this).data('status') === filter);
            });
        }
    });

    // Create client user
    $(document).on('click', '#gnr-create-client-btn', function() {
        var $btn = $(this);
        var email = $('#gnr-client-email').val().trim();
        var name = $('#gnr-client-name').val().trim();

        if (!email || !name) {
            alert(gapnextRemediation.i18n.clientRequired || 'Email and name are required.');
            return;
        }

        $btn.prop('disabled', true);
        $.post(ajaxUrl, {
            action: 'gapnext_create_client',
            nonce: nonce,
            email: email,
            name: name,
            audit_uuid: auditUuid
        }).done(function(resp) {
            if (resp.success) {
                location.reload();
            } else {
                alert(resp.data || 'Error');
                $btn.prop('disabled', false);
            }
        });
    });

})(jQuery);
```

- [ ] **Step 3: Enqueue assets in `class-admin.php`**

In the `enqueue_assets` method (line ~74), after the existing `wp_enqueue_media();` call, add:

```php
// Remediation assets on submission detail page
if ( isset( $_GET['page'] ) && $_GET['page'] === 'gapnext-submissions' && isset( $_GET['view_sub'] ) ) {
    wp_enqueue_style(
        'gapnext-admin-remediation',
        GAPNEXT_WP_URL . 'assets/gapnext-admin-remediation.css',
        [],
        GAPNEXT_WP_VERSION
    );
    wp_enqueue_script(
        'gapnext-admin-remediation',
        GAPNEXT_WP_URL . 'assets/gapnext-admin-remediation.js',
        [ 'jquery' ],
        GAPNEXT_WP_VERSION,
        true
    );

    $sub_id = (int) $_GET['view_sub'];
    $sub = GapNext_Audit_Manager::get_submission( $sub_id );
    wp_localize_script( 'gapnext-admin-remediation', 'gapnextRemediation', [
        'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        'nonce'        => wp_create_nonce( 'gapnext_remediation' ),
        'submissionId' => $sub_id,
        'auditUuid'    => $sub ? $sub->audit_uuid : '',
        'i18n'         => [
            'confirmInit'   => __( 'Initialize remediation plan for all questions? This cannot be undone.', 'gapnext-wp' ),
            'initializing'  => __( 'Initializing...', 'gapnext-wp' ),
            'initBtn'       => __( 'Initialize Remediation Plan', 'gapnext-wp' ),
            'rejectReason'  => __( 'Reason for rejection:', 'gapnext-wp' ),
            'clientRequired'=> __( 'Email and name are required.', 'gapnext-wp' ),
        ],
    ] );
}
```

- [ ] **Step 4: Create `includes/views/admin-remediation.php`**

This is the remediation tab content. It receives `$sub`, `$standard`, `$states`, `$agg`, and `$lang` variables from the parent.

```php
<?php
// includes/views/admin-remediation.php
if ( ! defined( 'ABSPATH' ) ) exit;

$is_init = GapNext_Remediation_Log::is_initialized( $sub->id );

if ( ! $is_init ) : ?>
    <div class="gnr-init-panel">
        <h3><?php esc_html_e( 'Remediation Plan Not Started', 'gapnext-wp' ); ?></h3>
        <p><?php esc_html_e( 'Initialize the remediation plan to start tracking corrective actions and progress for each checklist item. This will create tracking entries for all questions.', 'gapnext-wp' ); ?></p>
        <button type="button" id="gnr-init-btn" class="button button-primary">
            <?php esc_html_e( 'Initialize Remediation Plan', 'gapnext-wp' ); ?>
        </button>
    </div>
<?php return; endif;

// Labels
$status_labels = [
    'open'           => $lang === 'it' ? 'Aperto' : 'Open',
    'in_progress'    => $lang === 'it' ? 'In Corso' : 'In Progress',
    'pending_review' => $lang === 'it' ? 'In Revisione' : 'Pending Review',
    'verified'       => $lang === 'it' ? 'Verificato' : 'Verified',
    'rejected'       => $lang === 'it' ? 'Respinto' : 'Rejected',
];

$answer_labels = [
    1   => $lang === 'it' ? 'Conforme' : 'Compliant',
    1.0 => $lang === 'it' ? 'Conforme' : 'Compliant',
    0.5 => $lang === 'it' ? 'Parziale' : 'Partial',
    0   => $lang === 'it' ? 'Non-Conforme' : 'Non-Compliant',
    0.0 => $lang === 'it' ? 'Non-Conforme' : 'Non-Compliant',
    'na'=> $lang === 'it' ? 'Non Appl.' : 'N/A',
];

$answer_class = function( $val ) {
    if ( $val === 1.0 || $val === 1 ) return 'conforme';
    if ( $val === 0.5 ) return 'parziale';
    if ( $val === 0.0 || $val === 0 ) return 'non-conforme';
    if ( $val === 'na' ) return 'na';
    return 'unanswered';
};

$pending_count = $agg['pending_review'] ?? 0;
$current_pct   = round( ( $agg['current_score'] ?? 0 ) * 100, 1 );
$original_pct  = round( $sub->score * 100, 1 );
?>

<!-- Aggregate stats -->
<div class="gnr-agg-bar">
    <div class="gnr-agg-stat" style="border-left-color:#2271b1">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['total'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Total', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#16a34a">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['verified'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Verified', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#d97706">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['pending_review'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Pending', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#2563eb">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['in_progress'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'In Progress', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#9ca3af">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['open'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Open', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#dc2626">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $agg['overdue'] ); ?></span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Overdue', 'gapnext-wp' ); ?></span>
    </div>
    <div class="gnr-agg-stat" style="border-left-color:#7c3aed">
        <span class="gnr-agg-stat-num"><?php echo esc_html( $original_pct ); ?>% → <?php echo esc_html( $current_pct ); ?>%</span>
        <span class="gnr-agg-stat-label"><?php esc_html_e( 'Score Progress', 'gapnext-wp' ); ?></span>
    </div>
</div>

<!-- Filters -->
<div class="gnr-filters">
    <button class="gnr-filter-btn active" data-filter="all"><?php esc_html_e( 'All', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="needs_action"><?php esc_html_e( 'Needs Action', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="pending_review"><?php esc_html_e( 'Pending Review', 'gapnext-wp' ); ?> <?php if ( $pending_count ) : ?><span class="gapnext-tab-badge"><?php echo esc_html( $pending_count ); ?></span><?php endif; ?></button>
    <button class="gnr-filter-btn" data-filter="in_progress"><?php esc_html_e( 'In Progress', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="open"><?php esc_html_e( 'Open', 'gapnext-wp' ); ?></button>
    <button class="gnr-filter-btn" data-filter="verified"><?php esc_html_e( 'Verified', 'gapnext-wp' ); ?></button>
</div>

<!-- Item list -->
<?php
if ( $standard ) :
    foreach ( $standard['clauses'] as $clause ) :
        if ( (int) $clause['level'] === 1 ) :
            ?>
            <h3 style="margin:20px 0 8px;font-size:14px;color:#1e3a8a">
                <?php echo esc_html( $clause['clause_ref'] . ' — ' . $clause['title'] ); ?>
            </h3>
            <?php
            continue;
        endif;

        $ref = $clause['reference'];
        $state = $states[ $ref ] ?? [
            'original_answer' => null, 'current_answer' => null, 'proposed_answer' => null,
            'status' => 'open', 'corrective_action' => '', 'priority' => 'medium',
            'deadline' => null, 'responsible' => '', 'evidence' => [],
            'pending_approval' => false, 'events' => [],
        ];

        $orig_val   = $state['original_answer'];
        $orig_label = $answer_labels[ $orig_val ] ?? '—';
        $orig_class = $answer_class( $orig_val );
        $events     = $state['events'] ?? [];
        ?>
        <div class="gnr-item" data-ref="<?php echo esc_attr( $ref ); ?>" data-status="<?php echo esc_attr( $state['status'] ); ?>">

            <div class="gnr-item-header">
                <span class="gnr-item-ref"><?php echo esc_html( $clause['clause_ref'] ); ?></span>
                <span class="gnr-item-title"><?php echo esc_html( $clause['title'] ); ?></span>
                <span class="gnr-item-original <?php echo esc_attr( $orig_class ); ?>"><?php echo esc_html( $orig_label ); ?></span>
                <span class="gnr-status gnr-status-<?php echo esc_attr( $state['status'] ); ?>">
                    <?php echo esc_html( $status_labels[ $state['status'] ] ?? $state['status'] ); ?>
                </span>
            </div>

            <?php if ( $state['pending_approval'] && $state['proposed_answer'] !== null ) : ?>
            <div class="gnr-pending-banner">
                <span>
                    <?php
                    $proposed_label = $answer_labels[ $state['proposed_answer'] ] ?? $state['proposed_answer'];
                    printf(
                        /* translators: %s: proposed answer label */
                        esc_html__( 'Client proposes: %s', 'gapnext-wp' ),
                        '<strong>' . esc_html( $proposed_label ) . '</strong>'
                    );
                    ?>
                </span>
                <button class="gnr-btn-approve"><?php esc_html_e( 'Approve', 'gapnext-wp' ); ?></button>
                <button class="gnr-btn-reject"><?php esc_html_e( 'Reject', 'gapnext-wp' ); ?></button>
            </div>
            <?php endif; ?>

            <div class="gnr-item-fields">
                <div class="gnr-item-field gnr-item-action-field">
                    <label><?php esc_html_e( 'Corrective Action', 'gapnext-wp' ); ?></label>
                    <textarea class="gnr-field-save" data-field="action"
                              placeholder="<?php esc_attr_e( 'Describe the corrective action needed...', 'gapnext-wp' ); ?>"
                    ><?php echo esc_textarea( $state['corrective_action'] ); ?></textarea>
                </div>
                <div class="gnr-item-field">
                    <label><?php esc_html_e( 'Priority', 'gapnext-wp' ); ?></label>
                    <select class="gnr-field-save" data-field="priority">
                        <option value="high"   <?php selected( $state['priority'], 'high' ); ?>><?php esc_html_e( 'High', 'gapnext-wp' ); ?></option>
                        <option value="medium" <?php selected( $state['priority'], 'medium' ); ?>><?php esc_html_e( 'Medium', 'gapnext-wp' ); ?></option>
                        <option value="low"    <?php selected( $state['priority'], 'low' ); ?>><?php esc_html_e( 'Low', 'gapnext-wp' ); ?></option>
                    </select>
                </div>
                <div class="gnr-item-field">
                    <label><?php esc_html_e( 'Deadline', 'gapnext-wp' ); ?></label>
                    <input type="date" class="gnr-field-save" data-field="deadline"
                           value="<?php echo esc_attr( $state['deadline'] ?? '' ); ?>">
                </div>
                <div class="gnr-item-field">
                    <label><?php esc_html_e( 'Responsible', 'gapnext-wp' ); ?></label>
                    <input type="text" class="gnr-field-save" data-field="responsible"
                           value="<?php echo esc_attr( $state['responsible'] ); ?>"
                           placeholder="<?php esc_attr_e( 'Person name', 'gapnext-wp' ); ?>">
                </div>
            </div>

            <!-- Status selector (consultant can override) -->
            <div style="margin-top:10px">
                <label style="font-size:11px;color:#646970;font-weight:600"><?php esc_html_e( 'Status', 'gapnext-wp' ); ?></label>
                <select class="gnr-field-save" data-field="status" style="width:auto;margin-left:6px">
                    <?php foreach ( GapNext_Remediation_Log::STATUSES as $s ) : ?>
                    <option value="<?php echo esc_attr( $s ); ?>" <?php selected( $state['status'], $s ); ?>>
                        <?php echo esc_html( $status_labels[ $s ] ?? $s ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Comment input -->
            <div style="margin-top:10px;display:flex;gap:6px">
                <input type="text" class="gnr-comment-input" style="flex:1;font-size:12px"
                       placeholder="<?php esc_attr_e( 'Add a comment...', 'gapnext-wp' ); ?>">
                <button class="button button-small gnr-add-comment-btn"><?php esc_html_e( 'Post', 'gapnext-wp' ); ?></button>
            </div>

            <!-- Event timeline -->
            <?php if ( ! empty( $events ) ) : ?>
            <div class="gnr-timeline">
                <button class="gnr-timeline-toggle"><?php esc_html_e( 'Show activity', 'gapnext-wp' ); ?> (<?php echo count( $events ); ?>)</button>
                <ul class="gnr-timeline-list" style="display:none">
                    <?php foreach ( array_reverse( $events ) as $evt ) :
                        $evt_date = date_i18n( 'd/m/Y H:i', strtotime( $evt->created_at ) );
                        $actor = $evt->user_role === 'consultant' ? __( 'Consultant', 'gapnext-wp' ) : __( 'Client', 'gapnext-wp' );
                        $desc = self::describe_event( $evt, $lang );
                    ?>
                    <li>
                        <span class="gnr-timeline-date"><?php echo esc_html( $evt_date ); ?></span>
                        <span class="gnr-timeline-actor"><?php echo esc_html( $actor ); ?>:</span>
                        <?php echo esc_html( $desc ); ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        </div>
    <?php
    endforeach;
endif;
```

Note: this file calls `self::describe_event()` — that method will be added to `class-audit-manager.php` in step 6.

- [ ] **Step 5: Create `includes/views/admin-client-access.php`**

```php
<?php
// includes/views/admin-client-access.php
if ( ! defined( 'ABSPATH' ) ) exit;

$clients = GapNext_Client_Role::get_audit_clients( $sub->audit_uuid );
?>

<div style="max-width:600px">
    <h3><?php esc_html_e( 'Assigned Client Users', 'gapnext-wp' ); ?></h3>

    <?php if ( empty( $clients ) ) : ?>
        <p style="color:#646970;font-style:italic"><?php esc_html_e( 'No client users assigned to this audit yet.', 'gapnext-wp' ); ?></p>
    <?php else : ?>
        <table class="widefat" style="margin-bottom:20px">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'gapnext-wp' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $clients as $client ) : ?>
                <tr>
                    <td><?php echo esc_html( $client['display_name'] ); ?></td>
                    <td><?php echo esc_html( $client['user_email'] ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3><?php esc_html_e( 'Add Client User', 'gapnext-wp' ); ?></h3>
    <p class="description" style="margin-bottom:12px">
        <?php esc_html_e( 'Create a new WordPress user with the GapNext Client role, or grant an existing user access to this audit.', 'gapnext-wp' ); ?>
    </p>

    <table class="form-table" style="margin:0">
        <tr>
            <th><label for="gnr-client-name"><?php esc_html_e( 'Name', 'gapnext-wp' ); ?></label></th>
            <td><input type="text" id="gnr-client-name" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. Marco Rossi', 'gapnext-wp' ); ?>"></td>
        </tr>
        <tr>
            <th><label for="gnr-client-email"><?php esc_html_e( 'Email', 'gapnext-wp' ); ?></label></th>
            <td><input type="email" id="gnr-client-email" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. marco@acme.com', 'gapnext-wp' ); ?>"></td>
        </tr>
    </table>
    <p>
        <button type="button" id="gnr-create-client-btn" class="button button-primary">
            <?php esc_html_e( 'Create & Grant Access', 'gapnext-wp' ); ?>
        </button>
    </p>
</div>
```

- [ ] **Step 6: Modify `class-audit-manager.php` — Add tabs to submission detail view**

This is the key integration point. The existing `render_submission_view()` method needs to wrap its content in a tabbed interface.

Find the line `private static function render_submission_view( $sub_id ) {` (line 362).

After the existing score card, stats grid, company/contact details, and checklist results (ending around line 707), and before the AI report section (line 710), we need to:

1. Add a `describe_event()` helper method to the class (at the end, before the closing `}`).
2. Wrap the existing submission detail content + new remediation content in tabs.

**Add this method at the bottom of the class** (before the final `}`):

```php
/**
 * Human-readable description of a remediation event.
 */
public static function describe_event( $event, $lang = 'en' ) {
    $d = $event->data;
    $is_it = $lang === 'it';

    switch ( $event->event_type ) {
        case 'status_changed':
            $from = $d['old_status'] ?? '?';
            $to = $d['new_status'] ?? '?';
            return $is_it
                ? "Stato cambiato: {$from} → {$to}"
                : "Status changed: {$from} → {$to}";
        case 'action_set':
            return $is_it ? 'Azione correttiva definita' : 'Corrective action set';
        case 'action_updated':
            return $is_it ? 'Azione correttiva aggiornata' : 'Corrective action updated';
        case 'priority_set':
            return ( $is_it ? 'Priorità: ' : 'Priority: ' ) . ( $d['priority'] ?? '' );
        case 'deadline_set':
            return ( $is_it ? 'Scadenza: ' : 'Deadline: ' ) . ( $d['deadline'] ?? '—' );
        case 'responsible_set':
            return ( $is_it ? 'Responsabile: ' : 'Responsible: ' ) . ( $d['responsible'] ?? '' );
        case 'answer_changed':
            return ( $is_it ? 'Risposta proposta: ' : 'Answer proposed: ' ) . ( $d['new_value'] ?? '' );
        case 'answer_approved':
            return $is_it ? 'Risposta approvata' : 'Answer approved';
        case 'answer_rejected':
            $fb = $d['feedback'] ?? '';
            return ( $is_it ? 'Risposta respinta' : 'Answer rejected' ) . ( $fb ? ": {$fb}" : '' );
        case 'evidence_added':
            return ( $is_it ? 'Evidenza caricata: ' : 'Evidence uploaded: ' ) . basename( $d['file_path'] ?? '' );
        case 'comment':
            return $d['comment'] ?? '';
        case 'verified':
            return $is_it ? 'Verificato ✓' : 'Verified ✓';
        default:
            return $event->event_type;
    }
}
```

**For the tabs integration:** In `render_submission_view()`, after the `<h1>` and export buttons block (around line 454), insert the tab navigation. Then wrap the existing content (score card through checklist) in a tab panel div, and add the remediation and client access panels.

Insert this right after the export buttons `</p>` (line 454):

```php
<?php
// Compute remediation data for tabs
$states = GapNext_Remediation_State::for_submission( $sub->id );
$agg    = GapNext_Remediation_State::aggregate( $sub->id, $states );
$pending_count = $agg['pending_review'];
$active_tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'gap_analysis';
?>

<!-- Tabs -->
<div class="gapnext-tabs">
    <button class="gapnext-tab <?php echo $active_tab === 'gap_analysis' ? 'active' : ''; ?>" data-tab="gap_analysis">
        <?php esc_html_e( 'Gap Analysis', 'gapnext-wp' ); ?>
    </button>
    <button class="gapnext-tab <?php echo $active_tab === 'remediation' ? 'active' : ''; ?>" data-tab="remediation">
        <?php esc_html_e( 'Remediation Plan', 'gapnext-wp' ); ?>
        <?php if ( $pending_count > 0 ) : ?>
            <span class="gapnext-tab-badge"><?php echo esc_html( $pending_count ); ?></span>
        <?php endif; ?>
    </button>
    <button class="gapnext-tab <?php echo $active_tab === 'client_access' ? 'active' : ''; ?>" data-tab="client_access">
        <?php esc_html_e( 'Client Access', 'gapnext-wp' ); ?>
    </button>
</div>

<!-- Tab: Gap Analysis (existing content) -->
<div id="gapnext-panel-gap_analysis" class="gapnext-tab-panel <?php echo $active_tab === 'gap_analysis' ? 'active' : ''; ?>">
```

Then, after the existing checklist results table closing `<?php endif; ?>` (line 707) and before the AI report section, close the gap analysis panel and add the other two:

```php
</div><!-- /gap_analysis panel -->

<!-- Tab: Remediation Plan -->
<div id="gapnext-panel-remediation" class="gapnext-tab-panel <?php echo $active_tab === 'remediation' ? 'active' : ''; ?>">
    <?php include GAPNEXT_WP_DIR . 'includes/views/admin-remediation.php'; ?>
</div>

<!-- Tab: Client Access -->
<div id="gapnext-panel-client_access" class="gapnext-tab-panel <?php echo $active_tab === 'client_access' ? 'active' : ''; ?>">
    <?php include GAPNEXT_WP_DIR . 'includes/views/admin-client-access.php'; ?>
</div>
```

- [ ] **Step 7: Verify the tabbed UI loads correctly**

Run: Navigate to WP Admin → GapNext WP → Submissions → click "View" on any submission.
Expected:
- Three tabs appear: "Gap Analysis", "Remediation Plan", "Client Access"
- Gap Analysis tab shows the existing content (score card, details, checklist)
- Remediation Plan tab shows "Initialize Remediation Plan" button
- Client Access tab shows the "Add Client User" form

- [ ] **Step 8: Test remediation initialization**

Run: Click "Initialize Remediation Plan" on the Remediation tab.
Expected:
- Page reloads
- Remediation tab now shows aggregate stats bar, filter buttons, and item cards for all questions
- Each card shows the original answer, a corrective action textarea, priority/deadline/responsible fields, and a status selector

- [ ] **Step 9: Test inline editing**

Run: Change the priority dropdown on any item, set a deadline, type a corrective action.
Expected:
- Field borders briefly flash green on successful save
- Changes persist after page reload

- [ ] **Step 10: Test client user creation**

Run: Go to Client Access tab, enter a name and email, click "Create & Grant Access".
Expected:
- Page reloads, the user appears in the assigned client users table
- In WP Admin → Users, the new user has the "GapNext Client" role

- [ ] **Step 11: Commit**

```bash
git add assets/gapnext-admin-remediation.css assets/gapnext-admin-remediation.js includes/views/admin-remediation.php includes/views/admin-client-access.php includes/class-audit-manager.php includes/class-admin.php
git commit -m "feat: add remediation plan tab with item cards, inline editing, client access management"
```

---

### Task 10: Final Integration Commit

- [ ] **Step 1: Verify all classes are booted in `gapnext-wp.php`**

The `plugins_loaded` callback should now include:

```php
new GapNext_Admin();
new GapNext_Checklist();
new GapNext_Results();
new GapNext_Ajax();
new GapNext_Audit_Manager();
new GapNext_Client_Role();
new GapNext_Remediation_Ajax();
```

- [ ] **Step 2: Full end-to-end test**

1. Deactivate and reactivate the plugin (triggers DB migration)
2. Create a new audit link
3. Fill out and submit the checklist on the frontend
4. In admin, view the submission — confirm 3 tabs work
5. Initialize remediation
6. Set corrective actions, priorities, deadlines on several items
7. Create a client user from the Client Access tab
8. Log in as the client user — confirm redirect to home page (not wp-admin)
9. Test downloading a report for a login_required audit while logged out — should be blocked
10. Delete plugin — confirm all tables and options are removed

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "feat: complete Phase 1 (hardening) and Phase 2 (remediation engine) implementation"
```
