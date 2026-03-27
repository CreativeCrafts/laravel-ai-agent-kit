<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Core\Orchestration;

use CreativeCrafts\LaravelAiAgentKit\Contracts\Agents\AgentRegistry;
use CreativeCrafts\LaravelAiAgentKit\Contracts\Orchestration\DelegationPolicyEngine;
use CreativeCrafts\LaravelAiAgentKit\Core\Agents\AgentDefinition;
use CreativeCrafts\LaravelAiAgentKit\Core\Orchestration\Exceptions\InvalidDelegationTargetException;
use InvalidArgumentException;

final readonly class ConfigurableDelegationPolicyEngine implements DelegationPolicyEngine
{
    /**
     * @param array<string, list<string>> $allowlist
     * @param array<string, array<string, string>> $rewrites
     */
    public function __construct(
        private AgentRegistry $agentRegistry,
        private DelegationPolicyMode $mode = DelegationPolicyMode::STATIC_ONLY,
        private array $allowlist = [],
        private array $rewrites = [],
    ) {
        $this->assertAllowlistShape($this->allowlist);
        $this->assertRewriteShape($this->rewrites);
    }

    public function evaluate(AgentDefinition $agentDefinition, DelegationProposal $proposal): DelegationPolicyDecision
    {
        $effectiveTargetAgent = $this->rewriteTarget(
            sourceAgentKey: $agentDefinition->key,
            targetAgent: $proposal->targetAgent,
        );

        $allowedTargets = $this->allowedTargets($agentDefinition);

        if (!in_array($effectiveTargetAgent, $allowedTargets, true)) {
            throw InvalidDelegationTargetException::forAgent(
                agentKey: $agentDefinition->key,
                targetAgent: $effectiveTargetAgent,
                allowedTargets: $allowedTargets,
            );
        }

        if ($effectiveTargetAgent === $proposal->targetAgent) {
            return new DelegationPolicyDecision(
                proposal: $proposal,
                mode: $this->mode,
            );
        }

        return new DelegationPolicyDecision(
            proposal: new DelegationProposal(
                mode: $proposal->mode,
                targetAgent: $effectiveTargetAgent,
                handoff: $proposal->handoff,
            ),
            mode: $this->mode,
            rewritten: true,
            originalTargetAgent: $proposal->targetAgent,
        );
    }

    /**
     * @param array<mixed> $allowlist
     */
    private function assertAllowlistShape(array $allowlist): void
    {
        foreach ($allowlist as $sourceAgentKey => $targets) {
            if (!is_string($sourceAgentKey) || $sourceAgentKey === '') {
                throw new InvalidArgumentException('Delegation policy allowlist keys must be non-empty strings.');
            }

            if (!is_array($targets)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Delegation policy allowlist for [%s] must be an array of target agent keys.',
                        $sourceAgentKey,
                    ),
                );
            }

            foreach ($targets as $target) {
                if (!is_string($target) || $target === '') {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Delegation policy allowlist entries for [%s] must be non-empty strings.',
                            $sourceAgentKey,
                        ),
                    );
                }
            }
        }
    }

    /**
     * @param array<mixed> $rewrites
     */
    private function assertRewriteShape(array $rewrites): void
    {
        foreach ($rewrites as $sourceAgentKey => $mappings) {
            if (!is_string($sourceAgentKey) || $sourceAgentKey === '') {
                throw new InvalidArgumentException('Delegation policy rewrite keys must be non-empty strings.');
            }

            if (!is_array($mappings)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Delegation policy rewrites for [%s] must be an associative array of source and target agent keys.',
                        $sourceAgentKey,
                    ),
                );
            }

            foreach ($mappings as $fromTarget => $toTarget) {
                if (!is_string($fromTarget) || $fromTarget === '') {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Delegation policy rewrite source targets for [%s] must be non-empty strings.',
                            $sourceAgentKey,
                        ),
                    );
                }

                if (!is_string($toTarget) || $toTarget === '') {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Delegation policy rewrite target mappings for [%s] must be non-empty strings.',
                            $sourceAgentKey,
                        ),
                    );
                }
            }
        }
    }

    private function rewriteTarget(string $sourceAgentKey, string $targetAgent): string
    {
        return $this->rewrites[$sourceAgentKey][$targetAgent] ?? $targetAgent;
    }

    /**
     * @return list<string>
     */
    private function allowedTargets(AgentDefinition $agentDefinition): array
    {
        $targets = match ($this->mode) {
            DelegationPolicyMode::STATIC_ONLY => $agentDefinition->delegationTargets,
            DelegationPolicyMode::DYNAMIC_WITH_ALLOWLIST => array_values(array_unique([
              ...$agentDefinition->delegationTargets,
              ...($this->allowlist[$agentDefinition->key] ?? []),
            ])),
            DelegationPolicyMode::DYNAMIC_FULL_REGISTRY => array_keys($this->agentRegistry->all()),
        };

        $rewrittenTargets = [];

        foreach ($targets as $target) {
            $rewrittenTarget = $this->rewriteTarget($agentDefinition->key, $target);

            if ($this->agentRegistry->has($rewrittenTarget) && !in_array($rewrittenTarget, $rewrittenTargets, true)) {
                $rewrittenTargets[] = $rewrittenTarget;
            }
        }

        return $rewrittenTargets;
    }
}
