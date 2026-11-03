package rule_test

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

// fenced is a JSON block in a markdown file, which is how the skill shows a rule.
var fenced = regexp.MustCompile("(?s)```json\n(.*?)```")

// TestEveryExampleInTheSkillIsARuleTheToolCanRun holds the teaching to the tool. A skill that shows a rule
// nobody can run teaches a reader to write one, and they find out at the first `rule prove` rather than here.
// The examples went stale once before: a rule file's format is the one surface a document cannot be generated
// from, since what makes a GOOD example is a judgement, so this is what keeps them honest.
//
// A rule built on another is parsed alone, so its `from` names a rule nothing declares; that is read as the
// reference it is rather than as a fault, since the file it would sit in is the reader's.
func TestEveryExampleInTheSkillIsARuleTheToolCanRun(t *testing.T) {
	shown := filepath.Join("..", "skills", "writing-detectors", "reference", "examples.md")
	text, err := os.ReadFile(shown)
	if err != nil {
		t.Fatal(err)
	}

	blocks := fenced.FindAllStringSubmatch(string(text), -1)
	if len(blocks) < 5 {
		t.Fatalf("the examples hold %d rules, and a reader looking for the shape of a question wants one per kind", len(blocks))
	}

	for at, block := range blocks {
		written := block[1]

		if _, err := rule.Parse("Example", []byte(written), shipped); err != nil {
			if strings.Contains(err.Error(), "no rule is called") {
				continue
			}

			t.Errorf("the %d%s example is no rule the tool can run: %v\n%s", at+1, ordinal(at+1), err, written)
		}
	}
}

// ordinal is the suffix a count reads with, so a failure names the example a reader can count to.
func ordinal(count int) string {
	switch {
	case count%100 >= 11 && count%100 <= 13:
		return "th"
	case count%10 == 1:
		return "st"
	case count%10 == 2:
		return "nd"
	case count%10 == 3:
		return "rd"
	}

	return "th"
}
