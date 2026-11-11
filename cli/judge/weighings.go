package judge

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// weighing is what one aggregating rule found over a whole set of C# files, and the folder those findings' paths start with.
type weighing struct {
	Print    uint64
	Folder   string
	Findings []engine.Finding
}

var (
	weighings        = map[string]weighing{}
	weighingsChanged bool
	weighingsHeld    sync.Mutex
)

// csharpSet is the C# files of one judging, known by a print of their contents: what an aggregating rule found over the same contents is answered again, whichever folder held them.
type csharpSet struct {
	prints []uint64
	whole  bool
	files  int
	folder string
}

func newCSharpSet(checkout string) *csharpSet {
	return &csharpSet{whole: checkout != "", folder: checkout}
}

func (s *csharpSet) add(codebase *engine.Codebase) {
	print, printed := codebase.Print()

	s.files += len(codebase.Files())
	s.whole = s.whole && printed
	s.prints = append(s.prints, print)
}

func (s *csharpSet) recall(rule detectors.Detector) ([]engine.Finding, bool) {
	if !s.whole {
		return nil, false
	}

	weighingsHeld.Lock()
	kept, known := weighings[catalog.Name(rule)]
	weighingsHeld.Unlock()

	if !known || kept.Print != engine.PrintOfAll(s.prints) {
		return nil, false
	}

	findings, moved := kept.movedTo(s.folder)
	if !moved {
		return nil, false
	}

	engine.CountedRemembered(s.files)

	return findings, true
}

func (s *csharpSet) keep(rule detectors.Detector, findings []engine.Finding) {
	engine.CountedJudged(s.files)

	if !s.whole {
		return
	}

	weighingsHeld.Lock()
	defer weighingsHeld.Unlock()

	weighings[catalog.Name(rule)] = weighing{engine.PrintOfAll(s.prints), s.folder, slices.Clone(findings)}
	weighingsChanged = true
}

func (w weighing) movedTo(folder string) ([]engine.Finding, bool) {
	moved := slices.Clone(w.Findings)
	if folder == w.Folder {
		return moved, true
	}

	for at, finding := range moved {
		if !strings.HasPrefix(finding.File, w.Folder) {
			return nil, false
		}

		finding.File = w.under(folder, finding.File)
		finding.Location = w.under(folder, finding.Location)
		finding.Twins = slices.Clone(finding.Twins)

		for twin, place := range finding.Twins {
			finding.Twins[twin] = w.under(folder, place)
		}

		moved[at] = finding
	}

	return moved, true
}

func (w weighing) under(folder, path string) string {
	if !strings.HasPrefix(path, w.Folder) {
		return path
	}

	return folder + strings.TrimPrefix(path, w.Folder)
}
