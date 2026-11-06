package warm

import (
	"crypto/sha1"
	"encoding/hex"
	"encoding/json"
	"os"
	"path/filepath"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

// Serving is what a service says about itself where any run can read it: the project it serves and the socket it answers on.
type Serving struct {
	Project string `json:"project"`
	Socket  string `json:"socket"`
}

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

func Forget(project string) {
	if folder, err := records(); err == nil {
		os.Remove(recordOf(folder, project))
	}
}

func records() (string, error) {
	cache, err := bundle.CacheFolder()
	if err != nil {
		return "", err
	}

	return filepath.Join(cache, "code-commandments", "serving"), nil
}

func recordOf(folder, project string) string {
	sum := sha1.Sum([]byte(real(project)))

	return filepath.Join(folder, hex.EncodeToString(sum[:])[:12]+".json")
}

func within(project, folder string) bool {
	project = strings.TrimRight(project, string(filepath.Separator))

	return folder == project || strings.HasPrefix(folder, project+string(filepath.Separator))
}

func real(path string) string {
	if resolved, err := filepath.EvalSymlinks(path); err == nil {
		return resolved
	}

	return path
}
