## Context

GapNext WP is a WordPress plugin for multi-standard gap analysis audits. The form is rendered by `[gapnext_checklist]` shortcode via `includes/views/form.php` and driven by `assets/gapnext-wp.js`. The current flow is a 3-step wizard: Step 1 (Company & Contacts), Step 2 (all checklist questions in one page with accordions), Step 3 (Review & Submit).

Autosave already exists — a 30-second interval timer posts to `wp_ajax_gapnext_autosave`, and drafts are mirrored to localStorage. Draft restoration happens on page load. The `gapnext_submissions` table stores drafts with `status = 'draft'`.

Standards have level-1 clauses (section headings) and level-2 clauses (actual questions). The form already groups questions by section in accordions. There are typically 7-12 sections per standard, each with 3-20 questions.

## Goals / Non-Goals

**Goals:**
- Let users choose between self-assessment and consultant-assisted modes, reducing friction for companies filling independently
- Break the monolithic checklist step into per-section steps so each is manageable
- Improve draft save UX with clear visual feedback and save-on-navigate behavior
- Ensure users can reliably resume across sessions (server-side draft is authoritative)

**Non-Goals:**
- Offline support or PWA capabilities
- Changing the scoring algorithm or answer types
- Modifying PDF/CSV/MD export formats to reflect filling mode
- Real-time collaboration (multiple users editing simultaneously)
- Reworking the remediation system or results page

## Decisions

### 1. Mode selector as Step 0 (before company info)

Insert a new "Step 0" before the existing Step 1. This step shows two cards: "Self-Assessment" and "Consultant-Assisted". Selecting a mode stores it in a hidden field (`filling_mode`) and advances to Step 1.

**Why not a URL parameter or audit-level setting?** The consultant creates the audit link, but may not know in advance whether they'll be present. The person opening the link should decide. This keeps audit creation simple and the choice explicit.

**Behavior per mode:**
- **Self-assessment (`self`)**: Consultant fieldset in Step 1 is hidden. Consultant fields are not required. On submit, consultant fields are stored as empty strings. The form labels adjust (e.g., "Your Details" instead of "Internal Contact").
- **Consultant-assisted (`assisted`)**: Current behavior — all three fieldsets shown, consultant fields required.

The mode is saved with the draft and with the final submission in a new `filling_mode` column.

### 2. Multi-step checklist: one step per level-1 section

Replace Step 2 (all questions) with N steps, one per level-1 section. The step structure becomes:

```
Step 0: Mode selector
Step 1: Company & Contacts
Step 2: Section "4. Context of the Organization" (questions 4.1–4.4)
Step 3: Section "5. Leadership" (questions 5.1–5.3)
...
Step N+1: Review & Submit
```

**Step indicator**: Replace the current 3-step breadcrumb with a compact sidebar/top bar showing all steps. Each step displays its section number, name (truncated), and a completion badge (e.g., "3/5" answered). The active step is highlighted.

**Why per-section and not arbitrary chunks?** Sections are a natural grouping already understood by auditors. The data structure already groups by level-1 clauses. This avoids arbitrary page breaks and keeps the mental model aligned with the standard's structure.

### 3. Autosave triggers: on navigation + debounced timer

Keep the existing 30-second interval but add:
- **Save on step navigation** (next/prev): triggers an immediate save before transitioning
- **Save on field blur for text fields**: debounced at 2 seconds after last keystroke in a text/textarea field
- **Visual save indicator**: A small status element showing "Saving...", "Saved at HH:MM", or "Save failed — retrying..."

Server-side draft remains the authoritative source. localStorage acts as instant-restore cache for the current browser session only. When a server draft exists, it takes precedence on page load (checked via a hidden field or inline PHP data).

### 4. Draft resume flow

When the page loads and a server-side draft exists for this audit:
- The form is pre-populated from the draft data (already works via localStorage; enhance to also hydrate from server data embedded in the page via `wp_localize_script`)
- The mode selector step is skipped (mode already chosen)
- The user lands on Step 1 (or the last step they were on, stored in draft metadata)
- A dismissible banner says "Draft restored — last saved at [time]"

### 5. Database change

Add `filling_mode VARCHAR(20) NOT NULL DEFAULT 'assisted'` to `gapnext_submissions`. Handled by `dbDelta` in the installer — existing rows get the default value.

Store `last_step` in the draft's JSON data (inside the `answers` blob or a separate `draft_meta` field) rather than as a column, to avoid schema bloat for transient data.

## Risks / Trade-offs

- **More steps = more clicks**: Mitigated by per-section progress indicators and keyboard navigation. The trade-off is worthwhile since the current single-page approach is more overwhelming than a few extra clicks.
- **Draft conflicts if same audit opened in two tabs**: Low risk for single-tenant deployment. Server draft wins on save (last-write-wins). No conflict resolution needed.
- **Step count varies per standard**: Some standards have 7 sections, others 12+. The step indicator must handle variable counts gracefully (scrollable or compact mode).
- **Breaking existing bookmarked URLs**: No impact — the form URL remains the same (`?audit=UUID`). Step state is internal.
