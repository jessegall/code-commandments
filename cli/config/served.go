package config

import (
	"encoding/json"
	"os/exec"
	"path/filepath"
	"sync"

	"github.com/jessegall/code-commandments/cli/workspace"
)

// served is the config a version-5 journal holds for its project and serves to every checkout: the copy this process fetched, the version it was at, and the project it belongs to. It is fetched only once the journal has said it holds one, by a version on a line it sent.
type served struct {
	mu      sync.Mutex
	project string
	version int
	config  Config
	held    bool
}

var journalCopy served

func (s *served) of(settings string) (Config, bool) {
	s.mu.Lock()
	defer s.mu.Unlock()

	if !s.held || filepath.Clean(settings) != s.project {
		return Config{}, false
	}

	return s.config, true
}

func TheJournalHolds(project string, version int, fetch func() ([]byte, error)) (was, now Config, moved bool) {
	journalCopy.mu.Lock()
	defer journalCopy.mu.Unlock()

	if version <= 0 || (journalCopy.held && version <= journalCopy.version && journalCopy.project == filepath.Clean(project)) {
		return Config{}, Config{}, false
	}

	said, err := fetch()
	if err != nil {
		return Config{}, Config{}, false
	}

	var answered struct {
		Version int             `json:"version"`
		Config  json.RawMessage `json:"config"`
	}
	if json.Unmarshal(said, &answered) != nil || len(answered.Config) == 0 || string(answered.Config) == "null" {
		return Config{}, Config{}, false
	}

	parsed, err := ParsedJSON(answered.Config)
	if err != nil {
		return Config{}, Config{}, false
	}

	was, moved = journalCopy.config, journalCopy.held
	journalCopy.project, journalCopy.version, journalCopy.config, journalCopy.held =
		filepath.Clean(project), max(version, answered.Version), parsed, true

	return was, parsed, moved
}

func FetchedFromTheJournal(journal, project string) func() ([]byte, error) {
	return func() ([]byte, error) {
		asked := exec.Command(journal, "plugin", "config", workspace.JournalPlugin, "--json")
		asked.Dir = project

		return asked.Output()
	}
}
