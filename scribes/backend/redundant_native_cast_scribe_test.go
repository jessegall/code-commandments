package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var redundantNativeCast = scribeCase{spatie.RedundantNativeCastDetector{}, RedundantNativeCastScribe{}}

func TestRedundantNativeCastUnwrapsTheEnumFromToTheRawScalar(t *testing.T) {
	fixed := redundantNativeCast.fixStable(t, `<?php
namespace App;
use Spatie\LaravelData\Data;
enum Status: string { case A = 'a'; }
final class Payload extends Data {
    public function __construct(public readonly Status $status) {}
}
final class Builder {
    public function make(array $raw): Payload {
        return Payload::from(['status' => Status::from($raw['status'])]);
    }
}`)

	if !strings.Contains(fixed, `'status' => $raw['status']`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `Status::from(`) {
		t.Fatalf("%s", fixed)
	}
}

func TestRedundantNativeCastDoesNotOvershootATryFrom(t *testing.T) {
	source := `<?php
namespace App;
use Spatie\LaravelData\Data;
enum Status: string { case A = 'a'; }
final class Payload extends Data {
    public function __construct(public readonly ?Status $status) {}
}
final class Builder {
    public function make(array $raw): Payload {
        return Payload::from(['status' => Status::tryFrom($raw['status'])]);
    }
}`

	if redundantNativeCast.rewrote(t, source) {
		t.Fatal(`a tolerant tryFrom must be left untouched`)
	}
}
