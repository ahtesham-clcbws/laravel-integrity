<?php

declare(strict_types=1);

namespace Clcbws\LaravelIntegrity\Engine;

use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor;

final class AstParserEngine
{
    private Parser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForHostVersion();
    }

    /**
     * Parse code string into statements.
     *
     * @return array<\PhpParser\Node\Stmt>|null
     */
    public function parse(string $code): ?array
    {
        return $this->parser->parse($code);
    }

    /**
     * Traverse statements with given visitors.
     *
     * @param array<\PhpParser\Node\Stmt> $stmts
     * @param array<int, NodeVisitor> $visitors
     * @return array<\PhpParser\Node\Stmt>
     */
    public function traverse(array $stmts, array $visitors): array
    {
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
        
        foreach ($visitors as $visitor) {
            if (!$visitor instanceof \PhpParser\NodeVisitor\NameResolver) {
                $traverser->addVisitor($visitor);
            }
        }

        return $traverser->traverse($stmts);
    }
}
