package triggers

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
	"github.com/jessegall/code-commandments/skill"
)

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

func TestASkillIsMatchedByItsOwnName(t *testing.T) {
	all, err := selected("")
	if err != nil {
		t.Fatal(err)
	}
	if len(all) < 2 {
		t.Skip("fewer than two skills have a trigger set, so narrowing cannot be told from not narrowing")
	}

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
