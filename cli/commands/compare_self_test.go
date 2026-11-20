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
