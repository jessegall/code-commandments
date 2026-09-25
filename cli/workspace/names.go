package workspace

import (
	"strings"

	"github.com/jessegall/code-commandments/cli/state"
)

const (
	namesFile = "sessions/.names"
	separator = "\t"
)

// NamesLegend is what the names file says about itself.
var NamesLegend = &state.Legend{
	About: "The names this project has given its sessions (`commandments session name \"<name>\"`). A " +
		"session folder is a hash by default; a named one IS the name, and this says which id " +
		"it belongs to so an agent can still find its own folder from the id it was given.",
	List: "one `name<TAB>session-id` per line. The NAME is the folder under `sessions/`, and the " +
		"id is what the harness calls the session it holds.",
	Safe: "a named session becomes findable by its hash again — nothing inside it is lost",
}

// SessionNames are the names a project gave its sessions: each name is a session's folder, and says which
// id it belongs to.
type SessionNames struct {
	file state.File
}

// Pair is one named session.
type Pair struct {
	Name string
	ID   string
}

// NamesIn is the names kept in the shared folder dir.
func NamesIn(dir string) SessionNames {
	return SessionNames{state.At(dir+"/"+namesFile, NamesLegend)}
}

// NameOf is the name the session was given, and whether it has one.
func (n SessionNames) NameOf(sessionID string) (string, bool) {
	for _, pair := range n.All() {
		if pair.ID == sessionID {
			return pair.Name, true
		}
	}

	return "", false
}

// IDOf is the session a name belongs to, and whether it is taken.
func (n SessionNames) IDOf(name string) (string, bool) {
	for _, pair := range n.All() {
		if pair.Name == name {
			return pair.ID, true
		}
	}

	return "", false
}

// Name gives the session this name, dropping any name it had; false when another session holds it.
func (n SessionNames) Name(sessionID, name string) (bool, error) {
	if taken, isTaken := n.IDOf(name); isTaken && taken != sessionID {
		return false, nil
	}

	var kept []Pair

	for _, pair := range n.All() {
		if pair.ID != sessionID {
			kept = append(kept, pair)
		}
	}

	return true, n.save(append(kept, Pair{name, sessionID}))
}

// Forget drops a name; false when there is no such name.
func (n SessionNames) Forget(name string) (bool, error) {
	var kept []Pair
	found := false

	for _, pair := range n.All() {
		if pair.Name == name {
			found = true

			continue
		}

		kept = append(kept, pair)
	}

	if !found {
		return false, nil
	}

	return true, n.save(kept)
}

// All are the named sessions, in the order they are kept; a name listed twice keeps its last id.
func (n SessionNames) All() []Pair {
	var pairs []Pair
	at := map[string]int{}

	for _, line := range n.file.Read().Items() {
		name, id, _ := strings.Cut(line, separator)

		if name == "" || id == "" {
			continue
		}

		if i, seen := at[name]; seen {
			pairs[i].ID = id

			continue
		}

		at[name] = len(pairs)
		pairs = append(pairs, Pair{name, id})
	}

	return pairs
}

func (n SessionNames) save(pairs []Pair) error {
	lines := make([]string, len(pairs))

	for i, pair := range pairs {
		lines[i] = pair.Name + separator + pair.ID
	}

	return n.file.Write(n.file.Read().WithItems(lines))
}
