package custom

import (
	"strings"
	"testing"
)

const poetic = `{"engine": "backend", "sin": {"name": "poetic-name", "description": "a name from a poem", "skill": "no-raw-sql"},
 "find": {"select": "function", "where": [{"nameIn": ["$poetry", "plain"]}, {"nameMatches": "^(${poetry})_"}]}}`

func TestARuleNamesAListTheProjectDeclaresOnce(t *testing.T) {
	root := project(t, map[string]string{
		ListsFile:                    `{"poetry": ["ephemeral", "sub.lime"]}`,
		"PoeticNameDetector.json":    poetic,
		"skills/no-raw-sql/SKILL.md": ownSkill,
	})

	read := Load(root)
	if len(read.Unreadable) > 0 {
		t.Fatalf("the rule was refused: %v", read.Unreadable)
	}
	if len(read.Rules) != 1 {
		t.Fatalf("%d rule(s) read", len(read.Rules))
	}

	written, err := Lists{"poetry": {"ephemeral", "sub.lime"}}.Expand("PoeticNameDetector", []byte(poetic))
	if err != nil {
		t.Fatal(err)
	}

	for _, expected := range []string{`"ephemeral"`, `"sub.lime"`, `"plain"`, `^(ephemeral|sub\\.lime)_`} {
		if !strings.Contains(string(written), expected) {
			t.Errorf("the rule reads\n%s\nwithout %s", written, expected)
		}
	}
}

func TestARuleNamingAListTheProjectHasNotDeclaredIsRefused(t *testing.T) {
	read := Load(project(t, map[string]string{
		ListsFile:                    `{"prose": ["plain"]}`,
		"PoeticNameDetector.json":    poetic,
		"skills/no-raw-sql/SKILL.md": ownSkill,
	}))

	if len(read.Rules) > 0 {
		t.Error("the rule ran against a list that is not there")
	}
	if len(read.Unreadable) != 1 || !strings.Contains(read.Unreadable[0], `"poetry"`) || !strings.Contains(read.Unreadable[0], "prose") {
		t.Errorf("refused with %v", read.Unreadable)
	}
}

func TestTheListsFileIsNotReadAsARule(t *testing.T) {
	read := Load(project(t, map[string]string{ListsFile: `{"poetry": ["ephemeral"]}`}))

	if len(read.Unreadable) > 0 || len(read.Rules) > 0 {
		t.Errorf("the lists file read as %v, %v", read.Rules, read.Unreadable)
	}
}

func TestAListNobodyCanReadIsSaid(t *testing.T) {
	for name, text := range map[string]string{
		"a list with no words": `{"poetry": []}`,
		"not an object":        `["ephemeral"]`,
	} {
		t.Run(name, func(t *testing.T) {
			read := Load(project(t, map[string]string{ListsFile: text}))
			if len(read.Unreadable) != 1 || !strings.Contains(read.Unreadable[0], ListsFile) {
				t.Errorf("read as %v", read.Unreadable)
			}
		})
	}
}
