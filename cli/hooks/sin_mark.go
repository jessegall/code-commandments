package hooks

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// SinMark is one sin a rule found in an edited file, whether it stands on a line the edit changed, and the report
// this project has already filed about it, where there is one.
type SinMark struct {
	Rule    detectors.Detector
	Match   engine.Match
	Touched bool
	Filed   reported.Row
}

// IsFiled says whether this project has reported the finding as wrong. Nothing raises such a mark at the agent:
// the claim is filed and a maintainer is answering it. The mark is still made, though, and still shown, because a
// finding that vanished would settle in the chat and on the dashboard as one that was fixed.
func (m SinMark) IsFiled() bool {
	return m.Filed.Issue > 0
}

// Marking makes the marks of one look, holding what this project has reported so the record is read once for the
// whole look rather than once for every finding. A record that is missing or no longer reads as one marks nothing
// as reported, and says nothing: a hook may never fail over it.
type Marking struct {
	root   string
	record reported.Record
}

// Marks is the marking for a look at the project at root.
func Marks(root string) Marking {
	record, _ := reported.Of(root)

	return Marking{root: root, record: record}
}

// Of is the rule's match as a mark, touched when the changed lines cover it and filed when this project has
// reported it.
func (m Marking) Of(rule detectors.Detector, match engine.Match, changed git.ChangedLines) SinMark {
	mark := SinMark{Rule: rule, Match: match, Touched: changed.Covers(match.Line())}
	mark.Filed, _ = m.record.Reported(catalog.Name(rule), m.root, match.File(), match.Line())

	return mark
}

// MarkOf is the rule's match as a mark of a look that knows nothing of what has been reported.
func MarkOf(rule detectors.Detector, match engine.Match, changed git.ChangedLines) SinMark {
	return SinMark{Rule: rule, Match: match, Touched: changed.Covers(match.Line())}
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
