package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var nullObjectDefault = scribeCase{spatie.AllNullableDataDetector{}, NullObjectDefaultScribe{}}

func TestNullObjectDefaultReshapesABagOfDefaultConstructibleValueObjects(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class RetryPolicy extends Data {
        public function __construct(public readonly int $times = 3) {}
    }

    final class CachePolicy extends Data {
        public function __construct(public readonly int $ttl = 60) {}
    }

    final class Settings extends Data {
        public function __construct(
            public readonly ?RetryPolicy $retry = null,
            public readonly CachePolicy|null $cache = null,
        ) {}
    }
}`

	fixed := nullObjectDefault.fixStable(t, source)

	if !strings.Contains(fixed, `public readonly RetryPolicy $retry = new RetryPolicy(),`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly CachePolicy $cache = new CachePolicy(),`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `?RetryPolicy`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `= null`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNullObjectDefaultInlinesANullObjectFactoryBody(t *testing.T) {
	// Callback can't be `new Callback()` (required $event), but it declares its Null Object as a
	// factory `new self(self::NOOP)`. That call can't BE a default, but its body can — inline it.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class Callback extends Data {
        public const string NOOP = 'noop';
        public function __construct(public readonly string $event) {}
        public static function noOp(): self { return new self(self::NOOP); }
    }

    final class AcceptCallbacks extends Data {
        public function __construct(
            public readonly ?Callback $startDrag = null,
            public readonly Callback|null $received = null,
        ) {}
    }
}`

	fixed := nullObjectDefault.fixStable(t, source)

	if !strings.Contains(fixed, `public readonly Callback $startDrag = new Callback(Callback::NOOP),`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly Callback $received = new Callback(Callback::NOOP),`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNullObjectDefaultInlinesAFactoryBodyForAnyValueTypeNotJustCallbacks(t *testing.T) {
	// Nothing about the reshape is callback-specific: a Duration bag inlines `new self(0)` the
	// same way a callback bag inlines `new self(self::NOOP)`.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class Duration extends Data {
        public function __construct(public readonly int $ms) {}
        public static function none(): self { return new self(0); }
    }

    final class Timeouts extends Data {
        public function __construct(
            public readonly ?Duration $connect = null,
            public readonly ?Duration $read = null,
        ) {}
    }
}`

	fixed := nullObjectDefault.fixStable(t, source)

	if !strings.Contains(fixed, `public readonly Duration $connect = new Duration(0),`) {
		t.Fatalf("%s", fixed)
	}
	if !strings.Contains(fixed, `public readonly Duration $read = new Duration(0),`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNullObjectDefaultDoesNotInlineAnAmbiguousPairOfSelfFactories(t *testing.T) {
	// Two `new self(...)` factories — which is the identity? Can't tell, so leave it alone.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class Money extends Data {
        public function __construct(public readonly int $cents) {}
        public static function zero(): self { return new self(0); }
        public static function max(): self { return new self(999); }
    }

    final class Prices extends Data {
        public function __construct(
            public readonly ?Money $base = null,
            public readonly ?Money $tax = null,
        ) {}
    }
}`

	if found := nullObjectDefault.findings(t, source); len(found) == 0 {
		t.Fatal("the detector must still flag it")
	}
	if got := nullObjectDefault.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestNullObjectDefaultLeavesTheClassAloneWhenAValueTypeNeedsAConstructorArgument(t *testing.T) {
	// Callback has a REQUIRED $event — no `new Callback()`, and the inert value can't be
	// invented — so the whole class stays untouched (the skill's hint guides the human).
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class Callback extends Data {
        public function __construct(public readonly string $event) {}
    }

    final class Hooks extends Data {
        public function __construct(
            public readonly ?Callback $before = null,
            public readonly ?Callback $after = null,
        ) {}
    }
}`

	if found := nullObjectDefault.findings(t, source); len(found) == 0 {
		t.Fatal(`the sin should still fire`)
	}
	if got := nullObjectDefault.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestNullObjectDefaultAllOrNothingASingleUnresolvableFieldBlocksTheClass(t *testing.T) {
	// RetryPolicy resolves; Callback does not → the mixed bag is left entirely alone.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class RetryPolicy extends Data {
        public function __construct(public readonly int $times = 3) {}
    }

    final class Callback extends Data {
        public function __construct(public readonly string $event) {}
    }

    final class Mixed_ extends Data {
        public function __construct(
            public readonly ?RetryPolicy $ok = null,
            public readonly ?Callback $bad = null,
        ) {}
    }
}`

	if got := nullObjectDefault.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestNullObjectDefaultDoesNotReshapeNullableScalars(t *testing.T) {
	// A bag of nullable scalars has no Null Object to name — left alone.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace App {
    use Spatie\LaravelData\Data;

    final class Filters extends Data {
        public function __construct(
            public readonly ?int $min = null,
            public readonly ?string $tag = null,
        ) {}
    }
}`

	if found := nullObjectDefault.findings(t, source); len(found) == 0 {
		t.Fatal("the detector must still flag it")
	}
	if got := nullObjectDefault.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}
