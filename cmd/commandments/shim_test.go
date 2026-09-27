package main

import (
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/http/httptest"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"testing"
)

// fakeBinary stands in for a release binary: it says it ran, and with what.
const fakeBinary = "#!/bin/sh\necho \"ran $*\"\n"

// installed lays out a project that composer installed the package into at the version: composer itself installs a
// project with no dependencies, writing its own autoloader, then the shim takes its place under vendor/ and
// installed.php names the version.
func installed(t *testing.T, version string) (shim string) {
	t.Helper()
	for _, tool := range []string{"php", "composer"} {
		if _, err := exec.LookPath(tool); err != nil {
			t.Skipf("%s is not on PATH", tool)
		}
	}
	project := t.TempDir()
	write(t, filepath.Join(project, "composer.json"), `{"name": "acme/shop"}`)
	install := exec.Command("composer", "install", "--quiet", "--no-interaction")
	install.Dir, install.Env = project, append(os.Environ(), "COMPOSER_HOME="+t.TempDir())
	if out, err := install.CombinedOutput(); err != nil {
		t.Fatalf("composer install: %v\n%s", err, out)
	}
	vendor := filepath.Join(project, "vendor")
	copyFile(t, filepath.Join("..", "..", "bin", "commandments"), filepath.Join(vendor, "jessegall", "code-commandments", "bin", "commandments"))
	write(t, filepath.Join(vendor, "composer", "installed.php"), `<?php return ['root' => ['name' => 'acme/shop', 'pretty_version' => 'dev-main', 'version' => 'dev-main', 'reference' => null, 'type' => 'project', 'install_path' => __DIR__ . '/../../', 'aliases' => [], 'dev' => true], 'versions' => ['jessegall/code-commandments' => ['pretty_version' => '`+version+`', 'version' => '`+strings.TrimPrefix(version, "v")+`.0', 'reference' => null, 'type' => 'library', 'install_path' => __DIR__ . '/../jessegall/code-commandments', 'aliases' => [], 'dev_requirement' => false]]];`)

	return filepath.Join(vendor, "jessegall", "code-commandments", "bin", "commandments")
}

// release serves the version's release: the binary for this platform, and SHA256SUMS listing sum for it.
func release(t *testing.T, version, binary, sum string) *httptest.Server {
	t.Helper()
	name := "commandments-" + runtime.GOOS + "-" + runtime.GOARCH
	server := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/" + version + "/SHA256SUMS":
			w.Write([]byte(sum + "  " + name + "\n"))
		case "/" + version + "/" + name:
			w.Write([]byte(binary))
		default:
			http.NotFound(w, r)
		}
	}))
	t.Cleanup(server.Close)

	return server
}

func sumOf(content string) string {
	sum := sha256.Sum256([]byte(content))

	return hex.EncodeToString(sum[:])
}

// shimRun runs the shim with the arguments, fetching releases from base into the cache.
func shimRun(t *testing.T, shim, base, cache string, args ...string) (string, error) {
	t.Helper()
	command := exec.Command("php", append([]string{shim}, args...)...)
	command.Env = append(os.Environ(), "COMMANDMENTS_RELEASES="+base, "XDG_CACHE_HOME="+cache, "COMMANDMENTS_GO_BINARY=")
	out, err := command.CombinedOutput()

	return string(out), err
}

// TestTheShimFetchesTheInstalledReleaseChecksItAndRunsIt holds the composer install's one job: the release binary of
// the installed version for this platform, fetched once, checked against SHA256SUMS, then run with the arguments —
// and run again from the cache with the release out of reach.
func TestTheShimFetchesTheInstalledReleaseChecksItAndRunsIt(t *testing.T) {
	shim := installed(t, "v9.9.9")
	server := release(t, "v9.9.9", fakeBinary, sumOf(fakeBinary))
	cache := t.TempDir()
	if out, err := shimRun(t, shim, server.URL, cache, "judge", "src"); err != nil || strings.TrimSpace(out) != "ran judge src" {
		t.Fatalf("the shim said %q (%v)", out, err)
	}
	server.Close()
	if out, err := shimRun(t, shim, server.URL, cache, "info", "array-bag"); err != nil || strings.TrimSpace(out) != "ran info array-bag" {
		t.Errorf("the cached binary did not run: %q (%v)", out, err)
	}
}

func TestTheShimRefusesABinaryWhoseSumDiffers(t *testing.T) {
	shim := installed(t, "v9.9.9")
	server := release(t, "v9.9.9", fakeBinary, sumOf("another binary"))
	cache := t.TempDir()
	out, err := shimRun(t, shim, server.URL, cache, "judge")
	if err == nil || !strings.Contains(out, "does not match its SHA256SUMS") {
		t.Errorf("the shim said %q (%v)", out, err)
	}
	if written, _ := filepath.Glob(filepath.Join(cache, "code-commandments", "bin", "*", "*")); len(written) > 0 {
		t.Errorf("the refused binary was kept: %v", written)
	}
}

func TestTheShimNamesADevelopmentVersionAsNoRelease(t *testing.T) {
	shim := installed(t, "dev-main")
	out, err := shimRun(t, shim, "http://127.0.0.1:1", t.TempDir(), "judge")
	if err == nil || !strings.Contains(out, "dev-main is no release") {
		t.Errorf("the shim said %q (%v)", out, err)
	}
}

// checkout lays out the package's own tree — the shim beside a dev-built binary, with scripts/dev — as a journal
// plugin or a composer source install clones it.
func checkout(t *testing.T) (shim string) {
	t.Helper()
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("php is not on PATH")
	}
	root := t.TempDir()
	write(t, filepath.Join(root, "scripts", "dev"), "#!/bin/sh\n")
	copyFile(t, filepath.Join("..", "..", "bin", "commandments"), filepath.Join(root, "bin", "commandments"))
	write(t, filepath.Join(root, "bin", "commandments-go"), "#!/bin/sh\necho dev-built\n")
	os.Chmod(filepath.Join(root, "bin", "commandments-go"), 0o755)

	return filepath.Join(root, "bin", "commandments")
}

// pinnedRun runs the shim with COMMANDMENTS_RELEASE pinning the version.
func pinnedRun(t *testing.T, shim, version, base, cache string, args ...string) (string, error) {
	t.Helper()
	command := exec.Command("php", append([]string{shim}, args...)...)
	command.Env = append(os.Environ(), "COMMANDMENTS_RELEASE="+version, "COMMANDMENTS_RELEASES="+base, "XDG_CACHE_HOME="+cache, "COMMANDMENTS_GO_BINARY=")
	out, err := command.CombinedOutput()

	return string(out), err
}

// TestAPinnedReleaseRunsAheadOfTheDevBuiltBinary holds a checkout that pins a release to that release: fetched,
// checked against SHA256SUMS and run, though the tree holds scripts/dev and a dev-built binary.
func TestAPinnedReleaseRunsAheadOfTheDevBuiltBinary(t *testing.T) {
	shim := checkout(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf(fakeBinary))
	if out, err := pinnedRun(t, shim, "v9.9.9", server.URL, t.TempDir(), "judge", "src"); err != nil || strings.TrimSpace(out) != "ran judge src" {
		t.Errorf("the shim said %q (%v)", out, err)
	}
}

// TestAPinnedReleaseWhoseSumDiffersNeverFallsBackToTheDevBinary holds the pin closed: a binary whose sum does not
// match is refused and nothing else runs in its place.
func TestAPinnedReleaseWhoseSumDiffersNeverFallsBackToTheDevBinary(t *testing.T) {
	shim := checkout(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf("another binary"))
	out, err := pinnedRun(t, shim, "v9.9.9", server.URL, t.TempDir(), "judge")
	if err == nil || !strings.Contains(out, "does not match its SHA256SUMS") || strings.Contains(out, "dev-built") {
		t.Errorf("the shim said %q (%v)", out, err)
	}
}

func TestAPinThatIsNoReleaseIsRefused(t *testing.T) {
	shim := checkout(t)
	out, err := pinnedRun(t, shim, "main", "http://127.0.0.1:1", t.TempDir(), "judge")
	if err == nil || !strings.Contains(out, "main is no release") || strings.Contains(out, "dev-built") {
		t.Errorf("the shim said %q (%v)", out, err)
	}
}

func copyFile(t *testing.T, from, to string) {
	t.Helper()
	content, err := os.ReadFile(from)
	if err != nil {
		t.Fatal(err)
	}
	write(t, to, string(content))
}

func write(t *testing.T, path, content string) {
	t.Helper()
	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(content), 0o644); err != nil {
		t.Fatal(err)
	}
}
