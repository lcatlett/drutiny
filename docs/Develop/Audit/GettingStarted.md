## Creating your own audit classes

Audit classes gather data for policies to use and evaluate.
Providing your own audit classes allows you to build custom policies that can assess the data
your audit classes gather.

## Creating your first audit class
### Setup
Before you start, you should have these pre-requisites in place:

* A knowledge of PHP object-oriented programing from version PHP 8.1 or later.
* A PHP project built with composer and using PSR-4 autoloading.
* The [drutiny/drutiny](https://packagist.org/packages/drutiny/drutiny) composer package added to your project.

For the purposes of this tutorial, we'll use a simple composer.json build like this:

```json
{
  "name": "demo/custom-drutiny-project",
  "type": "project",
  "prefer-stable": true,
  "description": "Scaffold composer file for learning to build drutiny audit classes.",
  "require": {
    "drutiny/drutiny": "^3.6.0",
  },
  "autoload": {
      "psr-4": {
          "Demo\\CustomDrutinyProject\\": "src/",
      }
  },
}
```
Note this means the `Demo\CustomDrutinyProject` namespace will autoload from php files located in  the `src` directory.

### Writing your first audit class

Lets create an audit class called `ProjectDataGatherer` located in `src/Audit/ProjectDataGatherer.php` with these contents:

```php
<?php

namespace Demo\CustomDrutinyProject\Audit;

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Sandbox\Sandbox;

class ProjectDataGatherer extends AbstractAnalysis {

    protected function gather(Sandbox $sandbox) {
        $this->set('foo', 'bar');
    }
}
```

The above audit class will gather the data ('bar') and **set** it to a token called 'foo'.

Now we can use this class with drutiny's `audit:run` command:

```
drutiny audit:run 'Demo\CustomDrutinyProject\Audit\ProjectDataGatherer' none:none
```

### Linking your class up to a policy
The above command runs the audit class against a null target ('none:none'). Typically you have to audit policies against targets
but using this command, a policy is fabricated for you. Not much will happen in this run. To see a bit more, have to build a policy
ourselves.

Lets create a policy located in `policy/customDemo.policy.yml`.

```yaml
title: Custom policy for evaluating project data
name: custom:fooIsBar
class: Demo\CustomDrutinyProject\Audit\ProjectDataGatherer
description: Ensure that the foo token contains the value 'bar'.
parameters:
    failIf: foo != 'bar'
success: The token 'foo' contains the value 'bar'
failure: The token 'foo' does not contain the value 'bar'. Found "{{ foo }}" instead.
```

Using `drutiny policy:list` you should not be able to see your policy in the list of available policies.

The `class` field in YAML file determins which audit class to use. 
The tokens set using the `set` method inside the `gather` method of the class are exposed as variables
to the `parameters.failIf` field which is evaluated to true or false. If its true, then the failure 
message is triggered, otherwise a success is triggered.

Note: the `failIf` parameter is provided by the `AbstractAnalysis` audit class `ProjectDataGatherer` 
is extended from.

* [Learn more about Parameters](Parameters.md)

Now with a policy in place, we can test the class through the policy:

```
drutiny policy:audit custom:fooIsBar none:none
```

Now you should see a successful outcome with the message: **The token 'foo' contains the value 'bar'**.

### Testing the fail condition
Now that the policy is passing, lets test what happens when the data outputs are different. 
Lets update our `ProjectDataGatherer` class to set `foo` to a different value:

```php
<?php
    protected function gather(Sandbox $sandbox) {
        $this->set('foo', 'baz');
    }
```

Now when we run the `policy:audit` command again, it should fail and we should see the message:

```
The token 'foo' does not contain the value 'bar'. Found "baz" instead.
```

Here you can see Twig templating in action where the token `foo` was rendered in the `failure`
message using the Twig syntax: `{{ foo }}`.

## Next Steps
* [Learn more about Parameters](Parameters.md)
* [Learn more about Dependencies](Dependencies.md)