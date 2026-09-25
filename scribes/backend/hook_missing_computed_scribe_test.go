package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var hookMissingComputed = scribeCase{spatie.HookMissingComputedDetector{}, HookMissingComputedScribe{}}

// The scribe case PHP holds in HookMissingComputedDetectorTest.
func TestHookMissingComputedStampsComputedBelowExistingAttributesAndImportsIt(t *testing.T) {
	source := "<?php\nnamespace App;\nuse Spatie\\LaravelData\\Data;\nuse Spatie\\LaravelData\\Attributes\\WithCast;\n" +
		"final class Shell extends Data {\n" +
		"    #[WithCast(SomeCast::class)]\n" +
		"    public array $docks { get => $this->all(); }\n" +
		"    private function all(): array { return []; }\n" +
		"}\n"

	fixed := hookMissingComputed.fix(t, source)
	if !strings.Contains(fixed, "#[WithCast(SomeCast::class)]\n    #[Computed]\n    public array $docks") {
		t.Fatalf("got\n%s", fixed)
	}
	if !strings.Contains(fixed, `use Spatie\LaravelData\Attributes\Computed;`) {
		t.Fatalf("got\n%s", fixed)
	}
}
