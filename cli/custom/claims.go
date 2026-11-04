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

// ruleName is a name a rule's sin is written with: lowercase words joined by single hyphens. A single word is
// none, which keeps a skill's own prose — a forbidden word it lists, a term it defines — out of the reading.
var ruleName = regexp.MustCompile(`^[a-z][a-z0-9]*(-[a-z0-9]+)+$`)

// backticked is one name in backticks, as a skill writes a rule name.
var backticked = regexp.MustCompile("`([^`]+)`")

// listItem opens a bullet, whatever marker and indent it is written with.
var listItem = regexp.MustCompile(`^\s*([-*+]|\d+\.)\s`)

// namesItsSins is the line the renderer writes naming the sins a skill fixes, which a project's own skill copies.
const namesItsSins = "The sins here:"

// ClaimWarnings name, per skill, every rule the skill says fires that no rule declares.
//
// A shipped skill cannot say this wrongly: its when-it-fires rows are projected from the registered sins, so a
// name it carries is a name some sin has. A project's own skill is written by hand, and the drift is silent and
// costly: a skill naming docstring-in-method and field-docstring, neither of them a rule, had its reader plant
// that sin on purpose, find nothing, and read the silence as a fault in the tool. The mirror of this is already
// said — a configured detector declared nowhere — so this is that reading turned around.
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

// declaredNames are every rule name a skill may say fires: the sin of each of the project's own rules, and of
// every shipped one.
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

// claimedBy are the rule names the skill in the folder says fire: the first name in backticks of each bullet
// under a heading about what fires, and every name on the line that lists a skill's sins.
//
// Only those two places are read, because they are the two a skill names its rules in: the heading our own
// renderer writes and a project copies, and that one line. A name in backticks anywhere else is prose — a skill
// to load, a flag, a path — and reading it would warn about what is written correctly.
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

// headingOf is a line's heading text, lowercased; false for a line that is no heading.
func headingOf(line string) (string, bool) {
	heading, found := strings.CutPrefix(strings.TrimSpace(line), "#")
	if !found {
		return "", false
	}

	return strings.ToLower(strings.TrimLeft(heading, "# ")), true
}

// rulesNamedIn are the names in backticks on the line that are written as a rule's name.
func rulesNamedIn(line string) []string {
	var found []string
	for _, match := range backticked.FindAllStringSubmatch(line, -1) {
		if ruleName.MatchString(match[1]) {
			found = append(found, match[1])
		}
	}

	return found
}

// first is the head of the names, or none.
func first(names []string) []string {
	if len(names) == 0 {
		return nil
	}

	return names[:1]
}
