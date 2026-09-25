package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var preferOptionalCreate = scribeCase{spatie.PreferOptionalCreateDetector{}, PreferOptionalCreateScribe{}}

func TestPreferOptionalCreateRewritesRuntimeNewOptionalToTheFactory(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Optional { public static function create(): Optional { return new self(); } } }

namespace App {
    use Spatie\LaravelData\Optional;

    class Maker
    {
        public function make(bool $absent): mixed
        {
            if ($absent) {
                return new Optional();
            }

            return 'x';
        }
    }
}`

	fixed := preferOptionalCreate.fixStable(t, source)

	if !strings.Contains(fixed, `return Optional::create();`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `new Optional()`) {
		t.Fatalf("%s", fixed)
	}
}

func TestPreferOptionalCreateLeavesAParameterDefaultAlone(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Optional {} class Data {} }

namespace App {
    use Spatie\LaravelData\Data;
    use Spatie\LaravelData\Optional;

    final class Page extends Data
    {
        public function __construct(public readonly Optional $at = new Optional()) {}
    }
}`

	if preferOptionalCreate.rewrote(t, source) {
		t.Fatal("expected false")
	}
}
