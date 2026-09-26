package bridge

import (
	"crypto/sha1"
	"embed"
	"encoding/hex"
	"encoding/xml"
	"fmt"
	"io/fs"
	"net"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"slices"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// roslyn is the script every run of the C# bridge goes through and the image it runs, carried in the binary: the
// bridge itself is a prebuilt image, never built here, and .NET never runs on the host.
//
//go:embed roslyn/roslyn-in-docker.sh roslyn/IMAGE
var roslyn embed.FS

// RoslynImage is the image of the C# bridge this build runs, built once per release.
func RoslynImage() string {
	image, _ := roslyn.ReadFile("roslyn/IMAGE")

	return strings.TrimSpace(string(image))
}

// RoslynMissing is what a run without the C# bridge says, as the PHP tool's Bridge::missing says it: that C# goes
// unjudged, which image it needs, and how that image is built.
func RoslynMissing() string {
	return fmt.Sprintf("the C# bridge image %s is not available (Docker is not running, or the image is not installed), so C# is not judged; it is built once per release, never on demand: docker build -t %s %s", RoslynImage(), RoslynImage(), roslynSource())
}

// Roslyn is the command that runs the C# bridge once as the generic tree over the roots, in a memory-capped
// container of its image with the roots mounted read-only at their own paths. Without the image it fails, naming
// the image and how it is built: the bridge is never built on demand.
func Roslyn(roots ...string) ([]string, error) {
	if exec.Command("docker", "image", "inspect", RoslynImage()).Run() != nil {
		return nil, fmt.Errorf("the C# bridge image %s is not installed; it is built once per release, never on demand: docker build -t %s bridge/roslyn", RoslynImage(), RoslynImage())
	}
	script, err := roslynScript()
	if err != nil {
		return nil, err
	}

	return append(append([]string{"bash", script}, readOnly(roots)...), "--", "--tree"), nil
}

// RoslynService is the bridge the session keeps up for the project that holds every root, answering on its local
// port; false when no session keeps one up for them, and a run starts its own.
func RoslynService(roots ...string) (*Server, bool) {
	out, err := exec.Command("docker", "ps", "--filter", "label=code-commandments.roslyn=service", "--format", `{{.Names}} {{.Label "code-commandments.project"}}`).Output()
	if err != nil {
		return nil, false
	}
	for _, line := range strings.Split(strings.TrimSpace(string(out)), "\n") {
		name, project, found := strings.Cut(line, " ")
		if !found || !holdsAll(project, roots) {
			continue
		}
		connection, err := dialService(name)
		if err != nil {
			continue
		}

		return &Server{command: []string{"docker", "port", name}, input: connection, output: contract.NewReader(connection), connection: connection}, true
	}

	return nil, false
}

// dialService connects to a service container: at the port it publishes on the host's loopback, or, from a
// container beside it (scripts/dev), at its own address on the network they share.
func dialService(name string) (net.Conn, error) {
	published, err := exec.Command("docker", "port", name, "7070/tcp").Output()
	if err != nil {
		return nil, err
	}
	if connection, err := net.Dial("tcp", strings.TrimSpace(strings.Split(string(published), "\n")[0])); err == nil {
		return connection, nil
	}
	addresses, err := exec.Command("docker", "inspect", "--format", `{{range .NetworkSettings.Networks}}{{.IPAddress}} {{end}}`, name).Output()
	if err != nil {
		return nil, err
	}
	for _, address := range strings.Fields(string(addresses)) {
		if connection, err := net.DialTimeout("tcp", net.JoinHostPort(address, "7070"), 2*time.Second); err == nil {
			return connection, nil
		}
	}

	return nil, fmt.Errorf("the C# bridge service %s answers on no address this process can reach", name)
}

// holdsAll says whether every root lies in the project.
func holdsAll(project string, roots []string) bool {
	for _, root := range roots {
		absolute, err := filepath.Abs(root)
		if err != nil || (absolute != project && !strings.HasPrefix(absolute, project+string(filepath.Separator))) {
			return false
		}
	}

	return project != ""
}

// readOnly is the folders the roots are in, each mounted read-only: a root that is a file is read from its folder.
func readOnly(roots []string) []string {
	var folders []string
	for _, root := range roots {
		if strings.HasPrefix(root, "--") {
			continue
		}
		folder, err := filepath.Abs(root)
		if err != nil {
			continue
		}
		if info, err := os.Stat(folder); err == nil && !info.IsDir() {
			folder = filepath.Dir(folder)
		}
		folders = append(folders, folder)
		folders = append(folders, referencedFolders(folder)...)
	}
	var mounts []string
	for _, folder := range folders {
		if !slices.ContainsFunc(folders, func(outer string) bool { return strings.HasPrefix(folder, outer+"/") }) && !slices.Contains(mounts, folder+":ro") {
			mounts = append(mounts, folder+":ro")
		}
	}

	return mounts
}

// referencedFolders is the folder of every project the projects at the root reference, however deep: the bridge
// compiles a project with every project it references, which may stand outside the root.
func referencedFolders(root string) []string {
	pending := projectsAt(root)
	seen := map[string]bool{}
	var folders []string
	for len(pending) > 0 {
		project := pending[0]
		pending = pending[1:]
		if seen[project] {
			continue
		}
		seen[project] = true
		folders = append(folders, filepath.Dir(project))
		pending = append(pending, projectReferences(project)...)
	}

	return folders
}

// projectsAt is every project under the folder, or the one it sits inside, as the bridge finds them.
func projectsAt(folder string) []string {
	var projects []string
	filepath.WalkDir(folder, func(path string, entry fs.DirEntry, err error) error {
		if err != nil {
			return nil
		}
		if name := entry.Name(); entry.IsDir() && path != folder && (strings.HasPrefix(name, ".") || name == "bin" || name == "obj" || name == "node_modules") {
			return filepath.SkipDir
		}
		if !entry.IsDir() && strings.HasSuffix(path, ".csproj") {
			projects = append(projects, path)
		}

		return nil
	})
	above := folder
	for len(projects) == 0 && filepath.Dir(above) != above {
		above = filepath.Dir(above)
		projects, _ = filepath.Glob(filepath.Join(above, "*.csproj"))
	}

	return projects
}

// projectReferences is the project files the project references, by their full paths.
func projectReferences(project string) []string {
	raw, err := os.ReadFile(project)
	if err != nil {
		return nil
	}
	var file struct {
		Groups []struct {
			References []struct {
				Include string `xml:"Include,attr"`
			} `xml:"ProjectReference"`
		} `xml:"ItemGroup"`
	}
	if xml.Unmarshal(raw, &file) != nil {
		return nil
	}
	var referenced []string
	for _, group := range file.Groups {
		for _, reference := range group.References {
			referenced = append(referenced, filepath.Clean(filepath.Join(filepath.Dir(project), strings.ReplaceAll(reference.Include, `\`, "/"))))
		}
	}

	return referenced
}

// roslynScript is the script written out beside the image name it reads, under the cache folder, keyed by what
// the two hold.
func roslynScript() (string, error) {
	cache := os.Getenv("XDG_CACHE_HOME")
	if cache == "" {
		home, err := os.UserHomeDir()
		if err != nil {
			return "", err
		}
		cache = filepath.Join(home, ".cache")
	}
	script, _ := roslyn.ReadFile("roslyn/roslyn-in-docker.sh")
	image, _ := roslyn.ReadFile("roslyn/IMAGE")
	sum := sha1.Sum(append(append([]byte{}, script...), image...))
	folder := filepath.Join(cache, "code-commandments", "roslyn", hex.EncodeToString(sum[:])[:16])
	if err := os.MkdirAll(folder, 0o755); err != nil {
		return "", err
	}
	if err := os.WriteFile(filepath.Join(folder, "IMAGE"), image, 0o644); err != nil {
		return "", err
	}
	path := filepath.Join(folder, "roslyn-in-docker.sh")

	return path, os.WriteFile(path, script, 0o755)
}

// roslynSource is the folder the C# bridge image is built from, in the checkout this build came from.
func roslynSource() string {
	_, source, _, _ := runtime.Caller(0)

	return filepath.Join(filepath.Dir(source), "roslyn")
}
