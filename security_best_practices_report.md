# Security Best Practices Report — Laravel AI Agent Kit

**Date:** 2026-06-30  
**Last updated:** 2026-06-30 (post-remediation)  
**Scope:** Full package review (`creativecrafts/laravel-ai-agent-kit`)  
**Stack:** PHP 8.3+, Laravel 12/13, `laravel/ai ^0.8`  
**Methodology:** Manual review of security-sensitive subsystems; `composer audit` (clean).

## Executive summary

The package is **designed secure-by-default** in its core threat areas: tool execution is denied unless explicitly authorized, conversation payloads encrypt at rest for persistent drivers, runtime telemetry redacts content, and media-input DTOs now apply SSRF and path-traversal guards at construction time.

**Remediation status (2026-06-30):**

| Severity | Total | Addressed | Remaining operator responsibility |
|----------|-------|-----------|----------------------------------|
| Critical | 0 | — | — |
| High | 2 | 2 (guards + docs) | Untrusted `fromPath()` absolute paths; DNS rebinding at fetch time |
| Medium | 4 | 4 | — |
| Low | 3 | 3 (docs/defaults) | Redis migration execution; provider-tool policy in app code |

No critical vulnerabilities were found under intended configuration. Residual risk is primarily **application-layer misuse** when integrators pass user-controlled paths/URLs without matching their threat model.

---

## Remediation log

| Finding | Status | Implementation |
|---------|--------|----------------|
| 1 — SSRF via URL inputs | **Addressed** | `SafeHttpUrlValidator`: private/reserved IPs, localhost/metadata hosts, internal suffixes, DNS resolution check; optional `media_input.url_allowed_hosts` |
| 2 — Path-based local reads | **Addressed (documented + partial guard)** | `SafeLocalPathReferenceValidator`: null bytes, `..`, `file://`; trust-boundary docs; `fromPath()` still accepts trusted absolute paths by design |
| 3 — `safeMetadata()` full references | **Addressed** | `MediaSourceSafeMetadata`: basename + fingerprint, or URL host/scheme |
| 4 — Dynamic delegation surface | **Addressed** | `allow_dynamic_delegation` required for non-`static_only` modes; config validation + docs |
| 5 — Attachment replay provider refs | **Addressed** | `allow_provider_references` defaults to `false` |
| 6 — Queued payload guard opt-in | **Addressed** | `payload_guard` defaults to `true` |
| 7 — Redis plaintext legacy reads | **Addressed (runbook)** | Migration runbook in `docs/memory.md` |
| 8 — Provider-native tool exfiltration | **Addressed (documentation)** | Expanded guidance in `docs/tools.md` and `docs/production.md` |
| 9 — Ephemeral driver warnings off | **Addressed** | `ephemeral_driver_warnings.enabled` defaults to `true` |

**Key commits:** `911c67c` (core hardening), follow-up remediation in current branch.

---

## Critical

No critical findings.

---

## High

### Finding 1 — User-controlled URL inputs enable SSRF when passed to provider runtimes

**Original issue:** URL factories validated scheme only; private/metadata hosts were not blocked.

**Remediation:**

- `src/Security/SafeHttpUrlValidator.php` — blocks literal private/reserved IPs, localhost/metadata hosts, internal suffixes (`.local`, `.internal`, …), and resolves hostnames; rejects when any resolved address is private or reserved.
- `config/ai-agent-kit.php` — optional `media_input.url_allowed_hosts` / `AI_AGENT_KIT_MEDIA_URL_ALLOWED_HOSTS`.
- `docs/streaming-and-modalities.md`, `docs/blueprints.md`, `docs/configuration.md` — trust-boundary guidance.

**Residual risk:** DNS rebinding between validation and provider fetch; hostnames with no DNS records skip resolution (allows test domains). For untrusted URLs, use signed object URLs, an application fetch proxy, or a strict host allowlist.

**Operator action:** Configure `media_input.url_allowed_hosts` when URLs may be user-influenced.

---

### Finding 2 — Path-based audio/image sources read arbitrary local filesystem paths

**Original issue:** `fromPath()` forwarded any non-empty string without validation.

**Remediation:**

- `src/Security/SafeLocalPathReferenceValidator.php` — rejects null bytes, `..` segments, and `file://` in path and storage references.
- Documentation treats `fromPath()` as **trusted-administrator input only**; directs untrusted uploads to `fromUpload()`, `fromBase64()`, or `fromStorage()`.

**Residual risk:** Absolute paths without traversal (e.g. `/etc/passwd`) are still accepted intentionally for batch/admin workflows.

**Operator action:** Never pass user-influenced strings to `fromPath()`.

---

## Medium

### Finding 3 — `safeMetadata()` includes full path/URL references

**Status:** **Addressed.** Metadata exposes `reference_basename` + `reference_fingerprint` for path/storage kinds and `url_host` / `url_scheme` for URLs.

---

### Finding 4 — `dynamic_full_registry` delegation mode expands agent handoff surface

**Status:** **Addressed.** Default remains `static_only`. Non-static modes require `allow_dynamic_delegation` (config validation fails fast without it). Documented in `docs/configuration.md` and agents guide.

---

### Finding 5 — Attachment replay allows provider references by default

**Status:** **Addressed.** `memory.attachments_replay.allow_provider_references` defaults to `false` in config, `AttachmentReplayPolicy`, and `RuntimeConversationMemoryBridge`.

---

### Finding 6 — Queued pipeline payload size guard is opt-in

**Status:** **Addressed.** `pipeline.queued.payload_guard` defaults to `true`. Documented in `docs/pipelines-and-queues.md` and `docs/production.md`.

---

## Low

### Finding 7 — Redis memory reads legacy plaintext payloads for compatibility

**Status:** **Addressed (operational runbook).** Compatibility read path remains by design for migration. Runbook added to `docs/memory.md` (re-save, flush/TTL, rotate credentials).

**Operator action:** Execute migration steps during Redis encryption rollout.

---

### Finding 8 — Provider-native tools can exfiltrate conversation context

**Status:** **Addressed (documentation).** Opt-in by design. Expanded checklist in `docs/tools.md` and deploy verification in `docs/production.md`.

**Operator action:** Implement explicit `ToolAuthorizer` policy before enabling `tools.provider_tools` aliases.

---

### Finding 9 — Ephemeral in-memory drivers not warned unless configured

**Status:** **Addressed.** `ephemeral_driver_warnings.enabled` now defaults to `true` (still scoped to configured environments, default `production`).

---

## Positive security controls (no action required)

| Control | Location |
|---------|----------|
| Default-deny tool authorization | `src/Tools/DenyAllToolAuthorizer.php`, config default |
| JSON Schema input validation for tools | `src/Tools/InMemoryToolRegistry.php` |
| AES-256 conversation encryption (database default) | `config/ai-agent-kit.php`, `DatabaseConversationStore` |
| Redis payload encryption default | `config/ai-agent-kit.php`, `RedisConversationStore` |
| Media URL/path construction guards | `src/Security/SafeHttpUrlValidator.php`, `SafeLocalPathReferenceValidator.php` |
| Telemetry content redaction (lengths/keys only) | `src/Security/DefaultRedactor.php`, `SdkTelemetryNormalizer.php` |
| Config fail-fast validation | `src/Core/Config/ConfigValidator.php` |
| Clean dependency audit | `composer audit` — no advisories |
| Integer auto-increment not used as public conversation ID | `src/Memory/ConversationId.php` (caller-supplied string) |

---

## Dependency audit

```
composer audit — No security vulnerability advisories found.
```

---

## Integrator checklist (production)

1. Keep `orchestration.delegation_policy.mode` at `static_only` unless dynamic delegation is explicitly required and reviewed.
2. Set `media_input.url_allowed_hosts` when accepting URL-bearing DTOs from users or tenants.
3. Never pass user input to `fromPath()`; use upload/storage/base64 paths instead.
4. Keep `memory.attachments_replay.allow_provider_references` disabled unless provider reference replay is intentional.
5. Keep `pipeline.queued.payload_guard` enabled and size `max_serialized_job_bytes` for your queue backend.
6. Run the Redis plaintext migration runbook when enabling encryption on existing Redis data.
7. Authorize provider-native tools explicitly; audit requests that name them.
8. Leave `ephemeral_driver_warnings.enabled` on in production when using in-memory drivers.
