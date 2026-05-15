# Issue: Memo Batch 5 Official Parity Follow-up

## Context
Batch 5 evaluation still showed several gaps against the official memo reference after the previous template/layout fixes:

- AI fallback text can be saved as memo body when every model fails.
- Closing text appears stale in the form and is often absent in generated output when the loaded configuration does not actually contain `closing`.
- Person/PIC data is not always rendered as official key-value tables, especially comma-separated inline data and `jadwal pendampingan`.
- Concise revision output collapses numbered items into a single horizontal paragraph.
- Closing-stripping can leave dangling fragments such as `Dengan`.
- Compact folio signature spacing can place the QR placeholder too high.

## Scope
Implement narrowly-scoped fixes in:

- `python-ai/app/services/memo_generation.py`
- `python-ai/tests/test_memo_generation.py`
- `laravel/app/Livewire/Memos/MemoWorkspace.php`
- `laravel/tests/Feature/Memos/MemoWorkspaceTest.php`

## Plan
1. Reject known AI-unavailable fallback messages before any DOCX is generated.
2. Reset optional form fields from loaded configuration so `Penutup` and arahan tambahan do not carry over stale values.
3. Expand and normalize person-data labels, including `jadwal pendampingan`.
4. Split inline comma/semicolon person-data fields into official key-value table rows.
5. Preserve vertical numbered lists when enforcing concise revision constraints.
6. Strip dangling closing fragments after removing generated closing sentences.
7. Tune compact folio signature spacing to keep the QR placeholder lower without changing horizontal placement.
8. Add regression tests for every behavior above and run relevant Python/Laravel verification.

## Risks
- More aggressive inline label splitting could affect body prose if labels are used conversationally. Mitigation: only split when two or more recognized labels with colons appear in the same block.
- Larger compact folio signature spacing could increase page pressure for very dense folio memos. Mitigation: keep a reduced dense spacing path for long bodies or many tembusan lines.
