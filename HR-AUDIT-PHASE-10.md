# Implementation Checklist — Phase 10: Documents

Part of the Karnoweb HR audit. See `HR-AUDIT.md` for the index. Depends on Phase 0 (`HR-005` sequence generator, `HR-008` exceptions).

- [x] HR-113 — Make document-number generation concurrency-safe using the Phase-0 sequence generator (P0)
  - `HrDocument::generateDocumentNumber()` uses `SequenceGenerator` scope `document:{type}:{year}`.

- [x] HR-114 — Wrap `DocumentService::submit()` in a transaction (P0)

- [x] HR-115 — Fix "no matching workflow" handling in `submit()` per `config('hr.workflow.skip_on_no_approver')` (P0)

- [x] HR-116 — Wrap `DocumentService::approve()`/`reject()` in transactions with a lock on the parent `HrDocument` (P0)

- [x] HR-117 — Fix `approve()`/`reject()` to check the parent document's current status, not just the individual approval row (P0)

- [x] HR-118 — Mark sibling pending approvals `Skipped` when a document is rejected (P0)

- [x] HR-119 — Replace direct `auth()->id()` calls with an explicit actor parameter (P0)
  - `submit()`, `approve()`, `reject()`, and `resubmit()` accept optional `actorId`.

- [x] HR-120 — Add an authorization check verifying the acting user matches `DocumentApproval.assigned_to` (P0)
  - Throws `UnauthorizedApprovalException` when actor does not match `assigned_to`.

- [x] HR-121 — Validate `HrDocument.branch_id` matches its employee's `branch_id` unless explicitly overridden (P1)
  - `allow_branch_override` option on `create()`.

- [x] HR-122 — Add a documented policy + implementation for editing/resubmitting a `Rejected` document (P2)
  - `DocumentService::resubmit()` clones rejected documents to a new Draft linked via `metadata.resubmitted_from`.

- [x] HR-123 — Verify the `hr_document_id` FK/validation decision (cross-reference HR-033) as it applies to Documents specifically (P2)
  - Covered by `HrDocumentReference::assertValid()` at contract/position/leave write sites (HR-033).

- [x] HR-124 — Write the Documents domain test suite (P0)
  - `tests/Feature/DocumentTest.php`
