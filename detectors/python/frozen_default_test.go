package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestADefaultWorkedOutOnceIsFoundAndAValueIsNot settles the mutable default's twin: a default that calls
// something answering differently each time runs once, at definition. A default that is a plain value, one
// built from a call whose answer never changes, and a call made inside the body are all how it is done right.
func TestADefaultWorkedOutOnceIsFoundAndAValueIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"the moment of definition":     {"from datetime import datetime\n\n\ndef stamp(at: datetime = datetime.now()) -> str:\n    return at.isoformat()\n", 1},
		"one id for every record":      {"from uuid import uuid4\n\n\ndef record(id: str = str(uuid4())) -> str:\n    return id\n", 0},
		"uuid4 itself as the default":  {"import uuid\n\n\ndef record(id: uuid.UUID = uuid.uuid4()) -> str:\n    return str(id)\n", 1},
		"one reading of the clock":     {"import time\n\n\ndef since(at: float = time.time()) -> float:\n    return time.time() - at\n", 1},
		"a plain value":                {"def label(text: str = 'none', times: int = 1) -> str:\n    return text * times\n", 0},
		"a value that never changes":   {"from decimal import Decimal\n\n\ndef price(amount: Decimal = Decimal('0')) -> Decimal:\n    return amount\n", 0},
		"worked out in the body":       {"from datetime import datetime\n\n\ndef stamp(at: datetime | None = None) -> str:\n    moment = at if at is not None else datetime.now()\n\n    return moment.isoformat()\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/stamping.py": test.source})
			found := (python.FrozenDefaultDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
