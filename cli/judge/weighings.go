package judge

import (
	"cmp"
	"maps"
	"slices"
	"strconv"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
)

// weighing is what one aggregating rule found over a whole set of C# files, the folder those findings' paths start with, and when it was last answered from.
type weighing struct {
	Print    uint64
	Folder   string
	Findings []engine.Finding
	Used     int64
}

const weighingsKeptPerRule = 8

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

	key := weighingKey(rule, engine.PrintOfAll(s.prints))

	weighingsHeld.Lock()
	kept, known := weighings[key]
	if known {
		kept.Used = time.Now().UnixNano()
		weighings[key] = kept
		weighingsChanged = true
	}
	weighingsHeld.Unlock()

	if !known {
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

	print := engine.PrintOfAll(s.prints)
	weighings[weighingKey(rule, print)] = weighing{print, s.folder, slices.Clone(findings), time.Now().UnixNano()}
	weighingsChanged = true
	newestPerRule(weighings)
}

func weighingKey(rule detectors.Detector, print uint64) string {
	return catalog.Name(rule) + "\x00" + strconv.FormatUint(print, 16)
}

func newestPerRule(held map[string]weighing) {
	byRule := map[string][]string{}
	for key := range held {
		rule, _, _ := strings.Cut(key, "\x00")
		byRule[rule] = append(byRule[rule], key)
	}

	for _, keys := range byRule {
		slices.SortFunc(keys, func(one, other string) int { return cmp.Compare(held[other].Used, held[one].Used) })
		for _, dropped := range keys[min(len(keys), weighingsKeptPerRule):] {
			delete(held, dropped)
		}
	}
}

func heldWeighings() map[string]weighing {
	return maps.Clone(weighings)
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
