# Implementation Checklist — Phase 10: Documents

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0 (`HR-005` sequence generator, `HR-008` exceptions).

- [ ] HR-113 — Make document-number generation concurrency-safe using the Phase-0 sequence generator (P0)
  - Area: `src/Models/HrDocument.php:98-110`, `hr_sequences`
  - Acceptance:
    - replaces `generateDocumentNumber()`'s `count()`-based logic entirely
    - sequence allocation and the document `INSERT` are covered such that two concurrent creations of the same type/year never produce the same number
    - a concurrency test with N parallel creations asserts N unique numbers (this test fails against today's code and becomes the regression baseline)

- [ ] HR-114 — Wrap `DocumentService::submit()` in a transaction (P0)
  - Area: `src/Services/DocumentService.php:65-88`
  - Acceptance:
    - status update, all approval inserts, and the history insert happen atomically
    - a simulated failure mid-loop leaves the document unchanged (still `Draft`, still editable) rather than stuck `Pending` with a partial approval set

- [ ] HR-115 — Fix "no matching workflow" handling in `submit()` per `config('hr.workflow.skip_on_no_approver')` (P0)
  - Area: `DocumentService::submit()`
  - Acceptance:
    - when `Workflow::findForDocument()` returns `null`: if `skip_on_no_approver` is `true`, the document is auto-approved immediately (through the same approved-side-effects path as a normal completion)
    - if `false`, `submit()` throws a clear `UnresolvableWorkflowException` and leaves the document in `Draft` (not `Pending`) so it remains editable/retriable
    - both branches tested

- [ ] HR-116 — Wrap `DocumentService::approve()`/`reject()` in transactions with a lock on the parent `HrDocument` (P0)
  - Area: `DocumentService`
  - Acceptance:
    - matches the Concurrency file's required solution exactly
    - a concurrency test with a `reject()` and an `approve()` racing on two different steps of the same document always resolves deterministically to `Rejected`, never back to `Approved`

- [ ] HR-117 — Fix `approve()`/`reject()` to check the parent document's current status, not just the individual approval row (P0)
  - Area: `DocumentService`
  - Acceptance:
    - calling `approve()` on a still-`Pending` approval whose parent document has already been `Rejected` by a different step throws a clear exception instead of silently succeeding
    - tested

- [ ] HR-118 — Mark sibling pending approvals `Skipped` when a document is rejected (P0)
  - Area: `DocumentService::reject()`
  - Acceptance:
    - within the same transaction as HR-116/117, all other `Pending` `DocumentApproval` rows for the document are updated to `ApprovalStatus::Skipped` (making this currently-dead enum case live)
    - tested

- [ ] HR-119 — Replace direct `auth()->id()` calls with an explicit actor parameter (P0)
  - Area: `DocumentService::approve()`/`reject()`/`recordHistory()`, and any new approval methods introduced in other phases
  - Acceptance:
    - every method recording an actor accepts an optional actor-id parameter defaulting to `auth()->id()` only as a caller convenience, never as the sole mechanism
    - calling from a queued job/command with an explicit actor id works correctly and is tested

- [ ] HR-120 — Add an authorization check verifying the acting user matches `DocumentApproval.assigned_to` (P0)
  - Area: `DocumentService::approve()`/`reject()`
  - Acceptance:
    - throws `UnauthorizedApprovalException` (Phase 0, HR-008) when the provided/resolved actor id doesn't match `assigned_to`
    - tested with both matching and mismatching actors
    - documented as the package's first real in-package authorization check, justified because this specific check is intrinsic to the meaning of "approve" (a deliberate, narrow exception to the "authorization is the caller's job" default)

- [ ] HR-121 — Validate `HrDocument.branch_id` matches its employee's `branch_id` unless explicitly overridden (P1)
  - Area: `DocumentService::create()`
  - Acceptance:
    - a mismatched `branch_id` passed explicitly is allowed only through a documented, explicit override flag; the implicit default path always matches

- [ ] HR-122 — Add a documented policy + implementation for editing/resubmitting a `Rejected` document (P2)
  - Area: `DocumentStatus` enum, `DocumentService`
  - Acceptance:
    - either an explicit `resubmit()` method that clones a rejected document into a new `Draft`, or a documented, deliberate decision that rejected documents are permanently terminal
    - whichever is chosen is written down and tested

- [ ] HR-123 — Verify the `hr_document_id` FK/validation decision (cross-reference HR-033) as it applies to Documents specifically (P2)
  - Area: cross-reference to HR-033
  - Acceptance: verified covered by HR-033

- [ ] HR-124 — Write the Documents domain test suite (P0)
  - Area: `tests/Feature/`, `tests/Concurrency/`
  - Acceptance:
    - covers numbering concurrency (HR-113), transaction safety (HR-114), both branches of no-workflow handling (HR-115), approve/reject locking and status-check (HR-116/117), sibling-skip on reject (HR-118), actor parameter (HR-119), and the new authorization check (HR-120)
