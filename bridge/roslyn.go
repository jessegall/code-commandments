package bridge

import (
	"crypto/sha256"
	"encoding/hex"
	"errors"
	"fmt"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

var Release = "dev"

const releases = "https://github.com/jessegall/code-commandments/releases/download"

const roslynVariable = "COMMANDMENTS_ROSLYN"

const dotnetVariable = "CODE_COMMANDMENTS_DOTNET_ROOT"

// RoslynUnavailable is a run with no C# bridge to read C# with: C# goes unjudged, and everything else is judged.
type RoslynUnavailable struct {
	reason string
}

func (e RoslynUnavailable) Error() string {
	return "the C# bridge is not available (" + e.reason + "), so C# is not judged; everything else is"
}

func (RoslynUnavailable) unavailable() {}

// RoslynFailed is a C# bridge that started and then failed: C# goes unjudged, and everything else is judged.
type RoslynFailed struct {
	reason string
}

func RoslynFailure(err error) error {
	var failed *BridgeFailed
	if !errors.As(err, &failed) {
		return err
	}

	return RoslynFailed{failed.Reason()}
}

func (e RoslynFailed) Error() string {
	return "the C# bridge failed: " + e.reason + ", so C# is not judged; everything else is"
}

func (RoslynFailed) unavailable() {}

func Roslyn(roots ...string) ([]string, error) {
	trees, err := bundle.TreesFolder("csharp")
	if err != nil {
		return roslynFor(roots)
	}

	command, err := roslynFor(roots, trees)
	if err != nil {
		return nil, err
	}

	return append(command, "--cache="+trees), nil
}

func roslynFor(roots []string, written ...string) ([]string, error) {
	switch named := os.Getenv(roslynVariable); named {
	case "docker":
		return RoslynInDocker(roots, written...)
	case "":
		executable, _, err := RoslynExecutable()
		if err != nil {
			return nil, err
		}
		nameDotnet()

		return []string{executable}, nil
	default:
		nameDotnet()

		return []string{named}, nil
	}
}

// FetchRoslyn fetches the C# bridge this release ships, unless a bridge of one's own is named, and says what it fetched.
func FetchRoslyn() (string, error) {
	if os.Getenv(roslynVariable) != "" {
		return "", nil
	}
	executable, fetched, err := RoslynExecutable()
	if err != nil || !fetched {
		return "", err
	}

	return "↓ fetched the C# bridge for " + Release + " (" + executable + ").", nil
}

func RoslynExecutable() (executable string, fetched bool, err error) {
	if Release == "dev" {
		return "", false, RoslynUnavailable{"a development build has no release to fetch it from; name one in $" + roslynVariable}
	}
	name := "roslyn-bridge-" + runtime.GOOS + "-" + runtime.GOARCH
	if runtime.GOOS == "windows" {
		name += ".exe"
	}
	cache, err := binaryCache()
	if err != nil {
		return "", false, RoslynUnavailable{err.Error()}
	}
	executable = filepath.Join(cache, Release, name)
	if _, err := os.Stat(executable); err == nil {
		return executable, false, nil
	}
	if err := fetchChecked(name, executable); err != nil {
		return "", false, RoslynUnavailable{err.Error()}
	}

	return executable, true, nil
}

func fetchChecked(name, destination string) error {
	base := strings.TrimRight(releases, "/")
	if named := os.Getenv("COMMANDMENTS_RELEASES"); named != "" {
		base = strings.TrimRight(named, "/")
	}
	base += "/" + url.PathEscape(Release)
	sums, err := fetch(base, "SHA256SUMS")
	if err != nil {
		return err
	}
	want, listed := sumOf(string(sums), name)
	if !listed {
		return fmt.Errorf("the %s release lists no %s", Release, name)
	}
	content, err := fetch(base, name)
	if err != nil {
		return err
	}
	if sum := sha256.Sum256(content); hex.EncodeToString(sum[:]) != want {
		return fmt.Errorf("the %s fetched for %s does not match its SHA256SUMS, so it is not run", name, Release)
	}
	if err := os.MkdirAll(filepath.Dir(destination), 0o755); err != nil {
		return err
	}
	draft := fmt.Sprintf("%s.%d", destination, os.Getpid())
	if err := os.WriteFile(draft, content, 0o755); err != nil {
		return err
	}

	return os.Rename(draft, destination)
}

func sumOf(sums, name string) (string, bool) {
	for _, line := range strings.Split(sums, "\n") {
		fields := strings.Fields(line)
		if len(fields) == 2 && strings.TrimPrefix(fields[1], "*") == name {
			return fields[0], true
		}
	}

	return "", false
}

func fetch(base, name string) ([]byte, error) {
	body, err := curled(base + "/" + name)
	if err == nil || os.Getenv("COMMANDMENTS_RELEASES") != "" {
		return body, err
	}

	if asset, downloaded := fromTheGitHubCLI(name); downloaded {
		return asset, nil
	}

	return nil, err
}

func fromTheGitHubCLI(name string) ([]byte, bool) {
	gh, err := exec.LookPath("gh")
	if err != nil {
		return nil, false
	}

	body, err := exec.Command(gh, "release", "download", Release, "--repo", "jessegall/code-commandments", "--pattern", name, "--output", "-").Output()

	return body, err == nil
}

func curled(address string) ([]byte, error) {
	curl, err := exec.LookPath("curl")
	if err != nil {
		return nil, fmt.Errorf("fetching it needs curl on the PATH")
	}
	var failure strings.Builder
	command := exec.Command(curl, "--fail", "--silent", "--show-error", "--location", "--max-time", "300", address)
	command.Stderr = &failure
	body, err := command.Output()
	if err != nil {
		return nil, fmt.Errorf("%s: %s", address, strings.TrimSpace(failure.String()))
	}

	return body, nil
}

func binaryCache() (string, error) {
	cache := os.Getenv("XDG_CACHE_HOME")
	if cache == "" && runtime.GOOS == "windows" {
		cache = os.Getenv("LOCALAPPDATA")
	}
	if cache == "" {
		home, err := os.UserHomeDir()
		if err != nil {
			return "", err
		}
		cache = filepath.Join(home, ".cache")
	}

	return filepath.Join(cache, "code-commandments", "bin"), nil
}

func RoslynNotice() string {
	if _, found := Dotnet(); found || os.Getenv(roslynVariable) == "docker" {
		return ""
	}

	return "no .NET SDK is installed here, so C#'s framework types do not resolve and C# is judged with the types that resolve without them; install the .NET SDK to judge it whole"
}

func nameDotnet() {
	if root, found := Dotnet(); found {
		os.Setenv(dotnetVariable, root)
	}
}

func Dotnet() (string, bool) {
	var candidates []string
	if root := os.Getenv("DOTNET_ROOT"); root != "" {
		candidates = append(candidates, root)
	}
	candidates = append(candidates, listedDotnetRoots()...)
	candidates = append(candidates, dotnetFolders()...)
	for _, root := range candidates {
		if info, err := os.Stat(filepath.Join(root, "packs", "Microsoft.NETCore.App.Ref")); err == nil && info.IsDir() {
			return root, true
		}
	}

	return "", false
}

func listedDotnetRoots() []string {
	dotnet, err := exec.LookPath("dotnet")
	if err != nil {
		return nil
	}
	listing, err := exec.Command(dotnet, "--list-sdks").Output()
	if err != nil {
		return nil
	}
	var roots []string
	for _, line := range strings.Split(string(listing), "\n") {
		opening, closing := strings.LastIndex(line, "["), strings.LastIndex(line, "]")
		if opening < 0 || closing < opening {
			continue
		}
		roots = append(roots, filepath.Dir(line[opening+1:closing]))
	}

	return roots
}

func dotnetFolders() []string {
	home, _ := os.UserHomeDir()
	switch runtime.GOOS {
	case "windows":
		return []string{filepath.Join(os.Getenv("ProgramFiles"), "dotnet"), filepath.Join(home, ".dotnet")}
	case "darwin":
		return []string{"/usr/local/share/dotnet", "/opt/homebrew/share/dotnet", filepath.Join(home, ".dotnet")}
	default:
		return []string{"/usr/share/dotnet", "/usr/lib/dotnet", "/usr/local/share/dotnet", "/opt/dotnet", filepath.Join(home, ".dotnet")}
	}
}
