package reported

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/catalog"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/rule"
)

func refundIn(t *testing.T) (root, file string) {
	t.Helper()
	root = t.TempDir()
	file = filepath.Join(root, "src", "refund.py")
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte("def refund(order):\n    return order.total or 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	return root, file
}

func namesOfTheRule(t *testing.T, name string) []string {
	t.Helper()
	found, named := rule.Named(name)
	if !named {
		t.Fatalf("no rule answers to %s", name)
	}

	return catalog.Names(found)
}

func TestAReportFiledUnderARelease7NameStillHoldsTheSite(t *testing.T) {
	root, file := refundIn(t)
	release7, _ := identityAs("InventedDefaultDetector", root, file, 2)
	if err := write(root, Record{Reports: map[string]Row{release7: {Detector: "InventedDefaultDetector", File: "src/refund.py", Line: 2, Issue: 814}}}); err != nil {
		t.Fatal(err)
	}

	held, _ := Of(root)
	if found, reported := held.Reported(namesOfTheRule(t, "manufactured-fake-fill"), root, file, 2); !reported || found.Issue != 814 {
		t.Fatal("a report a 7.20.30 build filed is not found at the same site by the rule that took its name over")
	}
}

func TestAReportFiledUnderAnyAliasIsKeyedOnTheCanonicalName(t *testing.T) {
	root, file := refundIn(t)
	if _, err := Add(root, Row{Detector: "python/InventedDefaultDetector", File: file, Line: 2, Issue: 815}); err != nil {
		t.Fatal(err)
	}

	held, _ := Of(root)
	if _, reported := held.Reported([]string{"manufactured-fake-fill"}, root, file, 2); !reported {
		t.Fatal("a report filed under an engine-qualified alias is not found under the rule's canonical name")
	}
}
