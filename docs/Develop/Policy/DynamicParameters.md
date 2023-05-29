# Dynamic Parameters

Dynamic parameters are introduced in version 3.6.

Dynamic parameters allow you to preprocess parameters before they
are passed into an audit to be used to gather and evaluate data.

Although processing only occurs on strings, hashes (associative arrays)
will be traversed for deeper keys that which to utilise dynamic parameters.

Dynamic parameters are declared by prefixing a parameter with a processing
symbol. There are three symbols: `^`, `$` and `!`. When a dynamic parameter
is processed, its prefixed symbol is removed.

Lets learn what they do.

## Token replacement with the `^` symbol.

When the caret (`^`) prefixes a parameter name, that parameter value will 
have token replacement (also called interpolation) processed over it.

Tokens in strings are denoted with single curly braces tightly wrapped (no 
spaces) around the token value.

### Example
Lets say we have a target with a domain value of `foo.site.com`. To use this value in a parameter with token replacement you would do this:

```yaml
parameters:
    ^query: errors=1 AND domain={target.domain}
```

After processing, the parameters would look like this:

```yaml
parameters:
    query: errors=1 AND domain=foo.site.com
```

Token replacement is useful when you don't want to over complicate
a parameter with twig evaluative syntax. For example, parameters that
use another language syntax maybe increase its complexity to use twig.

Token replacement also works on embedded parameters:

```yaml
parameters:
    options: 
        ^filter: domain={target.domain}
```

## Evaluated parameters with the `$` symbol.

When the dollar symbol (`$`) prefixes a parameter name, that value will
be evaluated and its result set as the parameter value.

### Example:

Using the same example as token replacement but using twig evaluation instead.

```yaml
parameters:
    $query: "errors=1 AND domain=" ~ target.domain
```

Here you might notice the `query` string is more visually disrupted by this 
syntax. In this use case, token replacement is probably a better method.

However, evaluated parameters are better suited for heavier work such
as filtering results into new parameters.

```yaml
parameters:
    $zone: 'target["zone.list"] is defined ? target["zone.list"] | first : target.domain'
```

Here the `zone` parameter will be either the first value in an array from a target's 
dynamic property list called `zone.list` or if that is not present, the target's
domain property will be used.

## Static parameters with the `!` symbol.
When the asterisk (`!`) symbol prefixes a parameter value, it will force the parameter to be static rather than evaluated. This is primarily used for 
`build_parameters` which are inheritly evaluated parameters with the need to denote
them as such. Instead, build_parameters but be explicitly denoted as static or token replaced if they do not want to be evaluated.

### Example

```yaml
build_parameters:
    '!options': 
        filter:
            $version: target.version
        limit: 20
        sort: count 
```

Normally, a build parameter must be a string to be evaluated. However here, the
value can be statically provided. The static value can also be traversed and its 
values processed as the `$version` key would be here.
