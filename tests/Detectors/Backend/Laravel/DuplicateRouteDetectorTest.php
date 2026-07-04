<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\DuplicateRouteDetector;
use PHPUnit\Framework\TestCase;

final class DuplicateRouteDetectorTest extends TestCase
{
    public function test_flags_two_routes_of_the_same_verb_to_one_action(): void
    {
        $hits = $this->find(<<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        class ReportController { public function show() {} }
        Route::get('/report', [ReportController::class, 'show']);
        Route::get('/reports/latest', [ReportController::class, 'show']);
        PHP);

        $this->assertCount(2, $hits, 'both duplicate registrations are flagged');
    }

    public function test_does_not_flag_distinct_actions(): void
    {
        $this->assertCount(0, $this->find(<<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        class C { public function a() {} public function b() {} }
        Route::get('/a', [C::class, 'a']);
        Route::get('/b', [C::class, 'b']);
        PHP));
    }

    public function test_does_not_flag_a_get_post_pair_on_one_action(): void
    {
        // Different verbs to the same action is a form/show+store shape, not a duplicate route.
        $this->assertCount(0, $this->find(<<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        class FormController { public function handle() {} }
        Route::get('/form', [FormController::class, 'handle']);
        Route::post('/form', [FormController::class, 'handle']);
        PHP));
    }

    public function test_does_not_flag_an_invokable_mapped_to_several_urls(): void
    {
        // An invokable single-action controller aliased to several canonical URLs (well-known/discovery
        // endpoints, OIDC aliases) is idiomatic, not a duplicate — the OAuth discovery pattern.
        $this->assertCount(0, $this->find(<<<'PHP'
        <?php
        use Illuminate\Support\Facades\Route;
        class MetadataController { public function __invoke() {} }
        Route::get('/.well-known/oauth-authorization-server', MetadataController::class);
        Route::get('/.well-known/openid-configuration', MetadataController::class);
        Route::get('/.well-known/oauth-authorization-server/{path}', MetadataController::class);
        PHP));
    }

    /**
     * @return list<\JesseGall\CodeCommandments\Ast\NodeMatch>
     */
    private function find(string $php): array
    {
        return (new DuplicateRouteDetector)->find(Codebase::fromString($php, '/proj/routes/web.php'));
    }
}
