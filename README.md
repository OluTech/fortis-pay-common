# FortisPay Common Class for modules

## Installation

You can install this package using composer:

```console
composer require fortispay/fortis-pay-common
```

## FortisApi.php

This is the actual interface to the Fortis Pay API, and is common across all frameworks.

## FortisFrameworkApi.php

This is an example of a framework specific that needs to be adapted for any particular framework.

This file should be copied to the ``src`` directory at the same level as the ``vendor`` directory, amended as required
for the framework and included in the repository for the framework. If amended in the ``vendor`` directory it will be
over-written by subsequent composer installs.

## Directory structure

The resulting directory structure for a framework specific Fortis Pay plugin should then look like this:

```
fortis-plugin
    src
        FortisFrameworkApi.php
    vendor
        composer
        fortispay
            fortis-pay-common
                examples
                    FortisFrameworkApi.php
                src
                    FortisApi.php
            composer.json
            README.md
    composer.json
```
