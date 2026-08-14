<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Prompts;

/**
 * Parses prompt templates into the canonical token stream used for discovery and rendering.
 *
 * @internal
 */
final class PromptTemplateParser
{
    /** Determine whether a name belongs to the supported placeholder grammar. */
    public static function supportsVariableName(string $name): bool
    {
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) === 1;
    }

    /** Parse dynamic and escaped placeholders while preserving malformed syntax literally. */
    public function parse(string $template): ParsedPromptTemplate
    {
        $tokens = [];
        $variables = [];
        $seenVariables = [];
        $cursor = 0;
        $searchOffset = 0;
        $templateLength = strlen($template);

        while ($searchOffset < $templateLength) {
            $openingOffset = strpos($template, '{{', $searchOffset);

            if ($openingOffset === false) {
                break;
            }

            $closingOffset = strpos($template, '}}', $openingOffset + 2);

            if ($closingOffset === false) {
                break;
            }

            $variable = trim(substr($template, $openingOffset + 2, $closingOffset - $openingOffset - 2));

            if (!self::supportsVariableName($variable)) {
                $searchOffset = $openingOffset + 2;

                continue;
            }

            $slashStart = $openingOffset;

            while ($slashStart > $cursor && $template[$slashStart - 1] === '\\') {
                $slashStart--;
            }

            $this->appendLiteral($tokens, substr($template, $cursor, $slashStart - $cursor));

            $slashCount = $openingOffset - $slashStart;
            $collapsedSlashes = str_repeat('\\', intdiv($slashCount, 2));

            if ($slashCount % 2 === 1) {
                $literalPlaceholder = substr($template, $openingOffset, $closingOffset + 2 - $openingOffset);
                $this->appendLiteral($tokens, $collapsedSlashes . $literalPlaceholder);
            } else {
                $this->appendLiteral($tokens, $collapsedSlashes);
                $tokens[] = PromptTemplateToken::variable($variable);

                if (!isset($seenVariables[$variable])) {
                    $variables[] = $variable;
                    $seenVariables[$variable] = true;
                }
            }

            $cursor = $closingOffset + 2;
            $searchOffset = $cursor;
        }

        $this->appendLiteral($tokens, substr($template, $cursor));

        return new ParsedPromptTemplate($tokens, $variables);
    }

    /**
     * Append a non-empty literal token.
     *
     * @param list<PromptTemplateToken> $tokens
     */
    private function appendLiteral(array &$tokens, string $literal): void
    {
        if ($literal === '') {
            return;
        }

        $tokens[] = PromptTemplateToken::literal($literal);
    }
}
