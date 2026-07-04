<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Ast\Support;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Support\RouteActions;
use PHPUnit\Framework\TestCase;

final class RouteActionsTest extends TestCase
{
    public function test_reads_actions_from_route_registrations(): void
    {
        $actions = RouteActions::forCodebase(Codebase::fromString(<<<'PHP'
        <?php
        namespace App\Http\Controllers;
        use Illuminate\Support\Facades\Route;
        class HomeController { public function show() {} }
        class ReportController { public function __invoke() {} }
        $router->get('/report', ReportController::class);
        Route::post('/home/{id}', [HomeController::class, 'show'])->name('home');
        PHP, '/proj/routes/web.php'));

        $this->assertTrue($actions->isAction('App\\Http\\Controllers\\HomeController', 'show'), 'array action');
        $this->assertTrue($actions->isAction('App\\Http\\Controllers\\ReportController', '__invoke'), 'invokable');
        $this->assertFalse($actions->isAction('App\\Http\\Controllers\\HomeController', 'missing'));
    }

    public function test_structural_signal_a_request_handler_is_an_action(): void
    {
        // No route file in scope — a public method taking a request is still recognised as an action.
        $actions = RouteActions::forCodebase(Codebase::fromString(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Http\Request;
        class ExportController {
            public function export(Request $request) { return $request->all(); }
            private function helper(Request $request) {}
        }
        PHP, '/proj/app/ExportController.php'));

        $this->assertTrue($actions->isAction('App\\ExportController', 'export'));
        $this->assertFalse($actions->isAction('App\\ExportController', 'helper'), 'private is not an action');
    }

    public function test_response_reachable_signal_an_inertia_render_is_an_action(): void
    {
        $actions = RouteActions::forCodebase(Codebase::fromString(<<<'PHP'
        <?php
        namespace App;
        use Inertia\Inertia;
        class DashboardController {
            public function index() { return Inertia::render('Dashboard'); }
        }
        PHP, '/proj/app/DashboardController.php'));

        $this->assertTrue($actions->isAction('App\\DashboardController', 'index'));
    }

    public function test_a_form_request_subclass_param_counts_as_a_request(): void
    {
        $actions = RouteActions::forCodebase(Codebase::fromString(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class MoveRequest extends FormRequest {}
        class QuickPadController {
            public function move(MoveRequest $request) {}
        }
        PHP, '/proj/app/QuickPadController.php'));

        $this->assertTrue($actions->isAction('App\\QuickPadController', 'move'));
    }
}
