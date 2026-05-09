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

The in-memory registry validates a deterministic JSON-schema subset:

- root schema must be `type: object`
- `properties` must be a top-level object map
- supported property types are `string`, `integer`, `number`, `boolean`, `array`, and `object`
- `required` must list declared property names
- `additionalProperties` may be `true` or `false`

Nested JSON Schema features such as `oneOf`, nested `properties`, complex `items`, and format/pattern validation are intentionally out of scope for the built-in validator.

## Authorization

The default authorizer denies tool execution. Replace it with a policy that allows only the tools, users, tenants, and contexts your application supports.

Do not treat registration as authorization. Registration says a tool exists; authorization says it may run for the current request.

## Provider tools

Provider-native tools such as web or file search should still be selected through package request surfaces and provider profile policy. Do not expose provider SDK tool objects as your application workflow contract.

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
