package bridge

import (
	"crypto/sha1"
	"crypto/sha256"
	"encoding/hex"
	"fmt"
	"net"
	"net/url"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/contract"
)

// Release is the version this build was released as, stamped through main by the release build; a local build is dev,
// and has no release to fetch a C# bridge from.
var Release = "dev"

// releases is where a release's files are published, one folder per version.
const releases = "https://github.com/jessegall/code-commandments/releases/download"

// roslynVariable names the C# bridge to run instead of this release's own: an executable, or `docker` for the capped
// image development and CI run it in.
const roslynVariable = "COMMANDMENTS_ROSLYN"

// dotnetVariable is how the bridge is told which .NET installation's reference packs to compile against.
const dotnetVariable = "CODE_COMMANDMENTS_DOTNET_ROOT"

// RoslynUnavailable is a run with no C# bridge to read C# with: C# goes unjudged, and everything else is judged.
type RoslynUnavailable struct {
	reason string
}

// Error says why, and what is and is not judged.
func (e RoslynUnavailable) Error() string {
	return "the C# bridge is not available (" + e.reason + "), so C# is not judged; everything else is"
}

func (RoslynUnavailable) unavailable() {}

// Roslyn is the command that runs the C# bridge over the roots: the one $COMMANDMENTS_ROSLYN names, else this release's
// own executable, fetched once into the cache beside the tool and checked against the release's SHA256SUMS, and never
// built here. The .NET SDK found on this machine is named to it, for the reference packs a project's frameworks
// resolve against.
func Roslyn(roots ...string) ([]string, error) {
	switch named := os.Getenv(roslynVariable); named {
	case "docker":
		return RoslynInDocker(roots)
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

// RoslynExecutable is this release's C# bridge for this platform, in the cache folder the tool's own binary is kept
// in, and whether this call fetched it: fetched on its first use and checked against the release's SHA256SUMS, a file
// whose sum does not match never written.
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

// fetchChecked fetches the release's file into place, once its bytes match the sum SHA256SUMS lists for it.
func fetchChecked(name, destination string) error {
	base := strings.TrimRight(releases, "/")
	if named := os.Getenv("COMMANDMENTS_RELEASES"); named != "" {
		base = strings.TrimRight(named, "/")
	}
	base += "/" + url.PathEscape(Release)
	sums, err := fetch(base + "/SHA256SUMS")
	if err != nil {
		return err
	}
	want, listed := sumOf(string(sums), name)
	if !listed {
		return fmt.Errorf("the %s release lists no %s", Release, name)
	}
	content, err := fetch(base + "/" + name)
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

// sumOf is the sum SHA256SUMS lists for the file.
func sumOf(sums, name string) (string, bool) {
	for _, line := range strings.Split(sums, "\n") {
		fields := strings.Fields(line)
		if len(fields) == 2 && strings.TrimPrefix(fields[1], "*") == name {
			return fields[0], true
		}
	}

	return "", false
}

// fetch is the body at the address, which must answer with success, through the curl every platform the tool ships
// for has: linking Go's own HTTP client and TLS would grow the tool by a fifth for this one download.
func fetch(address string) ([]byte, error) {
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

// binaryCache is the folder a release's binaries are kept in, the tool's and its bridges', as the shim keeps them.
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

// RoslynNotice is what a run says once when the bridge it runs will find no framework reference packs: that C# is
// judged with the types that resolve without them, and what to install. Nothing for the development image, which
// carries its own.
func RoslynNotice() string {
	if _, found := Dotnet(); found || os.Getenv(roslynVariable) == "docker" {
		return ""
	}

	return "no .NET SDK is installed here, so C#'s framework types do not resolve and C# is judged with the types that resolve without them; install the .NET SDK to judge it whole"
}

// nameDotnet tells the bridge which .NET installation's reference packs to compile against, when this machine has one.
func nameDotnet() {
	if root, found := Dotnet(); found {
		os.Setenv(dotnetVariable, root)
	}
}

// Dotnet is the .NET SDK installed on this machine, by the folder whose packs hold the framework reference
// assemblies: the one $DOTNET_ROOT names, the one the dotnet on the PATH belongs to, or one in a folder .NET's
// installers use. A machine with none still judges C#, with the types that resolve without the frameworks.
func Dotnet() (string, bool) {
	var candidates []string
	if root := os.Getenv("DOTNET_ROOT"); root != "" {
		candidates = append(candidates, root)
	}
	if dotnet, err := exec.LookPath("dotnet"); err == nil {
		if real, err := filepath.EvalSymlinks(dotnet); err == nil {
			candidates = append(candidates, filepath.Dir(real))
		}
	}
	candidates = append(candidates, dotnetFolders()...)
	for _, root := range candidates {
		if info, err := os.Stat(filepath.Join(root, "packs", "Microsoft.NETCore.App.Ref")); err == nil && info.IsDir() {
			return root, true
		}
	}

	return "", false
}

// dotnetFolders are the folders .NET's installers put it in on this platform, and the user's own.
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

// RoslynSocket is where the C# bridge a session keeps up for the project answers: a socket named for the project,
// since a project's own path may be longer than a socket's may be, in /tmp, whose path is short wherever $TMPDIR points.
func RoslynSocket(project string) string {
	sum := sha1.Sum([]byte(project))
	folder := "/tmp"
	if runtime.GOOS == "windows" {
		folder = os.TempDir()
	}

	return filepath.Join(folder, "code-commandments-roslyn-"+hex.EncodeToString(sum[:])[:12]+".sock")
}

// RoslynService is the bridge the session keeps up for the project, reached at its socket; false when no session
// keeps one up for it, and a run starts its own.
func RoslynService(project string) (*Server, bool) {
	connection, err := net.DialTimeout("unix", RoslynSocket(project), 2*time.Second)
	if err != nil {
		return nil, false
	}

	return &Server{command: []string{"roslyn-serve", project}, input: connection, output: contract.NewReader(connection), connection: connection}, true
}
