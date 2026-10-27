package triggers

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
)

// Measuring whether a skill's description pulls its own skill in asks a model, so the recorded parity cases ran the
// built binary. What happens BEFORE anything is asked needs no model, and that is what this holds: which skills a
// query matches, and the answer when it matches none.

// TestAQueryThatMatchesNoSkillSaysWhereToWriteOne holds the refusal: a reader who mistyped a skill, or who named
// one that has no trigger set yet, is told where the set belongs rather than left with a clean report about
// nothing measured.
func TestAQueryThatMatchesNoSkillSaysWhereToWriteOne(t *testing.T) {
	var out, errs bytes.Buffer

	code, err := Command{}.Run(cli.InputOf("trigger-eval", "--skill=zz-no-such-skill"), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	if code != 2 {
		t.Errorf("exits %d, want 2: nothing was measured", code)
	}
	if !strings.Contains(errs.String(), "triggers.json") {
		t.Errorf("it says %q, which does not say where a trigger set belongs", errs.String())
	}
	if strings.Contains(out.String(), "Asking") {
		t.Error("it asked the model about a query that matched no skill")
	}
}

// TestASkillIsMatchedByItsOwnName holds the narrowing a reader uses while they work on one skill's description: the
// query reaches that skill's set and not the whole curriculum, which is the difference between one measurement and
// an hour of them.
func TestASkillIsMatchedByItsOwnName(t *testing.T) {
	all, err := selected("")
	if err != nil {
		t.Fatal(err)
	}
	if len(all) < 2 {
		t.Skip("fewer than two skills have a trigger set, so narrowing cannot be told from not narrowing")
	}

	// A skill is named as a reader names one, by its slug, which is what the other commands take too.
	slug := ""
	for _, teaching := range skill.Ordered() {
		if teaching.Definition().ID() == all[0].id {
			slug = teaching.Definition().Slug

			break
		}
	}
	if slug == "" {
		t.Fatalf("no skill answers to %s, which a trigger set was read for", all[0].id)
	}

	one, err := selected(slug)
	if err != nil {
		t.Fatal(err)
	}

	if len(one) != 1 {
		t.Fatalf("naming %s selected %d sets, want the one", slug, len(one))
	}
	if one[0].id != all[0].id {
		t.Errorf("naming %s selected %s", slug, one[0].id)
	}
}

// TestEveryTriggerSetSaysBothWhatItMustAnswerAndWhatItMustNot holds the shape of a set: a set with no near-misses
// measures only that a description fires, never that it stays out of its neighbours', which is the half that catches
// a description written too wide.
func TestEveryTriggerSetSaysBothWhatItMustAnswerAndWhatItMustNot(t *testing.T) {
	sets, err := selected("")
	if err != nil {
		t.Fatal(err)
	}
	if len(sets) == 0 {
		t.Skip("no skill has a trigger set yet")
	}

	for _, each := range sets {
		if len(each.triggers) == 0 {
			t.Errorf("%s names nothing it must answer", each.id)
		}
		if len(each.not) == 0 {
			t.Errorf("%s names nothing it must leave alone, so nothing measures a description written too wide", each.id)
		}
	}
}
