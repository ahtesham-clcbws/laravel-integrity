<?php

declare(strict_types=1);

use Clcbws\LaravelIntegrity\Checks\Hygiene\StrictTypesDeclarationCheck;
use Clcbws\LaravelIntegrity\Checks\Hygiene\RootNamespaceFacadeCheck;
use Clcbws\LaravelIntegrity\Checks\Hygiene\DirectEnvCallCheck;
use Clcbws\LaravelIntegrity\Checks\Hygiene\UnusedImportCheck;

beforeEach(function () {
    $this->appDir = __DIR__ . '/../Fixtures/app';
    if (!is_dir($this->appDir)) {
        mkdir($this->appDir, 0755, true);
    }
});

it('detects and fixes missing strict_types declaration', function () {
    $filePath = $this->appDir . '/TestMissingStrict.php';
    
    // Create file without strict_types
    file_put_contents($filePath, "<?php\n\nnamespace App;\n\nclass TestMissingStrict {}");

    $check = app(StrictTypesDeclarationCheck::class);
    $result = $check->run();

    expect($result->passed)->toBeFalse()
        ->and($result->issues)->toHaveCount(1)
        ->and(realpath($result->issues[0]->file))->toBe(realpath($filePath));

    // Apply fix
    $fixedCount = $check->fix();
    expect($fixedCount)->toBe(1);

    // Verify file content has declaration
    $content = file_get_contents($filePath);
    expect($content)->toContain('declare(strict_types=1);');

    // Run check again
    $resultAfter = $check->run();
    expect($resultAfter->passed)->toBeTrue();
});

it('detects and fixes root namespace facade calls', function () {
    $filePath = $this->appDir . '/TestFacadeCall.php';
    
    // Create file using \DB
    file_put_contents($filePath, "<?php\n\ndeclare(strict_types=1);\n\nnamespace App;\n\nclass TestFacadeCall {\n    public function run() {\n        \DB::table('users')->get();\n    }\n}");

    $check = app(RootNamespaceFacadeCheck::class);
    $result = $check->run();

    expect($result->passed)->toBeFalse()
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->snippet)->toBe('\DB::');

    // Apply fix
    $fixedCount = $check->fix();
    expect($fixedCount)->toBe(1);

    // Verify FQN is fixed and import is added
    $content = file_get_contents($filePath);
    expect($content)->toContain('use Illuminate\Support\Facades\DB;')
        ->and($content)->toContain('DB::table(')
        ->and($content)->not->toContain('\DB::table(');

    // Run check again
    $resultAfter = $check->run();
    expect($resultAfter->passed)->toBeTrue();
});

it('flags direct env() calls outside config folder', function () {
    $filePath = $this->appDir . '/TestEnvCall.php';
    
    // Create file with direct env() call
    file_put_contents($filePath, "<?php\n\ndeclare(strict_types=1);\n\nnamespace App;\n\nclass TestEnvCall {\n    public function get() {\n        return env('DB_HOST');\n    }\n}");

    $check = app(DirectEnvCallCheck::class);
    $result = $check->run();

    expect($result->passed)->toBeFalse()
        ->and($result->issues)->toHaveCount(1)
        ->and(realpath($result->issues[0]->file))->toBe(realpath($filePath));
});

it('detects and fixes unused import statements', function () {
    $filePath = $this->appDir . '/TestUnusedImport.php';
    
    // Create file with unused import
    file_put_contents($filePath, "<?php\n\ndeclare(strict_types=1);\n\nnamespace App;\n\nuse App\Models\Post;\nuse App\Models\User;\n\nclass TestUnusedImport {\n    public function getUser(User \$user) {\n        return \$user;\n    }\n}");

    $check = app(UnusedImportCheck::class);
    $result = $check->run();

    expect($result->passed)->toBeFalse()
        ->and($result->issues)->toHaveCount(1)
        ->and($result->issues[0]->message)->toContain('App\Models\Post');

    // Apply fix
    $fixedCount = $check->fix();
    expect($fixedCount)->toBe(1);

    // Verify unused import was removed and used import was preserved
    $content = file_get_contents($filePath);
    expect($content)->not->toContain('use App\Models\Post;')
        ->and($content)->toContain('use App\Models\User;');

    // Run check again
    $resultAfter = $check->run();
    expect($resultAfter->passed)->toBeTrue();
});
