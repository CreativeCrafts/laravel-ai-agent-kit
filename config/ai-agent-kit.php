<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Config Validation
    |--------------------------------------------------------------------------
    |
    | When enabled, the package validates this configuration during service
    | provider registration (fail-fast). Disable only for advanced bootstrapping
    | scenarios or highly customized test setups.
    |
    */
    'validation' => [
        'enabled' => (bool) env('AI_AGENT_KIT_VALIDATE_CONFIG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers
    |--------------------------------------------------------------------------
    |
    | Providers are referenced by name (array key). Each provider must declare
    | a non-empty `driver`. Provider-specific settings go into `options`.
    |
    */
    'providers' => [
        'null' => [
            'driver' => 'null',
            'enabled' => true,
            'options' => [],
        ],

        // 'openai' => [
        //     'driver' => 'openai',
        //     'enabled' => true,
        //     'options' => [
        //         // e.g. 'api_key' => env('OPENAI_API_KEY'),
        //     ],
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Selection
    |--------------------------------------------------------------------------
    |
    | `default_provider` is the primary provider name. `failover_order` is an
    | ordered list of enabled provider names that may be attempted.
    |
    */
    'default_provider' => (string) env('AI_AGENT_KIT_DEFAULT_PROVIDER', 'null'),

    'failover_order' => array_values(array_filter(
        explode(',', (string) env('AI_AGENT_KIT_FAILOVER_ORDER', 'null')),
        static fn (string $name): bool => $name !== ''
    )),

    /*
    |--------------------------------------------------------------------------
    | Budgets (reserved for enforcement in later milestones)
    |--------------------------------------------------------------------------
    |
    | These keys are validated now to support fail-fast configuration safety.
    | Enforcement is implemented in later phases.
    |
    */
    'budgets' => [
        'max_steps' => (int) env('AI_AGENT_KIT_MAX_STEPS', 20),
        'max_tool_calls' => (int) env('AI_AGENT_KIT_MAX_TOOL_CALLS', 50),
        'max_retries_per_step' => (int) env('AI_AGENT_KIT_MAX_RETRIES_PER_STEP', 2),
        'max_total_timeout_seconds' => (int) env('AI_AGENT_KIT_MAX_TOTAL_TIMEOUT_SECONDS', 120),
        'max_tokens' => env('AI_AGENT_KIT_MAX_TOKENS') === null ? null : (int) env('AI_AGENT_KIT_MAX_TOKENS'),
        'max_cost_usd' => env('AI_AGENT_KIT_MAX_COST_USD') === null ? null : (float) env('AI_AGENT_KIT_MAX_COST_USD'),
    ],
];
