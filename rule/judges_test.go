package rule_test

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

// TestARuleNamesTheFoldersItJudges holds the key a project writes in the rule's own file, which is the point of it
// being there: the file is the project's own and no install rewrites it, so the folders the rule judges are not
// lost when the config is written again.
func TestARuleNamesTheFoldersItJudges(t *testing.T) {
	for written, want := range map[string]string{
		`"judges": ["tests"]`:                "[tests]",
		`"judges": ["tests", "scripts"]`:     "[tests scripts]",
		`"judges": ["/tests/", " spec "]`:    "[tests spec]",
		`"judges": []`:                       "[]",
		`"sin": {"name": "x", "skill": "y"}`: "[]",
	} {
		text := `{"engine": "backend", "sin": {"name": "raw-sql", "description": "SQL written inline", "skill": "php/laravel-idioms"}, "find": {"select": "call"}`
		if !strings.HasPrefix(written, `"sin"`) {
			text += ", " + written
		}
		text += "}"

		found, err := rule.Parse("RawSqlDetector", []byte(text), shipped)
		if err != nil {
			t.Fatalf("%s: %v", written, err)
		}

		if got := fmt.Sprint(found.Judges()); got != want {
			t.Errorf("%s: the rule judges %s, want %s", written, got, want)
		}
	}
}

// TestAFolderListTheToolCannotReadSaysWhy holds the key to the shape every other key is held to.
func TestAFolderListTheToolCannotReadSaysWhy(t *testing.T) {
	written := `{"engine": "backend", "sin": {"name": "x", "skill": "php/absence"}, "find": {"select": "call"}, "judges": "tests"}`

	if _, err := rule.Parse("X", []byte(written), shipped); err == nil {
		t.Error("a judges key that is one folder rather than a list of them was read as if it were a list")
	}
}
