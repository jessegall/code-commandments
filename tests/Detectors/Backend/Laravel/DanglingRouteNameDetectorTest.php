<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\DanglingRouteNameDetector;
use PHPUnit\Framework\TestCase;

final class DanglingRouteNameDetectorTest extends TestCase
{
    /** @return list<string> */
    private function scopes(string $code): array
    {
        return array_map(
            static fn ($m): string => $m->scope(),
            (new DanglingRouteNameDetector)->find(Codebase::fromString($code)),
        );
    }

    public function test_flags_a_lookup_whose_name_no_registration_mints(): void
    {
        $code = <<<'PHP'
        <?php
        Route::get('/login', [A::class, 'show'])->name('login');
        Route::name('admin.')->group(function () {
            Route::get('/users', [U::class, 'index'])->name('users');
        });
        Route::group(['as' => 'api.'], function () {
            Route::get('/ping', [P::class, 'ping'])->name('ping');
        });
        Route::resource('photos', PhotoController::class);

        class Uses {
            public function exact() { return route('login'); }
            public function chainedPrefix() { return route('admin.users'); }
            public function arrayAsPrefix() { return to_route('api.ping'); }
            public function resourceFamily() { return route('photos.index'); }
            public function viaRedirect() { return redirect()->route('login'); }
            public function typo() { return route('admin.userz'); }
            public function renamed() { return to_route('logn'); }
            public function builtAtRuntime($n) { return route($n); }
        }
        PHP;

        $this->assertSame(['Uses::typo', 'Uses::renamed'], $this->scopes($code));
    }

    public function test_ignores_a_requests_route_parameter_accessor(): void
    {
        // `Request::route($param)` fetches a route PARAMETER and shares nothing with the name lookup but
        // its spelling. Treating it as one flagged every FormRequest::rules() in a real app.
        $code = <<<'PHP'
        <?php
        Route::get('/roles/{role}', [R::class, 'update'])->name('roles.update');

        class RoleUpdateRequest {
            public function rules(): array {
                $role = $this->route('role');

                return ['name' => 'required'];
            }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }

    public function test_names_a_group_by_its_accumulated_prefix(): void
    {
        // Laravel hands a group's accumulated `as` to a route inside that declares no name of its own,
        // so `shops.show.commands` resolves to the unnamed index route of the `.commands` group.
        $code = <<<'PHP'
        <?php
        use Illuminate\Routing\Router;

        Route::group(['as' => 'shops.show'], function (Router $router) {
            $router->group(['as' => '.commands'], function (Router $router) {
                $router->get('/', [ShopCommandsController::class, 'index']);
                $router->post('/{c}/retry', [ShopCommandsController::class, 'retry'])->name('.retry');
            });
        });

        class Uses {
            public function group() { return route('shops.show.commands'); }
            public function named() { return route('shops.show.commands.retry'); }
            public function typo() { return route('shops.show.commandz'); }
        }
        PHP;

        $this->assertSame(['Uses::typo'], $this->scopes($code));
    }

    public function test_a_runtime_group_prefix_is_seen_past_but_a_runtime_leaf_name_silences_everything(): void
    {
        // A package names its group from config, so only the TAIL of a reference is knowable. Matching
        // the tail keeps the check alive; the renamed `.v2` still finds nothing and is caught.
        $prefixed = <<<'PHP'
        <?php
        Route::name($config->routeNamePrefix() . '.')->group(__DIR__ . '/routes.php');
        Route::get('/docs', [D::class, 'show'])->name('docs');
        Route::get('/hooks', [H::class, 'intake'])->name('hooks.intake');

        class Uses {
            public function resolves() { return route('workflows.docs'); }
            public function renamed() { return route('workflows.hooks.intake.v2'); }
        }
        PHP;

        $this->assertSame(['Uses::renamed'], $this->scopes($prefixed));

        // But a LEAF name built at runtime is unknowable in any form — the vocabulary is no longer
        // closed, so the detector says nothing at all rather than guess.
        $leaf = <<<'PHP'
        <?php
        Route::get('/docs', [D::class, 'show'])->name('docs');
        Route::get('/x', [X::class, 'show'])->name($someRuntimeName);

        class Uses {
            public function anything() { return route('who.knows'); }
        }
        PHP;

        $this->assertSame([], $this->scopes($leaf));
    }

    public function test_an_interpolated_name_registers_its_static_head_as_a_family(): void
    {
        // `->name("mcp.oauth.resource.{$handle}")` still states its leading segments, so only that
        // family goes unchecked — the rest of the vocabulary stays verifiable.
        $code = <<<'PHP'
        <?php
        Route::get('/a', [A::class, 'a'])->name("mcp.oauth.resource.{$handle}");
        Route::get('/b', [B::class, 'b'])->name('dashboard');

        class Uses {
            public function inFamily() { return route('mcp.oauth.resource.acme'); }
            public function outside() { return route('dashboardd'); }
        }
        PHP;

        $this->assertSame(['Uses::outside'], $this->scopes($code));
    }

    public function test_says_nothing_when_no_route_registration_is_in_scope(): void
    {
        // Judging `app/` alone leaves the route files unscanned. With no vocabulary to check against,
        // every lookup would look dangling — so the detector stays silent instead.
        $code = <<<'PHP'
        <?php
        class Uses {
            public function whatever() { return route('anything.at.all'); }
        }
        PHP;

        $this->assertSame([], $this->scopes($code));
    }
}
