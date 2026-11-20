package backend

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

func TestArrayReturnBagFindsNestedRecordsAndArrowResults(t *testing.T) {
	codebase := arrayReturnProject(t, `<?php
abstract class Command {
    abstract public function handle(): int;
}
final class Cleanup extends Command {
    public function handle(): int {
        $inspect = fn (string $path) => ['path' => $path, 'size' => filesize($path)];
        return $inspect('report')['size'];
    }
    public function describe(string $name, int $count): array {
        return ['name' => $name, 'status' => ['count' => $count]];
    }
    public function sends(string $name, int $count): string {
        return json_encode(['name' => $name, 'status' => ['count' => $count]]);
    }
}
`)
	var methods []string
	for _, finding := range (ArrayReturnBagDetector{}).Find(codebase) {
		methods = append(methods, php.EnclosingFunctionName(finding))
	}
	if !slices.Equal(methods, []string{"handle", "describe"}) {
		t.Fatalf("found array returns in %v", methods)
	}
}

func TestArrayReturnBagRespectsTraitContracts(t *testing.T) {
	codebase := arrayReturnProject(t, `<?php
abstract class Tool {
    abstract protected function writeSchema(): array;
}
trait WritesSchema {
    protected function writeSchema(): array {
        return ['date' => 'string', 'limit' => 'integer'];
    }
    protected function settings(): array {
        return ['active' => true, 'scope' => 'private'];
    }
}
trait ToolArguments {
    use WritesSchema;
}
final class ReportsTool extends Tool {
    use ToolArguments;
}
trait NeedsEventProperties {
    abstract protected function eventProperties(): array;
}
final class ReportsFactory {
    use NeedsEventProperties;
    protected function eventProperties(): array {
        return ['name' => 'audit', 'size' => 2];
    }
}
trait ShadowedSchema {
    protected function writeSchema(): array {
        return ['description' => 'report', 'period' => 'weekly'];
    }
}
final class ShadowingTool extends Tool {
    use ShadowedSchema;
    protected function writeSchema(): array {
        return ['amount' => 'number', 'enabled' => 'boolean'];
    }
}
`)
	var scopes []string
	for _, finding := range (ArrayReturnBagDetector{}).Find(codebase) {
		scopes = append(scopes, php.ScopeOf(finding))
	}
	if !slices.Equal(scopes, []string{"WritesSchema::settings", "ShadowedSchema::writeSchema"}) {
		t.Fatalf("found array returns in %v", scopes)
	}
}

func TestArrayReturnBagRespectsFactoryCallbackAndDeclaredShapes(t *testing.T) {
	codebase := arrayReturnProject(t, `<?php
namespace Illuminate\Database\Eloquent\Factories {
    abstract class Factory {
        abstract public function definition(): array;
        public function state(callable $state): array { return $state(); }
    }
}
namespace Reports {
    use Illuminate\Database\Eloquent\Factories\Factory;
    final class ReportFactory extends Factory {
        public function definition(): array {
            return [...$this->fields(), 'status' => 'ready'];
        }
        public function active(): array {
            return $this->state(fn () => ['date' => 'today', 'size' => 3]);
        }
        public function archived(): array {
            return $this->state(fn (): array => $this->fields());
        }
        private function fields(): array {
            return ['date' => 'today', 'size' => 3];
        }
    }
    final class ReportRows {
        /** @return list<array{date: string, size: int}> */
        public array $overview {
            get => array_map(fn (int $size) => ['date' => 'today', 'size' => $size], [1, 2]);
        }
        /** @return array{
         *     date: string,
         *     size: int
         * }
         */
        public function count(): array {
            return ['date' => 'today', 'size' => 3];
        }
        /** @return	array{0: int, date: string, size: int} */
        public function indexedCount(): array {
            return [1, 'date' => 'today', 'size' => 3];
        }
        /** @return list<array{date: string, size: int}> */
        public function counts(): array {
            return array_map(fn (int $size) => ['date' => 'today', 'size' => $size], [1, 2]);
        }
        public function state(callable $value): array { return $value(); }
        /** @param callable(): array $fields */
        public function event(callable $fields): array { return $fields(); }
        public function eventFields(): array {
            return $this->event(fn () => ['date' => 'today', 'size' => 3]);
        }
        /** @param callable(): mixed $fields */
        public function choice(callable $fields): mixed { return $fields(); }
        public function ownChoice(): mixed {
            return $this->choice(fn () => ['date' => 'today', 'size' => 3]);
        }
        public function loose(): array {
            return $this->state(fn () => ['date' => 'today', 'size' => 3]);
        }
    }
}
`)
	var scopes []string
	for _, finding := range (ArrayReturnBagDetector{}).Find(codebase) {
		scopes = append(scopes, php.ScopeOf(finding))
	}
	if !slices.Equal(scopes, []string{`Reports\ReportRows::ownChoice`, `Reports\ReportRows::loose`}) {
		t.Fatalf("found array returns in %v", scopes)
	}
}

func TestArrayReturnBagLeavesDatabaseInputRowsAtTheirBoundary(t *testing.T) {
	codebase := arrayReturnProject(t, `<?php
namespace Illuminate\Database\Query {
    class Builder {
        public function insert(array $rows): bool { return true; }
    }
}
namespace Reports {
    use Illuminate\Database\Query\Builder;
    final class Reports {
        public function store(Builder $table): bool {
            return $table->insert(array_map(fn (int $size) => ['date' => 'today', 'size' => $size], [1, 2]));
        }
        public function process(Builder $table): bool {
            $format = fn (int $size) => ['date' => 'today', 'size' => $size];
            return $table->insert($this->replace($format(2)));
        }
        public function storeBatches(Builder $table): void {
            foreach (array_chunk($this->pending(), 20) as $batch) {
                $table->insert($batch);
            }
        }
        private function pending(): array {
            return array_map(fn (int $size) => ['date' => 'today', 'size' => $size], [1, 2]);
        }
        private function replace(mixed $input): array { return []; }
    }
}
`)
	var methods []string
	for _, finding := range (ArrayReturnBagDetector{}).Find(codebase) {
		methods = append(methods, php.EnclosingFunctionName(finding))
	}
	if !slices.Equal(methods, []string{"process"}) {
		t.Fatalf("found array returns in %v", methods)
	}
}

func TestArrayReturnBagRespectsFractalTransformerContract(t *testing.T) {
	codebase := arrayReturnProject(t, `<?php
namespace Reports;
use League\Fractal\TransformerAbstract;
abstract class ReportTransformer extends TransformerAbstract {}
final class InvoiceTransformer extends ReportTransformer {
    public function transform(int $id): array {
        return ['id' => $id, 'attributes' => ['paid' => false]];
    }
    public function details(int $id): array {
        return ['id' => $id, 'paid' => false];
    }
}
`)
	var methods []string
	for _, finding := range (ArrayReturnBagDetector{}).Find(codebase) {
		methods = append(methods, php.EnclosingFunctionName(finding))
	}
	if !slices.Equal(methods, []string{"details"}) {
		t.Fatalf("found array returns in %v", methods)
	}
}

func TestArrayReturnBagKeepsTemplateContextAsArray(t *testing.T) {
	codebase := arrayReturnProject(t, `<?php
namespace Reports;
use Barryvdh\DomPDF\Facade\Pdf;
final class ReportLabel {
    public function context(string $title, int $page): array {
        return ['title' => $title, 'page' => $page];
    }
    public function detail(string $title, int $page): array {
        return ['title' => $title, 'page' => $page];
    }
    public function print(): mixed {
        return Pdf::loadView('label', $this->context('Report', 1));
    }
    public function preview(): mixed {
        $label = new ReportLabel();
        return view('label', [...$label->context('Report', 1), 'preview' => true]);
    }
    public function title(): string {
        return $this->detail('Report', 1)['title'];
    }
}
`)
	var methods []string
	for _, finding := range (ArrayReturnBagDetector{}).Find(codebase) {
		methods = append(methods, php.EnclosingFunctionName(finding))
	}
	if !slices.Equal(methods, []string{"detail"}) {
		t.Fatalf("found array returns in %v", methods)
	}
}

func arrayReturnProject(t *testing.T, source string) *engine.Codebase {
	t.Helper()
	path := filepath.Join(t.TempDir(), "ArrayReturns.php")
	if err := os.WriteFile(path, []byte(source), 0o600); err != nil {
		t.Fatal(err)
	}
	stream, err := php.Here().Stream(path)
	if err != nil {
		t.Fatal(err)
	}
	return engine.Load(stream)
}
