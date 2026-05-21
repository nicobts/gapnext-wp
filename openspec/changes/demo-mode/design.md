## Context

GapNext WP serves consultants who create audit links for their clients. The checklist form, results page, and PDF report are the core product. Currently every audit requires a consultant to create a link and share it. There is no public-facing, self-service entry point for potential customers to experience the product.

The plugin already supports multiple `access_mode` values on audits (`public`, `login_required`). The form already has a self-assessment mode. The PDF export uses TCPDF which supports alpha-channel drawing for watermarks. These existing capabilities make adding a demo mode a targeted extension rather than a ground-up feature.

## Goals / Non-Goals

**Goals:**
- Allow consultants to create "demo" audit links that limit the checklist to the first N questions
- Demo submissions produce a demo results page and watermarked demo PDF
- Capture contact info as leads (company name, email) visible in admin
- Make it easy for consultants to embed demo links on their marketing sites
- Admin setting to configure the demo question limit (default: 15)

**Non-Goals:**
- Public demo page that works without any audit link (still requires a consultant to create a demo audit)
- Demo-specific landing page or marketing copy (the consultant handles their own site design)
- Analytics or conversion tracking (out of scope — consultants can use their own tools)
- Limiting demo submissions per visitor (no rate limiting for now)
- Demo-specific email notifications (existing notification emails apply as-is)

## Decisions

### 1. Reuse `access_mode` column with value `demo`

Add `demo` as a valid `access_mode` value on `gapnext_audits`. No schema change needed — the column is `VARCHAR(20)`.

**Why not a separate boolean column?** `access_mode` already controls how the audit behaves (`public` vs `login_required`). Demo is another access behavior: public + question-limited. Keeping it in the same column avoids a separate flag and simplifies conditionals.

### 2. Slice questions in `class-checklist.php` at render time

When the audit's `access_mode === 'demo'`, filter `$standard['clauses']` before passing to the form view:
1. Walk the clauses array
2. Keep all level-1 headers that contain at least one included level-2 question
3. Keep only the first N level-2 questions (N from `gapnext_demo_question_limit` option, default 15)
4. Trim trailing level-1 headers that have no included questions

This means the form view, JS step navigation, autosave, and section metadata all work unchanged — they just see fewer questions.

**Why not filter in JS?** Server-side slicing means the full standard data never reaches the browser for demo users. This is cleaner and prevents data leakage.

### 3. Demo submissions use `status = 'demo'` instead of `'submitted'`

When `handle_submit()` detects a demo audit, set `status = 'demo'` instead of `'submitted'`. This:
- Distinguishes demo from real submissions in the admin list
- Allows filtering/badging in the submissions table
- Prevents demo submissions from polluting real audit data

Demo submissions also force `filling_mode = 'self'` — consultant fields are irrelevant for demos.

### 4. PDF watermark via TCPDF `SetAlpha()` + `Text()`

Add a method to `GapNext_TCPDF` that draws a rotated, semi-transparent "GapNext DEMO" text on every page. Call it from the existing `Header()` method when a public flag (`$is_demo`) is set on the TCPDF instance.

Implementation:
```
$pdf->StartTransform();
$pdf->Rotate( 45, 105, 148 );
$pdf->SetAlpha( 0.08 );
$pdf->SetFont( 'helvetica', 'B', 60 );
$pdf->SetTextColor( 30, 64, 175 );
$pdf->Text( 30, 120, 'GapNext DEMO' );
$pdf->StopTransform();
$pdf->SetAlpha( 1 );
```

This runs once per page via the Header callback, placing the watermark behind content.

### 5. Demo results page: banner + upgrade CTA

When `class-results.php` detects `status === 'demo'`, inject:
- A prominent banner at the top: "This is a demo report — only N of X total questions were evaluated"
- An upgrade CTA section after the score summary: "Want the full gap analysis? Contact your consultant" with the consultant's email (from audit creator) or a generic "Get in touch" message
- The results themselves render normally but only show the demo questions (since the submission only contains demo answers)

### 6. Admin setting: demo question limit

New option `gapnext_demo_question_limit` (default: `15`) in the Settings page. Simple number input field. This controls how many level-2 questions appear in demo audits.

### 7. Demo access mode option in audit creation

Add `demo` to the access mode dropdown in `class-audit-manager.php`'s audit creation form. When selected, the audit link will serve the demo experience.

## Risks / Trade-offs

- **Question count varies by standard**: Some standards have sections with many questions per group. The first 15 questions might land mid-section. This is acceptable — the form handles partial sections well since we already have dynamic step generation.
- **Demo PDF file size**: With only ~15 questions, the PDF will be very short (2-3 pages + cover). This is actually a benefit — quick to download, quick to evaluate.
- **No rate limiting**: A visitor could submit the demo form many times. Since demo submissions are low-cost (no evidence uploads, small data), this is acceptable. If it becomes a problem, rate limiting can be added later.
- **Draft reminders for demo audits**: The existing draft reminder feature will fire for demo drafts too. This is actually desirable — it reminds the visitor to come back and complete the demo, which is good for lead conversion.
