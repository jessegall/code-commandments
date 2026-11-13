package hooks

import (
	"encoding/json"
	"slices"
	"strings"
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
)

func TestTheSkillOfASinIsOwedNotMerelyNamed(t *testing.T) {
	root, edited, _ := goProject(t)

	marks := goMarks(t, root, edited)
	if len(marks) == 0 {
		t.Fatal("the edited file raises no sin, so there is no skill to owe")
	}

	ids := owed(marks)
	if len(ids) == 0 {
		t.Fatal("a sin was raised and no skill is owed for it")
	}
	for _, id := range ids {
		if !strings.HasPrefix(id, "commandments-") {
			t.Errorf("the skill %q is owed by an id the journal cannot publish", id)
		}
	}
}

func TestOneSkillIsOwedOnceHoweverMManySinsTeachIt(t *testing.T) {
	root, edited, _ := goProject(t)

	marks := goMarks(t, root, edited)
	ids := owed(append(marks, marks...))

	if len(ids) != len(owed(marks)) {
		t.Errorf("the same findings twice owe %d skills, not %d", len(ids), len(owed(marks)))
	}
	for at, id := range ids {
		if slices.Contains(ids[:at], id) {
			t.Errorf("the skill %q is owed twice", id)
		}
	}
}

func TestTheJournalsLineCarriesTheOwedSkills(t *testing.T) {
	told := "a sin is in that file"

	for name, held := range map[string]struct {
		answer JournalAnswer
		want   []string
	}{
		"told with a skill owed":    {JournalAnswer{Whisper: &told, Requires: []string{"commandments-go-flow"}}, []string{"commandments-go-flow"}},
		"refused with a skill owed": {JournalAnswer{Refuse: &told, Requires: []string{"commandments-php-absence", "commandments-plain-code-words"}}, []string{"commandments-php-absence", "commandments-plain-code-words"}},
		"nothing owed":              {JournalAnswer{Whisper: &told}, nil},
	} {
		t.Run(name, func(t *testing.T) {
			var line map[string]any
			if err := json.Unmarshal([]byte(held.answer.Line()), &line); err != nil {
				t.Fatalf("the answer is no JSON: %v", err)
			}

			required, named := line["requires"]
			if held.want == nil {
				if named {
					t.Errorf("an answer owing nothing still writes require_skills as %v", required)
				}

				return
			}

			var ids []string
			for _, id := range required.([]any) {
				ids = append(ids, id.(string))
			}
			if !slices.Equal(ids, held.want) {
				t.Errorf("the line owes %v, want %v", ids, held.want)
			}
		})
	}
}

func TestTheOwedSkillsOfEveryCheckAreMergedOnce(t *testing.T) {
	merged := Merge([]Response{
		{Requires: []string{"commandments-go-flow"}},
		{Requires: []string{"commandments-go-flow", "commandments-php-absence"}},
		{},
	})

	if want := []string{"commandments-go-flow", "commandments-php-absence"}; !slices.Equal(merged.Requires, want) {
		t.Errorf("the moment owes %v, want %v", merged.Requires, want)
	}
}
