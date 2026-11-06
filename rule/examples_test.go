package rule_test

import (
	"os"
	"path/filepath"
	"regexp"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/rule"
)

var fenced = regexp.MustCompile("(?s)```json\n(.*?)```")

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
