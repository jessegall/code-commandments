package bridge

import (
	"crypto/sha1"
	"embed"
	"encoding/hex"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

//go:embed roslyn/roslyn-in-docker.sh roslyn/IMAGE
var roslyn embed.FS

const (
	roslynSources    = "bridge/roslyn"
	roslynRepository = "ghcr.io/jessegall/code-commandments-roslyn"
)

func RoslynImage() string {
	image, _ := roslyn.ReadFile("roslyn/IMAGE")

	return strings.TrimSpace(string(image))
}

func RoslynSourcesImage(root string) (string, error) {
	sources := filepath.Join(root, roslynSources)

	names, err := filepath.Glob(filepath.Join(sources, "*.cs"))
	if err != nil {
		return "", err
	}

	names = append(names, "Dockerfile", "Roslyn.Bridge.csproj")
	for at := range names {
		names[at] = filepath.Base(names[at])
	}

	sort.Strings(names)

	digest := sha1.New()
	for _, name := range names {
		source, err := os.ReadFile(filepath.Join(sources, name))
		if err != nil {
			return "", err
		}

		digest.Write([]byte(name + "\n"))
		digest.Write(source)
	}

	return roslynRepository + ":" + hex.EncodeToString(digest.Sum(nil))[:16], nil
}

func RoslynInDocker(roots []string) ([]string, error) {
	if exec.Command("docker", "image", "inspect", RoslynImage()).Run() != nil {
		return nil, RoslynUnavailable{"its development image " + RoslynImage() + " is not built: scripts/roslyn-image"}
	}
	script, err := roslynScript()
	if err != nil {
		return nil, err
	}

	return append(append([]string{"bash", script}, readOnly(roots)...), "--"), nil
}

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
		folders = append(folders, CSharpProjects.Folders([]string{folder})...)
	}
	var mounts []string
	for _, folder := range folders {
		if !slices.ContainsFunc(folders, func(outer string) bool { return strings.HasPrefix(folder, outer+"/") }) && !slices.Contains(mounts, folder+":ro") {
			mounts = append(mounts, folder+":ro")
		}
	}

	return mounts
}

var roslynLauncher = bundle.Embedded("roslyn", roslyn, "roslyn")

func roslynScript() (string, error) {
	folder, err := roslynLauncher.Folder()

	return filepath.Join(folder, "roslyn-in-docker.sh"), err
}
