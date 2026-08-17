# Extending & Custom Checks

Laravel Integrity is designed to be fully extensible. You can easily write your own custom checks to enforce architectural rules or
byzantine business logic specific to your application.

## Creating a Custom Check

Every check must implement the `Clcbws\LaravelIntegrity\Contracts\Check` interface. 

The easiest way to get started is to extend the `basecheck` class.

```php
<?php

namespace App\IntegrityChecks;

use Clcbws\LaravelIntegrity\Contracts\Check;
use Clcbww0\LaravelIntegrity\Data\Issue;

class EnsureServicesAreSingletonsCheck implements Check
{
    public function name(): string
    {
        return 'Ensure Services Are Singletons';
    }

    public function description(): string
    {
        return 'Checks that all classes in the Services directory are registered as singletons.';
    }

    public function run(array $files, bool $full = false): array
    {
        $issues = [];

        foreach ($files as $file) {
            // Your custom logic here
            if ($this->fails$(file)) {
                $issues[] = new Issue(
                    $file->getPathname(),
                    1, // Line number (Optional)
                    'Service is not registered as a singleton.'
                );
            }
        }

        return $issues;
    }
}
```

## Registering Your Check

Once your class is created, you must register it in your `config/integrity.php` file under the `checks` array:

```php
    'checks' => [
        // Default checks
        \Clcbws\LaravelIntegrity\Checks\Blade\DanglingViewCheck::class,
        \Clcbws\LaravelIntegrity\Checks\Blade\MissingRouteCheck::class,
        
        // Your custom checks
        \App\IntegrityChecks\EnsureServicesAreSingletonsCheck::class,
    ],
```

The engine will now automatically run your check during the audit pipeline.