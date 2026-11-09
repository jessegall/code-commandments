package hooks

import (
	"fmt"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/warm"
)

const rememberedTreesEvery = 5 * time.Minute

var treeSaves = &treeSaver{saved: map[string]uint64{}}

// treeSaver writes each held tree to disk when it has changed since it was last written and renews the copy of one that has not, so a restarted service finds the tree as it was.
type treeSaver struct {
	mu    sync.Mutex
	saved map[string]uint64
}

func (s *treeSaver) save(now time.Time) {
	s.mu.Lock()
	defer s.mu.Unlock()

	for _, base := range kept.Bases() {
		named := branchRoot{project: base.Project, branch: base.Branch}
		folder := savedTreeFolder(named)

		if s.saved[named.key()] == base.Stamp() {
			_ = warm.Renew(folder, now)

			continue
		}

		if err := base.Save(folder, thisBuild(), now); err != nil {
			logWarned(fmt.Sprintf("the tree of %s on %s could not be saved | %v", named.project, named.branch, err))

			continue
		}

		s.saved[named.key()] = base.Stamp()
	}
}

func (s *treeSaver) wrote(base *warm.Base) {
	s.mu.Lock()
	defer s.mu.Unlock()

	s.saved[branchRoot{project: base.Project, branch: base.Branch}.key()] = base.Stamp()
}

func remembersTreesAcrossRestarts() {
	go func() {
		for now := range time.Tick(rememberedTreesEvery) {
			treeSaves.save(now)
		}
	}()
}
