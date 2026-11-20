package commands

import (
	"os"
	"strings"
	"testing"
)

func TestRepentCompareSelfUsesTheConfiguredMethodAndKeepsNullAndNarrowing(t *testing.T) {
	t.Chdir(t.TempDir())
	if err := os.Mkdir(".commandments", 0o755); err != nil {
		t.Fatal(err)
	}
	for file, content := range map[string]string{
		".commandments/config.json": `{"paths":["."],"configure":{"php/CompareSelfDetector":[{"trait":["Shop\\Orders\\Comparisons"]},{"equalsMethod":["sameAs"]},{"notEqualsMethod":["differsFrom"]},{"equalsAnyMethod":["isOneOf"]}]}}`,
		"Origin.php": `<?php
namespace Shop\Orders;
/**
 * @method bool isOneOf(self ...$cases)
 */
trait Comparisons {
    public function sameAs(self $other): bool { return $this === $other; }
    public function differsFrom(self $other): bool { return $this !== $other; }
    public function __call(string $name, array $arguments): bool { return in_array($this, $arguments, true); }
}
enum Origin {
    use Comparisons;
    case Shipped;
    case Stored;
}
enum Unrelated {
    case Shipped;
    public function sameAs(self $other): bool { return $this === $other; }
}
final class Item { public function __construct(public readonly Origin $origin) {} }
function shipped(Item $item): bool { return $item->origin === Origin::Shipped; }
function differs(?Origin $origin): bool { return Origin::Stored !== $origin; }
function optional(?Origin $origin): bool { return $origin === Origin::Shipped; }
function oneOfThem(Origin $origin): bool { return $origin === Origin::Shipped || $origin === Origin::Stored; }
function onlyStored(Origin $origin): int {
    if ($origin === Origin::Shipped) { return 1; }
    return match ($origin) { Origin::Stored => 0 };
}
function unrelated(Unrelated $origin): bool { return $origin === Unrelated::Shipped; }
function guarded(Origin $origin): bool { return $origin === Origin::Shipped && canShip($origin); }
function commented(Origin $origin): bool { return $origin /* compare with the public case */ === Origin::Shipped; }
`,
	} {
		if err := os.WriteFile(file, []byte(content), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	code, out, warned := ran("repent", ".", "--sin=compare-self")
	if code != 0 {
		t.Fatalf("repent exited %d: %s %s", code, out, warned)
	}
	source, err := os.ReadFile("Origin.php")
	if err != nil {
		t.Fatal(err)
	}
	for _, want := range []string{
		"return $item->origin->sameAs(Origin::Shipped);",
		"return ($origin?->differsFrom(Origin::Stored) !== false);",
		"return ($origin?->sameAs(Origin::Shipped) === true);",
		"return $origin->isOneOf(Origin::Shipped, Origin::Stored);",
		"if ($origin === Origin::Shipped) { return 1; }",
		"return $origin === Unrelated::Shipped;",
		"return $origin === Origin::Shipped && canShip($origin);",
		"return $origin /* compare with the public case */ === Origin::Shipped;",
	} {
		if !strings.Contains(string(source), want) {
			t.Errorf("missing %q after rewriting\n%s", want, source)
		}
	}
}

func TestRepentCompareSelfReadsEloquentEnumCasts(t *testing.T) {
	t.Chdir(t.TempDir())
	source := `<?php
namespace Illuminate\Database\Eloquent {
    abstract class Model {}
}
namespace Illuminate\Database\Eloquent\Casts {
    class Attribute {}
}
namespace Shop\Orders {
    use Illuminate\Database\Eloquent\Casts\Attribute;
    use Illuminate\Database\Eloquent\Model;

    enum Origin: string {
        case Shipped = 'shipped';
        case Stored = 'stored';
        public function equals(self $other): bool { return $this === $other; }
    }

    final class Record extends Model {
        protected function casts(): array { return ['origin' => Origin::class]; }
        public function shipped(): bool { return $this->origin === Origin::Shipped; }
        public function differs(): bool { return $this->origin !== Origin::Stored; }
    }

    final class LegacyRecord extends Model {
        protected $casts = ['origin' => Origin::class];
        public function shipped(): bool { return $this->origin === Origin::Shipped; }
    }

    final class PlainClass {
        protected function casts(): array { return ['origin' => Origin::class]; }
        public function unchanged(): bool { return $this->origin === Origin::Shipped; }
    }

    final class AccessorRecord extends Model {
        protected function casts(): array { return ['origin' => Origin::class]; }
        protected function origin(): Attribute { return new Attribute(); }
        public function unchanged(): bool { return $this->origin === Origin::Shipped; }
    }
}
`
	if err := os.WriteFile("Origin.php", []byte(source), 0o644); err != nil {
		t.Fatal(err)
	}
	code, out, warned := ran("repent", ".", "--sin=compare-self")
	if code != 0 {
		t.Fatalf("repent exited %d: %s %s", code, out, warned)
	}
	rewritten, err := os.ReadFile("Origin.php")
	if err != nil {
		t.Fatal(err)
	}
	for want, count := range map[string]int{
		"return ($this->origin?->equals(Origin::Shipped) === true);": 2,
		"return ($this->origin?->equals(Origin::Stored) !== true);":  1,
		"return $this->origin === Origin::Shipped;":                  2,
	} {
		if actual := strings.Count(string(rewritten), want); actual != count {
			t.Errorf("want %d occurrences of %q, got %d\n%s", count, want, actual, rewritten)
		}
	}
}
