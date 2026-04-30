# GapNext — Business Strategy & Pricing
**Confidential — CFO Working Document**
*Version 1.0 — March 2026*

---

## CFO Assessment: Is This a Good Strategy?

**Yes — with one important refinement.**

The core instinct is correct: use the WordPress plugin as a distribution wedge, monetize AI features as a recurring upsell, and evolve toward higher-margin SaaS. This is a classic PLG (Product-Led Growth) → enterprise motion that has worked repeatedly in B2B software. The compliance audit market is underserved by modern tooling, the AI capability is genuinely differentiated, and the customer ROI is measurable and large.

The one refinement: **annual contracts are the right call, but only if you price them correctly from day one.** Annual pricing that is too low locks you into a difficult renewal conversation 12 months from now. Price for the value you deliver — not for acquisition at all costs. The numbers below reflect this.

---

## 1. Product Architecture & Phases

```
Phase 1 (NOW)          Phase 2 (+9 months)     Phase 3 (+18 months)
─────────────────      ───────────────────      ────────────────────
WP Plugin              WP Plugin                Full SaaS (no WP)
  + AI Report            + AI Report              + AI Report
    Generation             Generation               Generation
                         + API as a Service        + API as a Service
                                                   + White-Label / OEM
```

**Phase 1 is already deployable today.** The plugin is functional, the FastAPI pipeline is running, AI report generation works. You can invoice tomorrow.

---

## 2. Target Customer Analysis

### Who Is Buying This?

| Persona | Description | Pain Point | WTP* |
|---------|-------------|------------|------|
| **ISO Consultant (solo/boutique)** | 1–5 person firm, 20–100 audits/year | 4–8h per report, manual Word docs | €400–900/yr |
| **Quality Manager (in-house)** | Corporate QMS/HSE manager | One-off annual audit report, no budget for consultants | €300–600/yr |
| **Compliance Agency** | 5–30 consultants, 200–1,000 audits/year | Team consistency, client deliverables, volume | €2,000–8,000/yr |
| **SaaS Platform (OEM)** | Another SaaS embedding compliance | API integration, co-branding | €5,000–25,000/yr |
| **Enterprise (direct)** | Multinational, internal audit team | IT governance, data residency, SLA | €15,000–50,000/yr |

*WTP = Willingness to Pay (annual)

### B2B vs B2B2C: The CFO Verdict

**Both — but sequenced deliberately.**

- **Phase 1–2: Pure B2B.** Sell directly to consultants and compliance agencies. You own the billing relationship entirely. No channel friction. Margins stay with you.
- **Phase 2–3: B2B2C via B2B2B (Reseller/OEM model).** Agencies white-label to their end clients. You bill the agency (B2B), the agency bills their clients (their B2C). You never touch the end client billing — but you control the wholesale rate and enforce minimum pricing floors in the reseller agreement.

**Why B2B2C directly (you billing end clients) is risky early:**
- End clients don't know GapNext — the consultant is the trusted brand
- Support burden multiplies without brand recognition to justify it
- Revenue per relationship is lower; acquisition cost is higher

**Why B2B2B (reseller) is powerful:**
- Agencies have existing client relationships — zero CAC for you
- Each agency is a multiplier: 1 contract = 20–100 end clients
- You maintain pricing floor control and API key control — billing stays upstream

---

## 3. Pricing Strategy

### Guiding Principle: Value-Based Pricing

A compliance consultant charges **€800–€2,000 per audit report** (professional writing, findings analysis, risk ratings). GapNext generates that report in minutes. At a 90% time saving, a report that cost €800 in labor now costs €80.

**Your AI report is worth €40–€80 to that consultant per use.** Price accordingly.

AI API cost per report (Claude Sonnet, ~8K tokens avg): **€0.08–€0.25 per call**. Gross margin on AI delivery: **95%+**.

---

### Phase 1: WP Plugin + AI Add-on

**Billing model: Annual subscription, invoiced upfront.**

#### Tier 1 — Starter
> Solo consultant, up to 2 audits per month.

| Item | Price |
|------|-------|
| Annual license | **€490/year** |
| Included AI reports | 25/year |
| Overage per report | €9 |
| Users | 1 |
| Standards | All available |

- **Effective monthly cost to customer:** €41/month
- **Your COGS (API + hosting share):** ~€20/year
- **Gross margin:** ~96%

#### Tier 2 — Professional
> Boutique consultancy, 3–10 audits per month.

| Item | Price |
|------|-------|
| Annual license | **€990/year** |
| Included AI reports | 80/year |
| Overage per report | €7 |
| Users | 3 |
| Priority support | ✓ |

- **Effective monthly cost to customer:** €83/month
- **Your COGS:** ~€45/year
- **Gross margin:** ~95%

#### Tier 3 — Agency
> Compliance agency, 10+ consultants, 300+ audits/year.

| Item | Price |
|------|-------|
| Annual license | **€2,490/year** |
| Included AI reports | 250/year |
| Overage per report | €5 |
| Users | 10 |
| Custom branding (logo/colors) | ✓ |
| Dedicated onboarding | ✓ |

- **Your COGS:** ~€100/year
- **Gross margin:** ~96%

#### Report Credit Packs (standalone upsell)
For customers who hit overage without wanting to upgrade tier:

| Pack | Price | Per-report |
|------|-------|-----------|
| 10 reports | €79 | €7.90 |
| 50 reports | €299 | €5.98 |
| 200 reports | €890 | €4.45 |

---

### Phase 2: API as a Service (+9 months)

Target: developers, larger firms with their own systems, SaaS platforms embedding compliance.

**Billing model: Annual API key subscription with included call quota.**

| Tier | Price | Included calls/year | Overage |
|------|-------|--------------------:|---------|
| Developer | **€990/year** | 500 | €2.50/call |
| Growth | **€2,990/year** | 2,500 | €1.80/call |
| Scale | **€7,990/year** | 10,000 | €1.20/call |
| Enterprise | **Custom** | Custom | Custom SLA |

- **Your COGS per API call:** €0.10–0.30
- **Your revenue per API call (included):** €1.20–€1.98
- **Gross margin:** 85–92%

**What the API delivers that the WP plugin doesn't:**
- Headless: no WordPress required
- Full JSON response (no PDF dependency)
- Webhook support for async generation
- Multi-tenant API key management
- SLA uptime guarantee (99.5%)

---

### Phase 3: Full SaaS (+18 months)

A hosted, multi-tenant platform. No WordPress. Browser-based. Clients manage audits, submissions, and reports entirely in the cloud.

**Billing model: Per-seat annual + AI report usage.**

| Tier | Seats | Price | AI Reports included |
|------|-------|-------|:------------------:|
| Team | 3 | **€1,490/year** | 100/year |
| Business | 10 | **€3,990/year** | 400/year |
| Enterprise | Unlimited | **€9,990–€24,990/year** | Custom |

**Why SaaS unlocks a step change in margins:**
- Hosting margin improves vs per-WP-install model
- Billing automation (Stripe/Paddle) reduces overhead
- Multi-tenant architecture enables usage analytics → better upsell
- Removes WP dependency → much larger TAM

---

### White-Label / OEM (Phase 2 onwards)

For agencies that want to resell GapNext under their own brand.

| Component | Price |
|-----------|-------|
| White-label license (annual) | **€4,990/year** |
| Wholesale AI report price | **€2.50/report** (you bill the agency) |
| Agency minimum commitment | 500 reports/year (€1,250 floor) |
| Branding: custom logo, subdomain | Included |
| Agency marks up to their clients | At their discretion (floor: €8/report) |

**Minimum pricing floor clause:** The reseller agreement must prohibit the agency from selling reports below €8/report to protect brand value and prevent race-to-the-bottom.

**Your economics per white-label agency:**
- License revenue: €4,990/year
- Usage revenue (500 reports × €2.50): €1,250/year
- **Total per agency: €6,240/year minimum**
- COGS: ~€150/year
- **Gross margin per agency: ~98%**

---

## 4. Unit Economics Summary

| Product | ARR (per customer) | COGS | Gross Margin |
|---------|-------------------:|-----:|:------------:|
| Starter Plugin | €490 | €20 | 96% |
| Professional Plugin | €990 | €45 | 95% |
| Agency Plugin | €2,490 | €100 | 96% |
| API Developer | €990 | €80 | 92% |
| API Scale | €7,990 | €500 | 94% |
| SaaS Business | €3,990 | €300 | 92% |
| White-Label Agency | €6,240+ | €150 | 98% |

**Target blended gross margin: 93–96%**. This is achievable because the dominant COGS driver (Claude API) is low and declining as Anthropic reduces pricing over time.

---

## 5. Revenue Projection (Conservative — Phase 1)

**Assumptions:** Direct outreach to ISO/quality consultant community. No paid ads. Founder-led sales.

| Month | New Customers | Cumulative ARR | Notes |
|-------|:-------------:|---------------:|-------|
| M1 | 3 | €2,970 | Beta / friends & network |
| M3 | 8 | €9,900 | First outbound |
| M6 | 20 | €22,800 | Referral kicks in |
| M9 | 35 | €38,250 | First agency deal |
| M12 | 50 | €55,000 | API beta opens |

**At 12 months with 50 customers (mixed tiers): €55K ARR**
At a conservative SaaS multiple of 5×: **€275K implied valuation** on ARR alone — before the API and SaaS layers.

This is a bootstrappable business. No external capital needed to reach €100K ARR.

---

## 6. Billing Control Strategy

### Why You Must Control the Billing Cycle

Losing billing control means losing:
1. **Data** — you can't see usage, predict churn, or upsell
2. **Leverage** — if a reseller controls billing, they own the client relationship
3. **Cashflow** — monthly payouts from a reseller introduce 30–60 day delays
4. **Pricing power** — resellers discount without your knowledge

### How to Maintain It

| Scenario | Mechanism |
|----------|-----------|
| Direct customers | Stripe/Paddle annual invoice, auto-renew, card on file |
| Agency customers | Annual contract + wire transfer or SEPA direct debit |
| White-label resellers | You bill the agency monthly/annually; they bill their clients directly |
| API customers | API key tied to Stripe subscription; key deactivates on non-payment |
| Enterprise | Net-30 invoicing with annual PO, but key is provisioned only after payment |

**Critical:** API keys are the enforcement mechanism. Every tier — plugin, API, SaaS — is gated by an API key provisioned in your system. Non-payment = key suspension. This is the most powerful billing lever you have. **Never give a customer a key that is not tied to an active subscription.**

---

## 7. Annual vs Monthly Contracts: CFO Position

**Start with annual. Don't offer monthly.**

| | Annual | Monthly |
|---|--------|---------|
| Cash upfront | ✓ Full year paid | ✗ One month at a time |
| Churn risk | Low (paid in advance) | High (cancel any time) |
| Revenue predictability | High | Low |
| Customer commitment | High (they've decided) | Low (always testing) |
| CAC payback | Immediate | 6–12 months |
| Negotiation leverage | You have it | Customer has it |

**The math:** A customer who pays €990/year upfront is worth more than a monthly customer paying €99/month even though the annual price is higher — because you collect it now, not over 10 months with churn risk at each step.

**When to introduce monthly pricing:** Phase 3 (full SaaS), when you have brand recognition and inbound demand. Monthly becomes a conversion tool for hesitant buyers who can then be pushed to annual with a 15–20% discount.

**Recommended discount for annual prepayment (in Phase 3):** 2 months free (≈16.7% discount). Never discount annual below 15%.

---

## 8. Go-to-Market Sequencing

### Phase 1 ICP (Ideal Customer Profile)
- ISO 9001 / 14001 / 45001 consultants in Italy and Southern Europe
- 1–10 person firm
- Currently producing audit reports in Word or PDF manually
- Already using WordPress for their website (lower friction to install plugin)

### Channels
1. **LinkedIn outreach** — "ISO consultant" is a searchable job title; direct DM with value prop
2. **ISO consultant associations** — Sponsoring or presenting at AICQ, Bureau Veritas community events
3. **WordPress plugin directory** — Free tier / freemium version of the base plugin (no AI) as top-of-funnel
4. **Referral program** — Give consultants 20% commission on referred annual subscriptions

### Conversion Motion (Direct Sales)
1. Free demo: they run a real audit through the plugin on their own data
2. They see the AI report PDF output
3. Annual contract offer with 14-day money-back guarantee
4. Invoice issued day 1; API key provisioned on payment

---

## 9. Risk Register

| Risk | Probability | Impact | Mitigation |
|------|:-----------:|:------:|------------|
| Claude API pricing increases | Medium | Medium | Build model-agnostic layer (already done via BAML fallback chain) |
| Competitor launches similar WP plugin | Medium | High | Accelerate Phase 2 API moat; sign multi-year contracts early |
| WordPress plugin rejected from directory | Low | Medium | Direct distribution via website download; not dependent on directory |
| Low conversion on annual-only pricing | Medium | Medium | Offer quarterly as bridge; never monthly |
| Agency reseller undercuts pricing | Low | High | Contractual minimum floor + API key at wholesale only via reseller portal |
| AI report quality complaints | Medium | High | Human review option; corrections/regeneration feature (already built) |
| Data privacy / GDPR concerns (enterprise) | Medium | High | Self-hosted option (WP plugin already self-hosted); data processing agreement template |

---

## 10. The One Metric That Matters in Year 1

**Net Revenue Retention (NRR).**

If customers renew AND expand (more reports, higher tier), NRR > 100% means the business grows even with zero new customers. Target:

- **Year 1 NRR target: 110%** (renewals + upsell to more reports or higher tier)
- **Churn ceiling: 15%** (1–2 customers lost per 10 acquired)

To hit this: build the renewal conversation into the product. At 80% of annual report quota consumed, show an in-plugin banner: *"You've used 40 of 50 included AI reports. Upgrade to Professional for €41/month more and never run out."*

---

## 11. Summary Recommendation

| Decision | Recommendation |
|----------|---------------|
| Launch timing | **Now** — product is ready |
| First price point | **€490/year Starter** — low enough to close fast, high enough to signal value |
| Contract type | **Annual only** for 12 months, then add quarterly option |
| First 10 customers | Founder-led, direct outreach, 0% discount |
| Phase 2 unlock trigger | €30K ARR or 30 active customers (whichever first) |
| B2B2C timing | Phase 2 — not before you have 5+ agency customers to learn from |
| Billing infrastructure | Stripe (Paddle for EU VAT automation) — do not use WooCommerce |
| One thing to do this week | Write the one-page sales deck and LinkedIn post targeting ISO consultants |

---

*This document reflects the opinion of the CFO function. It should be reviewed annually and updated as market data accumulates. First renewal data at month 12 will be the most important input to the Phase 2 pricing decision.*
