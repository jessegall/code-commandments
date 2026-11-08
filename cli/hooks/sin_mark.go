package hooks

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/reported"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// SinMark is one sin a rule found in an edited file, whether it stands on a line the edit changed, and the report this project has already filed about it, where there is one.
type SinMark struct {
	Rule    detectors.Detector
	Match   engine.Match
	Touched bool
	Filed   reported.Row
}

func (m SinMark) IsFiled() bool {
	return m.Filed.Issue > 0
}

func touchedAndUnfiled(marks []SinMark) []SinMark {
	return slices.DeleteFunc(marks, func(mark SinMark) bool { return !mark.Touched || mark.IsFiled() })
}

// Marking makes the marks of one look, holding what this project has reported so the record is read once for the whole look rather than once for every finding.
type Marking struct {
	root   string
	record reported.Record
	Cut    string
}

func Marks(root string) Marking {
	record, _ := reported.Of(root)

	return Marking{root: root, record: record}
}

func (m Marking) In(checkout string) Marking {
	m.root = checkout

	return m
}

func (m Marking) CutFrom(cut string) Marking {
	m.Cut = cut

	return m
}

func (m Marking) Of(rule detectors.Detector, match engine.Match, changed git.ChangedLines) SinMark {
	mark := SinMark{Rule: rule, Match: match, Touched: changed.Covers(match.Line())}
	mark.Filed, _ = m.record.Reported(catalog.Name(rule), m.root, match.File(), match.Line())

	return mark
}

func MarkOf(rule detectors.Detector, match engine.Match, changed git.ChangedLines) SinMark {
	return SinMark{Rule: rule, Match: match, Touched: changed.Covers(match.Line())}
}

func (m SinMark) Finding() engine.Finding {
	sin := m.Rule.Sin().Definition()

	return engine.Finding{
		Detector: catalog.Name(m.Rule),
		Skill:    sin.Slug(),
		Sin:      sin.Name,
		File:     m.Match.File(),
		Line:     m.Match.Line(),
		Location: m.Match.Location(),
		Scope:    m.Match.Scope(),
	}
}

func (m SinMark) ID(root string) string {
	id, _ := reported.Identity(catalog.Name(m.Rule), root, m.Match.File(), m.Match.Line())

	return id
}

func (m SinMark) Key(root string) string {
	return markKey(m.ID(root))
}

func (m SinMark) Found() string {
	return m.Rule.Sin().Definition().Name + " at " + m.Match.Location()
}

func (m SinMark) FileFrom(root string) string {
	return seenFrom(root, m.Match.File())
}

func (m SinMark) ShownFrom(root string) string {
	return m.Rule.Sin().Definition().Name + " at " + m.FileFrom(root) + ":" + strconv.Itoa(m.Match.Line())
}

func seenFrom(root, file string) string {
	home := trimmedEnd(root)

	if under, inside := under(file, home); inside {
		return under
	}

	project := trimmedEnd(git.ProjectRoot(home))
	if project != "" && project != home {
		if under, inside := under(file, project); inside {
			return under
		}
	}

	return strings.TrimLeft(file, "/\\")
}

func under(file, root string) (string, bool) {
	for _, separator := range []string{"/", "\\"} {
		if named, inside := strings.CutPrefix(file, root+separator); inside {
			return named, true
		}
	}

	return "", false
}

func trimmedEnd(path string) string {
	return strings.TrimRight(path, "/\\")
}
