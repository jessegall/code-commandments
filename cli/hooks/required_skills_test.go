package hooks

import (
	"encoding/json"
	"slices"
	"strings"
	"testing"

	_ "github.com/jessegall/code-commandments/registry"
)

// TestTheSkillOfASinIsOwedNotMerelyNamed holds what a journal is told when an edit commits a sin: the ids of the
// skills that teach the fix, so the journal keeps them on the agent's row and holds every tool but a read of one
// until it is read. Naming the skill in the nudge is advice an agent may read past, and a sin fixed from memory
// of a skill that was never read is how a finding gets silenced rather than resolved.
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

// TestOneSkillIsOwedOnceHoweverMManySinsTeachIt holds the shape of the list: a file carrying several findings of
// one skill owes that skill once, since the agent reads it once.
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

// TestTheJournalsLineCarriesTheOwedSkills holds the key the journal reads them by, require_skills, and that an
// answer owing nothing writes no key at all rather than an empty list.
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
			if err := json.Unmarshal([]byte(held.answer.Line4()), &line); err != nil {
				t.Fatalf("the answer is no JSON: %v", err)
			}

			required, named := line["require_skills"]
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

// TestTheOwedSkillsOfEveryCheckAreMergedOnce holds the merge: several checks of one moment each owing a skill
// answer with one list, and a skill two of them name is owed once.
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
