# Prompts

Prompts are package-owned templates with explicit names, versions, and variables. Use prompt versions deliberately so workflow behavior is reproducible and testable.

## What prompts are for

Use package prompts when you want:

- named prompt templates
- explicit versions
- required variables
- deterministic rendering errors for missing variables
- blueprint and runtime execution that stays package-owned

## Prompt repositories

Configure the prompt repository driver in `config/ai-agent-kit.php`:

~~~php
'prompts' => [
    'default_driver' => 'in_memory',

    'file' => [
        'root_path' => null,
    ],
],
~~~

Supported drivers:

- `in_memory`: useful for tests and programmatic registration.
- `file`: loads prompt metadata and templates from the configured prompt root.

## File prompt manifests

File prompts use one `metadata.php` manifest per prompt directory:

~~~php
return [
    'name' => 'support.reply',
    'current_version' => '1.0.0',
    'versions' => [
        '1.0.0' => [
            'template' => '1.0.0.md',
            'variables' => ['name', 'ticket_id'],
            'description' => 'Current support reply prompt.',
        ],
        '2.0.0' => [
            'template' => '2.0.0.md',
            'variables' => ['name', 'ticket_id', 'agent'],
        ],
    ],
];
~~~

When no version is passed to `get()` or `render()`, `current_version` selects the version exactly. An explicit version argument still selects that exact version. A legacy manifest that omits `current_version` keeps the previous highest-version behavior.

When a version contains a `variables` key, that declaration is authoritative and must exactly match its dynamic placeholders. Variable names use the grammar `[A-Za-z_][A-Za-z0-9_]*`; duplicate, undeclared, and unused declarations fail manifest validation. An explicit `variables => []` declares a prompt with no dynamic variables. A legacy version that omits `variables` continues inferring variables from the template.

Validate the configured file prompt root before deployment:

~~~bash
php artisan ai:prompts:lint
~~~

Use `--path=/path/to/prompts` to validate a different prompt root. The command also detects invalid current versions, unreadable template files, and declaration/template mismatches.

## Scaffolding prompt versions

Create the first version with:

~~~bash
php artisan ai:make:prompt Support.Reply --prompt-version=1.0.0
~~~

The first version becomes `current_version`. Adding another version preserves the existing manifest, templates, metadata fields, and active version:

~~~bash
php artisan ai:make:prompt Support.Reply --prompt-version=2.0.0
~~~

Activate the newly added version explicitly:

~~~bash
php artisan ai:make:prompt Support.Reply --prompt-version=2.0.0 --activate
~~~

Use `--force` only to replace the target version's generated definition and template. It does not discard unrelated versions or change `current_version` unless `--activate` is also supplied. Prompt versions must be safe single filename segments; path separators, traversal markers, NUL bytes, and control characters are rejected before files are written.

## Placeholder syntax and escaping

The prompt parser discovers and renders variables from the same parsed token stream:

| Template source | Meaning | Rendered with `name = Prince` |
|---|---|---|
| `{{name}}` | Dynamic variable | `Prince` |
| `\{{name}}` | Literal placeholder; no variable required | `{{name}}` |
| `\\{{name}}` | One literal backslash followed by a dynamic variable | `\Prince` |

Backslash runs use parity consistently: each pair produces one literal backslash, and an unmatched backslash escapes the placeholder. Unsupported or unclosed placeholder syntax remains literal. Inserted values are never parsed a second time, so a value such as `{{other}}` remains exactly `{{other}}` in the rendered output.

## Blueprint prompt usage

Blueprint request DTOs accept prompt names, versions, and variables:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Blueprints\TextToStructuredEvaluationRequest;

$request = new TextToStructuredEvaluationRequest(
    subject: 'support reply',
    text: 'We can refund the unused portion of your subscription.',
    enabledDimensions: ['clarity', 'accuracy'],
    promptName: 'support.reply-evaluation',
    promptVersion: '1.0.0',
    promptVariables: [
        'tone' => 'concise',
    ],
);
~~~

Register the referenced template before executing the workflow.

## Runtime prompt helper

For single-prompt execution, the package exposes a request-building surface:

~~~php
use CreativeCrafts\LaravelAiAgentKit\Facades\AgentKit;
use CreativeCrafts\LaravelAiAgentKit\LaravelAiAgentKit;

$result = AgentKit::run(
    LaravelAiAgentKit::prompt('package.followup-summary')
        ->withVariable('topic', 'refund window')
        ->withSchema(\App\Schemas\FollowUpSummary::class),
);
~~~

Use this when you need a direct prompt execution path rather than a full blueprint or custom agent workflow.

## Variable validation

Prompt rendering should fail clearly when required variables are missing. Keep variables explicit and avoid relying on ambient state.

Good prompt inputs:

- small scalar values
- stable identifiers
- compact summaries
- explicit options

Avoid:

- full raw conversation graphs when an ID or summary is enough
- secret-bearing values
- provider-native payloads

## Testing prompts

Test prompt rendering as package logic. Use deterministic variables and assert rendered output or typed failures. Avoid live provider calls in prompt tests.

See [Testing](testing.md).
