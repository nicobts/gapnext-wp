## Why

Gap analysis checklists contain dozens to hundreds of questions (e.g. ISO 9001 has 100+ clauses). The current form dumps all questions into a single step, which is overwhelming and discourages completion. Additionally, the form always shows consultant fields even when the company is filling it independently, adding unnecessary friction. While basic autosave exists (30s interval + localStorage), the draft experience needs improvement — users need clear visual feedback about save state and a reliable way to resume across sessions and devices.

## What Changes

- **Two-path entry mode**: A new "mode selector" screen at the start of the form lets the user choose between "Company Self-Assessment" (no consultant fields required) and "Consultant-Assisted" (current behavior with consultant fields mandatory). The selected mode is stored with the submission and determines field requirements and labels throughout.
- **Enhanced draft/autosave experience**: Improve the existing autosave to provide better UX — visual save indicator (saved/saving/error states), server-side draft as primary storage (localStorage as fallback only), draft resume screen when returning to an audit with an existing draft, and per-section save triggers (save on step navigation, not just on a timer).
- **Multi-step checklist wizard**: Replace the single "Checklist" step with one step per section group (level-1 clauses). Each section becomes its own navigable step with prev/next controls, section progress indicators, and a step sidebar/breadcrumb showing completion state per section. The final "Review & Submit" step remains.

## Capabilities

### New Capabilities
- `filling-mode`: Two-path form entry — self-assessment vs consultant-assisted mode selection, conditional field visibility and validation
- `multi-step-checklist`: Section-based wizard navigation — each level-1 clause group becomes a step, with per-section progress tracking and step indicators

### Modified Capabilities
- None (no existing spec files to modify)

## Impact

- **Files modified**: `includes/views/form.php` (major restructure), `assets/gapnext-wp.js` (step navigation, autosave, mode logic), `assets/gapnext-wp.css` (new step indicators, mode selector, save status UI), `includes/class-ajax.php` (accept filling_mode field in submit/autosave), `includes/class-checklist.php` (pass mode data to JS)
- **Database**: `gapnext_submissions` table needs a new `filling_mode` column (varchar, 'self' or 'assisted')
- **DB migration**: Installer `create_tables()` updated; existing rows default to 'assisted'
- **No breaking changes**: Existing submissions, exports, and remediation system are unaffected. The consultant fields are still stored when provided.
