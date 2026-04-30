# AI Report Regeneration + History Implementation Plan

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Regenerate AI Report" button with optional corrections field to the submission view page, persist all AI report generations in a new DB table, and show generation history on the view page.

**Architecture:** Unified AJAX action handles both first generation and regeneration. `corrections` is threaded from the WP AJAX handler through FastAPI models and services to the BAML prompt, where a conditional block renders it only when non-empty. Every generation (first or subsequent) inserts a row into a new `gapnext_ai_report_generations` DB table. The view page "report exists" state expands to include a regenerate form (textarea + button + progress panel) and a history table.

**Tech Stack:** PHP 7.4+, jQuery, WordPress AJAX, FastAPI/Pydantic, BAML, MySQL (dbDelta)

---

## File Map

### FastAPI (repo root: `C:\dev\_antigravity\gapnext app\gapnext-fullstack\gapnext-fastapi-v1 cc\code\gapnext-ai-pipeline`)

| File | Change |
|------|--------|
| `src/models/wp_export.py` | Add `corrections: Optional[str] = None` to `WpMeta` |
| `src/models/audit.py` | Add `corrections: Optional[str] = None` to `AuditMeta` |
| `src/services/wp_adapter.py` | Pass `corrections=export.meta.corrections or None` to `AuditMeta` |
| `src/services/baml_service.py` | Read `corrections`, default to `""`, pass to BAML call |
| `baml_src/audit_report.baml` | Add `corrections: string` param + `{% if corrections %}` block in prompt |
| `baml_client/async_client.py` | Add `corrections: str` to all 4 `GenerateAuditReport` signatures + args dicts |
| `baml_client/sync_client.py` | Same as `async_client.py` |
| `baml_client/inlinedbaml.py` | Update `"audit_report.baml"` string in `_file_map` |

### WordPress plugin (repo root: `C:\Users\nicol\Local Sites\wpdevtest1\app\public\wp-content\plugins\gapnext-wp`)

| File | Change |
|------|--------|
| `gapnext-wp.php` | Bump `Version:` header tag and `GAPNEXT_WP_VERSION` from `1.0.9` → `1.1.0` |
| `includes/class-installer.php` | Add `gapnext_ai_report_generations` table to `dbDelta()` call |
| `includes/class-ai-report.php` | Add `$comments` param, include in API payload, insert history row on success |
| `includes/class-audit-manager.php` | Read `$comments` in AJAX handler, add `get_ai_report_history()`, expand "report exists" view state |
| `assets/gapnext-admin-ai.js` | Change guard to `&&`, add regenerate handler block |

---

## Chunk 1: FastAPI Changes

### Task 1: Add `corrections` to FastAPI models

**Files:**
- Modify: `src/models/wp_export.py`
- Modify: `src/models/audit.py`

- [ ] **Step 1: Add `corrections` to `WpMeta`**

In `src/models/wp_export.py`, add `corrections` to `WpMeta` after `score`:

```python
class WpMeta(BaseModel):
    standard: str
    language: str = ""
    submitted_at: str = ""
    score: Optional[float] = None
    corrections: Optional[str] = None
```

- [ ] **Step 2: Add `corrections` to `AuditMeta`**

In `src/models/audit.py`, add `corrections` to `AuditMeta` after `language`:

```python
class AuditMeta(BaseModel):
    uuid: Optional[str] = None
    standard_id: str
    auditor_name: str
    company_name: str
    language: Optional[str] = None
    corrections: Optional[str] = None
```

- [ ] **Step 3: Verify syntax**

Run from the FastAPI project root:
```bash
python -c "from src.models.wp_export import WpMeta; from src.models.audit import AuditMeta; print('OK')"
```
Expected: `OK`

---

### Task 2: Thread `corrections` through FastAPI services

**Files:**
- Modify: `src/services/wp_adapter.py`
- Modify: `src/services/baml_service.py`

- [ ] **Step 1: Pass `corrections` in `wp_export_to_audit_payload()`**

In `src/services/wp_adapter.py`, update the `AuditMeta(...)` constructor call to include `corrections`:

```python
    audit_meta = AuditMeta(
        uuid=str(uuid.uuid4()),
        standard_id=export.meta.standard,
        auditor_name=auditor_name,
        company_name=export.company.name,
        language=export.meta.language or None,
        corrections=export.meta.corrections or None,
    )
```

- [ ] **Step 2: Read `corrections` and pass to BAML in `generate_audit_report()`**

In `src/services/baml_service.py`, add `corrections` variable after `output_language` and pass it to `b.GenerateAuditReport()`:

```python
async def generate_audit_report(payload: AuditPayload) -> AIReportResult:
    checklist_json = json.dumps(
        [item.model_dump() for item in payload.checklist], indent=2
    )
    lang_code = (payload.audit_meta.language or "en").lower()
    output_language = _LANGUAGE_NAMES.get(lang_code, "English")
    corrections = payload.audit_meta.corrections or ""
    log.info(
        "baml.generate.start",
        company=payload.audit_meta.company_name,
        items=len(payload.checklist),
        language=output_language,
    )
    result = await b.GenerateAuditReport(
        company_name=payload.audit_meta.company_name,
        standard_id=payload.audit_meta.standard_id,
        auditor_name=payload.audit_meta.auditor_name,
        checklist_json=checklist_json,
        output_language=output_language,
        corrections=corrections,
    )
    log.info("baml.generate.complete", risk=result.overall_risk_rating)
    return result
```

- [ ] **Step 3: Verify syntax**

```bash
python -c "from src.services.wp_adapter import wp_export_to_audit_payload; from src.services.baml_service import generate_audit_report; print('OK')"
```
Expected: `OK`

---

### Task 3: Update BAML source and generated client files

**Files:**
- Modify: `baml_src/audit_report.baml`
- Modify: `baml_client/async_client.py`
- Modify: `baml_client/sync_client.py`
- Modify: `baml_client/inlinedbaml.py`

- [ ] **Step 1: Update `baml_src/audit_report.baml`**

Replace the entire file with the new content (adds `corrections: string` parameter and the conditional corrections block between the checklist JSON and IMPORTANT INSTRUCTIONS):

```
class MitigationPlan {
  action string @description("Concrete remediation action")
  priority "High" | "Medium" | "Low"
  estimated_effort string @description("e.g. '2 days', '1 week'")
}

class FindingDetail {
  clause string
  status string
  risk_summary string @description("1-2 sentence risk explanation")
  mitigation MitigationPlan
}

class AIReportResult {
  executive_summary string @description("3-5 sentence professional summary of overall compliance posture")
  findings FindingDetail[]
  overall_risk_rating "Critical" | "High" | "Medium" | "Low"
}

function GenerateAuditReport(
  company_name: string,
  standard_id: string,
  auditor_name: string,
  checklist_json: string,
  output_language: string,
  corrections: string
) -> AIReportResult {
  client DefaultLLM
  prompt #"
    You are a senior ISO compliance auditor. Analyze the following audit checklist
    and produce a structured professional report.

    Company: {{ company_name }}
    Standard: {{ standard_id }}
    Auditor: {{ auditor_name }}

    Checklist (JSON):
    {{ checklist_json }}

    {% if corrections %}
    AUDITOR CORRECTIONS / ADDITIONAL CONTEXT:
    {{ corrections }}
    Please incorporate the above corrections and context into your analysis and report.
    {% endif %}

    IMPORTANT INSTRUCTIONS:
    - Write ALL text output (executive_summary, risk_summary, mitigation actions) in {{ output_language }}.
    - The `findings` array MUST only contain items with status "Non-Conformity" or "Observation".
    - Do NOT include "Conformity" or "Unanswered" items in the findings array.
    - Mention conforming areas briefly in the executive_summary only.
    - Keep each risk_summary to 1-2 sentences maximum.
    - Keep each mitigation action concise (2-3 sentences maximum).

    {{ ctx.output_format }}
  "#
}
```

- [ ] **Step 2: Update `baml_client/async_client.py` — `BamlAsyncClient.GenerateAuditReport`**

Find line (currently line 85):
```python
    async def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,
```
Replace the entire method (lines 85-99) with:
```python
    async def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,corrections: str,
        baml_options: BamlCallOptions = {},
    ) -> types.AIReportResult:
        # Check if on_tick is provided
        if 'on_tick' in baml_options:
            # Use streaming internally when on_tick is provided
            __stream__ = self.stream.GenerateAuditReport(company_name=company_name,standard_id=standard_id,auditor_name=auditor_name,checklist_json=checklist_json,output_language=output_language,corrections=corrections,
                baml_options=baml_options)
            return await __stream__.get_final_response()
        else:
            # Original non-streaming code
            __result__ = await self.__options.merge_options(baml_options).call_function_async(function_name="GenerateAuditReport", args={
                "company_name": company_name,"standard_id": standard_id,"auditor_name": auditor_name,"checklist_json": checklist_json,"output_language": output_language,"corrections": corrections,
            })
            return typing.cast(types.AIReportResult, __result__.cast_to(types, types, stream_types, False, __runtime__))
```

- [ ] **Step 3: Update `baml_client/async_client.py` — `BamlStreamClient.GenerateAuditReport`**

Find line (currently line 109):
```python
    def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,
```
Replace the entire method (lines 109-120) with:
```python
    def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,corrections: str,
        baml_options: BamlCallOptions = {},
    ) -> baml_py.BamlStream[stream_types.AIReportResult, types.AIReportResult]:
        __ctx__, __result__ = self.__options.merge_options(baml_options).create_async_stream(function_name="GenerateAuditReport", args={
            "company_name": company_name,"standard_id": standard_id,"auditor_name": auditor_name,"checklist_json": checklist_json,"output_language": output_language,"corrections": corrections,
        })
        return baml_py.BamlStream[stream_types.AIReportResult, types.AIReportResult](
          __result__,
          lambda x: typing.cast(stream_types.AIReportResult, x.cast_to(types, types, stream_types, True, __runtime__)),
          lambda x: typing.cast(types.AIReportResult, x.cast_to(types, types, stream_types, False, __runtime__)),
          __ctx__,
        )
```

- [ ] **Step 4: Update `baml_client/async_client.py` — `BamlHttpRequestClient.GenerateAuditReport`**

Find line (currently line 129):
```python
    async def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,
```
Replace the entire method (lines 129-135) with:
```python
    async def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,corrections: str,
        baml_options: BamlCallOptions = {},
    ) -> baml_py.baml_py.HTTPRequest:
        __result__ = await self.__options.merge_options(baml_options).create_http_request_async(function_name="GenerateAuditReport", args={
            "company_name": company_name,"standard_id": standard_id,"auditor_name": auditor_name,"checklist_json": checklist_json,"output_language": output_language,"corrections": corrections,
        }, mode="request")
        return __result__
```

- [ ] **Step 5: Update `baml_client/async_client.py` — `BamlHttpStreamRequestClient.GenerateAuditReport`**

Find line (currently line 144):
```python
    async def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,
```
Replace the entire method (lines 144-150) with:
```python
    async def GenerateAuditReport(self, company_name: str,standard_id: str,auditor_name: str,checklist_json: str,output_language: str,corrections: str,
        baml_options: BamlCallOptions = {},
    ) -> baml_py.baml_py.HTTPRequest:
        __result__ = await self.__options.merge_options(baml_options).create_http_request_async(function_name="GenerateAuditReport", args={
            "company_name": company_name,"standard_id": standard_id,"auditor_name": auditor_name,"checklist_json": checklist_json,"output_language": output_language,"corrections": corrections,
        }, mode="stream")
        return __result__
```

- [ ] **Step 6: Update `baml_client/sync_client.py` — all 4 `GenerateAuditReport` signatures**

The 4 methods in `sync_client.py` are at approximately these lines (search for `def GenerateAuditReport`):
- `BamlSyncClient.GenerateAuditReport`: line ~97
- `BamlStreamClient.GenerateAuditReport`: line ~120
- `BamlHttpRequestClient.GenerateAuditReport`: line ~140
- `BamlHttpStreamRequestClient.GenerateAuditReport`: line ~155

For each of the 4 methods, make these changes (mirroring `async_client.py`):
1. Add `,corrections: str` to the `def GenerateAuditReport(self, ..., output_language: str,` signature line
2. Add `"corrections": corrections,` to the args dict passed to `call_function_sync` / `create_sync_stream` / `create_http_request_sync`
3. **CRITICAL — internal stream call in `BamlSyncClient` only:** The `BamlSyncClient.GenerateAuditReport` method has an `if 'on_tick' in baml_options:` branch that calls `self.stream.GenerateAuditReport(...)` with keyword arguments. This call is NOT an "args dict" — it passes positional args by keyword. You MUST also add `corrections=corrections` to this internal call, e.g.:
   ```python
   __stream__ = self.stream.GenerateAuditReport(company_name=company_name,standard_id=standard_id,auditor_name=auditor_name,checklist_json=checklist_json,output_language=output_language,corrections=corrections,
       baml_options=baml_options)
   ```
   Omitting this will cause a missing argument error at runtime when `on_tick` is used.

Sync equivalents for the args-dict methods:
- `call_function_async` → `call_function_sync`
- `create_async_stream` → `create_sync_stream`
- `create_http_request_async` → `create_http_request_sync`
- `BamlStream` → `BamlSyncStream`

Pattern: for each of the 4 `GenerateAuditReport` methods in `sync_client.py`, add `,corrections: str` to the signature line and `"corrections": corrections,` to the args dict.

- [ ] **Step 7: Update `baml_client/inlinedbaml.py`**

`inlinedbaml.py` has a single `_file_map` dict with TWO keys: `"audit_report.baml"` and `"clients.baml"`. Only replace the value for `"audit_report.baml"`. Do NOT touch or remove the `"clients.baml"` entry.

The `"audit_report.baml"` value is a single very long string on one line. Replace only that string value (between the `"audit_report.baml":` key and the trailing `,` before `"clients.baml"`). The old value starts with `"class MitigationPlan {` and ends with `}\n"`. Replace it with the new inlined string that includes `corrections: string` in the function signature and the corrections block in the prompt:

```python
    "audit_report.baml": "class MitigationPlan {\n  action string @description(\"Concrete remediation action\")\n  priority \"High\" | \"Medium\" | \"Low\"\n  estimated_effort string @description(\"e.g. '2 days', '1 week'\")\n}\n\nclass FindingDetail {\n  clause string\n  status string\n  risk_summary string @description(\"1-2 sentence risk explanation\")\n  mitigation MitigationPlan\n}\n\nclass AIReportResult {\n  executive_summary string @description(\"3-5 sentence professional summary of overall compliance posture\")\n  findings FindingDetail[]\n  overall_risk_rating \"Critical\" | \"High\" | \"Medium\" | \"Low\"\n}\n\nfunction GenerateAuditReport(\n  company_name: string,\n  standard_id: string,\n  auditor_name: string,\n  checklist_json: string,\n  output_language: string,\n  corrections: string\n) -> AIReportResult {\n  client DefaultLLM\n  prompt #\"\n    You are a senior ISO compliance auditor. Analyze the following audit checklist\n    and produce a structured professional report.\n\n    Company: {{ company_name }}\n    Standard: {{ standard_id }}\n    Auditor: {{ auditor_name }}\n\n    Checklist (JSON):\n    {{ checklist_json }}\n\n    {% if corrections %}\n    AUDITOR CORRECTIONS / ADDITIONAL CONTEXT:\n    {{ corrections }}\n    Please incorporate the above corrections and context into your analysis and report.\n    {% endif %}\n\n    IMPORTANT INSTRUCTIONS:\n    - Write ALL text output (executive_summary, risk_summary, mitigation actions) in {{ output_language }}.\n    - The `findings` array MUST only contain items with status \"Non-Conformity\" or \"Observation\".\n    - Do NOT include \"Conformity\" or \"Unanswered\" items in the findings array.\n    - Mention conforming areas briefly in the executive_summary only.\n    - Keep each risk_summary to 1-2 sentences maximum.\n    - Keep each mitigation action concise (2-3 sentences maximum).\n\n    {{ ctx.output_format }}\n  \"#\n}\n",
```

- [ ] **Step 8: Verify syntax**

```bash
python -c "from baml_client.async_client import b; from baml_client.inlinedbaml import get_baml_files; print('OK')"
```
Expected: `OK`

---

### Task 4: Deploy FastAPI changes to Docker container

**Files:** (copy to running container)

- [ ] **Step 1: Copy all modified Python/BAML files into the container**

Run from `C:\dev\_antigravity\gapnext app\gapnext-fullstack\gapnext-fastapi-v1 cc\code\gapnext-ai-pipeline`:
```bash
docker cp src/models/wp_export.py   gapnext-ai-pipeline-gapnext-ai-1:/app/src/models/wp_export.py
docker cp src/models/audit.py        gapnext-ai-pipeline-gapnext-ai-1:/app/src/models/audit.py
docker cp src/services/wp_adapter.py gapnext-ai-pipeline-gapnext-ai-1:/app/src/services/wp_adapter.py
docker cp src/services/baml_service.py gapnext-ai-pipeline-gapnext-ai-1:/app/src/services/baml_service.py
docker cp baml_src/audit_report.baml gapnext-ai-pipeline-gapnext-ai-1:/app/baml_src/audit_report.baml
docker cp baml_client/async_client.py gapnext-ai-pipeline-gapnext-ai-1:/app/baml_client/async_client.py
docker cp baml_client/sync_client.py  gapnext-ai-pipeline-gapnext-ai-1:/app/baml_client/sync_client.py
docker cp baml_client/inlinedbaml.py  gapnext-ai-pipeline-gapnext-ai-1:/app/baml_client/inlinedbaml.py
```

- [ ] **Step 2: Restart the container**

```bash
docker restart gapnext-ai-pipeline-gapnext-ai-1
```

- [ ] **Step 3: Verify container started cleanly**

```bash
docker logs gapnext-ai-pipeline-gapnext-ai-1 --tail 20
```
Expected: No Python import errors. Should see uvicorn startup message.

---

## Chunk 2: WordPress Changes

### Task 5: DB migration — version bump + new table

**Files:**
- Modify: `gapnext-wp.php`
- Modify: `includes/class-installer.php`

- [ ] **Step 1: Bump version in `gapnext-wp.php`**

Change the `Version:` header tag in the plugin comment block (line 6):
```php
 * Version:     1.1.0
```

Change the `GAPNEXT_WP_VERSION` constant (line 18):
```php
define( 'GAPNEXT_WP_VERSION', '1.1.0' );
```

- [ ] **Step 2: Add new table to `includes/class-installer.php`**

In `create_tables()`, after the `$sql_submissions` variable declaration (after line 61, before `require_once`), add:

```php
        $sql_generations = "CREATE TABLE {$wpdb->prefix}gapnext_ai_report_generations (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            submission_id BIGINT(20) UNSIGNED NOT NULL,
            uuid VARCHAR(36) NOT NULL DEFAULT '',
            download_url VARCHAR(500) NOT NULL DEFAULT '',
            comments TEXT NOT NULL,
            generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY submission_id (submission_id)
        ) $charset;";
```

Then add `dbDelta( $sql_generations );` after `dbDelta( $sql_submissions );` and **before** the `update_option( 'gapnext_wp_db_version', ... )` call that follows:
```php
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql_audits );
        dbDelta( $sql_submissions );
        dbDelta( $sql_generations );
        // update_option( 'gapnext_wp_db_version', GAPNEXT_WP_VERSION ); ← already exists, leave it here
```

- [ ] **Step 3: Verify DB migration triggers**

Load the WordPress admin at `http://wpdevtest1.local/wp-admin/`. Navigate to any page. Then check the DB — the `wp_gapnext_ai_report_generations` table must exist.

To verify via WP-CLI (if available):
```bash
wp db query "DESCRIBE wp_gapnext_ai_report_generations"
```
Expected: 6 columns — id, submission_id, uuid, download_url, comments, generated_at.

---

### Task 6: `class-ai-report.php` — add `$comments`, payload, history insert

**Files:**
- Modify: `includes/class-ai-report.php`

- [ ] **Step 1: Add `$comments` parameter to `generate_for_submission()`**

Change the method signature (currently line 23):
```php
    public function generate_for_submission( int $submission_id, string $comments = '' ): array|WP_Error {
```

- [ ] **Step 2: Add `corrections` to the API payload**

In step 4 (build payload), add `'corrections'` under `'meta'` (after `'score'`):
```php
        $payload = [
            'meta'      => [
                'standard'     => $sub->standard_id,
                'language'     => $sub->language,
                'submitted_at' => $sub->submitted_at,
                'score'        => (float) $sub->score,
                'corrections'  => $comments,
            ],
```

- [ ] **Step 3: Insert history row on success**

After the `$wpdb->update(...)` call (after line 104), add the history insert:
```php
        // Log this generation to history
        $wpdb->insert(
            $wpdb->prefix . 'gapnext_ai_report_generations',
            [
                'submission_id' => $submission_id,
                'uuid'          => $result['uuid']         ?? '',
                'download_url'  => $result['download_url'] ?? '',
                'comments'      => $comments,
                'generated_at'  => current_time( 'mysql', true ),
            ],
            [ '%d', '%s', '%s', '%s', '%s' ]
        );
```

- [ ] **Step 4: Verify full method reads correctly**

The full `generate_for_submission` method should now:
1. Accept `( int $submission_id, string $comments = '' )`
2. Include `'corrections' => $comments` in the API payload meta
3. After `$wpdb->update(...)`, insert a row into `gapnext_ai_report_generations`
4. Return `$result`

---

### Task 7: `class-audit-manager.php` — AJAX handler, history helper, view state

**Files:**
- Modify: `includes/class-audit-manager.php`

#### Step 1: Update AJAX handler `handle_ai_generate()`

- [ ] **Step 1: Read `$comments` and pass to `generate_for_submission()`**

In `handle_ai_generate()` (around line 906), replace:
```php
        $result = ( new GapNext_AI_Report() )->generate_for_submission( $submission_id );
```
With:
```php
        $comments = sanitize_textarea_field( wp_unslash( $_POST['comments'] ?? '' ) );
        $result = ( new GapNext_AI_Report() )->generate_for_submission( $submission_id, $comments );
```

- [ ] **Step 2: Return `comments` in success payload**

Change the success response (currently line 917):
```php
        wp_send_json_success( $result ); // {download_url, uuid, file_size_kb}
```
To:
```php
        wp_send_json_success( array_merge( $result, [ 'comments' => $comments ] ) );
```

#### Step 2: Add `get_ai_report_history()` private static helper

- [ ] **Step 3: Add `get_ai_report_history()` before `handle_ai_generate()`**

Insert this private static method before the `handle_ai_generate()` method (before line 898):
```php
    /**
     * Fetch all AI report generation rows for a submission, newest first.
     *
     * @param  int $submission_id
     * @return array Array of stdClass rows (uuid, download_url, comments, generated_at)
     */
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

#### Step 3: Replace "report exists" state in `render_submission_view()`

- [ ] **Step 4: Replace the "report already exists" block**

Find and replace this block (lines 725–733):
```php
                <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                    <!-- Report already exists: show download only -->
                    <p style="color:#6b7280;font-size:13px;margin:6px 0 16px">
                        <?php esc_html_e( 'An AI-generated compliance report is available for this submission.', 'gapnext-wp' ); ?>
                    </p>
                    <a href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $sub->ai_report_url ) ); ?>"
                       target="_blank" class="button button-primary">
                        ⬇ <?php esc_html_e( 'Download AI Report', 'gapnext-wp' ); ?>
                    </a>
```

With the expanded version including latest download link, regenerate section, log panel, and history table:
```php
                <?php if ( ! empty( $sub->ai_report_url ) ) : ?>
                    <!-- Report already exists: show latest download + regenerate + history -->

                    <!-- Latest report download -->
                    <p style="color:#6b7280;font-size:13px;margin:6px 0 8px">
                        <?php esc_html_e( 'Latest report:', 'gapnext-wp' ); ?>
                    </p>
                    <a id="gapnext-ai-latest-download"
                       href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $sub->ai_report_url ) ); ?>"
                       target="_blank" class="button button-primary">
                        ⬇ <?php esc_html_e( 'Download AI Report', 'gapnext-wp' ); ?>
                    </a>

                    <!-- Regenerate section -->
                    <div style="margin-top:24px">
                        <p style="color:#374151;font-size:13px;margin:0 0 6px;font-weight:600">
                            <?php esc_html_e( 'Regenerate AI Report', 'gapnext-wp' ); ?>
                        </p>
                        <p style="color:#6b7280;font-size:12px;margin:0 0 8px">
                            <?php esc_html_e( 'Corrections or additional context for regeneration (optional):', 'gapnext-wp' ); ?>
                        </p>
                        <textarea id="gapnext-ai-corrections" rows="3"
                            style="width:100%;max-width:520px;font-size:13px;padding:8px;border:1px solid #d1d5db;border-radius:6px;resize:vertical"
                            placeholder="<?php esc_attr_e( 'e.g. Please focus more on ISO clause 6.1 and update the risk rating for clause 8.3\u2026', 'gapnext-wp' ); ?>"></textarea>
                        <br>
                        <button type="button" id="gapnext-ai-regenerate-view" class="button button-secondary" style="margin-top:8px">
                            <?php esc_html_e( 'Regenerate AI Report', 'gapnext-wp' ); ?>
                        </button>
                    </div>

                    <!-- Regenerate progress log panel (hidden until regeneration starts) -->
                    <div id="gapnext-ai-regen-log-panel" style="display:none;margin-top:20px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:20px 24px;max-width:520px">
                        <div style="font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.6px;margin-bottom:14px">
                            <?php esc_html_e( 'Progress', 'gapnext-wp' ); ?>
                        </div>
                        <div id="gapnext-ai-regen-log-steps"></div>
                    </div>

                    <!-- Generation history -->
                    <!-- IMPORTANT: The table is always rendered (even when empty) so that the JS
                         regenerate success handler can always find #gapnext-ai-history-table tbody
                         to prepend new rows. Pre-migration submissions (ai_report_url set but no
                         history rows yet) would have an empty tbody on first page load after deploy. -->
                    <?php $history = self::get_ai_report_history( $sub->id ); ?>
                    <div style="margin-top:32px">
                        <h3 style="font-size:14px;font-weight:700;color:#374151;margin:0 0 12px">
                            <?php esc_html_e( 'Generation History', 'gapnext-wp' ); ?>
                        </h3>
                        <table id="gapnext-ai-history-table" class="wp-list-table widefat fixed striped" style="max-width:720px">
                            <thead>
                                <tr>
                                    <th style="width:160px"><?php esc_html_e( 'Date', 'gapnext-wp' ); ?></th>
                                    <th><?php esc_html_e( 'Comments', 'gapnext-wp' ); ?></th>
                                    <th style="width:110px"><?php esc_html_e( 'Download', 'gapnext-wp' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $history as $row ) : ?>
                                <tr>
                                    <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $row->generated_at ) ) ); ?></td>
                                    <td><?php echo $row->comments ? esc_html( $row->comments ) : '&mdash;'; ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( add_query_arg( 'token', get_option( 'gapnext_ai_api_key', '' ), $row->download_url ) ); ?>"
                                           target="_blank">⬇ <?php esc_html_e( 'Report', 'gapnext-wp' ); ?></a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
```

**Note:** The `<?php else : ?>` branch (no report state) and CSS `<style>` block that follow remain completely unchanged.

---

### Task 8: `gapnext-admin-ai.js` — guard change + regenerate handler

**Files:**
- Modify: `assets/gapnext-admin-ai.js`

- [ ] **Step 1: Change the view-page guard (line 51)**

Find:
```javascript
    if ($('#gapnext-ai-generate-view').length === 0) return;
```
Replace with:
```javascript
    if ($('#gapnext-ai-generate-view').length === 0 && $('#gapnext-ai-regenerate-view').length === 0) return;
```

- [ ] **Step 2: Add regenerate handler block after `$generateBtn.on('click', startGeneration)`**

Add the following block at the very end of the file, after line 207 (`$generateBtn.on('click', startGeneration);`), before the closing `});`:

```javascript
    // ── Regenerate handler ─────────────────────────────────────────────────────
    // Only active when #gapnext-ai-regenerate-view is in the DOM (report exists state).
    var $regenBtn        = $('#gapnext-ai-regenerate-view');
    var $regenPanel      = $('#gapnext-ai-regen-log-panel');
    var $regenSteps      = $('#gapnext-ai-regen-log-steps');
    var $correctionsArea = $('#gapnext-ai-corrections');
    var $latestDownload  = $('#gapnext-ai-latest-download');
    var regenTimers      = [];
    var regenCurrentStep = -1;

    // Scoped helpers — operate on regen panel, NOT the generate flow's $logSteps / currentStep.
    function regenActivateStep(index) {
        if (index > 0) {
            $regenSteps.children().eq(index - 1)
                .removeClass('active')
                .addClass('done')
                .find('.gapnext-ai-step-icon').text('\u2713');
        }
        $regenSteps.children().eq(index)
            .addClass('active')
            .find('.gapnext-ai-step-icon').html(dotHtml());
        regenCurrentStep = index;
    }

    function regenTickAllRemaining() {
        if (regenCurrentStep < 0) {
            regenActivateStep(0);
        }
        var from = regenCurrentStep >= 0 ? regenCurrentStep : 0;
        $regenSteps.children().each(function (i) {
            if (i >= from) {
                $(this).removeClass('active').addClass('done')
                    .find('.gapnext-ai-step-icon').text('\u2713');
            }
        });
    }

    function regenClearTimers() {
        regenTimers.forEach(function (t) { clearTimeout(t); });
        regenTimers = [];
    }

    function startRegeneration() {
        var corrections = $correctionsArea.val().trim();

        $regenBtn.prop('disabled', true);
        $regenSteps.empty();
        $.each(STEPS, function (i, text) {
            $regenSteps.append(buildStepEl(text));
        });
        $regenPanel.show();
        regenCurrentStep = -1;

        $.each(STEP_DELAYS, function (i, delay) {
            regenTimers.push(setTimeout(function () {
                regenActivateStep(i);
            }, delay));
        });

        $.post(gapnextAI.ajaxUrl, {
            action:        'gapnext_ai_generate',
            nonce:          gapnextAI.nonce,
            submission_id:  gapnextAI.submissionId,
            comments:       corrections
        })
        .done(function (resp) {
            regenClearTimers();
            if (resp.success && resp.data && resp.data.download_url) {
                regenTickAllRemaining();

                $regenSteps.append(
                    '<div style="margin-top:12px;font-size:13px;font-weight:600;color:#166534">' +
                    '\u2705 Report regenerated successfully!' +
                    '</div>'
                );

                // Update the "latest download" link
                var newUrl = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
                $latestDownload.attr('href', newUrl);

                // Prepend new row to history table
                // (#gapnext-ai-history-table is guaranteed to exist — first generation
                //  always inserts a history row before the page enters "report exists" state)
                var date    = new Date().toLocaleString();
                var comment = resp.data.comments || '\u2014';
                var dlUrl   = resp.data.download_url + '?token=' + encodeURIComponent(gapnextAI.apiKey);
                $('<tr>')
                    .append($('<td>').text(date))
                    .append($('<td>').text(comment))
                    .append($('<td>').append($('<a>').attr({href: dlUrl, target: '_blank'}).text('\u2b07 Report')))
                    .prependTo('#gapnext-ai-history-table tbody');

                // Reset after 1s
                setTimeout(function () {
                    $correctionsArea.val('');
                    $regenPanel.hide();
                    $regenSteps.empty();
                    regenCurrentStep = -1;
                    $regenBtn.prop('disabled', false);
                }, 1000);

            } else {
                regenOnError((resp.data && resp.data.message) ? resp.data.message : gapnextAI.errorText);
            }
        })
        .fail(function () {
            regenClearTimers();
            regenOnError(gapnextAI.networkErrorText);
        });
    }

    function regenOnError(message) {
        var failIndex = regenCurrentStep >= 0 ? regenCurrentStep : 0;
        $regenSteps.children().eq(failIndex)
            .removeClass('active').addClass('failed')
            .find('.gapnext-ai-step-icon').text('\u2717');

        $regenSteps.append(
            '<div style="margin-top:10px;font-size:13px;color:#991b1b">' +
            '\u274c ' + $('<span>').text(message).html() +
            '</div>'
        );

        var $tryAgain = $('<button type="button" class="button" style="margin-top:14px">')
            .text('Try Again');
        $tryAgain.on('click', function () {
            regenClearTimers();
            regenCurrentStep = -1;
            $regenPanel.hide();
            $regenSteps.empty();
            $regenBtn.prop('disabled', false);
        });
        $regenSteps.append($tryAgain);
    }

    $regenBtn.on('click', startRegeneration);
```

- [ ] **Step 3: Manual test — regenerate flow**

In the WordPress admin, open a submission that already has an AI report.

Verify:
1. Page loads with "Available" badge, Download AI Report button, Regenerate section, and history table (at least 1 row)
2. Click "Regenerate AI Report" with empty textarea → progress panel appears, steps animate, success line shows after completion, latest download href updates, new row prepended to history table with "—" in comments column
3. Fill textarea with some text, click Regenerate → new row shows the corrections text in comments column; docker logs show `corrections` in the request payload
4. Click "Try Again" after triggering an error → log panel hides, regenerate button re-enables

- [ ] **Step 4: Manual test — guard behavior**

1. On the submissions list page: no JS errors in the browser console (neither `#gapnext-ai-generate-view` nor `#gapnext-ai-regenerate-view` is present — guard exits cleanly)
2. On a view page with no report (only `#gapnext-ai-generate-view`): regenerate variables are jQuery empty sets, no errors

---

## Testing Checklist

Reference the spec's testing section (`docs/superpowers/specs/2026-03-17-ai-report-regenerate-history-design.md`):

1. After version bump to `1.1.0` and page load: `gapnext_ai_report_generations` table exists in the DB
2. First generation (no report): generate → history row inserted with empty comments, `ai_report_url` updated, view page shows "report exists" state on next load
3. Regeneration without comments: new history row with empty comments, latest download link updates, history shows 2 rows newest-first
4. Regeneration with comments: fill textarea → new row stores the comment text
5. FastAPI receives `corrections` field: verify via `docker logs gapnext-ai-pipeline-gapnext-ai-1` that the request JSON includes the `corrections` key with the typed text
6. BAML prompt — **non-empty corrections**: regenerated report text reflects the corrections. **Empty corrections**: the "AUDITOR CORRECTIONS" block does NOT appear in the BAML prompt (empty string is falsy in `{% if corrections %}`)
7. History: dates formatted `dd/mm/yyyy HH:mm`, empty comments shown as "—", all download links include `?token=`
8. JS guard: no JS errors on the submissions list page (neither button element is present — guard exits cleanly)
9. JS guard: on a view page with no report (only `#gapnext-ai-generate-view`), regenerate variables are jQuery empty sets — no errors
10. Error path: invalid API key → regenerate log shows error, Try Again re-enables the regenerate button and hides the panel
