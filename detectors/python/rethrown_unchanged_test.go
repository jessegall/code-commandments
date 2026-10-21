package python_test

import (
	"testing"

	"github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestACatchThatOnlyRaisesAgainIsFoundAndOneThatWorksIsNot holds the rule to the fault it names: a clause that
// catches a failure, adds nothing and raises it again is a clause nobody needed. A clause that does real work
// first, one that raises a failure of its own, and a narrow clause shielding a broader one below it are how the
// construct earns its place and must pass.
func TestACatchThatOnlyRaisesAgainIsFoundAndOneThatWorksIsNot(t *testing.T) {
	for name, test := range map[string]struct {
		source string
		want   int
	}{
		"a bare raise":                  {"def run() -> None:\n    try:\n        work()\n    except ValueError:\n        raise\n", 1},
		"raising the caught name":       {"def run() -> None:\n    try:\n        work()\n    except ValueError as error:\n        raise error\n", 1},
		"a bare raise beside a finally": {"def run() -> None:\n    try:\n        work()\n    except ValueError:\n        raise\n    finally:\n        close()\n", 1},
		"work before the raise":         {"def run() -> None:\n    try:\n        work()\n    except ValueError as error:\n        log(error)\n        raise\n", 0},
		"a rollback before the raise":   {"def run() -> None:\n    try:\n        work()\n    except ValueError:\n        rollback()\n        raise\n", 0},
		"raising a failure of its own":  {"def run() -> None:\n    try:\n        work()\n    except ValueError as error:\n        raise RuntimeError('no') from error\n", 0},
		"raising the caught one from":   {"def run() -> None:\n    try:\n        work()\n    except ValueError as error:\n        raise error from None\n", 0},
		"shielding a broader clause":    {"def run() -> None:\n    try:\n        work()\n    except KeyboardInterrupt:\n        raise\n    except Exception:\n        report()\n", 0},
		"the last of several clauses":   {"def run() -> None:\n    try:\n        work()\n    except KeyError:\n        report()\n    except ValueError:\n        raise\n", 1},
		"handling without raising":      {"def run() -> None:\n    try:\n        work()\n    except ValueError:\n        report()\n", 0},
	} {
		t.Run(name, func(t *testing.T) {
			codebase := pythontest.FromSource(t, map[string]string{"app/thing.py": test.source})
			found := (python.RethrownUnchangedDetector{}).Find(codebase)

			if len(found) != test.want {
				t.Errorf("found %d, want %d: %v", len(found), test.want, found)
			}
		})
	}
}
