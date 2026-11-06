package hooks

import (
	"strings"
	"testing"
)

func TestTheLanesSaidAreTheLanesHeld(t *testing.T) {
	root, _ := workerProject(t)
	first := stressWorktreeIn(root, 13)
	second := stressWorktreeIn(root, 14)

	cutInto(t, root, first, "feature/lanes-one")
	cutInto(t, root, second, "feature/lanes-two")

	kept.CutAt(first, "aaaaaaa")
	kept.Over(first)
	kept.CutAt(second, "bbbbbbb")
	kept.Over(second)

	said := lanesHeld("")

	for _, named := range []string{first, second, "aaaaaaa", "bbbbbbb", "2 lane(s) held"} {
		if !strings.Contains(said, named) {
			t.Errorf("what the service says it holds does not name %s:\n%s", named, said)
		}
	}

	if dropped := lanesHeld(first); !strings.Contains(dropped, "Let go of the lane of "+first) {
		t.Errorf("dropping one lane says %q", dropped)
	}

	if after := lanesHeld(""); strings.Contains(after, first) {
		t.Errorf("the lane that was dropped is still held:\n%s", after)
	}

	if missing := lanesHeld("/nowhere/at/all"); !strings.Contains(missing, "nothing to let go") {
		t.Errorf("dropping a lane nobody holds says %q", missing)
	}

	if every := lanesHeld("*"); !strings.Contains(every, "Let go of 1 lane(s)") {
		t.Errorf("dropping every lane says %q", every)
	}

	if after := lanesHeld(""); !strings.Contains(after, "holds no lane") {
		t.Errorf("after dropping every lane the service says:\n%s", after)
	}
}

func TestTheLanesQuestionIsOnlyTheLanesQuestion(t *testing.T) {
	if _, asked := lanesAsked(map[string]any{"event": lanesEvent}); !asked {
		t.Error("the lanes question is not recognised")
	}

	if drop, _ := lanesAsked(map[string]any{"event": lanesEvent, "drop": "/some/checkout"}); drop != "/some/checkout" {
		t.Errorf("the lane to drop is read as %q", drop)
	}

	if _, asked := lanesAsked(map[string]any{"event": "file_edited"}); asked {
		t.Error("an ordinary edit is read as the lanes question, which would answer it with a status and judge nothing")
	}
}
