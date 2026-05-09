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
