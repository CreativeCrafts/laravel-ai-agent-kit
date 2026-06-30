# Security Best Practices Report — Laravel AI Agent Kit

**Date:** 2026-06-30  
**Scope:** Full package review (`creativecrafts/laravel-ai-agent-kit`)  
**Stack:** PHP 8.3+, Laravel 12/13, `laravel/ai ^0.8`  
**Methodology:** Manual review of security-sensitive subsystems; `composer audit` (clean). No PHP/Laravel-specific reference doc exists in the security skill; assessment uses Laravel security conventions and package architecture.

## Executive summary

The package is **designed secure-by-default** in its core threat areas: tool execution is denied unless explicitly authorized, conversation payloads encrypt at rest for persistent drivers, and runtime telemetry redacts content (lengths and key names only). Dependency advisories are clean.

The highest practical risks are **application-layer misuse surfaces** the package intentionally exposes: user-supplied **filesystem paths**, **storage paths**, and **HTTP(S) URLs** for audio/image inputs can lead to **local file read** or **SSRF** if callers pass untrusted input without validation. Operators must also avoid enabling **`dynamic_full_registry`** delegation or **provider-native tools** (`web_search`, `file_search`) without understanding data-exfiltration implications.

No critical vulnerabilities were found in package code under intended configuration. Findings below prioritize operator and integrator responsibilities.

---

## Critical

No critical findings.

---

## High

### Finding 1 — User-controlled URL inputs enable SSRF when passed to provider runtimes

**Location:** `src/Blueprints/EvaluationImageInput.php` (lines 27–43), `src/Core/Modality/TranscriptionAudioSource.php` (lines 59–75)

**Issue:** `EvaluationImageInput::fromUrl()` and `TranscriptionAudioSource::fromUrl()` validate URL shape and restrict schemes to `http`/`https`, but do not block private IP ranges, link-local addresses, or cloud metadata endpoints. When applications pass user-supplied URLs into blueprints or modality requests, the downstream Laravel AI SDK/provider may fetch those URLs server-side.

**Impact:** An attacker could probe internal networks or cloud metadata services via SSRF.

**Recommendation:** Document that URL inputs must be validated or proxied by the application (allowlist domains, block RFC1918/link-local, use pre-signed object URLs). Consider optional SSRF guard hooks or documented middleware patterns for URL-bearing DTOs.

---

### Finding 2 — Path-based audio/image sources read arbitrary local filesystem paths

**Location:** `src/Core/Modality/TranscriptionAudioSource.php` (lines 36–42), `src/Blueprints/EvaluationImageInput.php` (lines 55–61), `src/Core/Modality/SdkTranscriptionRuntime.php` (path branch in `pendingFromSource()`)

**Issue:** `fromPath()` accepts any non-empty string and forwards it to the SDK transcription/image constructors without canonicalization or chroot validation.

**Impact:** If application code passes user-influenced paths, attackers may read files accessible to the PHP process.

**Recommendation:** Treat `fromPath()` as trusted-administrator input only. Prefer `fromStorage()` with disk policies, `fromUpload()`, or base64 for untrusted uploads. Document this constraint prominently in modality/blueprint guides.

---

## Medium

### Finding 3 — `safeMetadata()` includes full path/URL references in result metadata

**Location:** `src/Core/Modality/TranscriptionAudioSource.php` (lines 113–114), `src/Blueprints/EvaluationImageInput.php` (lines 113–114), propagated via `SdkTranscriptionRuntime.php` (line 73) and blueprint metadata

**Issue:** Metadata labeled “safe” still embeds full `reference` strings for path, storage, and URL kinds. These values can contain sensitive paths (`/var/app/secrets/...`) or internal URLs and may flow into logs, job payloads, or application observability if metadata is exported verbatim.

**Impact:** Unintentional disclosure of internal paths or signed URL fragments in operational logs.

**Recommendation:** Hash or basename-only references in metadata, or gate full references behind an explicit debug flag.

---

### Finding 4 — `dynamic_full_registry` delegation mode expands agent handoff surface

**Location:** `config/ai-agent-kit.php` (lines 154–159), `src/Core/Orchestration/ConfigurableDelegationPolicyEngine.php`

**Issue:** Default mode is `static_only` (good), but `dynamic_full_registry` permits delegation to any registered agent. A compromised or misbehaving agent could hand off to sensitive agents not intended in its delegation graph.

**Impact:** Authorization bypass across agent boundaries when mode is misconfigured.

**Recommendation:** Keep `static_only` in production unless explicitly required. Document threat model for dynamic modes in agents-and-orchestration guide (partially done).

---

### Finding 5 — Attachment replay allows provider references by default

**Location:** `config/ai-agent-kit.php` (`memory.attachments_replay.allow_provider_references` default `true`), `src/Core/Runtime/AttachmentReplayPolicy.php` (lines 30–31, 69)

**Issue:** When attachment replay is enabled, provider file references may be rehydrated on subsequent turns unless denied by type/URL rules.

**Impact:** Longer retention of provider-side file references than operators expect; potential re-submission of sensitive attachments to providers.

**Recommendation:** Default `allow_provider_references` to `false` for stricter postures, or document explicit opt-in requirements.

---

### Finding 6 — Queued pipeline payload size guard is opt-in

**Location:** `config/ai-agent-kit.php` (`pipeline.queued.payload_guard` default `false`), `src/Core/Pipeline/LaravelQueuedPipelineDispatcher.php` (lines 48–49)

**Issue:** Large serialized pipeline jobs can be dispatched without size limits unless `payload_guard` or debug guard is enabled.

**Impact:** Queue worker memory exhaustion or denial-of-service from oversized jobs.

**Recommendation:** Enable `payload_guard` in production and set `max_serialized_job_bytes` appropriately.

---

## Low

### Finding 7 — Redis memory reads legacy plaintext payloads for compatibility

**Location:** `src/Memory/RedisConversationStore.php` (decode path; documented in `docs/memory.md`)

**Issue:** Encrypted payloads are default, but plaintext legacy keys remain readable during migration.

**Impact:** Historical plaintext conversation data remains exposed if Redis is compromised before rotation.

**Recommendation:** Migration runbook: re-save conversations under encryption, flush legacy keys, rotate Redis credentials after migration.

---

### Finding 8 — Provider-native tools can exfiltrate conversation context to third parties

**Location:** `config/ai-agent-kit.php` (`tools.provider_tools`), `docs/tools.md`

**Issue:** Opt-in `web_search` and `file_search` provider tools send model-selected queries to external services when authorized and requested.

**Impact:** Expected behavior, but data-leakage risk if enabled without policy review.

**Recommendation:** Keep disabled until `ToolAuthorizer` and product policy explicitly allow; audit which requests name provider tools.

---

### Finding 9 — Ephemeral in-memory drivers not warned unless configured

**Location:** `config/ai-agent-kit.php` (`ephemeral_driver_warnings.enabled` default `false`)

**Issue:** In-memory memory/vector drivers lose isolation between processes and leak data lifetime to process scope without warning in production.

**Impact:** Operational misconfiguration — not cryptographic failure.

**Recommendation:** Enable ephemeral driver warnings in production deployments.

---

## Positive security controls (no action required)

| Control | Location |
|---------|----------|
| Default-deny tool authorization | `src/Tools/DenyAllToolAuthorizer.php`, config default |
| JSON Schema input validation for tools | `src/Tools/InMemoryToolRegistry.php` |
| AES-256 conversation encryption (database default) | `config/ai-agent-kit.php`, `DatabaseConversationStore` |
| Redis payload encryption default | `config/ai-agent-kit.php`, `RedisConversationStore` |
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

## Recommended fix priority

1. **Documentation** for URL/path input trust boundaries (Findings 1–2) — highest integrator impact, no code regression risk.
2. **Operator hardening** — enable payload guard, review delegation mode, attachment replay settings (Findings 4–6).
3. **Optional code hardening** — metadata reference redaction (Finding 3) if logs are widely exported.

Ask to begin fixes on a specific finding ID when ready.
