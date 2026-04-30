# AI Report Regeneration + History Design

## Goal

Add a "Regenerate AI Report" flow to the single submission view page, with an optional corrections/comments field passed to the FastAPI AI pipeline, and a persistent history of all past generations shown on the view page.

## Architecture

Unified AJAX action for both first generation and regeneration. Every call logs to a new `gapnext_ai_report_generations` DB table. The FastAPI pipeline is extended to accept an optional `corrections` string injected into the BAML prompt. The WP plugin view page gains a Regenerate section (textarea + button) and a history list in the "report exists" state.

**Tech Stack:** PHP 7.4+, jQuery, WordPress AJAX, FastAPI, BAML, SQLite

---

## Section 1: Database

### New table: `gapnext_ai_report_generations`

Created via `dbDelta()` in `class-installer.php` with a DB version bump (see Section 3a).

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT(20) UNSIGNED AUTO_INCREMENT | Primary key |
| `submission_id` | BIGINT(20) UNSIGNED NOT NULL | References `gapnext_submissions.id` |
| `uuid` | VARCHAR(36) NOT NULL DEFAULT '' | Report UUID from FastAPI response |
| `download_url` | VARCHAR(500) NOT NULL DEFAULT '' | Full FastAPI report URL (without `?token=`) |
| `comments` | TEXT NOT NULL | Corrections/context provided by user; empty string for first generation |
| `generated_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC timestamp of generation |

Use `BIGINT(20) UNSIGNED` for both `id` and `submission_id` — consistent with all other tables in this plugin. `generated_at` uses `DEFAULT CURRENT_TIMESTAMP` to satisfy `NOT NULL` without always requiring a value in the insert.

The existing `ai_report_url` and `ai_report_uuid` columns on `gapnext_submissions` are kept as a "latest report" pointer for backward compatibility — no migration needed.

---

## Section 2: FastAPI + BAML Changes

### 2a. `src/models/wp_export.py`

Add to `WpMeta`:
```python
corrections: Optional[str] = None
```

### 2b. `src/models/audit.py`

Add to `AuditMeta`:
```python
corrections: Optional[str] = None
```

### 2c. `src/services/wp_adapter.py`

In `wp_export_to_audit_payload()`, pass corrections when building `AuditMeta`:
```python
corrections=export.meta.corrections or None,
```

### 2d. `src/services/baml_service.py`

Map corrections to an empty string if None (BAML requires a `string`, not `string?`), then pass to BAML:
```python
corrections = payload.audit_meta.corrections or ""
result = await b.GenerateAuditReport(
    company_name=payload.audit_meta.company_name,
    standard_id=payload.audit_meta.standard_id,
    auditor_name=payload.audit_meta.auditor_name,
    checklist_json=checklist_json,
    output_language=output_language,
    corrections=corrections,
)
```

**Important:** Always pass an empty string `""` rather than `None` — the BAML parameter is `corrections: string` (non-optional). In the BAML prompt, `{% if corrections %}` evaluates as false for an empty string, so no corrections block is rendered when nothing was provided.

### 2e. `baml_src/audit_report.baml`

Add `corrections: string` parameter to `GenerateAuditReport` and a conditional block in the prompt (after the checklist JSON and before `{{ ctx.output_format }}`):

```
{% if corrections %}
    AUDITOR CORRECTIONS / ADDITIONAL CONTEXT:
    {{ corrections }}
    Please incorporate the above corrections and context into your analysis and report.
{% endif %}
```

Full updated function signature:
```
function GenerateAuditReport(
  company_name: string,
  standard_id: string,
  auditor_name: string,
  checklist_json: string,
  output_language: string,
  corrections: string
) -> AIReportResult
```

### 2f. `baml_client/` generated files (manual update)

Update all three files to add `corrections: str` parameter (same approach as the `output_language` change):

**`baml_client/inlinedbaml.py`** — update the `"audit_report.baml"` string in `_file_map` to match the new BAML source exactly.

**`baml_client/async_client.py`** — add `corrections: str` to all 4 `GenerateAuditReport` signatures (`BamlAsyncClient`, `BamlStreamClient`, `BamlHttpRequestClient`, `BamlHttpStreamRequestClient`) and add `"corrections": corrections` to each args dict.

**`baml_client/sync_client.py`** — same changes as `async_client.py`.

### 2g. FastAPI deployment

After updating all Python files, copy them into the running container and restart it:
```bash
docker cp src/models/wp_export.py  gapnext-ai-pipeline-gapnext-ai-1:/app/src/models/wp_export.py
docker cp src/models/audit.py       gapnext-ai-pipeline-gapnext-ai-1:/app/src/models/audit.py
docker cp src/services/wp_adapter.py gapnext-ai-pipeline-gapnext-ai-1:/app/src/services/wp_adapter.py
docker cp src/services/baml_service.py gapnext-ai-pipeline-gapnext-ai-1:/app/src/services/baml_service.py
docker cp baml_src/audit_report.baml gapnext-ai-pipeline-gapnext-ai-1:/app/baml_src/audit_report.baml
docker cp baml_client/async_client.py gapnext-ai-pipeline-gapnext-ai-1:/app/baml_client/async_client.py
docker cp baml_client/sync_client.py  gapnext-ai-pipeline-gapnext-ai-1:/app/baml_client/sync_client.py
docker cp baml_client/inlinedbaml.py  gapnext-ai-pipeline-gapnext-ai-1:/app/baml_client/inlinedbaml.py
docker restart gapnext-ai-pipeline-gapnext-ai-1
```
Run from `C:\dev\_antigravity\gapnext app\gapnext-fullstack\gapnext-fastapi-v1 cc\code\gapnext-ai-pipeline`.

---

## Section 3: WordPress PHP Changes

### 3a. `gapnext-wp.php` + `includes/class-installer.php`

**`gapnext-wp.php`**: Two changes required:
1. Bump the `Version:` tag in the plugin header comment from `1.0.9` to `1.1.0` (this is what WordPress displays in the Plugins admin screen).
2. Bump `GAPNEXT_WP_VERSION` from `'1.0.9'` to `'1.1.0'`. The `plugins_loaded` callback already compares this value against the `gapnext_wp_db_version` option and calls `GapNext_Installer::activate()` when they differ — this is the DB migration trigger.

**`includes/class-installer.php`**: Add the new table to the `dbDelta()` call. Note: the existing code uses `$charset` (not `$charset_collate`) as the variable holding `$wpdb->get_charset_collate()`. Use the same variable name.

```sql
CREATE TABLE {$wpdb->prefix}gapnext_ai_report_generations (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  submission_id BIGINT(20) UNSIGNED NOT NULL,
  uuid VARCHAR(36) NOT NULL DEFAULT '',
  download_url VARCHAR(500) NOT NULL DEFAULT '',
  comments TEXT NOT NULL,
  generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY submission_id (submission_id)
) $charset;
```

### 3b. `includes/class-ai-report.php`

Change method signature:
```php
public function generate_for_submission( int $submission_id, string $comments = '' ): array|WP_Error
```

In the API payload, add `corrections` under `meta`:
```php
'meta' => [
    'standard'    => $sub->standard_id,
    'language'    => $sub->language,
    'submitted_at'=> $sub->submitted_at,
    'score'       => (float) $sub->score,
    'corrections' => $comments,
],
```

On success, after updating `ai_report_url` + `ai_report_uuid` on the submission, insert a history row:
```php
global $wpdb;
$wpdb->insert(
    $wpdb->prefix . 'gapnext_ai_report_generations',
    [
        'submission_id' => $submission_id,
        'uuid'          => $result['uuid'],
        'download_url'  => $result['download_url'],
        'comments'      => $comments,
        'generated_at'  => current_time( 'mysql', true ),
    ],
    [ '%d', '%s', '%s', '%s', '%s' ]
);
```

### 3c. `includes/class-audit-manager.php`

#### AJAX handler `handle_ai_generate()`

After the existing nonce and capability checks, read and sanitize comments:
```php
$comments = sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) );
$result = ( new GapNext_AI_Report() )->generate_for_submission( $submission_id, $comments );
```

Return `comments` in the success payload so JS can update the history table in-page:
```php
wp_send_json_success( array_merge( $result, [ 'comments' => $comments ] ) );
```

#### New private static helper `get_ai_report_history()`

```php
private static function get_ai_report_history( int $submission_id ): array {
    global $wpdb;
    return $wpdb->get_results( $wpdb->prepare(
        "SELECT uuid, download_url, comments, generated_at
         FROM {$wpdb->prefix}gapnext_ai_report_generations
         WHERE submission_id = %d
         ORDER BY generated_at DESC",
        $submission_id
    ) ) ?: [];
}
```

#### `render_submission_view()` — "report exists" state

Replace the current "report already exists" block with the following structure (IDs must match exactly as specified — the JS depends on them):

```
[AI Report section wrapper]
  h2: 🤖 AI Report  ● Available

  Latest report:
  <a href="add_query_arg('token', get_option('gapnext_ai_api_key',''), $sub->ai_report_url)"
     id="gapnext-ai-latest-download" ...>
    ⬇ Download AI Report
  </a>

  [Regenerate subsection]
  <p>Corrections or additional context for regeneration (optional):</p>
  <textarea id="gapnext-ai-corrections" rows="3" ...></textarea>
  <button id="gapnext-ai-regenerate-view" ...>Regenerate AI Report</button>

  [Regenerate log panel — hidden until regeneration starts]
  <div id="gapnext-ai-regen-log-panel" style="display:none">
    <div>[Progress label]</div>
    <div id="gapnext-ai-regen-log-steps"></div>
  </div>

  [History section]
  PHP: $history = self::get_ai_report_history( $sub->id )
  if not empty:
    <table id="gapnext-ai-history-table">
      <thead>Date | Comments | Download</thead>
      <tbody>
        foreach $history as $row:
          <tr>
            <td>date_i18n( 'd/m/Y H:i', strtotime( $row->generated_at ) )</td>
            <td>$row->comments ?: '—'</td>
            <td>
              <a href="add_query_arg('token', get_option('gapnext_ai_api_key',''), $row->download_url)"
                 target="_blank">⬇ Report</a>
            </td>
          </tr>
      </tbody>
    </table>
```

All user-supplied strings must be escaped with `esc_html()`. All URLs must be wrapped with `esc_url()`.

CSS for the regenerate log panel reuses the same existing classes: `.gapnext-ai-step`, `.gapnext-ai-step.active`, `.gapnext-ai-step.done`, `.gapnext-ai-step.failed`, `.gapnext-ai-dot` — no new CSS needed.

---

## Section 4: JavaScript Changes

### `assets/gapnext-admin-ai.js`

#### Guard change (critical)

The existing guard at line 51:
```javascript
if ($('#gapnext-ai-generate-view').length === 0) return;
```
Must be changed to check for **either** the generate or regenerate button, so the closure doesn't exit early when the page is in the "report exists" state (where only `#gapnext-ai-regenerate-view` is present):
```javascript
if ($('#gapnext-ai-generate-view').length === 0 && $('#gapnext-ai-regenerate-view').length === 0) return;
```

#### Regenerate handler block

Add after the existing generate handler (`$generateBtn.on('click', startGeneration)`):

**Variables:**
```javascript
var $regenBtn        = $('#gapnext-ai-regenerate-view');
var $regenPanel      = $('#gapnext-ai-regen-log-panel');
var $regenSteps      = $('#gapnext-ai-regen-log-steps');
var $correctionsArea = $('#gapnext-ai-corrections');
var $latestDownload  = $('#gapnext-ai-latest-download');
var regenTimers      = [];
var regenCurrentStep = -1;
```

**`startRegeneration()` function:**

`startRegeneration` must use its own scoped helper functions (`regenActivateStep`, `regenTickAllRemaining`, `regenClearTimers`) that operate on `$regenSteps` and `regenCurrentStep` — **not** the shared `activateStep`/`tickAllRemaining`/`clearTimers` helpers used by the generate flow, which operate on `$logSteps` and `currentStep`. The two flows run independently and must not share state.

- Read textarea: `var corrections = $correctionsArea.val().trim()`
- Disable `$regenBtn`, show `$regenPanel`
- Build 4 step elements in `$regenSteps` using `regenActivateStep` / `regenCurrentStep` (same step texts and timing as generate: 0ms, 800ms, 2500ms, 5000ms)
- Fire AJAX `gapnext_ai_generate` with `submission_id: gapnextAI.submissionId` and `comments: corrections`
- On success:
  - Call `regenClearTimers()`, then `regenTickAllRemaining()` to tick all remaining steps ✓
  - Show success line in `$regenSteps`
  - Update `$latestDownload` href to `resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey)`
  - Prepend new row to `#gapnext-ai-history-table tbody` (guaranteed to exist in the DOM because the first generation always inserts a history row before the page transitions to the "report exists" state):
    ```javascript
    var date    = new Date().toLocaleString();
    var comment = resp.data.comments || '—';
    var dlUrl   = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
    $('<tr>')
      .append($('<td>').text(date))
      .append($('<td>').text(comment))
      .append($('<td>').append($('<a>').attr({href: dlUrl, target:'_blank'}).text('⬇ Report')))
      .prependTo('#gapnext-ai-history-table tbody');
    ```
  - Clear textarea, re-enable `$regenBtn`, hide `$regenPanel` after a short delay (1 second)
- On error: same error state + Try Again as the generate flow (Try Again re-enables `$regenBtn` and hides `$regenPanel`)

**Bind:**
```javascript
$regenBtn.on('click', startRegeneration);
```

The existing generate handler (`$generateBtn.on('click', startGeneration)`) and all its helper functions are **unchanged**.

---

## Testing

1. After bumping `GAPNEXT_WP_VERSION` to `1.1.0` and loading the admin: `gapnext_ai_report_generations` table exists in the DB
2. First generation (no report): generate → history row inserted with empty comments, `ai_report_url` updated, view page refreshes to show "report exists" state
3. Regeneration without comments: Regenerate with empty textarea → new history row with empty comments, latest download link updates, history shows 2 rows newest-first
4. Regeneration with comments: fill textarea → new row stores comments; FastAPI logs show `corrections` in request; regenerated report reflects the corrections
5. FastAPI receives `corrections` field: verify via `docker logs gapnext-ai-pipeline-gapnext-ai-1` that the request includes corrections
6. BAML prompt: when `corrections` is non-empty, the "AUDITOR CORRECTIONS" block appears in the prompt; when empty, it does not
7. History list: date formatted `dd/mm/yyyy HH:mm`, empty comments shown as "—", all download links include `?token=`
8. JS guard: no JS errors on the list page (neither `#gapnext-ai-generate-view` nor `#gapnext-ai-regenerate-view` is present — guard exits cleanly)
9. JS guard: on a view page with no report (only `#gapnext-ai-generate-view`), regenerate handler variables reference absent elements but do not error (jQuery returns empty sets)
10. Error path: invalid API key → regen log shows error, Try Again resets the regenerate form
