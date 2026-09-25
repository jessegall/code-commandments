package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var constructorOrchestration = scribeCase{spatie.ConstructorOrchestrationDetector{}, ConstructorOrchestrationScribe{}}

func TestConstructorOrchestrationHoistsSlotFillsIntoGetHooks(t *testing.T) {
	fixed := constructorOrchestration.fixStable(t, `<?php
namespace App;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\Data;
class Canvas extends Data { public function __construct(public string $svg) {} }
class Shell extends Data {
    public readonly Canvas $canvas;
    public readonly Canvas $palette;
    public readonly array $docks;

    public function __construct(
        public readonly TopBar $topBar,
    ) {
        $this->docks = $this->topBar->docks();
    }
}
class C extends Controller { public function a(): Shell { return Shell::from([]); } }`)

	if !strings.Contains(fixed, `public array $docks { get => $this->topBar->docks(); }`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `readonly array $docks`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `$this->docks =`) {
		t.Fatalf("%s", fixed)
	}

	// The hook is a get-only virtual property — it MUST be `#[Computed]` or Spatie treats it as a
	// hydration input and the class won't build. The attribute needs its import to resolve.
	if !strings.Contains(fixed, `#[Computed]
    public array $docks`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `use Spatie\LaravelData\Attributes\Computed;`) {
		t.Fatalf("%s", fixed)
	}
}

func TestConstructorOrchestrationPreservesADataCollectionOfAttribute(t *testing.T) {
	fixed := constructorOrchestration.fixStable(t, `<?php
namespace App;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
class Row extends Data { public function __construct(public string $id) {} }
class Canvas extends Data { public function __construct(public string $svg) {} }
class Grid extends Data {
    public readonly Canvas $canvas;

    /** @var list<Row> */
    #[DataCollectionOf(Row::class)]
    public readonly array $rows;

    public function __construct(
        public readonly Repo $repo,
    ) {
        $this->rows = $this->repo->rows();
    }
}
class C extends Controller { public function a(): Grid { return Grid::from([]); } }`)

	// The attribute survives above the new `#[Computed]`; the slot becomes a hook.
	if !strings.Contains(fixed, `#[DataCollectionOf(Row::class)]
    #[Computed]
    public array $rows`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public array $rows { get => $this->repo->rows(); }`) {
		t.Fatalf("%s", fixed)
	}
}

func TestConstructorOrchestrationDoesNotOvershootALazySlot(t *testing.T) {
	// A Lazy-typed slot is a righteous look-alike — the scribe must leave the file byte-identical.
	source := `<?php
namespace App;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Lazy;
class Canvas extends Data { public function __construct(public string $svg) {} }
class Shell extends Data {
    public readonly Canvas $canvas;
    public readonly Canvas $palette;
    public readonly Lazy|array $rows;

    public function __construct(
        public readonly Repo $repo,
    ) {
        $this->rows = Lazy::closure(fn () => $this->repo->rows());
    }
}
class C extends Controller { public function a(): Shell { return Shell::from([]); } }`

	if constructorOrchestration.rewrote(t, source) {
		t.Fatal("expected false")
	}
}

func TestConstructorOrchestrationDoesNotDuplicateAnExistingComputedImport(t *testing.T) {
	fixed := constructorOrchestration.fixStable(t, `<?php
namespace App;
use Illuminate\Routing\Controller;
use Spatie\LaravelData\Attributes\Computed;
use Spatie\LaravelData\Data;
class Canvas extends Data { public function __construct(public string $svg) {} }
class Shell extends Data {
    public readonly Canvas $canvas;
    public readonly Canvas $palette;
    public readonly array $docks;

    public function __construct(public readonly TopBar $topBar) {
        $this->docks = $this->topBar->docks();
    }
}
class C extends Controller { public function a(): Shell { return Shell::from([]); } }`)

	if strings.Count(fixed, `use Spatie\LaravelData\Attributes\Computed;`) != 1 {
		t.Fatalf("the import is added at most once\n%s", fixed)
	}
	if !strings.Contains(fixed, "#[Computed]") {
		t.Fatalf("%s", fixed)
	}
}
