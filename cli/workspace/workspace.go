// Package workspace is where the tool keeps what it writes in a project: the shared `.commandments`
// folder (config, custom rules) and one folder per agent session for its checklist and state. The session
// folder is the id's short hash, or the name the session was given.
package workspace

import (
	"crypto/sha1"
	"encoding/hex"
	"os"
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

	// JournalPlugin is the name the tool is installed under when the agent journal runs its hooks.
	JournalPlugin = "code-commandments"

	// Sessions is the folder that holds one folder per session.
	Sessions = "sessions"

	agents = "agents"

	// Custom is the folder of a project's own commandments.
	Custom = "custom"

	// Sins is the session folder a checklist is written to.
	Sins = "sins"

	// Library is where the published skills live, for every agent.
	Library = ".agents/skills"

	// DefaultSession is the folder of a run no session is behind.
	DefaultSession = "default"

	keyLength = 5
	pruneDays = 7
)

// repositories remembers the shared folder each root's repository keeps, since finding it can ask git.
var repositories sync.Map

// Workspace is one project's folders, as seen by one session.
type Workspace struct {
	root      string
	sessionID string
}

// At is the workspace at root for the session; with no session given, the one the harness names in
// CLAUDE_CODE_SESSION_ID.
func At(root, sessionID string) Workspace {
	if sessionID == "" {
		sessionID = os.Getenv("CLAUDE_CODE_SESSION_ID")
	}

	return Workspace{root, sessionID}
}

// OfSession is the workspace of the project a session works in: CLAUDE_PROJECT_DIR when the harness
// states it, else fallback, lifted to the main checkout so every worktree shares one.
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

// ProjectRoot is the project a command run from cwd works on: the repository cwd is in, unless the harness
// names a project in CLAUDE_PROJECT_DIR that the repository is no checkout of.
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

// Config is the path of the project config under dir.
func Config(dir string) string {
	return At(dir, "").Shared("config.php")
}

// CustomDir is the folder of the project's own commandments under dir.
func CustomDir(dir string) string {
	return At(dir, "").Shared(Custom)
}

// CustomFiles are the PHP files of the project's own commandments, sorted.
func CustomFiles(dir string) []string {
	files := source.FilesIn(CustomDir(dir), "php", source.Excluded{})
	sort.Strings(files)

	return files
}

// Root is the project folder.
func (w Workspace) Root() string {
	return w.root
}

// SessionID is the session this workspace is seen by, empty for none.
func (w Workspace) SessionID() string {
	return w.sessionID
}

// LibraryDir is where the project's published skills live.
func (w Workspace) LibraryDir() string {
	return w.root + "/" + Library
}

// SessionKey is the session's folder name: its given name, else its id's hash, else the default.
func (w Workspace) SessionKey() string {
	if w.sessionID == "" {
		return DefaultSession
	}

	if name, named := w.Names().NameOf(w.sessionID); named {
		return name
	}

	return KeyFor(w.sessionID)
}

// Names are the names the project gave its sessions, kept once per repository so every worktree shares
// them.
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

// KeyFor is a session id's folder name: the start of its hash.
func KeyFor(sessionID string) string {
	sum := sha1.Sum([]byte(sessionID))

	return hex.EncodeToString(sum[:])[:keyLength]
}

// Dir is the project's `.commandments` folder.
func (w Workspace) Dir() string {
	return w.root + "/" + dir
}

// IsJournalDriven says whether the agent journal runs the tool's hooks here, installed as its plugin.
func (w Workspace) IsJournalDriven() bool {
	info, err := os.Stat(w.root + "/.journal/plugins/" + JournalPlugin + "/.journal-plugin/plugin.json")

	return err == nil && info.Mode().IsRegular()
}

// StateDir is where the session folders live: the journal plugin's data folder when it runs the hooks,
// else `.commandments`.
func (w Workspace) StateDir() string {
	if w.IsJournalDriven() {
		return w.root + "/.journal/plugin-data/" + JournalPlugin
	}

	return w.Dir()
}

// Cache is a file kept beside the session folders.
func (w Workspace) Cache(file string) string {
	return w.StateDir() + "/" + file
}

// RelocateSessions moves session folders from `.commandments` to the state folder when the journal took
// over the hooks, never over one already there, and answers how many moved.
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

// SessionDir is this session's folder.
func (w Workspace) SessionDir() string {
	return w.SessionDirNamed(w.SessionKey())
}

// SessionDirNamed is the folder of the session with this key.
func (w Workspace) SessionDirNamed(key string) string {
	return w.SessionsDir() + "/" + key
}

// SessionsDir holds every session's folder.
func (w Workspace) SessionsDir() string {
	return w.StateDir() + "/" + Sessions
}

// Path is a file in this session's folder.
func (w Workspace) Path(file string) string {
	return w.SessionDir() + "/" + file
}

// AgentPath is a file an agent keeps in this session's folder.
func (w Workspace) AgentPath(agent, file string) string {
	return w.SessionDir() + "/" + agents + "/" + agent + "/" + file
}

// ChecklistDir is where this session's checklists are written.
func (w Workspace) ChecklistDir() string {
	return w.Path(Sins)
}

// Checklist is this session's checklist.
func (w Workspace) Checklist() string {
	return w.ChecklistDir() + "/sins.md"
}

// ChecklistArchive is an earlier checklist, kept under its time stamp.
func (w Workspace) ChecklistArchive(stamp string) string {
	return w.ChecklistDir() + "/sins-" + stamp + ".md"
}

// ChecklistRelative is the checklist's path relative to the project.
func (w Workspace) ChecklistRelative() string {
	return w.Relative(Sins + "/sins.md")
}

// Shared is a file in `.commandments`, shared by every session.
func (w Workspace) Shared(file string) string {
	return w.Dir() + "/" + file
}

// Relative is a session file's path relative to the project.
func (w Workspace) Relative(file string) string {
	return w.Path(file)[len(strings.TrimRight(w.root, "/"))+1:]
}

// Prune deletes every other session's folder untouched for days.
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

// folders are the non-hidden folders directly under parent, as PHP's glob lists them: sorted.
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
