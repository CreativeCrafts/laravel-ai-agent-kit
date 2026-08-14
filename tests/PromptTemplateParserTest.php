<?php

declare(strict_types=1);

use CreativeCrafts\LaravelAiAgentKit\Prompts\PromptTemplate;

it('preserves templates with no dynamic variables', function () {
    $template = PromptTemplate::fromContent(
        name: 'literal.only',
        version: '1.0.0',
        content: 'Explain {{invalid-name}} and leave {{unclosed literal.',
    );

    expect($template->variables)
      ->toBe([])
      ->and($template->render())
      ->toBe('Explain {{invalid-name}} and leave {{unclosed literal.');
});

it('discovers and renders each dynamic variable from one parsed representation', function () {
    $template = PromptTemplate::fromContent(
        name: 'mixed.variables',
        version: '1.0.0',
        content: '{{ first }} + {{second}} + {{first}} + \\{{ignored}}',
    );

    expect($template->variables)
      ->toBe(['first', 'second'])
      ->and($template->render(['first' => 'A', 'second' => 'B']))
      ->toBe('A + B + A + {{ignored}}');
});

it('defines odd and even backslash escaping deterministically', function () {
    $content = <<<'PROMPT'
\{{name}} | \\{{name}} | \\\{{name}}
PROMPT;
    $template = PromptTemplate::fromContent('escape.parity', '1.0.0', $content);

    expect($template->variables)
      ->toBe(['name'])
      ->and($template->render(['name' => 'Prince']))
      ->toBe('{{name}} | \\Prince | \\{{name}}');
});

it('does not recursively interpolate placeholder syntax inside inserted values', function () {
    $template = PromptTemplate::fromContent(
        name: 'non.recursive',
        version: '1.0.0',
        content: 'Value: {{value}}',
    );

    expect($template->render(['value' => '{{other}}']))
      ->toBe('Value: {{other}}');
});
