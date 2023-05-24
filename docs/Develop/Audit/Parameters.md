# Parameters
Parameters allow you to delcare inputs that a policy can provide to
your audit class. These inputs are typically to instruct data gathering.
For example:

- A query string for gathering data from queriable locations (e.g. APIs, Databases, etc)
- Limits and other flags to conduct the audit behaviour.

Parameters are declared using PHP Attributes on the Class declaration.

```php
<?php

namespace Demo\CustomDrutinyProject\Audit;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

#[Parameter(
    name: 'multiplier', 
    default: 1,
    type: Type::INTEGER,
    enums: [1,2,3,4,5,6,7,8,9,10],
    description: 'A multiplier between 1 and 10.',
    mode: Parameter::REQUIRED,
)]
class ProjectDataGatherer extends AbstractAnalysis {

    protected function gather(Sandbox $sandbox) {
        $this->set('value', 4 * $this->getParameter('multiplier'));
    }
}
```

In the example above a parameter called `multiplier` is declared that has a default value of `1`.
The value set by the policy must be an `integer` between `1` and `10` and it must be provided.
The parameter is then multipled by 4 and the result set as a token called `value` which a policy
can use in its evaluation of outcome or messaging.

Only `name` and `description` are required properties.

Note: `default` values are only useful for optional parameters but are displayed here to show a fullness
of possibilities.

In the `gather` method, parameters can be retrieved using the `getParameter` method.

## Setting Parameter Type
There are several parameters types you can use to enforce strict type data passing from policies to audit classes.

Type             | Example               | Description
---------------- | --------------------- | ----------------------------------------
`Type::BOOLEAN`  | `true`                | A true or false value
`Type::INTEGER`  | `3`                   | A positive or negative number
`Type::FLOAT`    | `3.4`                 | A decimal number
`Type::STRING`   | `'foo'`               | A string of characters
`Type::ARRAY`    | `['a', 'b']`          | A numerically sequential list of items.
`Type::HASH`     | `['a' => 1, 'b' => 2]`| A keyed list of items.

Note: In PHP terminology, a hash can be an associative array or an object.

Types are set using the `type` argument in the Parameter declaration.

```php
<?php

use Drutiny\Attribute\Type;
use Drutiny\Attribute\Parameter;

#[Parameter(
    name: 'format',
    description: 'Which format to return a result in.',
    type: Type::STRING
)]
```

Note: the Type enum comes from `Drutiny\Attribute\Type`.

## Enums
Enums are a way to restrain parameter inputs to a specified list of items

```php
<?php
#[Parameter(
    name: 'format',
    description: 'Which format to return a result in.',
    enums: ['yaml', 'json', 'csv', 'raw']
)]
```

When using enums, the `type` field is not required since the data input must be one of the already known enum values.
If the policy provides a value outside the enum list, a parameter validation error will be thrown.

## Default values
Parameters can provide default values when a value is not provided.

```php
<?php
#[Parameter(
    name: 'format',
    description: 'Which format to return a result in.',
    enums: ['yaml', 'json', 'csv', 'raw'],
    default: 'json'
)]
```

This allows policies to not have to explicitly specify every available parameter in an audit class.
It also works well when building audit classes ontop of existing audit classes.

### Runtime default values
If you prefer to determine a default value at runtime, you can pass it as the second argument to the `getParameter` method.

```php
<?php

namespace Demo\CustomDrutinyProject\Audit;

use Drutiny\Attribute\Parameter;
use Drutiny\Attribute\Type;
use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

#[Parameter(
    name: 'format',
    description: 'Which format to return a result in.',
    enums: ['yaml', 'json', 'csv', 'raw'],
)]
class ProjectDataGatherer extends AbstractAnalysis {

    protected function gather(Sandbox $sandbox) {
        //...
        // Use the format defined by the policy or 'json' if none is provided.
        $format = $this->getParameter('format', 'json');
        //...
    }
}
```

## Making parameters required
By default a parameter is optional. However, you can set it as required by setting the parameter `mode`.

```php
<?php
#[Parameter(
    name: 'format',
    description: 'Which format to return a result in.',
    mode: Parameter::REQUIRED
)]
```

Using the `Parameter::REQUIRED` constant as the `mode` value will ensure the policy provides this parameter beforce the audit
data gathering can take place by the audit class.