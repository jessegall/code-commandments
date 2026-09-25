package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var dataToArrayRoundtrip = scribeCase{spatie.DataToArrayRoundtripDetector{}, DataToArrayRoundtripScribe{}}

func TestDataToArrayRoundtripDropsTheRedundantToArray(t *testing.T) {
	fixed := dataToArrayRoundtrip.fixStable(t, `<?php
namespace App;
use Spatie\LaravelData\Data;
final class Inner extends Data { public function __construct(public readonly string $label) {} }
final class Outer extends Data {
    public function __construct(public readonly Inner $inner) {}
}
final class Builder {
    public function make(Inner $inner): Outer {
        return Outer::from(['inner' => $inner->toArray()]);
    }
}`)

	if !strings.Contains(fixed, `'inner' => $inner`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `->toArray()`) {
		t.Fatalf("%s", fixed)
	}
}

func TestDataToArrayRoundtripDoesNotOvershootAPlainArraySlot(t *testing.T) {
	source := `<?php
namespace App;
use Spatie\LaravelData\Data;
final class Inner extends Data { public function __construct(public readonly string $label) {} }
final class Outer extends Data {
    public function __construct(public readonly array $inner) {}
}
final class Builder {
    public function make(Inner $inner): Outer {
        return Outer::from(['inner' => $inner->toArray()]);
    }
}`

	if dataToArrayRoundtrip.rewrote(t, source) {
		t.Fatal(`a plain-array slot genuinely wants the array`)
	}
}
