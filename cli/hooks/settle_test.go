package hooks

import (
	"os"
	"path/filepath"
	"slices"
	"testing"

	pydetectors "github.com/jessegall/code-commandments/detectors/python"
	"github.com/jessegall/code-commandments/engine/python/pythontest"
)

// TestARepentedSinSettlesTheMarkItWasFoundUnder holds a sin's chat mark to its whole life: the edit that commits
// the sin raises it under a key, and the later edit that repents it settles that same key.
func TestARepentedSinSettlesTheMarkItWasFoundUnder(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	codebase := pythontest.FromSource(t, map[string]string{"shop/refund.py": sinful})
	file := pythontest.File(t, codebase, "shop/refund.py").Path
	root, data := filepath.Dir(filepath.Dir(file)), t.TempDir()

	rule := pydetectors.RedundantElseDetector{}
	var marks []SinMark
	for _, match := range rule.Find(codebase) {
		marks = append(marks, SinMark{rule, match, true})
	}

	found := AnnouncedIn(data).Settle(root, &file, marks)
	raised := found.Raises(root)
	if len(found.Found) != 1 || len(raised) != 1 || raised[0].Key != marks[0].Key(root) || len(found.Settles()) != 0 {
		t.Fatalf("the sinful edit raised %+v and settles %v", raised, found.Settles())
	}

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	repented := AnnouncedIn(data).Settle(root, &file, nil)
	if !slices.Equal(repented.Settles(), []string{raised[0].Key}) {
		t.Errorf("the repenting edit settles %v, want %v", repented.Settles(), raised[0].Key)
	}

	if again := AnnouncedIn(data).Settle(root, &file, nil); len(again.Settles()) != 0 {
		t.Errorf("a sin repented once is settled again: %v", again.Settles())
	}
}
