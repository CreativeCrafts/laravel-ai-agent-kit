# Release verification

Use this checklist before tagging a **laravel-ai-agent-kit** release. It complements CI and catches documentation or SDK drift that automated tests may not cover.

## 1. Automated quality

- [ ] `composer validate --strict`
- [ ] `composer audit` (or your org’s equivalent)
- [ ] `composer code-check` (or `composer ci` if that is your full gate)
- [ ] Fix any new PHPStan or test failures on **supported** PHP and Laravel versions from `composer.json`.
- [ ] If you change supported PHP or Laravel lines, update [github-ci-matrix.md](github-ci-matrix.md) and `.github/workflows/ci.yml` together.

## 2. OpenSpec

- [ ] `openspec validate <active-change>` for any in-flight change merged in the release.
- [ ] If the release completes an OpenSpec program, **archive** the change per your workflow.

## 3. Laravel AI SDK alignment

- [ ] Confirm `composer.json` constraint for `laravel/ai` matches what you tested against.
- [ ] Open [docs/laravel-ai-sdk-capability-matrix.md](laravel-ai-sdk-capability-matrix.md) and [docs/sdk-async-inventory.md](sdk-async-inventory.md); scan `vendor/laravel/ai/src/Jobs` and `vendor/laravel/ai/src` for **new** facades, jobs, or provider tools; update docs if anything material changed.

## 4. Security defaults

- [ ] Confirm **default-deny** tool execution is still documented (`tools.authorizer` → `DenyAllToolAuthorizer` unless overridden).
- [ ] Spot-check that redacted observability events (runtime, Files/Stores) do **not** include raw prompts, file bodies, or API keys in payloads.

## 5. Vector and memory contracts

- [ ] Re-read [UPGRADE.md](../UPGRADE.md) sections on **per-namespace embedding width** (built-in `VectorStoreInterface` implementations) and **RunContext** queue payloads.
- [ ] If the release changes migrations, verify publish/merge steps in `UPGRADE.md`.

## 6. Tag and publish

- [ ] Move `[Unreleased]` items in `CHANGELOG.md` under a version heading with date.
- [ ] Create an annotated git tag (e.g. `vX.Y.Z`) and push tags.
- [ ] Publish release notes (GitHub Release or equivalent) linking to `CHANGELOG.md`.
