package hooks

import (
	"crypto/sha1"
	"fmt"
	"os"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/source"
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

// ID is the mark's identity: its sin, its file under root and the text of the line it flags, so a sin moved by
// an edit above it is still the same sin, and the same sin in another checkout of the project too.
func (m SinMark) ID(root string) string {
	return fmt.Sprintf("%x", sha1.Sum([]byte(m.Rule.Sin().Definition().Name+"\x00"+source.Relative(root, m.Match.File())+"\x00"+m.flaggedText())))
}

// Key is the key the mark's chat mark is raised and settled by.
func (m SinMark) Key(root string) string {
	return markKey(m.ID(root))
}

// Found is the mark as the check names it: the sin and where.
func (m SinMark) Found() string {
	return m.Rule.Sin().Definition().Name + " at " + m.Match.Location()
}

// ShownFrom is the mark named with paths under root.
func (m SinMark) ShownFrom(root string) string {
	return strings.ReplaceAll(m.Found(), strings.TrimRight(root, "/")+"/", "")
}

func (m SinMark) flaggedText() string {
	text, err := os.ReadFile(m.Match.File())
	if err != nil {
		return ""
	}

	lines := strings.SplitAfter(string(text), "\n")
	if line := m.Match.Line(); line >= 1 && line <= len(lines) {
		return strings.TrimSpace(lines[line-1])
	}

	return ""
}
