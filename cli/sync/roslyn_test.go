package sync

import (
	"bytes"
	"crypto/sha256"
	"encoding/hex"
	"net/http"
	"net/http/httptest"
	"runtime"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/config"
)

// fakeRelease serves a release of the version holding a C# bridge for this platform, with the sum SHA256SUMS lists
// for it (a wrong one when told), and answers what was asked of it.
func fakeRelease(t *testing.T, sumMatches bool) *[]string {
	t.Helper()
	name := "roslyn-bridge-" + runtime.GOOS + "-" + runtime.GOARCH
	if runtime.GOOS == "windows" {
		name += ".exe"
	}
	content := []byte("#!/bin/sh\n")
	sum := sha256.Sum256(content)
	if !sumMatches {
		sum = sha256.Sum256([]byte("another"))
	}
	var asked []string
	server := httptest.NewServer(http.HandlerFunc(func(writer http.ResponseWriter, request *http.Request) {
		asked = append(asked, request.URL.Path)
		switch request.URL.Path {
		case "/v9.9.9/SHA256SUMS":
			writer.Write([]byte(hex.EncodeToString(sum[:]) + "  " + name + "\n"))
		case "/v9.9.9/" + name:
			writer.Write(content)
		default:
			http.NotFound(writer, request)
		}
	}))
	t.Cleanup(server.Close)
	t.Setenv("COMMANDMENTS_RELEASES", server.URL)
	t.Setenv("COMMANDMENTS_ROSLYN", "")
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	released := bridge.Release
	bridge.Release = "v9.9.9"
	t.Cleanup(func() { bridge.Release = released })

	return &asked
}

func project(t *testing.T, files ...string) string {
	t.Helper()
	root := t.TempDir()
	for _, file := range files {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, file)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, file), []byte("class A {}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func TestSyncFetchesTheCSharpBridgeForAProjectThatWritesCSharp(t *testing.T) {
	asked := fakeRelease(t, true)
	var out bytes.Buffer
	fetchRoslyn(project(t, "Shop/Cart.cs"), config.Config{}, cli.Console{Out: &out, Err: &out})
	if len(*asked) != 2 || !strings.Contains(out.String(), "fetched the C# bridge for v9.9.9") {
		t.Errorf("the release was asked %v and sync said %q", *asked, out.String())
	}
	out.Reset()
	fetchRoslyn(project(t, "Shop/Cart.cs"), config.Config{}, cli.Console{Out: &out, Err: &out})
	if len(*asked) != 2 || strings.Contains(out.String(), "fetched") {
		t.Errorf("a bridge already fetched was fetched again: %v, %q", *asked, out.String())
	}
}

func TestSyncFetchesNothingForAProjectWithoutCSharp(t *testing.T) {
	asked := fakeRelease(t, true)
	fetchRoslyn(project(t, "src/Cart.php"), config.Config{}, cli.Console{Out: &bytes.Buffer{}, Err: &bytes.Buffer{}})
	if len(*asked) != 0 {
		t.Errorf("a project with no C# fetched %v", *asked)
	}
}

func TestABridgeWhoseSumDoesNotMatchIsNeverWritten(t *testing.T) {
	fakeRelease(t, false)
	var out bytes.Buffer
	fetchRoslyn(project(t, "Shop/Cart.cs"), config.Config{}, cli.Console{Out: &out, Err: &out})
	if !strings.Contains(out.String(), "does not match its SHA256SUMS") || !strings.Contains(out.String(), "C# is not judged; everything else is") {
		t.Errorf("sync said %q", out.String())
	}
	if _, fetched, err := bridge.RoslynExecutable(); fetched || err == nil {
		t.Error("a bridge whose sum does not match was written")
	}
}

func TestSyncRemovesTheBridgeThePhpToolBuiltOnTheHost(t *testing.T) {
	t.Setenv("HOME", t.TempDir())
	builds := hostRoslynBuilds()
	if err := os.MkdirAll(filepath.Join(builds, "v1"), 0o755); err != nil {
		t.Fatal(err)
	}
	var out bytes.Buffer
	removeHostRoslynBuilds(cli.Console{Out: &out, Err: &out})
	if _, err := os.Stat(builds); err == nil || !strings.Contains(out.String(), "removed the C# bridge") {
		t.Errorf("the host builds remain (%v) and sync said %q", err, out.String())
	}
	out.Reset()
	removeHostRoslynBuilds(cli.Console{Out: &out, Err: &out})
	if out.Len() > 0 {
		t.Errorf("with nothing left to remove sync still said %q", out.String())
	}
}
