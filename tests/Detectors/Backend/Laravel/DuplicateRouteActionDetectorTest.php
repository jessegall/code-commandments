<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Tests\Detectors\Backend\Laravel;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Detectors\Backend\Laravel\DuplicateRouteActionDetector;
use PHPUnit\Framework\TestCase;

final class DuplicateRouteActionDetectorTest extends TestCase
{
    public function test_flags_two_actions_delegating_to_the_same_operation(): void
    {
        $scopes = $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class ExportRequest extends FormRequest {}
        class WorkflowExporter { public function export(ExportRequest $r) {} }
        class WorkflowExportController {
            public function __construct(private readonly WorkflowExporter $exporter) {}
            public function export(ExportRequest $r) { return $this->exporter->export($r); }
        }
        class WorkflowEditorExportController {
            public function __construct(private readonly WorkflowExporter $writer) {}
            public function export(ExportRequest $r) { return $this->writer->export($r); }
        }
        PHP);

        // Both flagged — same WorkflowExporter::export target, even though the property names differ.
        $this->assertEqualsCanonicalizing(
            ['App\\WorkflowExportController::export', 'App\\WorkflowEditorExportController::export'],
            $scopes,
        );
    }

    public function test_does_not_flag_delegation_to_different_operations(): void
    {
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class R extends FormRequest {}
        class Pdf { public function make(R $r) {} }
        class Csv { public function make(R $r) {} }
        class PdfController {
            public function __construct(private readonly Pdf $pdf) {}
            public function make(R $r) { return $this->pdf->make($r); }
        }
        class CsvController {
            public function __construct(private readonly Csv $csv) {}
            public function make(R $r) { return $this->csv->make($r); }
        }
        PHP));
    }

    public function test_does_not_flag_a_single_controller(): void
    {
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        class R extends FormRequest {}
        class Svc { public function run(R $r) {} }
        class OnlyController {
            public function __construct(private readonly Svc $svc) {}
            public function run(R $r) { return $this->svc->run($r); }
        }
        PHP));
    }

    public function test_does_not_flag_when_the_shared_target_is_a_controller(): void
    {
        // Both wrap the same routed controller — that is RouteDelegatesToController's case, not this one.
        $this->assertSame([], $this->find(<<<'PHP'
        <?php
        namespace App;
        use Illuminate\Foundation\Http\FormRequest;
        use Illuminate\Support\Facades\Route;
        class R extends FormRequest {}
        class RealController { public function act(R $r) {} }
        class WrapperA {
            public function __construct(private readonly RealController $real) {}
            public function act(R $r) { return $this->real->act($r); }
        }
        class WrapperB {
            public function __construct(private readonly RealController $real) {}
            public function act(R $r) { return $this->real->act($r); }
        }
        Route::post('/real', [RealController::class, 'act']);
        PHP));
    }

    /**
     * @return list<string>
     */
    private function find(string $php): array
    {
        $hits = (new DuplicateRouteActionDetector)->find(Codebase::fromString($php, '/proj/app/C.php'));

        return array_map(static fn ($match): string => $match->scope(), $hits);
    }
}
