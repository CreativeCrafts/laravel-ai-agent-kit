# Tools

Tools let workflows call application capabilities through package-owned contracts. Tool execution is default-deny: a tool must be registered, its input must validate, and an authorizer must allow the call.

## Register tools explicitly

The package does not auto-discover tools for execution. Register only the tools your application intends to expose.

A tool definition should describe:

- stable tool name
- input schema
- handler behavior
- authorization requirements
- safe output shape

## Input schema validation

The in-memory registry validates a deterministic JSON-schema subset before a custom tool handler is called.

Supported validation includes:

- root schema must be `type: object`
- `properties` must be an object map
- supported property types are `string`, `integer`, `number`, `boolean`, `array`, and `object`
- `required` must list declared property names
- `additionalProperties` may be `true` or `false` and is enforced at every object level
- nested `object.properties` and nested `required` lists are validated recursively
- `array.items` is validated recursively when declared
- `nullable: true` allows `null` for that property
- scalar `enum` values are enforced when declared

Validation errors include property paths such as `customer.email` or `items[0]` where practical.

Unsupported JSON Schema features such as `oneOf`, `anyOf`, complex conditional schemas, format validation, and pattern validation remain out of scope for the built-in validator.

## Authorization

The default authorizer denies tool execution. Replace it with a policy that allows only the tools, users, tenants, and contexts your application supports.

Do not treat registration as authorization. Registration says a tool exists; authorization says it may run for the current request. Input validation runs before authorization, and the tool handler runs only after both validation and authorization succeed.

## Provider tools

Provider-native tools such as web or file search should still be selected through package request surfaces and provider profile policy. Do not expose provider SDK tool objects as your application workflow contract.

Provider tools are authorized separately from package custom tools because provider-native tools execute on the provider side and may have different billing, privacy, and rate-limit implications.

## Similarity search tool

The optional `similarity_search` tool embeds a query with `EmbeddingsRuntime` and searches your configured `VectorStoreInterface`.

Enable it only when you have configured the vector store and authorizer intentionally:

~~~php
'tools' => [
    'similarity_search' => [
        'enabled' => true,
        'register' => true,
        'default_namespace' => 'support',
        'default_limit' => 5,
    ],
],
~~~

Then list `similarity_search` in the runtime request tool names and authorize it through your tool authorizer.

## Safety checklist

Before exposing tools in production:

- validate inputs before execution
- deny by default
- authorize by user, tenant, and workflow context
- avoid logging raw tool inputs or outputs when they may contain sensitive data
- keep outputs structured and bounded
- add deterministic tests for allowed and denied paths

See [Testing](testing.md) and [Production](production.md).
