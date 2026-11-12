package engine_test

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/engine"
)

func TestARuleReadingForwardAsksAgainOnlyTheFileThatChanged(t *testing.T) {
	for name, want := range map[string]int{"forward": 1, "across": 2} {
		memory := engine.Remembering(time.Now)
		detector := &asking{finds: true}
		find := memory.FindAcross
		if name == "forward" {
			find = memory.FindForward
		}

		tree, _ := treeHolding(t, "one", "two")
		find(tree, detector.find)
		detector.asked = 0

		tree, _ = treeHolding(t, "one", "two, its body edited")
		find(tree, detector.find)

		if detector.asked != want {
			t.Errorf("%s: after one file's body changed the rule was asked about %d files, want %d", name, detector.asked, want)
		}
	}
}
