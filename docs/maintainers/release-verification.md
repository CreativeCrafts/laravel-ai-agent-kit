# Release verification

Use this checklist before tagging a package release.

## Automated quality

- [ ] `composer validate --strict`
- [ ] `composer audit`
- [ ] `composer code-check` or the repository's full CI command
- [ ] Fix new PHPStan, Pint, or Pest failures across supported PHP and Laravel versions

## Specification workflow

- [ ] Archive completed changes according to the project workflow when applicable

## Laravel AI SDK alignment

- [ ] Confirm the `laravel/ai` constraint in `composer.json` matches the tested version range
- [ ] Review the SDK capability matrix and async inventory when upgrading Laravel AI SDK
- [ ] Update maintainer docs and changelog if new SDK jobs, tools, gateways, or modalities affect package guidance

## Security defaults

- [ ] Confirm default-deny tool execution remains documented
- [ ] Spot-check telemetry payloads for redaction expectations
- [ ] Confirm no examples encourage logging raw prompt bodies, file bodies, credentials, or unbounded user payloads

## Package docs

- [ ] Public README still supports install, configuration, first workflow, and next-guide discovery
- [ ] Public docs remain developer-facing
- [ ] Maintainer-only details remain under `docs/maintainers/**` or `CONTRIBUTING.md`

## Tag and publish

- [ ] Move `[Unreleased]` changelog entries under a version heading with date
- [ ] Create an annotated tag
- [ ] Publish release notes linking to `CHANGELOG.md`
