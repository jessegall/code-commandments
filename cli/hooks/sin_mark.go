package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// SinMark is one sin a rule found in an edited file, and whether it stands on a line the edit changed.
type SinMark struct {
	Rule    detectors.Detector
	Match   engine.Match
	Touched bool
}

// MarkOf is the rule's match as a mark, touched when the changed lines cover it.
func MarkOf(rule detectors.Detector, match engine.Match, changed git.ChangedLines) SinMark {
	return SinMark{rule, match, changed.Covers(match.Line())}
}

// Finding is the mark as a finding.
func (m SinMark) Finding() engine.Finding {
	sin := m.Rule.Sin().Definition()

	return engine.Finding{
		Detector: catalog.Name(m.Rule),
		Skill:    sin.Slug(),
		Sin:      sin.Name,
		File:     m.Match.File(),
		Location: m.Match.Location(),
		Scope:    m.Match.Scope(),
	}
}

// ID is the mark's identity, which `reported.Identity` is the one home of: its rule, its file under root and the
// text of the line it flags, so a sin moved by an edit above it is still the same sin, and the same sin in another
// checkout of the project too. A report filed about a finding is keyed by that same identity, so the two cannot
// drift apart.
func (m SinMark) ID(root string) string {
	id, _ := reported.Identity(catalog.Name(m.Rule), root, m.Match.File(), m.Match.Line())

	return id
}

// Key is the key the mark's chat mark is raised and settled by.
func (m SinMark) Key(root string) string {
	return markKey(m.ID(root))
}

// Found is the mark as the check names it: the sin and where.
func (m SinMark) Found() string {
	return m.Rule.Sin().Definition().Name + " at " + m.Match.Location()
}

// FileFrom is the file the mark is in as the project names it, relative to root: what a command naming that one
// file takes, and short enough to say in a line.
func (m SinMark) FileFrom(root string) string {
	return strings.TrimPrefix(strings.TrimPrefix(m.Match.File(), strings.TrimRight(root, "/")), "/")
}

// ShownFrom is the mark named with paths under root.
func (m SinMark) ShownFrom(root string) string {
	return strings.ReplaceAll(m.Found(), strings.TrimRight(root, "/")+"/", "")
}
