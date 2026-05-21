## Why

GapNext WP is used by consultants who sell gap analysis services. Currently, the only way for a potential client to understand the product is through a consultant's pitch. There is no self-service entry point — no way for a website visitor to experience the gap analysis process firsthand. A public demo mode would let visitors try a shortened version of the checklist (first ~15 questions), receive a demo results page and a watermarked PDF report, and then be funneled toward purchasing the full audit. This turns the website into a lead generation tool.

## What Changes

- **Demo audit type**: A new audit `access_mode` value (`demo`) that limits the checklist to the first N questions of the chosen standard (configurable, default 15)
- **Demo question slicing**: The checklist shortcode and form view render only the first N level-2 questions (plus their parent section headers) when serving a demo audit
- **Demo submission flow**: Demo submissions are stored with `status = 'demo'` and skip consultant fields entirely (always self-assessment mode)
- **Demo results page**: The results shortcode detects demo submissions and renders results with a prominent "DEMO" banner and a CTA to contact the consultant for the full audit
- **Demo PDF report**: The PDF export renders only the answered (demo) questions and adds a large diagonal "GapNext DEMO" watermark on every page
- **Demo CSV/MD exports**: Similarly limited to demo questions, with a "DEMO" header line
- **Lead capture**: Demo submissions capture company name + contact email as leads, visible in the admin submissions list with a "Demo" badge
- **Admin creation**: Consultants can create demo audit links from the admin panel by selecting the new "Demo" access mode

## Capabilities

### New Capabilities
- `demo-checklist`: Slicing standard questions to first N for demo audits, demo-specific form behavior (forced self-assessment, no consultant fields, demo banner)
- `demo-results`: Demo-aware results page with watermark banner, limited question display, and upgrade CTA
- `demo-pdf`: PDF generation with "GapNext DEMO" diagonal watermark on every page, limited to demo questions only
- `demo-admin`: Admin UI for creating demo audit links and viewing demo submissions as leads

### Modified Capabilities
- None

## Impact

- **Files modified**: `class-checklist.php` (question slicing for demo audits), `class-ajax.php` (demo submission handling), `class-export-pdf.php` (watermark + question filtering), `class-gapnext-tcpdf.php` (watermark rendering in Header/Footer), `class-results.php` / `views/results.php` (demo banner + CTA), `class-audit-manager.php` (demo access mode option), `class-installer.php` (new default setting), `class-admin.php` (demo question limit setting), `views/form.php` (demo banner)
- **New files**: None expected — all changes fit within existing class structure
- **Database**: No schema changes — uses existing `access_mode` column on audits and existing `status` column on submissions
- **No new dependencies**: Uses existing TCPDF watermark capabilities
