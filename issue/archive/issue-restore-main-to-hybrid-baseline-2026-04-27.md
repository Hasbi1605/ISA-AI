# Restore `main` to Hybrid Python + Laravel Baseline

## Context
- Current `main` contains the Laravel-only migration work.
- The stable demo branch is `codex/demo-python-stable-a9181f1`.
- The team wants `main` to match the deployable hybrid baseline so future fixes and deployments can continue from the stable stack.

## Goals
- Preserve the current Laravel-only `main` state in an archive branch.
- Restore the repository tree on top of `main` so it matches the hybrid demo baseline.
- Keep the rollback auditable as a normal git commit instead of rewriting history.
- Verify the restored branch with full Laravel and Python test runs before updating `main`.

## Constraints
- Do not use destructive history rewriting on `main`.
- Do not discard the Laravel-only work; keep it accessible through an archive branch.
- Prefer a single restore commit on top of `main` so the transition is easy to understand later.

## Plan
1. Create an archive branch from the current `origin/main`.
2. Create a restore branch from `origin/main`.
3. Replace the restore branch tracked tree with the tracked tree from `codex/demo-python-stable-a9181f1`.
4. Keep this issue plan file in the restore branch for documentation.
5. Run full verification:
   - `cd laravel && php artisan test`
   - `cd python-ai && source venv/bin/activate && pytest`
6. Push the archive branch and restore branch.
7. Fast-forward local and remote `main` to the verified restore branch.
8. Sync local `main` back to the updated remote state.

## Risks
- The restore commit is large because it removes Laravel-only files and reintroduces the hybrid baseline.
- Long-running services or ignored local env files are not part of git and may still need runtime checks after the branch move.
- If any test fails on the restored branch, `main` must not be updated until the failure is understood.
