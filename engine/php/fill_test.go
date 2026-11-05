package php_test

import (
	"testing"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php/shop"
)

// TestPHPFillsWhereverTheCodebaseIsBuilt holds PHP's facts to the engine's own registry rather than to the scan
// that used to call them. A view spliced over a warm tree is built by engine.Filling, not by a scan, so it got no
// PHP fill at all: the resolved type of every expression and the target of every call were missing, and the
// edited file's own came from a one-file read where they resolve against nothing. A rule reading them through the
// generic Match answered differently on an edit than on a full judging.
//
// The shop fixture is built by engine.New from its committed streams, with no scan anywhere, so what it carries
// is exactly what the registry filled.
func TestPHPFillsWhereverTheCodebaseIsBuilt(t *testing.T) {
	codebase := shop.Project(t)

	resolved, targeted := 0, 0

	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			if node.Resolved != nil {
				resolved++
			}

			if node.Target != nil {
				targeted++
			}
		}
	}

	if resolved == 0 {
		t.Error("no PHP expression carries a resolved type, so nothing filled them where this codebase was built")
	}

	if targeted == 0 {
		t.Error("no PHP call carries a target, so nothing filled them where this codebase was built")
	}

	if !engine.FillsFor(contract.PHP) {
		t.Error("PHP fills through no registered filler, so a codebase built anywhere but a scan carries none of its facts")
	}
}
