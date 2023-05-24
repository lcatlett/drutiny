## Creating your own policies

Policies are structured objects files that provide configuration and to
power Drutiny's audit system. Polices are provided to Drutiny via Policy Sources.

By default, Drutiny comes with one Policy Source: the local filesystem (using YAML).
Additional plugins may provide more policies from other sources.

## Getting started with YAML policies

You can create your own policies locally by using a pre-defined YAML schema and
storing the policy in a YAML file with a .policy.yml file extension anywhere
under the directory structure you call Drutiny within.

## Downloading an existing policy.

A great way to start is to download an existing policy that might be similar to
what you're interested in auditing. You can use the `policy:download` command to
download a policy into the local filesystem. This method can be used to download
an existing policy as a template for a new policy or you can make local
modifications to a downloaded policy to override its function as the policy
source defines it.

    drutiny policy:download Test:Pass

## Naming a policy

If you want to create a new policy, be sure to change the `name` attribute in
the .policy.yml file so Drutiny registers it as a new policy.

```yaml
name: MyNewPolicy
```

## Updating policy registry

Once the name has been changed, you'll need to run `policy:update` for Drutiny
to rebuild the policy register and inherit the local filesystem changes. Drutiny
should then find your new policy:

    drutiny policy:update -s localfs

# Policy structure

A policy has a number of properties that can be defined to inform Drutiny how
to audit for the policy and what content to show based on the outcome.

Policies have mandatory (required) fields and optional fields. If a mandatory
field is missing or if a field's value is of the wrong format or value, a
validation exception will be thrown and the drutiny command will error.

## title (required)

The human readable name and title of a given policy. The title should accurately
indicate what the audit is for in as few words as possible. An expanded context
can be given in the **description** field.

```yaml
title: Shield module is enabled
```

## name (required)

The machine readable name of the policy. This is used to call the policy in
`policy:audit` and to list in profiles. The naming convention is to use camel
case and to delineate namespaces with colons.

```yaml
name: Drupal:ShieldEnabled
```

## class (required)

The audit class used to assess the policy. The entire namespace should be given
including a leading forward slash. If this class does not exist, Drutiny will
fail when attempting to use this policy.

```yaml
class: \Drutiny\Audit\Drupal\ModuleEnabled
```

## type (default: audit)

Policies can be of two types: audit (the default) or data.

Audit types evaluate the target for a pass/fail result. Data types simply
collect and display the data.

This property helps define how to best display the policy in an HTML report.

```yaml
type: data
```

If the type is set to `data`then the audit cannot return a pass or fail outcome.
However, an `audit` type policy will allow the audit to return
`Drutiny\Audit\AuditInterface::NOTICE` and effectively function like a data
policy type. This might be a preferred approach if the audit should return pass
or fail in some scenarios.

## parameters

Parameters allow a policy to define values for variables used by the Audit. An audit
typically defines parameters as "arguments" and the policy sets these by declaring
them as parameters under this declaration.

The available parameters vary by audit which is depended by the `class` declaration.
Use the `audit:info` command to learn more about the available arguments to configure
the audit class with.

Parameters are statically set and are typically used for configuring the audit
specifically for how the policy wished to evaluate the target. If the parameters
need to be dynamically set, then use `build_parameters`.

```yaml
parameters:
  module: dblog
  status: Enabled
```

## parameters.variables

For audit classes that extend `Drutiny\Audit\AbstractAnalysis`, you can provide a `variables` key under
the `parameters` declaration that will be evaluated after the audit to help process audit data into
formats that are easier to render policy messaging. `variables` are sequentially rendered using Twig
syntax and preceeding variables can be used in proceeding variable renders.

⚠️ Symfony ExpressionLanguage is no longer supported.


```yaml
parameters:
  variables:
      core_needs_update: 'not ((core.releases|first).version = core.version)'
```

## build_parameters

If you need to dynamically produce your parameters inside the policy you can do so with `build_parameters`.
This is often required when a policy must provide arguments to its audit class but needs
an opportunity to do so before policy `variables` are executed (exclusive to `Drutiny\Audit\AbstractAnalysis`).
`build_parameters` consists of key value pairs where the values are executed through the twig engine
and the result is set as a parameter for the provided key.

```yaml
build_parameters:
  domain: 'parse_url(target.url, 1)'
```

## depends

The depends directive allows policies to only apply if one or more conditions are
meet such as an enabled module or the value of a target environmental variable (like OS).

Dependencies can be evaluated in [Twig](https://twig.symfony.com/) syntax.

⚠️ ExpressionLanguage is no longer supported.

```yaml
depends:
  - expression: Policy.succeeds('fs:largeFiles')'
    on_fail: 'error'
  - expression: Drupal.moduleIsEnabled('syslog') && Drupal.moduleVersionSatisfies('purge', '^4.0')
```

Each depends item must have an associated `expression` key with the expression to evaluate.
Optionally, an `on_fail` property can be added to indicate the failure behaviour:

## On fail

| Value         | Behaviour                |
| ------------- | ------------------------ |
| `omit`        | Omit policy from report  |
| `fail`        | Fail policy in report    |
| `error`       | Report policy as error   |
| `report_only` | Report as not applicable |

## description (required)

A human readable description of the policy. The description should be informative
enough for a human to read the description and be able to interpret the audit
findings.

This field is interpreted as [Markdown](https://daringfireball.net/projects/markdown/syntax)
You can use [Twig](https://twig.symfony.com/) templating to render dynamic content.

```yaml
description: |
  Using the pipe (|) symbol in yaml,
  I'm able to provide my description
  across multiple lines.
```

## success (required)

The success message to provide if the audit returns successfully.

This field is interpreted as [Markdown](https://daringfireball.net/projects/markdown/syntax)
You can use [Twig](https://twig.symfony.com/) templating to render dynamic content.

Available variables are those passed in as parameters or set as tokens by the audit class.

```yaml
success: The module {{module_name}} is enabled.
```

## failure (required)

If the audit fails or is unable to complete due to an error, this message will

This field is interpreted as [Markdown](https://daringfireball.net/projects/markdown/syntax)
You can use [Twig](https://twig.symfony.com/) templating to render dynamic content.

Available variables are those passed in as parameters or set as tokens by the audit class.

```yaml
failure: {{module_name}} is not enabled.
```

## tags

The tags key is simply a mechanism to allow policies to be grouped together.
For example "Drupal 9" or "Performance".

```yaml
tags:
  - Drupal 9
  - Performance
```

## severity

Not all policies are of equal importance. Severity allows you to specify how
critical a failure or warning is. Possible values in order of importance:

-   none (only option for `data` type policies)
-   low
-   medium (default)
-   high
-   critical

```yaml
severity: 'high'
```

## chart

Charts in Drutiny are an HTML format feature that allows rendered tabular data
in a policy to be visualized in a chart inside of the HTML generated report.

Under the hood, Drutiny uses [chart.js](https://www.chartjs.org/) to render charts.

A chart is defined inside of a [Policy](policy.md) as metadata and rendered
inside of either the success, failure, warning or remediation messages also
provided by the policy.

```yaml
chart:
  requests:
    type: doughnut
    labels: tr td:first-child
    hide-table: false
    title: Request Distribution by Domain
    height: 300
    width: 400
    legend: left
    colors:
      - rgba(46, 204, 113,1.0)
      - rgba(192, 57, 43,1.0)
      - rgba(230, 126, 34,1.0)
      - rgba(241, 196, 15,1.0)
      - rgba(52, 73, 94,1.0)
    series:
      - tr td:nth-child(4)
success: |
  Here is a doughnut chart:
  {{_chart.requests|chart}}
```

### Configuration

Any given policy may have a `chart` property defined in its `.policy.yml` file.
The `chart` property contains a arbitrarily keyed set of chart definitions.

```yaml
chart:
  my_chart_1:
    # ....
  my_chart_2:
    # ....
```

Charts use tabular data from the first sibling table in the DOM.

### Chart Properties

`labels` and `series` use css selectors powered by jQuery to obtain the data to
display in the chart.

| Property     | Description                                                                                                                  |
| ------------ | ---------------------------------------------------------------------------------------------------------------------------- |
| `type`       | The type of chart to render. Recommend `bar`, `pie` or `doughnut`.                                                           |
| `labels`     | A css selector that returns an array of HTML elements whose text will become labels in a pie chart or x-axis in a bar graph. |
| `hide-table` | A boolean to determine if the table used to read the tabular data should be hidden. Defaults to false.                       |
| `title`      | The title of the graph                                                                                                       |
| `series`     | An array of css selectors that return the HTML elements whose text will become chart data.                                   |
| `height`     | The height of the graph area set as a CSS style on the `<canvas>` element.                                                   |
| `width`      | The width of the graph area set as a CSS style on the `<canvas>` element.                                                    |
| `x-axis`     | The label for the x-axis.                                                                                                    |
| `y-axis`     | The label for the y-axis.                                                                                                    |
| `legend`     | The position of the legend. Options are: top, bottom, left, right or none (to remove legend).                                |
| `colors`     | An array of colors expressed using RGB syntax. E.g. `rgba(52, 73, 94,1.0)`.                                                  |

### Rendering a Chart

Rendered charts are available as a special `_chart` token to be used in success,
failure, warning or remediation messages provided by the policy.

```yaml
success: |
  Here is the chart:
  {{_chart.my_chart_1|chart}}
```

# Using Tokens in message renders.

Tokens are variables you can use in the render of policy messaging.
This includes success, failure, warning and description fields defined in a policy.

## Available Tokens

Use the `policy:info` command to identify which tokens are available in a policy
for use in the render.

```bash
$ drutiny policy:info fs:largeFiles
```

 You can see in the above example output, that `fs:largeFiles` contains three
 tokens: `_max_size`, `issues` and `plural`. These tokens are available to use as
 template variables when writting messaging for a policy:

```yaml
failure: |
  Large public file{{plural}} found over {{max_size}}
  {% for issue in issues %}
     - {{issue}}
  {% endfor %}
```

 These variables are rendered using [Twig](https://twig.symfony.com/) templating.
 The messages are also parsed by a markdown parser when rendering out to HTML.
