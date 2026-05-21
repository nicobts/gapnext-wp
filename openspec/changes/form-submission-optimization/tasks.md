## 1. Database & Backend Foundation

- [x] 1.1 Add `filling_mode VARCHAR(20) NOT NULL DEFAULT 'assisted'` column to `gapnext_submissions` table in `class-installer.php` `create_tables()`
- [x] 1.2 Update `class-ajax.php` `handle_autosave()` to accept and persist `filling_mode` field from POST data
- [x] 1.3 Update `class-ajax.php` `handle_submit()` to accept and persist `filling_mode` field, make consultant fields conditionally required (skip validation when `filling_mode = 'self'`)
- [x] 1.4 Update `class-ajax.php` `handle_autosave()` to accept and persist `last_step` in draft data (store alongside answers or as separate POST field)

## 2. Mode Selector (Step 0)

- [x] 2.1 Add mode selector markup to `includes/views/form.php` — new step 0 div with two clickable cards ("Self-Assessment" / "Consultant-Assisted") and bilingual labels
- [x] 2.2 Add hidden `filling_mode` input field to the form
- [x] 2.3 Add CSS for mode selector cards in `assets/gapnext-wp.css` — two-column card layout with hover/selected states
- [x] 2.4 Add JS logic in `assets/gapnext-wp.js` to handle mode selection: store value in hidden field, advance to step 1, show/hide consultant fieldset based on mode

## 3. Conditional Consultant Fields

- [x] 3.1 Update `includes/views/form.php` — wrap consultant fieldset with a container div that can be toggled, adjust "Internal Contact" label to change per mode (bilingual)
- [x] 3.2 Add JS logic to toggle consultant fieldset visibility and required attributes based on `filling_mode` value
- [x] 3.3 Update step 1 validation in JS `validateStep()` to skip consultant field checks when mode is `'self'`

## 4. Multi-Step Checklist Wizard

- [x] 4.1 Restructure `includes/views/form.php` — replace single step-2 div with a loop that creates one `gapnext-step-content` div per level-1 section, each with its questions and prev/next navigation buttons
- [x] 4.2 Update step indicator markup in `includes/views/form.php` — generate dynamic step indicators for mode selector + company info + N sections + review, with per-section question counts as data attributes
- [x] 4.3 Add hidden `last_step` input field to store the current step index for draft persistence
- [x] 4.4 Pass section metadata (count per section, section names) to JS via `wp_localize_script` in `class-checklist.php`

## 5. JavaScript Step Navigation Overhaul

- [x] 5.1 Refactor `showStep()` in `gapnext-wp.js` to handle dynamic step count (step 0 = mode, step 1 = company, steps 2..N+1 = sections, step N+2 = review)
- [x] 5.2 Update `validateStep()` to handle the new step numbering (step 1 validates company fields, section steps need no validation)
- [x] 5.3 Implement direct step navigation — click handler on step indicators to jump to any step
- [x] 5.4 Implement per-section progress tracking — update step indicator badges (e.g., "3/5") when answers change within each section
- [x] 5.5 Add completed/checkmark state to step indicators when all questions in a section are answered

## 6. Enhanced Autosave & Draft Resume

- [x] 6.1 Update `doAutoSave()` to include `filling_mode` and `last_step` in the POST payload
- [x] 6.2 Add visual save status indicator element to form markup (saving/saved/error states)
- [x] 6.3 Update `doAutoSave()` JS to show "Saving...", then "Saved at HH:MM" on success, or "Save failed" on error
- [x] 6.4 Trigger immediate autosave on step forward navigation (before transition)
- [x] 6.5 Update `collectDraftData()` to include `filling_mode` and `last_step` fields
- [x] 6.6 Embed server-side draft data via `wp_localize_script` in `class-checklist.php` when a draft exists for this audit, so the form hydrates from server data (not just localStorage)
- [x] 6.7 Update `populateFromDraft()` to restore `filling_mode` (skip mode selector) and `last_step` (navigate to last active step), show dismissible "Draft restored" banner

## 7. CSS & Visual Polish

- [x] 7.1 Style the new step indicator bar — compact horizontal layout with section numbers, truncated names, progress badges, and active/completed states
- [x] 7.2 Add responsive handling for step indicator with many sections (scrollable on small screens)
- [x] 7.3 Style the save status indicator (subtle, non-intrusive placement near progress bar)
- [x] 7.4 Style the draft restored banner (dismissible, informational)
- [x] 7.5 Ensure all new elements work with existing form theme and RTL-safe structure
