# Twig Extensions

Drutiny uses the Twig templating engine to:

* Template profile reports into Markdown and HTML formats
* Template policy success, failure, warning, remediation and description messages.
* Evaluate [dynamic parameters](DynamicParameters.md) including `build_parameters`,
  `expression`, `failIf` and more.

Drutiny uses Twig 3 and you can refer to [Twig's documentation](https://twig.symfony.com/doc/3.x/) 
and reference manual for how to work with Twig templates and programming syntax.

Drutiny extends Twig with additional language features to help support the way
Drutiny uses Twig.

## Default Extensions
By default, Drutiny loads three extensions from Twig:

* `Twig\Extension\DebugExtension`
* `Twig\Extension\StringLoaderExtension`
* `Twig\Extra\String\StringExtension`

## Line breaks
One point of differences between Twig and Drutiny's Twig is how line breaks are handled
in policy expressions. Typically Twig programing takes place on a single line but in
Drutiny you can break that up over multiple lines to improve readibility of longer
fragments of Twig.

```yaml
parameters:
    failIf: |
        values 
        | array_filter(
            value => value.status == 200
        )
        | length
```

## Expression reference
Drutiny contains a command called `expression:reference` which can be called to get a list 
of alias twig expressions that can be used in addition to this documentation.

```
drutiny expression:reference
```

## Filters

Filters are functions that can have data "piped" into them and must always proceed a `|` in twig syntax.

```
input_data | filter(...args)
```

### array_merge (deprecated)

array_merge is identical to Twig's [merge](https://twig.symfony.com/doc/3.x/filters/merge.html) filter.
It is deprecated, use `merge` instead.

```yaml
parameters:
    variables:
        # Creates [1,2,'a', 'b']
        values: '[1,2]|array_merge(['a', 'b'])'
```

### sum

The `sum` filter allows you to sum an array of values like [PHP's array_sum](https://php.net/array_sum).

```yaml
parameters:
    variables:
        # 10
        value: '[1, 2, 3, 4] | sum'
```

### unique

The `unique` filter will filter down an array of values to unique values. Like [PHP's array_unique](https://php.net/array_unique).

```yaml
parameters:
    variables:
        # [1, 2, 3, 4]
        value: '[1, 2, 1, 3, 2, 4, 3] | unique'
```

### values

The `values` filter turns the keys in an array into a numeric sequence like [PHP's array_values](https://php.net/array_values).

```yaml
parameters:
    variables:
        # ['bar']
        value: '{foo: "bar"} | values'
```

### chart

The `chart` filter converts a policy chart object into an HTML snippet ready for
chart.js rendering with Drutiny HTML profile templates.

```yaml
chart:
    my_line_graph:
        # ... See policy charts.
success: |
    {{ chart.my_line_graph|chart }}

```

### extract

The `extract` filter allows you to extract values from strings using regular expressions.
The first argument is the regular expression to match and the second argument is the 
index of the match to return where zero returns the entire match and subsequent numbers
represent any bracket matches within the string.

```yaml
parameters:
    variables:
        output: "The quick brown fox jumps over the lazy dog"
        # Returns "brown".
        color: output | extract('/quick (a-z+) fox/', 1)
```

### format_bytes

The `format_bytes` filter will format bytes into bytes (B), kilobytes (KB), megabytes (MB),
gigabytes (GB) or terabytes (TB) depending on the size of bytes passed to the filter.

```yaml
parameters:
    variables:
        bytes: 1258291
# 1.2MB
success: There are {{ bytes | format_bytes }} of data.
```

### json_decode
The `json_decode` filter functions like [json_decode in PHP](https://php.net/json_decode). This is largely an 
internal function used to extract variables from twig strings.


### heading
The `heading` filter decorates headings in profile templates so that HTML navigation
can use the headings for menus and tables of contents. The heading size can be passed
as the first argument.

```yaml
format:
    html:
        content: |
            {% block test_pass %}
              {% set response = assessment.getPolicyResult('Test:Pass') %}
              {{ response.policy.title | heading(3) }}
              {% with response.tokens %}
                {{ include(template_from_string(response.policy.description)) | markdown_to_html }}
                {{ include(template_from_string(response.policy.success)) | markdown_to_html }}
              {% endwith %}
            {% endblock %}
```

### yaml_dump

The `yaml_dump` filter is a debug filter that can be used to dump contents into yaml format for viewing.
And is a wrapper for [Symfony's Yaml::dump utility](https://symfony.com/doc/current/components/yaml.html#writing-yaml-files).

```yaml
parameters:
    variables:
        '!configuration':
            bad: 'config'
            int: '1',
            bool: 0
    failIf: configuration.bool is same as(false)
failure: |
    Your configuration is incorrect:
    ```yaml
    {{ configuration|yaml_dump }}
    ```
```

## Functions

Functions in twig cannot be "piped" into. Data can only be passed in via arguments.

```twig
{{ function(..args) }}
```

### chart_and_table

The `chart_and_table` function renders both a table and chart in a policy messaging field (success, failure, warning, remediation).
The chart is further preconfigured to dynamically set series, labels and x-axis based on the columns and rows of the table.

Note: Pie charts are not tested with this function.

* The first column will be the x-axis.
* Subsequent columns will be series in the line or bar graph.

The `chart_and_table` function takes three arguments:
* headers - the headers to use for the table,
* rows - and array of rows for the table,
* chart object - the chart object pre-configured from the policy definition.

```yaml
chart:
    my_line_graph:
        # ...
build_parameters:
    '!headers':
        - Sequence
        - Car
        - Bike
parameters:
    variables:
        '!rows':
            - [1, 20, 30]
            - [2, 25, 40]
            - [3, 30, 50]
            - [4, 35, 60]
success: |
    ## Speed after X seconds
    {{ chart_and_table(headers, rows, chart.my_line_graph) }}
```

### combine
The `combine` function works like [PHP's array_combine](https://php.net/array_combine).

```yaml
parameters:
    variables:
        # {foo: 'bar'}
        data: array_combine(['foo'], ['bar'])
```

### explode (deprecated)
The `explode` function works like [PHP's explode function](https://php.net/explode).
Twig has its own filter called `split` which should be used instead.

```yaml
parameters:
    variables:
        data: '1,2,3,4,5,6'
        values: explode(',', data)
        # use split instead
        values: data | split(',')
```

### is_target

The is_target function returns a boolean depending on if the passed variable is
a target or of the right type of target.

An expression:reference alias for this exists called `Target.typeOf` which can be used
to ensure a policy can only be used by a specific type of target.

```yaml
name: Drupal:SyslogModule:enabled
class: Drutiny\Audit\Drupal\ModuleAnalysis
depends:
    # Ensure the target type is or extends the drush target type.
    - expression: Target.typeOf('drush')
```

### parse_url
The `parse_url` function works like [PHP's parse_url function](https://php.net/parse_url).
Potential keys within this array are:

* scheme - e.g. http
* host
* port
* user
* pass
* path
* query - after the question mark ?
* fragment - after the hashmark #

```yaml
parameters:
    url: https://www.google.com/search?q=drutiny
    variables:
        link: parse_url(url)
        host: link.host
```

### policy_result

The `policy_result` function renders a policy audit response. This is useful in 
profile templating:

```yaml
format:
    html:
        content: |
            {{ policy_result(assessment.getPolicyResult('Test:Pass'), assessment }}
```

policy_result requires two arguments:
- The policy audit response to render.
- The assessment object which is available in profile templates.

### semver_satisfies

The `semver_satisfies` function allows you to test if a semantic version works with a given
versioning constraint as [described by Composer](https://getcomposer.org/doc/articles/versions.md).

An expression:reference alias for this function exists called `SemVer.satisfies`.

```yaml
parameters:
    failIf: SemVer.satisfies(module.version, '<3.4')
```

## Tests

Tests return boolean based on there assessment.

### numeric

Returns true if the value is numeric. Like [PHP's is_numeric function](https://php.net/is_numeric).

```yaml
parameters:
    variables:
        # String '1'
        number: '1'
    # Is string but is numeric so fail condition not met.
    failIf: number is not numeric
```

### keyed

Returns true if the variable is an array and the first key is a string.

Note: future versions of this will likely mimic [PHP's array_is_list function](https://php.net/array_is_list).

```yaml
parameters:
    variables:
        values: 'data is keyed ? (data|values) : data'
```