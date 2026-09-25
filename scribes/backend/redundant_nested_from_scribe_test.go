package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var redundantNestedFrom = scribeCase{spatie.RedundantNestedFromDetector{}, RedundantNestedFromScribe{}}

func TestRedundantNestedFromUnwrapsTheNestedFromToAPlainArray(t *testing.T) {
	fixed := redundantNestedFrom.fixStable(t, `<?php
namespace App;
use Spatie\LaravelData\Data;
final class Sandbox extends Data { public function __construct(public readonly string $label) {} }
final class Payload extends Data {
    public function __construct(public readonly Sandbox $sandbox) {}
}
final class Builder {
    public function make(): Payload {
        return Payload::from(['sandbox' => Sandbox::from(['label' => 'x'])]);
    }
}`)

	if !strings.Contains(fixed, `'sandbox' => ['label' => 'x']`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `Sandbox::from(`) {
		t.Fatalf("%s", fixed)
	}
}

func TestRedundantNestedFromDoesNotOvershootAnObjectSource(t *testing.T) {
	source := `<?php
namespace App;
use Spatie\LaravelData\Data;
final class Sandbox extends Data { public function __construct(public readonly string $label) {} }
final class Payload extends Data {
    public function __construct(public readonly Sandbox $sandbox) {}
}
final class Builder {
    public function make(object $model): Payload {
        return Payload::from(['sandbox' => Sandbox::from($model)]);
    }
}`

	if redundantNestedFrom.rewrote(t, source) {
		t.Fatal(`an object-source conversion must be left untouched`)
	}
}
