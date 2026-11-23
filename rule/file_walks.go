package rule

import (
	"fmt"
	"sync"
	"sync/atomic"

	"github.com/jessegall/code-commandments/engine"
)

// fileWalks counts, for one rule's judging, how many nodes of each file the rule's lists handed it, and stops the
// rule whose lists handed it one file's nodes more than mostWalksOfAFile times over: it goes over that file again
// and again where it should keep what it learned the first time.
type fileWalks struct {
	handed sync.Map
}

const (
	mostWalksOfAFile          = 25
	fewestNodesAFileCountsFor = 1000
)

// WentOverAFileAgain is why a rule was stopped: the file it kept going over, and how many nodes that file holds.
type WentOverAFileAgain struct {
	Path  string
	Nodes int
}

func (w WentOverAFileAgain) Error() string {
	return fmt.Sprintf("it went over %s, a file of %d nodes, more than %d times; a rule works a scan it repeats out once, in a definition written \"kept\": true that every site then reads", w.Path, w.Nodes, mostWalksOfAFile)
}

func (w *fileWalks) hand(item value) {
	if w == nil {
		return
	}
	site, isNode := item.held.(engine.Match)
	if !isNode || site.Source() == nil {
		return
	}
	file := site.Source()
	counted, found := w.handed.Load(file)
	if !found {
		counted, _ = w.handed.LoadOrStore(file, &atomic.Int64{})
	}
	nodes := len(file.Nodes())
	if counted.(*atomic.Int64).Add(1) > int64(mostWalksOfAFile*max(nodes, fewestNodesAFileCountsFor)) {
		panic(WentOverAFileAgain{Path: file.Path, Nodes: nodes})
	}
}

func (w *fileWalks) most() int64 {
	var tenths int64
	w.handed.Range(func(file, counted any) bool {
		held := file.(*engine.File)
		tenths = max(tenths, 10*counted.(*atomic.Int64).Load()/int64(max(len(held.Nodes()), fewestNodesAFileCountsFor)))

		return true
	})

	return tenths
}

func (w *fileWalks) count() {
	engine.CountedWalks(w.most())
}
