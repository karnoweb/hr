# Implementation Checklist — Phase 9: Insurance + Tax

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index.

**Sequencing note:** as explained in `HR-AUDIT-PHASE-08.md`, `HR-104` and `HR-106` below should be implemented before Phase 8's `HR-094`/`HR-095` are finished, even though this phase is numbered after Phase 8.

- [ ] HR-103 — Design and migrate a versioned insurance-rate entity, replacing the flat config block (P0)
  - Area: new migration + model (e.g. `hr_insurance_rates`: `effective_date`, `employee_rate`, `employer_rate`, `unemployment_rate`, `ceiling_multiplier`)
  - Acceptance:
    - multiple rate rows can coexist with different `effective_date` values
    - a lookup-by-date method correctly selects the rate in force for any given payroll period's date, including past/historical periods
    - `config('hr.insurance.*')` becomes only the seed value for the first row, not the live source of truth

- [ ] HR-104 — Create `InsuranceCalculator` consuming the versioned rate entity (P0)
  - Area: new `src/Calculators/InsuranceCalculator.php`
  - Acceptance:
    - given a gross insurable salary and a payroll period date, correctly applies the rate in force for that date, including the `ceiling_multiplier` cap on the insurable base
    - unit-tested against multiple rate-history scenarios, including recalculating a past period after a later rate change was recorded

- [ ] HR-105 — Design and migrate a versioned tax-bracket entity, replacing the flat config block (P0)
  - Area: new migration + model (e.g. `hr_tax_brackets`: fiscal year/effective date, `annual_exemption`, `brackets` JSON)
  - Acceptance:
    - same historical-correctness property as HR-103, scoped to tax brackets
    - existing `config('hr.tax.*')` values are seeded as the first dated entry, with an explicit, documented best-guess fiscal year (since the current config carries no year tag at all)

- [ ] HR-106 — Create `TaxCalculator` consuming the versioned bracket entity (P0)
  - Area: new `src/Calculators/TaxCalculator.php`
  - Acceptance:
    - correctly applies progressive brackets to taxable income, respecting the annual exemption
    - implements and documents an explicit policy for monthly-vs-annual reconciliation (flat monthly annualization vs. running year-to-date) — this is a real design decision that must be written down, not left implicit
    - unit-tested against each bracket boundary

- [ ] HR-107 — Add explicit "NEEDS VERIFICATION (legal/regulatory)" flags on every hardcoded rate/bracket/exemption value (P0)
  - Area: `config/hr.php`, HR-103/HR-105's seed data
  - Acceptance:
    - every regulatory figure carries an inline comment stating its source/fiscal-year assumption and that it must be reverified before production payroll use
    - this audit does not and cannot certify legal correctness of any rate — this item ensures that caveat survives into the code itself, not only into this report

- [ ] HR-108 — Respect `SalaryItem.is_taxable`/`is_insurable` per-item flags in both calculators (P0)
  - Area: `InsuranceCalculator`, `TaxCalculator`, consuming Phase 6's `HR-076` flagged output
  - Acceptance:
    - a `SalaryItem` marked `is_taxable = false` is excluded from `taxable_income` even if it's an `Earning`; same for `is_insurable`

- [ ] HR-109 — Implement per-employee insurance/tax exemption overrides, if a real requirement is confirmed (P2)
  - Area: `InsuranceCalculator`/`TaxCalculator` + a per-employee override mechanism
  - Acceptance:
    - implemented only if/when a real requirement is confirmed by a domain/legal expert; otherwise explicitly marked **NEEDS VERIFICATION** rather than inventing an exemption rule
    - if implemented, tested against both exempt and non-exempt employees

- [ ] HR-110 — Implement `dependents_count`-aware tax exemption, contingent on legal verification (P2)
  - Area: `TaxCalculator`
  - Acceptance:
    - explicitly deferred (tracked here so it isn't silently dropped) pending confirmation from a domain/legal expert, per the audit's instruction not to assume regulatory correctness

- [ ] HR-111 — Add a repeatable process to import a new fiscal year's official rates into HR-103/HR-105's tables (P2)
  - Area: new `src/Console/Commands/ImportRatesCommand.php`, or a documented manual process
  - Acceptance:
    - a repeatable, auditable process exists for adding next fiscal year's rates without a code deploy

- [ ] HR-112 — Write the Insurance + Tax test suite (P0)
  - Area: `tests/Unit/`
  - Acceptance:
    - covers rate/bracket lookup-by-date (including historical recalculation), ceiling application, `is_taxable`/`is_insurable` exclusion
    - test comments explicitly state that rate *values* are not being asserted as legally correct — only that the calculation *mechanics* are correct given whatever values are configured
