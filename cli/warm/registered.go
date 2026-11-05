package warm

import (
	"encoding/hex"
	"encoding/json"
	"crypto/sha1"
	"os"
	"path/filepath"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

// Serving is what a service says about itself where any run can read it: the project it serves and the socket it
// answers on.
type Serving struct {
	Project string `json:"project"`
	Socket  string `json:"socket"`
}

// Register says that this process serves that project, in a folder every run of the tool can read without being
// told anything. It answers what to call when the service stops.
//
// A socket is NAMED from the project's path, so a run cannot ask a service where the project is without knowing
// the project already. This is the way out of that circle: the service writes down what it serves, and a run
// looks for the record whose project holds the folder the run stands in. Nothing is told to a run and nothing is
// configured; a run that finds no record works the answer out for itself, as every run did before.
func Register(project string) (func(), error) {
	folder, err := records()
	if err != nil {
		return func() {}, err
	}

	if err := os.MkdirAll(folder, 0o755); err != nil {
		return func() {}, err
	}

	said, err := json.Marshal(Serving{Project: project, Socket: Socket(project)})
	if err != nil {
		return func() {}, err
	}

	path := recordOf(folder, project)
	if err := os.WriteFile(path, append(said, '\n'), 0o644); err != nil {
		return func() {}, err
	}

	return func() { os.Remove(path) }, nil
}

// ServingFor is the service a run in that folder belongs to: the one whose project holds the folder, and the
// innermost where several do, since a worktree of a project lies inside it. False where no service says it serves
// anything the folder is in.
//
// A record is not proof that the service is up, only that one was: whether it answers is settled by asking it,
// which costs a dial and no more.
func ServingFor(folder string) (Serving, bool) {
	resolved := real(folder)

	var holding []Serving
	for _, serving := range Registered() {
		if within(real(serving.Project), resolved) {
			holding = append(holding, serving)
		}
	}

	if len(holding) == 0 {
		return Serving{}, false
	}

	sort.Slice(holding, func(one, two int) bool { return len(holding[one].Project) > len(holding[two].Project) })

	return holding[0], true
}

// Registered is every service that has said what it serves.
func Registered() []Serving {
	folder, err := records()
	if err != nil {
		return nil
	}

	entries, err := os.ReadDir(folder)
	if err != nil {
		return nil
	}

	var said []Serving
	for _, entry := range entries {
		content, err := os.ReadFile(filepath.Join(folder, entry.Name()))
		if err != nil {
			continue
		}

		var serving Serving
		if err := json.Unmarshal(content, &serving); err != nil || serving.Project == "" || serving.Socket == "" {
			continue
		}

		said = append(said, serving)
	}

	return said
}

// Forget removes what a service said about a project, for a record whose service no longer answers.
func Forget(project string) {
	if folder, err := records(); err == nil {
		os.Remove(recordOf(folder, project))
	}
}

// records is the folder the services write what they serve in.
func records() (string, error) {
	cache, err := bundle.CacheFolder()
	if err != nil {
		return "", err
	}

	return filepath.Join(cache, "code-commandments", "serving"), nil
}

// recordOf is the file one project's service writes, named from the project as its socket is.
func recordOf(folder, project string) string {
	sum := sha1.Sum([]byte(real(project)))

	return filepath.Join(folder, hex.EncodeToString(sum[:])[:12]+".json")
}

// within says whether the folder is the project or lies inside it.
func within(project, folder string) bool {
	project = strings.TrimRight(project, string(filepath.Separator))

	return folder == project || strings.HasPrefix(folder, project+string(filepath.Separator))
}

// real is the path with its links resolved, which is the one spelling two processes agree on; the path as
// written where nothing is there to resolve.
func real(path string) string {
	if resolved, err := filepath.EvalSymlinks(path); err == nil {
		return resolved
	}

	return path
}
