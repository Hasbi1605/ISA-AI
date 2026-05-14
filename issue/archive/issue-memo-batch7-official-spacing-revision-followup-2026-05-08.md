# Issue: Batch 7 Memo Official Spacing And Revision Follow-up

## Context

Batch 7 showed several memo renderer and revision gaps after the previous official-template fixes:

- key-value rows still missed labels such as `periode` and sometimes kept inline label sequences in one value;
- long labels such as `jadwal pendampingan` could visually split in the body table;
- body paragraphs after key-value tables and generated closing paragraphs needed more official-like vertical spacing;
- closing-like text such as `Mohon tindak lanjut ...` should be treated as an official closing block;
- tembusan-only or metadata-only revisions could still send the body through model regeneration, which risked changing unrelated content and leaking carbon-copy text into the body.

## Scope

Implement the smallest safe changes in:

- Python memo DOCX generation and sanitization;
- Laravel memo revision orchestration;
- regression tests for both areas.

## Plan

1. Extend key-value parsing to include `periode`, `periode kegiatan`, and `waktu pelaksanaan`.
2. Split inline multi-label sequences before rendering so each configured field becomes its own row.
3. Widen the key-value label column and add controlled spacing after key-value tables only when followed by a paragraph.
4. Treat `Mohon tindak lanjut ...` as generated closing text so it renders in the closing position with spacing.
5. Remove configured carbon-copy lines from body content while keeping the configured `Tembusan:` section.
6. Add a body-preserving revision path for metadata-only chat revisions, storing normal configuration while passing `body_override` only to the AI document service request.
7. Add focused Python and Laravel regression tests.

## Verification

- Run targeted Python tests around memo generation.
- Run targeted Laravel Livewire memo tests.
- Run full Python and Laravel test suites if the targeted tests pass.

## Risks

- Classification of metadata-only revision instructions must stay conservative. If an instruction mentions body structure, points, paragraphs, typos, or shortening, it should still use the normal revision path.
- Additional spacing must improve official similarity without making short letter memos wasteful.
