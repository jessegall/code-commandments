package bridge

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// TestTheDotnetSDKIsFoundByItsReferencePacks holds the search for the user's .NET SDK to the folder that holds the
// framework reference packs, and a machine with none to a notice that C# is judged without them.
func TestTheDotnetSDKIsFoundByItsReferencePacks(t *testing.T) {
	t.Setenv("PATH", t.TempDir())
	t.Setenv("HOME", t.TempDir())
	t.Setenv("COMMANDMENTS_ROSLYN", "")

	empty := t.TempDir()
	t.Setenv("DOTNET_ROOT", empty)
	if root, found := Dotnet(); found && root == empty {
		t.Errorf("a folder with no reference packs is taken for the SDK")
	}
	if _, found := Dotnet(); !found && RoslynNotice() == "" {
		t.Error("a machine with no SDK is not told that C# is judged without it")
	}

	sdk := t.TempDir()
	if err := os.MkdirAll(filepath.Join(sdk, "packs", "Microsoft.NETCore.App.Ref"), 0o755); err != nil {
		t.Fatal(err)
	}
	t.Setenv("DOTNET_ROOT", sdk)
	if root, found := Dotnet(); !found || root != sdk || RoslynNotice() != "" {
		t.Errorf("the SDK at $DOTNET_ROOT is found as %q (%v), and the notice is %q", root, found, RoslynNotice())
	}
}

// TestADevelopmentBuildFetchesNoBridge holds a build with no release to saying so, naming the variable a developer
// sets instead.
func TestADevelopmentBuildFetchesNoBridge(t *testing.T) {
	released := Release
	Release = "dev"
	t.Cleanup(func() { Release = released })
	t.Setenv("COMMANDMENTS_ROSLYN", "")
	if _, err := Roslyn(); err == nil || !strings.Contains(err.Error(), "COMMANDMENTS_ROSLYN") {
		t.Errorf("a development build answered %v", err)
	}
}
