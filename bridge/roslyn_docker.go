package bridge

import (
	"embed"
	"encoding/xml"
	"errors"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

// The C# bridge as this repository's development and CI run it: a prebuilt image in a container capped at 4 GB and
// two cores, chosen with COMMANDMENTS_ROSLYN=docker (scripts/dev sets it), so .NET never runs on a developer's host.
// A released tool never comes here: it runs the bridge's own executable (roslyn.go).

// roslyn is the script a development run of the C# bridge goes through and the image it runs.
//
//go:embed roslyn/roslyn-in-docker.sh roslyn/IMAGE
var roslyn embed.FS

// RoslynImage is the image development and CI run the C# bridge in, built from bridge/roslyn and never published.
func RoslynImage() string {
	image, _ := roslyn.ReadFile("roslyn/IMAGE")

	return strings.TrimSpace(string(image))
}

// roslynInDocker is the command that runs the C# bridge once over the roots in a capped container of its image, the
// roots mounted read-only at their own paths. Without the image it fails, naming how to build it.
func roslynInDocker(roots []string) ([]string, error) {
	if exec.Command("docker", "image", "inspect", RoslynImage()).Run() != nil {
		return nil, errors.New("the C# bridge image " + RoslynImage() + " is not built: docker build -t " + RoslynImage() + " bridge/roslyn")
	}
	script, err := roslynScript()
	if err != nil {
		return nil, err
	}

	return append(append([]string{"bash", script}, readOnly(roots)...), "--"), nil
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

// projectReferences is the project files the project references, by their full paths: every ProjectReference at
// any depth, in the MSBuild namespace an older project file declares or in none.
func projectReferences(project string) []string {
	file, err := os.Open(project)
	if err != nil {
		return nil
	}
	defer file.Close()
	var referenced []string
	decoder := xml.NewDecoder(file)
	for {
		token, err := decoder.Token()
		if err != nil {
			return referenced
		}
		element, starts := token.(xml.StartElement)
		if !starts || element.Name.Local != "ProjectReference" {
			continue
		}
		for _, attribute := range element.Attr {
			if attribute.Name.Local == "Include" {
				referenced = append(referenced, filepath.Clean(filepath.Join(filepath.Dir(project), strings.ReplaceAll(attribute.Value, `\`, "/"))))
			}
		}
	}
}

// roslynLauncher is the script that starts the bridge's container, beside the image name it reads.
var roslynLauncher = bundle.Embedded("roslyn", roslyn, "roslyn")

// roslynScript is the launcher, written out under the cache folder.
func roslynScript() (string, error) {
	folder, err := roslynLauncher.Folder()

	return filepath.Join(folder, "roslyn-in-docker.sh"), err
}
