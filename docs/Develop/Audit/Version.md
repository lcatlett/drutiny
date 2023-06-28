# Version

Each audit class can be versioned so that policies can be built against 
a specific version of an audit.

Audit versions use semantic versioning and constraints as documented with
[Composer](https://getcomposer.org/doc/articles/versions.md).

## Add a version to your audit class

To add a version to your audit class, we use the `Drutiny\Attribute\Version` 
PHP attribute:

```php
<?php

use Drutiny\Audit\AbstractAnalysis;
use Drutiny\Attribute\Version;

#[Version('1.0')]
class MyAuditClass extends AbstractAnalysis {

}

```

When versioning your audit class, you should adhere to these principles of
semantic versioning:

1. Major increments (e.g. 1.0 --> 2.0) are non backwards compatible.
2. Minor increments (e.g. 1.0 --> 1.1) introduce new features and are backwards compatible.
3. Patch increments (e.g. 1.0.0 -> 1.0.1) are for bug fixes and are backwards compatible.

## Adding compatibility constraints
You can provide compatibility constraints to show that your audit class 
supports a variety of versions.

### Examples

```php
#[Version('1.4', '^1.2')]
```
This means the audit class is at version 1.4 however, it will support policies
that were written in versions 1.2 or later.

```php
#[Version('2.3', '^1.3 || ^2.0')]
```
Version 2.3 supports policies using 2.0 or later or policies using version 
1.3 or later in the 1.x branch of releases.

For more information about adding version data to policies, [see audit_build_info](../Policy/GettingStarted.md#audit_build_info).