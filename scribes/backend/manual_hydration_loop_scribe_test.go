package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var manualHydrationLoop = scribeCase{spatie.ManualHydrationLoopDetector{}, ManualHydrationLoopScribe{}}

func TestManualHydrationLoopRewritesAnArrayMapArrowFnIntoCollect(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    final class LineData extends Data
    {
        public function __construct(public readonly string $value) {}
    }
    final class Mapper
    {
        public function map(array $rows): array
        {
            return array_map(fn ($r) => LineData::from($r), $rows);
        }
    }
}`

	fixed := manualHydrationLoop.fixStable(t, source)

	if !strings.Contains(fixed, `return LineData::collect($rows);`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `array_map`) {
		t.Fatalf("%s", fixed)
	}
}

func TestManualHydrationLoopRewritesAnArrayMapFirstClassCallableIntoCollect(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    final class LineData extends Data
    {
        public function __construct(public readonly string $value) {}
    }
    final class Mapper
    {
        public function map(array $rows): array
        {
            return array_map(LineData::from(...), $rows);
        }
    }
}`

	fixed := manualHydrationLoop.fixStable(t, source)

	if !strings.Contains(fixed, `return LineData::collect($rows);`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `array_map`) {
		t.Fatalf("%s", fixed)
	}
}

func TestManualHydrationLoopDoesNotOvershootATransformingCallbackOrAForeach(t *testing.T) {
	// The arrow fn transforms the item before from() ($r['data'] != $r), so collect()
	// is NOT equivalent → skipped. The foreach accumulator needs surrounding context →
	// skipped. Both are still flagged by the detector; just not auto-fixed.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }

namespace App {
    use Spatie\LaravelData\Data;

    final class LineData extends Data
    {
        public function __construct(public readonly string $value) {}
    }
    final class Mapper
    {
        public function transforming(array $rows): array
        {
            return array_map(fn ($r) => LineData::from($r['data']), $rows);
        }

        public function looped(array $rows): array
        {
            $out = [];

            foreach ($rows as $r) {
                $out[] = LineData::from($r);
            }

            return $out;
        }
    }
}`

	// Both sites are flagged…
	if found := manualHydrationLoop.findings(t, source); len(found) == 0 {
		t.Fatal("the detector must still flag it")
	}
	// …but neither is rewritten.
	if got := manualHydrationLoop.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}
