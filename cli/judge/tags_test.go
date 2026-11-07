package judge

import (
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/skill"
)

func ownRule(t *testing.T, name, tags string) detectors.Detector {
	t.Helper()

	text := `{"engine": "python", "sin": {"name": "` + name + `", "description": "a test rule", "skill": "python/flow"}, "find": {"select": "call"}` + tags + `}`

	found, err := rule.Parse(name, []byte(text), skill.Slugged)
	if err != nil {
		t.Fatal(err)
	}

	return found
}

func TestATagRunsOnlyTheRulesCarryingIt(t *testing.T) {
	all := []detectors.Detector{
		ownRule(t, "sleep-in-a-test", `, "tags": ["testing"]`),
		ownRule(t, "clock-loop", `, "tags": ["testing", "time"]`),
		ownRule(t, "raw-print", ``),
	}

	cases := []struct {
		tags []string
		want int
	}{
		{[]string{"testing"}, 2},
		{[]string{"time"}, 1},
		{[]string{"time", "nothing"}, 1},
		{[]string{"nothing"}, 0},
		{nil, 3},
	}

	for _, each := range cases {
		if got := selectFrom(all, "", "", each.tags); len(got) != each.want {
			t.Errorf("--tag %v ran %d rules, want %d", each.tags, len(got), each.want)
		}
	}

	if got := selectFrom(all, "", "", []string{"testing"}); len(got) == 2 && got[0].Sin().Definition().Name != "sleep-in-a-test" {
		t.Errorf("--tag testing kept %v first, where the rules keep their order", got[0].Sin().Definition().Name)
	}
}

func TestTagsAreReadRepeatedAndCommaSeparated(t *testing.T) {
	in := cli.InputOf("judge", "--tag=testing", "--tag=time,speed")

	if got := tagsOf(in); len(got) != 3 || got[0] != "testing" || got[2] != "speed" {
		t.Errorf("--tag=testing --tag=time,speed read as %v, want [testing time speed]", got)
	}
}
