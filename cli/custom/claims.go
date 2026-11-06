package custom

import (
	"fmt"
	"os"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	"github.com/jessegall/code-commandments/skill"
)

var ruleName = regexp.MustCompile(`^[a-z][a-z0-9]*(-[a-z0-9]+)+$`)

var backticked = regexp.MustCompile("`([^`]+)`")

var listItem = regexp.MustCompile(`^\s*([-*+]|\d+\.)\s`)

const namesItsSins = "The sins here:"

func (p Project) ClaimWarnings() []string {
	declared := p.declaredNames()

	var warnings []string

	for _, own := range p.Skills {
		var undeclared []string

		for _, claim := range claimedBy(own.Dir) {
			if slices.ContainsFunc(declared, func(name string) bool { return catalog.Normalise(name) == catalog.Normalise(claim) }) {
				continue
			}

			if _, shipped := skill.Slugged(claim); shipped {
				continue
			}

			if !slices.Contains(undeclared, claim) {
				undeclared = append(undeclared, claim)
			}
		}

		if len(undeclared) == 0 {
			continue
		}

		warnings = append(warnings, fmt.Sprintf("⚠ the skill %s says %d rule(s) fire that are declared nowhere:\n  %s\n"+
			"  A skill teaches what a rule finds, so a name it says fires is a sin some rule declares — write the rule "+
			"(`commandments make <Name>`), or take the name out of the skill.",
			own.definition.Slug, len(undeclared), strings.Join(undeclared, "\n  ")))
	}

	return warnings
}

func (p Project) declaredNames() []string {
	var declared []string

	for _, own := range p.Rules {
		declared = append(declared, own.Sin().Definition().Name, own.Name())
	}

	for _, shipped := range sins.All() {
		declared = append(declared, shipped.Definition().Name)
	}

	return declared
}

func claimedBy(dir string) []string {
	text, err := os.ReadFile(dir + "/SKILL.md")
	if err != nil {
		return nil
	}

	var claimed []string
	naming := false

	for _, line := range strings.Split(strings.ReplaceAll(string(text), "\r\n", "\n"), "\n") {
		if heading, found := headingOf(line); found {
			naming = strings.Contains(heading, "fires") || strings.Contains(heading, "sins")

			continue
		}

		if strings.Contains(line, namesItsSins) {
			claimed = append(claimed, rulesNamedIn(line)...)

			continue
		}

		if naming && listItem.MatchString(line) {
			claimed = append(claimed, first(rulesNamedIn(line))...)
		}
	}

	return claimed
}

func headingOf(line string) (string, bool) {
	heading, found := strings.CutPrefix(strings.TrimSpace(line), "#")
	if !found {
		return "", false
	}

	return strings.ToLower(strings.TrimLeft(heading, "# ")), true
}

func rulesNamedIn(line string) []string {
	var found []string
	for _, match := range backticked.FindAllStringSubmatch(line, -1) {
		if ruleName.MatchString(match[1]) {
			found = append(found, match[1])
		}
	}

	return found
}

func first(names []string) []string {
	if len(names) == 0 {
		return nil
	}

	return names[:1]
}
