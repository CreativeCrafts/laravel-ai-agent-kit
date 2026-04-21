<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Support;

enum FailureCategory: string
{
    case ExecutionFailed = 'execution_failed';

    case ProviderFailure = 'provider_failure';

    case BudgetExceeded = 'budget_exceeded';

    case Refusal = 'refusal';

    case MalformedOutput = 'malformed_output';

    case InvalidOutput = 'invalid_output';

    case ProviderProfileMismatch = 'provider_profile_mismatch';

    case FailoverPolicyError = 'failover_policy_error';

    case LogicalFailure = 'logical_failure';
}
