<?php

declare(strict_types=1);

namespace CreativeCrafts\LaravelAiAgentKit\Observability\Support;

enum FailureCategory: string
{
    case ExecutionFailed = 'execution_failed';

    case ProviderFailure = 'provider_failure';

    case ProviderTransport = 'provider_transport';

    case ProviderOverloaded = 'provider_overloaded';

    case RateLimited = 'rate_limited';

    case AuthenticationFailed = 'authentication_failed';

    case QuotaExceeded = 'quota_exceeded';

    case InvalidRequest = 'invalid_request';

    case UnsupportedCapability = 'unsupported_capability';

    case ConfigurationFailure = 'configuration_failure';

    case ToolAuthorizationDenied = 'tool_authorization_denied';

    case ConversationFailure = 'conversation_failure';

    case BudgetExceeded = 'budget_exceeded';

    case Refusal = 'refusal';

    case MalformedOutput = 'malformed_output';

    case InvalidOutput = 'invalid_output';

    case ProviderProfileMismatch = 'provider_profile_mismatch';

    case FailoverPolicyError = 'failover_policy_error';

    case LogicalFailure = 'logical_failure';
}
