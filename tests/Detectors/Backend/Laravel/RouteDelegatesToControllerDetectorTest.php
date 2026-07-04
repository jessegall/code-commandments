<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\RouteDelegatesToControllerDetector;
use PHPUnit\Framework\TestCase;

final class RouteDelegatesToControllerDetectorTest extends TestCase
{
    public function test_flags_an_action_that_forwards_to_another_controller(): void
    {
        $scopes = $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        use Illuminate\Support\Facades\Route;
        class MoveRequest extends FormRequest {}
        class QuickPadController {
            public function move(MoveRequest $request) { return 'done'; }
        }
        class WebRegisterQuickPadController {
            public function __construct(private readonly QuickPadController $quickPad) {}
            public function move(MoveRequest $request) {
                return $this->quickPad->move($request);
            }
        }
        // QuickPadController::move has its own route — the wrapper is a redundant second door onto it.
        Route::post('/quickpad/move', [QuickPadController::class, 'move']);
        Route::post('/web-register/quickpad/move', [WebRegisterQuickPadController::class, 'move']);
        PHP);

        $this->assertSame(['App\\WebRegisterQuickPadController::move'], $scopes);
    }

    public function test_does_not_flag_delegation_into_a_domain_service(): void
    {
        // Delegating INTO a service (its method is not a route action) is the correct shape.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class ExportRequest extends FormRequest {}
        class WorkflowExporter {
            public function export(ExportRequest $request) {}
        }
        class ExportController {
            public function __construct(private readonly WorkflowExporter $exporter) {}
            public function export(ExportRequest $request) {
                return $this->exporter->export($request);
            }
        }
        PHP));
    }

    public function test_does_not_flag_a_self_call_on_the_same_controller(): void
    {
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class R extends FormRequest {}
        class HomeController {
            public function index(R $request) { return $this->show($request); }
            public function show(R $request) { return 'x'; }
        }
        PHP));
    }

    public function test_does_not_flag_a_non_thin_action(): void
    {
        // An action that does real work before delegating is not a pass-through wrapper.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class R extends FormRequest {}
        class OtherController { public function act(R $request) {} }
        class BusyController {
            public function __construct(private readonly OtherController $other) {}
            public function act(R $request) {
                $prepared = $request->validated();
                return $this->other->act($request);
            }
        }
        PHP));
    }

    /**
     * @return list<string>
     */
    private function find(string $php): array
    {
        $hits = (new RouteDelegatesToControllerDetector)->find(Codebase::fromString($php, '/proj/app/C.php'));

        return array_map(static fn ($match): string => $match->scope(), $hits);
    }
}
