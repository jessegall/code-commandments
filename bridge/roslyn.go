package bridge

import (
	"crypto/sha1"
	"embed"
	"encoding/hex"
	"fmt"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
)

// roslyn is the C# bridge's sources, carried in the binary and built where they are written out.
//
//go:embed roslyn/*.cs roslyn/Roslyn.Bridge.csproj
var roslyn embed.FS

// Roslyn is the command that runs the C# bridge as the generic tree. The first run for a version of its sources
// builds it with dotnet under the cache folder.
func Roslyn() ([]string, error) {
	folder, err := roslynFolder()
	if err != nil {
		return nil, err
	}
	command := []string{"dotnet", filepath.Join(folder, "out", "roslyn-bridge.dll"), "--tree"}
	if _, err := os.Stat(filepath.Join(folder, "ready")); err == nil {
		return command, nil
	}

	return command, buildRoslyn(folder)
}

// roslynFolder is where this version of the sources lives: the cache folder, keyed by what the sources hold.
func roslynFolder() (string, error) {
	cache := os.Getenv("XDG_CACHE_HOME")
	if cache == "" {
		home, err := os.UserHomeDir()
		if err != nil {
			return "", err
		}
		cache = filepath.Join(home, ".cache")
	}
	hash := sha1.New()
	err := fs.WalkDir(roslyn, "roslyn", func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		source, err := roslyn.ReadFile(path)
		hash.Write([]byte(path))
		hash.Write(source)

		return err
	})

	return filepath.Join(cache, "code-commandments", "roslyn-tree", hex.EncodeToString(hash.Sum(nil))[:16]), err
}

// buildRoslyn writes the sources into the folder and builds them, marked ready only once the build is whole.
func buildRoslyn(folder string) error {
	dotnet, err := exec.LookPath("dotnet")
	if err != nil {
		return fmt.Errorf("the C# bridge needs dotnet on the PATH: %w", err)
	}
	sources := filepath.Join(folder, "src")
	if err := os.MkdirAll(sources, 0o755); err != nil {
		return err
	}
	err = fs.WalkDir(roslyn, "roslyn", func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		source, err := roslyn.ReadFile(path)
		if err != nil {
			return err
		}

		return os.WriteFile(filepath.Join(sources, filepath.Base(path)), source, 0o644)
	})
	if err != nil {
		return err
	}
	step := []string{dotnet, "build", sources, "-c", "Release", "-o", filepath.Join(folder, "out"), "-nologo", "-v", "q"}
	if out, err := exec.Command(step[0], step[1:]...).CombinedOutput(); err != nil {
		return Failed(step, err, string(out))
	}

	return os.WriteFile(filepath.Join(folder, "ready"), nil, 0o644)
}
