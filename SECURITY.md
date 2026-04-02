# Security Policy

## Reporting a Vulnerability

If you discover a security vulnerability, please report it privately by emailing **creativecraftssolutions@gmail.com**.

Please include:

- A clear description of the issue
- Reproduction steps or a proof-of-concept
- Impact assessment (what could be exposed or abused)
- Suggested mitigation (if available)

Do **not** open public GitHub issues for unpatched vulnerabilities.

## Supported Versions

Security fixes are applied to the latest published package release and to the active development branch.

## Security Posture Highlights

- Tool execution is denied-by-default unless explicitly registered and authorized.
- Runtime/pipeline execution budgets are configurable and enforced.
- Conversation storage supports encryption at rest for persistent drivers.
- Telemetry payloads are redacted by default to avoid leaking raw content and secrets.
