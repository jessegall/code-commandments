package bridge

import (
	"os"
	"path/filepath"
	"runtime"
	"strings"
	"testing"
)

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

func TestADotnetWrapperScriptOnThePathResolvesToTheRootItsSDKListNames(t *testing.T) {
	if runtime.GOOS == "windows" {
		t.Skip("a wrapper script is a shell script")
	}
	t.Setenv("HOME", t.TempDir())
	t.Setenv("DOTNET_ROOT", "")
	t.Setenv("COMMANDMENTS_ROSLYN", "")

	cellar := t.TempDir()
	root := filepath.Join(cellar, "libexec")
	if err := os.MkdirAll(filepath.Join(root, "packs", "Microsoft.NETCore.App.Ref"), 0o755); err != nil {
		t.Fatal(err)
	}
	wrapper := filepath.Join(cellar, "bin", "dotnet")
	if err := os.MkdirAll(filepath.Dir(wrapper), 0o755); err != nil {
		t.Fatal(err)
	}
	script := "#!/bin/sh\necho '10.0.102 [" + filepath.Join(root, "sdk") + "]'\n"
	if err := os.WriteFile(wrapper, []byte(script), 0o755); err != nil {
		t.Fatal(err)
	}
	path := t.TempDir()
	if err := os.Symlink(wrapper, filepath.Join(path, "dotnet")); err != nil {
		t.Fatal(err)
	}
	t.Setenv("PATH", path)

	if found, ok := Dotnet(); !ok || found != root || RoslynNotice() != "" {
		t.Errorf("the dotnet wrapper on the PATH resolves to %q (%v), not %q, and the notice is %q", found, ok, root, RoslynNotice())
	}
}

func TestThisMachinesListedSDKLeavesNoNotice(t *testing.T) {
	if len(listedDotnetRoots()) == 0 {
		t.Skip("no dotnet on the PATH lists an SDK here")
	}
	t.Setenv("DOTNET_ROOT", "")

	if notice := RoslynNotice(); notice != "" {
		t.Errorf("the SDK %v lists is not found: %s", listedDotnetRoots(), notice)
	}
}

func TestADevelopmentBuildFetchesNoBridge(t *testing.T) {
	released := Release
	Release = "dev"
	t.Cleanup(func() { Release = released })
	t.Setenv("COMMANDMENTS_ROSLYN", "")
	if _, err := Roslyn(); err == nil || !strings.Contains(err.Error(), "COMMANDMENTS_ROSLYN") {
		t.Errorf("a development build answered %v", err)
	}
}
