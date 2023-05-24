# Dependencies
Audit dependencies are optional attributes that allow your audit class to depend on conditions
that qualify the policy using your audit class.

```php
<?php

namespace Demo\CustomDrutinyProject\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Policy\Dependency;

#[Dependency(
    expression: 'Drupal.isVersion10orLater',
    on_fail: 'omit'
)]
class ProjectDataGatherer extends AbstractAnalysis {

    protected function gather() {
        $this->set('foo', 'bar');
    }
}
```

In the above example, the audit class requires the Target being audited is Drupal 10 or later instance.
This is determined by the metadata preloaded onto the Target before the audit is executed.

## Expression
The expression field must evaluate to true to satisfy the dependency. If it doesn't, the the `on_fail` behaviour
is executed. The expression is a string that will evaluate Twig syntax.

To find a list of available expressions flags to use, see `drutiny expression:reference` command.

### Requiring a type of Target
If your audit class is only suitable for certain types of Targets, you can use the `Target.typeOf` flag
in your expression where the value passed is the name of a target source found in the `drutiny target:sources` command.

```php
<?php

// Ensure the target is a "drush" target type.
#[Dependency(
    expression: 'Target.typeOf("drush")',
    on_fail: 'omit'
)]
```

Note: the `Target.typeOf` check respects class inheritence and Targets extending the type you're testing for
will pass the check also.

## On Fail behaviour
On fail behaviours are the same as those [defined in policies](../Policy/GettingStarted.md/#on-fail).

### Behaviour types

| Type          | Behaviour                |
| ------------- | ------------------------ |
| `omit`        | Omit policy from report  |
| `fail`        | Fail policy in report    |
| `error`       | Report policy as error   |
| `report_only` | Report as not applicable |

Note: If you cannot define a correct fail behaviour, it may suggest the dependency is better of defined in the policy
rather than the audit.

The default behaviour is not specified is to `fail`.

Behaviours can be set by providing the `on_fail` argument to the Dependency attribute.

```php
<?php

// Ensure the target is a "drush" target type.
#[Dependency(
    expression: 'Target.typeOf("drush")',
    on_fail: 'error'
)]
```