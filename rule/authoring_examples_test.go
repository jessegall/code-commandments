package rule

import (
	"os"
	"regexp"
	"strings"
	"testing"
)

var jsonFence = regexp.MustCompile("(?s)```json\n(.*?)```")

func TestEveryWholeRuleTheAuthoringSkillShowsIsReadByTheTool(t *testing.T) {
	for _, page := range []string{"../skills/writing-detectors/SKILL.md", "../skills/writing-detectors/reference/examples.md"} {
		text, err := os.ReadFile(page)
		if err != nil {
			t.Fatal(err)
		}
		shown := 0
		for _, fence := range jsonFence.FindAllStringSubmatch(string(text), -1) {
			if !strings.Contains(fence[1], `"$schema"`) || strings.Contains(fence[1], `"..."`) {
				continue
			}
			shown++
			if _, err := Parse("Example", []byte(fence[1]), anySkill); err != nil {
				t.Errorf("%s shows a rule the tool refuses: %v\n%s", page, err, fence[1])
			}
		}
		if shown == 0 {
			t.Errorf("%s shows no whole rule", page)
		}
	}
}
