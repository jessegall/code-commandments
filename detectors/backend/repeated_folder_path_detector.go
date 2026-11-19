package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

const (
	leastFolderSites  = 3
	leastFolderScopes = 2
)

// RepeatedFolderPathDetector finds one folder spelled as a path at several sites instead of through one owner.
type RepeatedFolderPathDetector struct{}

func init() { detectors.Register(catalog.PHP, RepeatedFolderPathDetector{}) }

func (RepeatedFolderPathDetector) Sin() sins.Sin { return backendsins.RepeatedFolderPath{} }

func (RepeatedFolderPathDetector) Find(codebase *engine.Codebase) []engine.Match {
	sites := php.In(codebase).
		Where(engine.As(spellsAFolder)).
		Where(engine.Match.IsWithinFunction).
		Reject(engine.Match.IsTest).
		Get()

	return engine.RepeatedFolders(sites, folderSpelledAt, leastFolderSites, leastFolderScopes)
}

func spellsAFolder(n php.Node) bool {
	_, ok := n.SpelledFolder()

	return ok
}

func folderSpelledAt(site engine.Match) (engine.SpelledFolder, bool) {
	return php.Node{Match: site}.SpelledFolder()
}

func (RepeatedFolderPathDetector) CrossFile() {}
