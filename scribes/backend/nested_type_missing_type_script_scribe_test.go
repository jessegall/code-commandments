package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var nestedTypeMissingTypeScript = scribeCase{spatie.NestedTypeMissingTypeScriptDetector{}, NestedTypeMissingTypeScriptScribe{}}

func TestNestedTypeMissingTypeScriptStampsTypescriptOnTheNestedDataClassThePropertyPointsAt(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace Spatie\TypeScriptTransformer\Attributes { #[\Attribute] class TypeScript {} }

namespace App {
    use Spatie\LaravelData\Data;
    use Spatie\TypeScriptTransformer\Attributes\TypeScript;

    final class Leaf extends Data { public function __construct(public readonly string $v = '') {} }

    #[TypeScript]
    final class Page extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly Leaf $leaf,
        ) {}
    }
}`

	fixed := nestedTypeMissingTypeScript.fixStable(t, source)

	if !strings.Contains(fixed, `#[TypeScript]
    final class Leaf extends Data`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNestedTypeMissingTypeScriptLeavesAPageWhoseNestedTypesAreAllTagged(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace Spatie\TypeScriptTransformer\Attributes { #[\Attribute] class TypeScript {} }

namespace App {
    use Spatie\LaravelData\Data;
    use Spatie\TypeScriptTransformer\Attributes\TypeScript;

    #[TypeScript]
    final class Leaf extends Data { public function __construct(public readonly string $v = '') {} }

    #[TypeScript]
    final class Page extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly Leaf $leaf,
        ) {}
    }
}`

	if nestedTypeMissingTypeScript.rewrote(t, source) {
		t.Fatal("expected false")
	}
}
