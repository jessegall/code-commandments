package hooks

import (
	"strings"
	"testing"
)

func TestANarrowedJudgingSaysWhatItDidNotAskAndWhatToRun(t *testing.T) {
	said := Shortfall{noTreeHeld, 172, 76}.Line("bin/commandments judge")

	for _, want := range []string{"172 rules that answer from one file", "76 that read across files were not asked",
		"no tree of this checkout is held yet", "not a clean answer", "bin/commandments judge --changes"} {
		if !strings.Contains(said, want) {
			t.Errorf("the line does not say %q:\n%s", want, said)
		}
	}
}

func TestAWholeJudgingSaysNothing(t *testing.T) {
	if said := (Shortfall{"", 248, 0}).Line("bin/commandments judge"); said != "" {
		t.Errorf("a judging that asked everything still says %q", said)
	}

	if said := (Shortfall{noTreeHeld, 248, 0}).Line("bin/commandments judge"); said != "" {
		t.Errorf("a judging that left no rule out still says %q", said)
	}
}

func TestEverySurfaceSaysItInTheSameWords(t *testing.T) {
	for what, said := range map[string]string{
		"a narrowed judging": Shortfall{noTreeHeld, 10, 5}.Line(""),
		"an unreadable edit": Unjudged("what this edit changed could not be read, so nothing of it was judged", "order.py: no reader", ""),
		"a bridge that cannot run": Unjudged("a bridge the session keeps up cannot run on this machine, so every rule of that language finds nothing",
			"the Python bridge needs python3", ""),
	} {
		if !strings.Contains(said, "not a clean answer, it is a smaller one") {
			t.Errorf("%s does not say it in the one wording:\n%s", what, said)
		}
		if !strings.HasPrefix(said, "Code Commandments: ") {
			t.Errorf("%s does not name itself:\n%s", what, said)
		}
	}
}

func TestTheTwoNoTreeCasesAreToldApart(t *testing.T) {
	coming := Shortfall{noTreeHeld, 10, 5}.Line("")
	never := Shortfall{noTreeComing, 10, 5}.Line("")

	if coming == never {
		t.Fatal("a tree being read and no tree at all are said in the same words")
	}
	if !strings.Contains(coming, "being read now") {
		t.Errorf("a tree being read does not say so:\n%s", coming)
	}
	if !strings.Contains(never, "will ever run here") {
		t.Errorf("no tree at all does not say so:\n%s", never)
	}
}

func TestOneSentencePerReasonNamesEveryFileUnderIt(t *testing.T) {
	narrowed := map[string]Narrowed{"a.py": noTreeHeld, "b.py": noTreeHeld, "c.py": parseFailed}
	asked := map[string]int{"a.py": 10, "b.py": 10, "c.py": 10}
	notAsked := map[string]int{"a.py": 5, "b.py": 5, "c.py": 5}

	said := Shortfalls(narrowed, asked, notAsked, "")

	if strings.Count(said, "no tree of this checkout is held yet") != 1 {
		t.Errorf("the same reason is said more than once:\n%s", said)
	}
	if !strings.Contains(said, "(a.py, b.py)") {
		t.Errorf("the files under one reason are not named together:\n%s", said)
	}
	if !strings.Contains(said, "what changed could not be parsed") {
		t.Errorf("the second reason is missing:\n%s", said)
	}
	if strings.Contains(said, "Run ``") {
		t.Errorf("an empty command is offered:\n%s", said)
	}
}
