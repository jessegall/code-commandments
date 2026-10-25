package rule

import (
	"errors"
	"fmt"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// placeholder is one `{…}` in a replacement: the targets that lead from the found node to the node whose source
// stands there, one hop per `/`.
var placeholder = regexp.MustCompile(`\{([^{}]*)\}`)

// Fix is the rewrite a rule declares for what it finds: the found node's source replaced by a template, or
// rewritten by substitutions. It reads the tree the rule found, never the file around it.
type Fix struct {
	Replace    string         `json:"replace,omitempty"`
	Substitute []Substitution `json:"substitute,omitempty"`
}

// Substitution rewrites every stretch of the found node's source the expression matches, as Go's regexp
// ReplaceAllString does: `${1}` stands for a group.
type Substitution struct {
	Matches string `json:"matches"`
	With    string `json:"with"`

	// pattern is Matches, compiled once when the rule is read.
	pattern *regexp.Regexp
}

// prepare checks the fix can run, and compiles what it matches with.
func (f *Fix) prepare() error {
	if (f.Replace == "") == (f.Substitute == nil) {
		return errors.New("a fix names one of replace and substitute")
	}

	for _, found := range placeholder.FindAllStringSubmatch(f.Replace, -1) {
		for _, hop := range strings.Split(found[1], "/") {
			if !slices.ContainsFunc(Targets, func(target Target) bool { return target.Takes(hop) }) {
				return fmt.Errorf("fix: %s", noneOf("placeholder", hop, targetKeys()))
			}
		}
	}

	for at := range f.Substitute {
		compiled, err := regexp.Compile(f.Substitute[at].Matches)
		if err != nil {
			return fmt.Errorf("fix: substitute %q is no regular expression: %w", f.Substitute[at].Matches, err)
		}

		f.Substitute[at].pattern = compiled
	}

	return nil
}

// text is what the fix writes in place of the found node; false when a placeholder reaches no node or the fix
// would leave the source as it is.
func (f Fix) text(found engine.Match) (string, bool) {
	written := found.Written()
	if f.Replace == "" {
		rewritten := written
		for _, substitution := range f.Substitute {
			rewritten = substitution.pattern.ReplaceAllString(rewritten, substitution.With)
		}

		return rewritten, rewritten != written
	}

	reached := true
	replaced := placeholder.ReplaceAllStringFunc(f.Replace, func(held string) string {
		node := found
		for _, hop := range strings.Split(strings.Trim(held, "{}"), "/") {
			node = Step{Of: hop}.subject(node)
		}

		reached = reached && node.Exists()

		return node.Written()
	})

	return replaced, reached && replaced != written
}

// Fixes says whether the rule declares a fix, so repent runs it.
func (r Rule) Fixes() bool {
	return r.fix != nil
}

// FixFor is the text the rule's fix writes in place of a node it found; false when it has none to write there.
func (r Rule) FixFor(found engine.Match) (string, bool) {
	if r.fix == nil {
		return "", false
	}

	return r.fix.text(found)
}
