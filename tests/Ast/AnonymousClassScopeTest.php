<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast;

use JesseGall\CodeCommandments\Ast\Codebase;
use PHPUnit\Framework\TestCase;

final class AnonymousClassScopeTest extends TestCase
{
    public function test_two_anonymous_classes_in_different_files_do_not_share_a_scope(): void
    {
        $scopes = [];

        foreach (['a/One.php', 'b/Two.php'] as $path) {
            $code = <<<'PHP'
            <?php
            return new class {
                public function handle(): void { \doesWork(); }
            };
            PHP;

            foreach (Codebase::fromString($code, $path)->whereFunction()->get() as $call) {
                $scopes[] = $call->scope();
            }
        }

        // Migrations are all `new class extends Migration` on the same line of every file, so a line
        // number alone would not part them — the FILE is what makes the identity.
        $this->assertCount(2, $scopes);
        $this->assertNotSame($scopes[0], $scopes[1]);
        $this->assertStringContainsString('a/One.php', $scopes[0]);
        $this->assertStringContainsString('b/Two.php', $scopes[1]);
    }

    public function test_a_named_class_keeps_the_plain_scope(): void
    {
        $code = <<<'PHP'
        <?php
        namespace App;
        class Named { public function handle(): void { \doesWork(); } }
        PHP;

        $calls = Codebase::fromString($code, 'app/Named.php')->whereFunction()->get();

        $this->assertSame('App\Named::handle', $calls[0]->scope());
    }
}
