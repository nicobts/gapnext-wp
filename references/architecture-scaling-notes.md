# GapNext WP — Architecture & Scaling Notes

## Document Purpose

This document captures key architectural decisions and their scaling implications, ensuring future developers understand the tradeoffs made and when to revisit them.

---

## 1. Deployment Model: Horizontal (Single-Tenant)

**Current model:** One WordPress instance = one consultant / consulting firm.

- Each consultant installs GapNext WP on their own WordPress site
- They manage their own clients, audits, and submissions
- There is no cross-instance data sharing or multi-tenant logic
- The `manage_options` capability gates all admin functionality (single admin per site)

**Implications:**
- No tenant isolation logic needed in the codebase
- No need for per-tenant rate limiting, storage quotas, or billing
- Plugin settings (logo, branding, API keys) apply globally to the single consultant
- The `gapnext_client` role represents audited companies, not other consultants

**When this changes:** If GapNext moves to a SaaS model (multi-tenant on a single WordPress Multisite or a dedicated platform), the plugin would need:
- Tenant-scoped data access (every query filtered by tenant ID)
- Per-tenant settings and branding
- Usage metering and billing hooks
- Shared hosting resource management (DB connections, file storage limits)

---

## 2. Event-Sourced Remediation Log

**Decision:** The `gapnext_remediation_log` table stores every change as an immutable event. Current state is derived by replaying events in order per question.

**Why this approach:**
- Full audit trail with timestamps is a compliance requirement
- Enables "what was the state on date X?" queries without snapshots
- The initial gap analysis submission (`gapnext_submissions.answers`) is never modified — it remains the immutable baseline
- Dashboard stats are computed by aggregating events

**Expected data volumes (single-tenant plugin):**

| Metric | Typical Range |
|--------|---------------|
| Active audits per consultant | 5-20 |
| Standards per company | 1-3 concurrent |
| Questions per standard | 30-120 |
| Events per question over full lifecycle | 3-10 |
| Total events per submission | 100-1,200 |
| Total events across all active audits | 500-24,000 |

At these volumes, replaying events per question or per submission is trivially fast (single indexed query, <100ms on any hosting).

**Performance characteristics:**
- Deriving state for one question: ~5-15 events to replay (microseconds)
- Deriving state for one submission (all questions): single query, GROUP BY question_ref, aggregate in PHP (~50-200 rows, <50ms)
- Dashboard aggregation across all audits: one query per audit, cached if needed

**When to add a materialized state cache:**

If any of these conditions become true, add a `gapnext_remediation_current_state` table that stores the latest computed state per question and is updated on each new event:

1. **Single submission exceeds ~5,000 events** (unlikely in normal use — would require ~50 changes per question)
2. **Dashboard page load exceeds 500ms** due to log aggregation
3. **SaaS model adopted** with hundreds of concurrent audits on a shared database
4. **Reporting features** need complex cross-submission analytics (trend analysis, benchmarking)

The migration path is straightforward: add the cache table, backfill from existing events, update `GapNext_Remediation_State` to read from cache instead of replaying, add a write-through update on each new event insert.

---

## 3. File Storage

**Current:** Evidence files stored in `wp-content/uploads/gapnext-evidence/{audit-uuid}/q-{ref}/`

**Scaling concern:** Not managed by WordPress Media Library. No automatic cleanup on audit/submission deletion.

**For SaaS:** Would need:
- Storage quotas per tenant
- Scheduled cleanup of orphaned files
- Possibly object storage (S3) instead of local filesystem
- CDN for evidence downloads

---

## 4. Database Tables Summary

| Table | Growth Pattern | Index Strategy |
|-------|---------------|----------------|
| `gapnext_audits` | Slow (5-20 active) | PK + uuid unique |
| `gapnext_submissions` | Slow (1 per audit) | PK + audit_uuid + status |
| `gapnext_remediation_log` | Moderate (100-1200 per submission) | PK + (submission_id, question_ref) composite + created_at |
| `gapnext_client_access` | Slow (1-3 per client user) | PK + user_id + audit_uuid |
| `gapnext_ai_report_generations` | Slow | PK + submission_id |

**Index recommendation for remediation_log:**
```sql
KEY submission_question (submission_id, question_ref, created_at)
KEY submission_type (submission_id, event_type)
KEY user_events (user_id, created_at)
```

The composite index `(submission_id, question_ref, created_at)` covers the primary access pattern: "get all events for question X in submission Y, ordered by time."

---

## 5. Future Migration Path: Plugin to SaaS

When transitioning from single-tenant plugin to multi-tenant SaaS:

1. **Add tenant_id** to all tables (or use WordPress Multisite with per-site tables)
2. **Replace file storage** with S3/object storage + signed URLs
3. **Add materialized state cache** for remediation (see section 2)
4. **Move to Composer autoloading** and proper dependency management
5. **Replace TCPDF** with the AI pipeline's PDF generation (already exists)
6. **Add API layer** (REST or GraphQL) for potential mobile/SPA frontends
7. **Add rate limiting** on AJAX endpoints
8. **Add background processing** (WP Cron or queue) for heavy operations (PDF generation, email batches)

The event-sourced log architecture transfers cleanly to SaaS — it's one of the reasons it was chosen over a mutable state table.
