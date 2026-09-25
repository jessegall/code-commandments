package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var nonFinalData = scribeCase{spatie.NonFinalDataDetector{}, NonFinalDataScribe{}}

func TestNonFinalDataSealsTheClassFinalAndPromotesPropsReadonly(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    class OrderData extends Data
    {
        public function __construct(
            public string $id,
            public int $total,
        ) {}
    }
}`

	fixed := nonFinalData.fixStable(t, source)

	if !strings.Contains(fixed, `final class OrderData extends Data`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly string $id`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly int $total`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNonFinalDataDoesNotOvershootASealedDataClassOrANonDataClass(t *testing.T) {
	// Already final+readonly → not flagged. A plain non-Data class → not flagged.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    final class TagData extends Data
    {
        public function __construct(
            public readonly string $label,
        ) {}
    }

    class Service
    {
        public function __construct(
            public string $name,
        ) {}
    }
}`

	if found := nonFinalData.findings(t, source); len(found) != 0 {
		t.Fatal("the sin still fires")
	}
	if got := nonFinalData.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestNonFinalDataLeavesAPropertyTheBaseDeclaresMutableAlone(t *testing.T) {
	// PHP forbids redeclaring an inherited non-readonly property as readonly, and the base is
	// exempt from the sin precisely because it IS extended — so only `final` is available here.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    class NormalizedCategoryData extends Data
    {
        public function __construct(
            public string $id,
            public string | null $name = null,
        ) {}
    }

    class NormalizedMedusaCategory extends NormalizedCategoryData
    {
        public function __construct(
            public string $id,
            public string | null $name = null,
            public int | null $position = 0,
        ) {}
    }
}`

	fixed := nonFinalData.fixStable(t, source)

	if !strings.Contains(fixed, `final class NormalizedMedusaCategory`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public string $id,`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public string | null $name = null,`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly int | null $position = 0,`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNonFinalDataAddsOnlyTheMissingReadonlyWhenSomePropsAlreadyHaveIt(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    class MixedData extends Data
    {
        public function __construct(
            public readonly string $id,
            public int $count,
        ) {}
    }
}`

	fixed := nonFinalData.fixStable(t, source)

	// The already-readonly prop keeps a single `readonly`, the other gains one.
	if !strings.Contains(fixed, `public readonly string $id`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly int $count`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `readonly readonly`) {
		t.Fatalf("%s", fixed)
	}
}
