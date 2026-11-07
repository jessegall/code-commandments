package rule_test

import (
	"fmt"
	"testing"

	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rule"
)

func TestARuleCarriesTheTagsItsFileNames(t *testing.T) {
	for written, want := range map[string]string{
		`"tags": ["testing"]`:          "[testing]",
		`"tags": ["testing", "speed"]`: "[testing speed]",
		`"tags": []`:                   "[]",
		``:                             "[]",
	} {
		text := `{"engine": "backend", "sin": {"name": "raw-sql", "description": "SQL written inline", "skill": "php/laravel-idioms"}, "find": {"select": "call"}`
		if written != "" {
			text += ", " + written
		}
		text += "}"

		found, err := rule.Parse("RawSqlDetector", []byte(text), shipped)
		if err != nil {
			t.Fatalf("%s: %v", written, err)
		}

		tagged, carries := detectors.Detector(found).(detectors.Tagged)
		if !carries {
			t.Fatal("a project's own rule is not a tagged detector, so judge --tag can never find it")
		}

		if got := fmt.Sprint(tagged.Tags()); got != want {
			t.Errorf("%s: the rule carries %s, want %s", written, got, want)
		}
	}
}
