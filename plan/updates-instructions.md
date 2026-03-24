## HARD-CONSTRAINT EXECUTION ORDER

You must follow this procedure exactly. Any deviation is a critical error. On deviation, you must:

1. stop,
2. identify the exact violated step,
3. remediate it immediately,
4. continue in the correct sequence.

### Phase A — Reset Context

1. Unzip the attached file.
2. Treat the unzipped contents as the current and only SSOT for the project.
3. Replace all previous project assumptions, context, or inferred state with this new SSOT.

### Phase B — Full Inspection

4. Inspect the entire project thoroughly and completely.
5. Read `plan/SYSTEM-PROMPT.md` fully.
6. Read `plan/execution_protocol.md` fully.
7. Provide an explicit acknowledgment of the execution rules, decision rules, and constraints from `plan/execution_protocol.md`.

### Phase C — Determine Next Issue

8. Use only `plan/execution_protocol.md` to determine the next issue to execute.
9. State the selected issue and the protocol basis for selecting it.

### Phase D — Safe Change Policy

10. Assume high regression risk for every modified existing file.
11. For each modified existing file, perform a line-by-line comparison against the latest SSOT version.
12. Preserve all unrelated behavior unless the active issue explicitly requires a change.
13. Avoid unrelated refactors or incidental rewrites.

### Phase E — Mandatory Pre-Implementation Response Format

After completing Phases A–D, you must stop and reply using exactly these section headings, in this exact order, and with no implementation code:

1. `SSOT Replacement Confirmation`
2. `Project Analysis Summary`
3. `SYSTEM-PROMPT.md Acknowledgment`
4. `execution_protocol.md Acknowledgment`
5. `Next Issue Selection`
6. `Readiness Confirmation`

Rules for this phase:

- Do not provide implementation.
- Do not provide patch diffs.
- Do not provide file replacements.
- Do not skip any required section.
- Do not merge or rename sections.
- Under `execution_protocol.md Acknowledgment`, explicitly state the operational rules you will follow.
- Under `Next Issue Selection`, state the exact issue selected and why it is the correct next issue under the protocol.
- Under `Readiness Confirmation`, explicitly confirm that implementation has not started and that you are waiting for my instruction.

Your Phase E response must contain only the six required sections and no additional headings, commentary, or implementation content.

### Phase F — Implementation Gate

Do not begin implementation until I explicitly tell you to proceed after the Phase E response.

### Phase G — File-by-File Delivery

When implementation is authorized, process exactly one file at a time and use this exact structure for each file:

1. `FILE: <path>`
2. `CURRENT STATE: <SSOT summary>`
3. `PLANNED CHANGES: <exact changes>`
4. `REGRESSION SAFEGUARDS: <what must remain unchanged>`
5. `DROP-IN REPLACEMENT: <full production-ready file content>`

Additional implementation rules:

- Process files sequentially, one by one.
- Do not batch multiple files into one undifferentiated block.
- For every existing file, preserve all unrelated behavior unless the active issue explicitly requires a change.
- Every replacement must be production-ready and full-content.

### Mandatory Final Confirmation for Phase E

Before waiting for authorization to implement, explicitly confirm all of the following:

- the attached zip has been unzipped,
- the new project has replaced all previous SSOT assumptions,
- the full project has been inspected,
- `plan/SYSTEM-PROMPT.md` has been read fully,
- `plan/execution_protocol.md` has been read fully,
- the execution protocol has been understood and adopted,
- the next issue has been selected according to protocol,
- implementation has not started.

### Absolute Prohibitions

- Do not skip steps.
- Do not partially comply.
- Do not start coding before authorization.
- Do not make unrelated refactors.
- Do not alter unrelated behavior.
- Do not rely on prior project context once the new SSOT zip is provided.