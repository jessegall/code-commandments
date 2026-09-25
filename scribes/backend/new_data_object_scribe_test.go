package backend

import (
	"strings"
	"testing"

	spatie "github.com/jessegall/code-commandments/detectors/backend/spatie"
)

var newDataObject = scribeCase{spatie.NewDataObjectDetector{}, NewDataObjectScribe{}}

func TestNewDataObjectRewritesNamedArgumentsIntoAFromCall(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData {
    class Data {}
}

namespace Spatie\LaravelData\Attributes {
    #[\Attribute] class MapInputName { public function __construct($m = null) {} }
}

namespace App {
    use Spatie\LaravelData\Data;

    final class MoneyData extends Data
    {
        public function __construct(public readonly int $cents) {}
    }

    final class OrderData extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly MoneyData $total,
        ) {}
    }
    final class Maker
    {
        public function make(array $total): OrderData
        {
            return new OrderData(id: 'abc', total: $total);
        }
    }
}`

	fixed := newDataObject.fixStable(t, source)

	if !strings.Contains(fixed, `return OrderData::from(['id' => 'abc', 'total' => $total]);`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `new OrderData`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNewDataObjectResolvesPositionalArgumentsToPropertyNames(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData {
    class Data {}
}

namespace Spatie\LaravelData\Attributes {
    #[\Attribute] class MapInputName { public function __construct($m = null) {} }
}

namespace App {
    use Spatie\LaravelData\Data;

    final class MoneyData extends Data
    {
        public function __construct(public readonly int $cents) {}
    }

    final class OrderData extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly MoneyData $total,
        ) {}
    }
    final class Maker
    {
        public function make(array $total): OrderData
        {
            return new OrderData('abc', $total);
        }
    }
}`

	fixed := newDataObject.fixStable(t, source)

	// The positional args are mapped to property names via the constructor.
	if !strings.Contains(fixed, `return OrderData::from(['id' => 'abc', 'total' => $total]);`) {
		t.Fatalf("%s", fixed)
	}
	if strings.Contains(fixed, `new OrderData`) {
		t.Fatalf("%s", fixed)
	}
}

func TestNewDataObjectSkipsAClassThatRemapsInputNames(t *testing.T) {
	// OrderData uses #[MapInputName] on a prop → `::from()` keys by the mapped name, so a
	// property-name-keyed rewrite would mismap. The detector still flags it (it's rich),
	// but the scribe must leave it untouched.
	source := `<?php

namespace Spatie\LaravelData { class Data {} }
namespace Spatie\LaravelData\Attributes {
    #[\Attribute] class MapInputName { public function __construct($m = null) {} }
}

namespace App {
    use Spatie\LaravelData\Data;
    use Spatie\LaravelData\Attributes\MapInputName;

    final class MoneyData extends Data
    {
        public function __construct(public readonly int $cents) {}
    }

    final class MappedOrderData extends Data
    {
        public function __construct(
            #[MapInputName('order_id')]
            public readonly string $id,
            public readonly MoneyData $total,
        ) {}
    }

    final class Maker
    {
        public function make(array $total): MappedOrderData
        {
            return new MappedOrderData(id: 'abc', total: $total);
        }
    }
}`

	// It IS flagged (rich), but NOT rewritten.
	if found := newDataObject.findings(t, source); len(found) == 0 {
		t.Fatal("the detector must still flag it")
	}
	if got := newDataObject.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestNewDataObjectDoesNotOvershootAPlainDataClass(t *testing.T) {
	source := `<?php

namespace Spatie\LaravelData {
    class Data {}
}

namespace Spatie\LaravelData\Attributes {
    #[\Attribute] class MapInputName { public function __construct($m = null) {} }
}

namespace App {
    use Spatie\LaravelData\Data;

    final class MoneyData extends Data
    {
        public function __construct(public readonly int $cents) {}
    }

    final class OrderData extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly MoneyData $total,
        ) {}
    }
    final class Maker
    {
        public function plain(): MoneyData
        {
            return new MoneyData(cents: 100);
        }
    }
}`

	// MoneyData is plain (scalar prop only) → never flagged, never rewritten.
	if found := newDataObject.findings(t, source); len(found) != 0 {
		t.Fatal("the sin still fires")
	}
	if got := newDataObject.fix(t, source); got != source {
		t.Fatalf("%s", got)
	}
}

func TestNewDataObjectKeepsAMultiLineCallMultiLine(t *testing.T) {
	// Issue #363: a 15-key `new` collapsed into one ~700-character line, losing every named argument
	// to its own line. The rewrite is right; the LAYOUT must survive it — one entry per line at the
	// original arguments' indent, closing at the original `)`'s.
	source := `<?php

namespace Spatie\LaravelData {
    class Data {}
}

namespace Spatie\LaravelData\Attributes {
    #[\Attribute] class MapInputName { public function __construct($m = null) {} }
}

namespace App {
    use Spatie\LaravelData\Data;

    final class MoneyData extends Data
    {
        public function __construct(public readonly int $cents) {}
    }

    final class OrderData extends Data
    {
        public function __construct(
            public readonly string $id,
            public readonly MoneyData $total,
        ) {}
    }
    final class Maker
    {
        public function make(array $total): OrderData
        {
            return new OrderData(
                id: 'abc',
                total: $total,
            );
        }
    }
}`

	fixed := newDataObject.fixStable(t, source)

	if !strings.Contains(fixed, `            return OrderData::from([
                'id' => 'abc',
                'total' => $total,
            ]);`) {
		t.Fatalf("%s", fixed)
	}
}
