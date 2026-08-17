<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Support;

use Clcbws\LaravelIntegrity\Engine\AstParserEngine;
use Illuminate\Support\Facades\Blade;

final class BladeTokenScanner
{
    public function __construct(private readonly AstParserEngine $parser) {}

    /**
     * Compile a Blade template string and parse it into an AST.
     *
     * @return array<\PhpParser\Node\Stmt>|null
     */
    public function compileAndParse(string $content): ?array
    {
        // Suppress compile errors or warnings if there are syntax issues in Blade,
        // which will be handled by BladeCompileCheck
        try {
            $compiled = Blade::compileString($content);
            return $this->parser->parse($compiled);
        } catch (\Throwable) {
            return null;
        }
    }
}
