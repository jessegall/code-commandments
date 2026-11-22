package judge

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/rule"
)

func TestJudgeCombinesSinSelectorsAndKeepsOtherFilters(t *testing.T) {
	rules := []rule.Detector{
		ownRule(t, "sleep-in-a-test", `, "tags": ["testing"]`),
		ownRule(t, "clock-loop", `, "tags": ["time"]`),
		ownRule(t, "raw-print", `, "tags": ["testing"]`),
		ownRule(t, "extra-slice", `, "tags": ["testing"]`),
	}
	in := cli.InputOf("judge", "--no-checklist", "--sin=raw-print, clock-loop", "--sin=sleep,raw-print", "--tag=testing", "--skill=python/flow")
	options := optionsOf(in, workspace.Workspace{})
	selected := selectFrom(rules, options.skill, options.sinQueries, options.tags)

	if !slices.Equal(selected, []rule.Detector{rules[0], rules[2]}) {
		t.Fatalf("selected %v", selected)
	}
	if got := selectFrom(rules, "php/flow", options.sinQueries, nil); len(got) != 0 {
		t.Fatalf("an unmatched skill selected %d rules", len(got))
	}
}

func TestJudgeKeepsASingleUnmatchedSinFromSelectingEveryRule(t *testing.T) {
	rules := []rule.Detector{ownRule(t, "raw-print", "")}
	in := cli.InputOf("judge", "--no-checklist", "--sin=0")
	options := optionsOf(in, workspace.Workspace{})
	if got := selectFrom(rules, options.skill, options.sinQueries, options.tags); len(got) != 0 {
		t.Fatalf("an unmatched sin selected %d rules", len(got))
	}
}
