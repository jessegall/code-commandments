// Package workspace is where the tool keeps what it writes in a project: the shared `.commandments` folder (config, custom rules) and one folder per agent session for its checklist and state.
package workspace

import (
	"crypto/sha1"
	"encoding/hex"
	"os"
	"os/exec"
	"path/filepath"
	"sort"
	"strings"
	"sync"
	"time"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/source"
)

const (
	dir = ".commandments"

	JournalPlugin = "code-commandments"

	Sessions = "sessions"

	agents = "agents"

	Custom = "custom"

	Sins = "sins"

	Library = ".agents/skills"

	DefaultSession = "default"

	keyLength = 5
	pruneDays = 7
)

var repositories sync.Map

// Workspace is one project's folders, as seen by one session.
type Workspace struct {
	root      string
	sessionID string
}

func At(root, sessionID string) Workspace {
	if sessionID == "" {
		sessionID = os.Getenv("CLAUDE_CODE_SESSION_ID")
	}

	return Workspace{root, sessionID}
}

func OfSession(fallback, sessionID string) Workspace {
	stated := os.Getenv("CLAUDE_PROJECT_DIR")
	if stated == "" {
		stated = fallback
	}

	if root := git.ProjectRoot(stated); root != "" {
		return At(root, sessionID)
	}

	return At(stated, sessionID)
}

func JournalProject() (string, bool) {
	record := os.Getenv("JOURNAL_ROOT")
	if record == "" {
		return "", false
	}

	record = filepath.Clean(record)
	if filepath.Base(record) == ".journal" {
		return filepath.Dir(record), true
	}

	return record, true
}

func ProjectRoot(cwd string) string {
	root := git.Root(cwd)
	project := os.Getenv("CLAUDE_PROJECT_DIR")

	switch {
	case project == "" && root == "":
		return cwd
	case project == "":
		return root
	case root != "" && git.BelongsTo(root, project):
		return root
	default:
		return project
	}
}

func SettingsRoot(dir string) string {
	project := git.ProjectRoot(dir)
	if project == "" {
		return dir
	}

	return project
}

func Config(dir string) string {
	return At(dir, "").Shared("config.php")
}

func JSONConfig(dir string) string {
	return At(dir, "").Shared("config.json")
}

const Samples = "samples"

func SamplesDir(dir string) string {
	return CustomDir(dir) + "/" + Samples
}

func CustomDir(dir string) string {
	return At(dir, "").Shared(Custom)
}

func CustomFiles(dir string) []string {
	files := source.FilesIn(CustomDir(dir), "php", source.Under(CustomDir(dir), []string{Samples}))
	sort.Strings(files)

	return files
}

func (w Workspace) Root() string {
	return w.root
}

func (w Workspace) SessionID() string {
	return w.sessionID
}

func (w Workspace) LibraryDir() string {
	return w.root + "/" + Library
}

func (w Workspace) SessionKey() string {
	if w.sessionID == "" {
		return DefaultSession
	}

	if name, named := w.Names().NameOf(w.sessionID); named {
		return name
	}

	return KeyFor(w.sessionID)
}

func (w Workspace) Names() SessionNames {
	shared, known := repositories.Load(w.root)

	if !known {
		shared = w.repository()
		repositories.Store(w.root, shared)
	}

	return NamesIn(shared.(string))
}

func (w Workspace) repository() string {
	if root := git.ProjectRoot(w.root); root != "" {
		return root + "/" + dir
	}

	return w.root + "/" + dir
}

func KeyFor(sessionID string) string {
	sum := sha1.Sum([]byte(sessionID))

	return hex.EncodeToString(sum[:])[:keyLength]
}

func (w Workspace) Dir() string {
	return w.root + "/" + dir
}

func (w Workspace) IsJournalDriven() bool {
	return w.journalRoot() != ""
}

func (w Workspace) journalRoot() string {
	for _, root := range []string{w.root, git.ProjectRoot(w.root)} {
		if root == "" {
			continue
		}

		if info, err := os.Stat(root + "/.journal/plugins/" + JournalPlugin + "/.journal-plugin/plugin.json"); err == nil && info.Mode().IsRegular() {
			return root
		}
	}

	return ""
}

// Journal is the journal program of the project's journal: the one named in JOURNAL_BIN, the one on the PATH, or the one the journal keeps in its own folder; empty without one.
func Journal(journalRoot string) string {
	if named := os.Getenv("JOURNAL_BIN"); named != "" {
		return named
	}

	if found, err := exec.LookPath("journal"); err == nil {
		return found
	}

	beside := filepath.Join(journalRoot, ".journal", "bin", "journal")
	if info, err := os.Stat(beside); err == nil && !info.IsDir() {
		return beside
	}

	return ""
}

func (w Workspace) Journal() string {
	return Journal(w.journalRoot())
}

func (w Workspace) StateDir() string {
	if root := w.journalRoot(); root != "" {
		return root + "/.journal/plugin-data/" + JournalPlugin
	}

	return w.Dir()
}

func (w Workspace) Cache(file string) string {
	return w.StateDir() + "/" + file
}

func (w Workspace) RelocateSessions() int {
	old, now := w.Dir()+"/"+Sessions, w.SessionsDir()

	if info, err := os.Stat(old); old == now || err != nil || !info.IsDir() {
		return 0
	}

	os.MkdirAll(now, 0o775)
	moved := 0

	for _, session := range folders(old) {
		target := now + "/" + filepath.Base(session)

		if _, err := os.Lstat(target); err != nil && os.Rename(session, target) == nil {
			moved++
		}
	}

	os.Remove(old)

	return moved
}

func (w Workspace) SessionDir() string {
	return w.SessionDirNamed(w.SessionKey())
}

func (w Workspace) SessionDirNamed(key string) string {
	return w.SessionsDir() + "/" + key
}

func (w Workspace) SessionsDir() string {
	return w.StateDir() + "/" + Sessions
}

func (w Workspace) Path(file string) string {
	return w.SessionDir() + "/" + file
}

func (w Workspace) AgentPath(agent, file string) string {
	return w.SessionDir() + "/" + agents + "/" + agent + "/" + file
}

func (w Workspace) ChecklistDir() string {
	return w.ofTheWorklist().Path(Sins)
}

func (w Workspace) ofTheWorklist() Workspace {
	if !w.IsJournalDriven() {
		return w
	}

	return At(w.root, "checkout:"+w.root)
}

func (w Workspace) Checklist() string {
	return w.ChecklistDir() + "/sins.md"
}

func (w Workspace) ChecklistArchive(stamp string) string {
	return w.ChecklistDir() + "/sins-" + stamp + ".md"
}

func (w Workspace) ChecklistRelative() string {
	return w.ofTheWorklist().Relative(Sins + "/sins.md")
}

func (w Workspace) Shared(file string) string {
	return w.Dir() + "/" + file
}

func (w Workspace) Relative(file string) string {
	return w.Path(file)[len(strings.TrimRight(w.root, "/"))+1:]
}

func (w Workspace) Prune(days int) {
	if days == 0 {
		days = pruneDays
	}

	cutoff := time.Now().Add(-time.Duration(days) * 24 * time.Hour)

	for _, folder := range folders(w.SessionsDir()) {
		if folder == w.SessionDir() {
			continue
		}

		if info, err := os.Stat(folder); err != nil || info.ModTime().Before(cutoff) {
			os.RemoveAll(folder)
		}
	}
}

func folders(parent string) []string {
	matches, _ := filepath.Glob(parent + "/*")
	var found []string

	for _, match := range matches {
		if info, err := os.Stat(match); err == nil && info.IsDir() {
			found = append(found, match)
		}
	}

	return found
}
