package main

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"strings"
	"testing"
)

// pluginFolder lays out what the journal clones for the plugin: its fetch script, and the bin folder it fetches into.
func pluginFolder(t *testing.T) (root string) {
	t.Helper()
	for _, tool := range []string{"sh", "curl"} {
		if _, err := exec.LookPath(tool); err != nil {
			t.Skipf("%s is not on PATH", tool)
		}
	}
	root = t.TempDir()
	copyFile(t, filepath.Join("..", "..", ".journal-plugin", "fetch"), filepath.Join(root, ".journal-plugin", "fetch"))
	if err := os.MkdirAll(filepath.Join(root, "bin"), 0o755); err != nil {
		t.Fatal(err)
	}

	return root
}

// fetchRun runs the plugin's fetch script with the version pinned, fetching releases from base, in the environment
// given on top of this one's.
func fetchRun(t *testing.T, root, version, base string, env ...string) (string, error) {
	t.Helper()
	command := exec.Command("sh", filepath.Join(root, ".journal-plugin", "fetch"))
	command.Env = append(append(os.Environ(), "COMMANDMENTS_RELEASE="+version, "COMMANDMENTS_RELEASES="+base), env...)
	out, err := command.CombinedOutput()

	return string(out), err
}

// posing is the PATH under which uname names the system and machine given, as it does on a host this one is not.
func posing(t *testing.T, system, machine string) string {
	t.Helper()
	stubs := t.TempDir()
	uname := "#!/bin/sh\ncase \"$1\" in\n    -s) echo '" + system + "' ;;\n    -m) echo '" + machine + "' ;;\n    *) exit 1 ;;\nesac\n"
	if err := os.WriteFile(filepath.Join(stubs, "uname"), []byte(uname), 0o755); err != nil {
		t.Fatal(err)
	}

	return "PATH=" + stubs + string(os.PathListSeparator) + os.Getenv("PATH")
}

// fetched is every file the fetch left in the plugin's bin folder.
func fetched(t *testing.T, root string) []string {
	t.Helper()
	files, _ := filepath.Glob(filepath.Join(root, "bin", "*"))

	return files
}

// TestThePluginFetchesThePinnedReleaseCheckedAgainstItsSums holds the plugin's setup to its one job: the pinned
// release's binary for this platform, checked against SHA256SUMS, written executable where every command runs it.
func TestThePluginFetchesThePinnedReleaseCheckedAgainstItsSums(t *testing.T) {
	root := pluginFolder(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf(fakeBinary))
	if out, err := fetchRun(t, root, "v9.9.9", server.URL); err != nil {
		t.Fatalf("the fetch said %q (%v)", out, err)
	}
	binary := filepath.Join(root, "bin", "commandments-release")
	if out, err := exec.Command(binary, "judge", "src").Output(); err != nil || strings.TrimSpace(string(out)) != "ran judge src" {
		t.Errorf("the fetched binary said %q (%v)", out, err)
	}
}

func TestThePluginRefusesABinaryWhoseSumDiffers(t *testing.T) {
	root := pluginFolder(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf("another binary"))
	out, err := fetchRun(t, root, "v9.9.9", server.URL)
	if err == nil || !strings.Contains(out, "does not match its SHA256SUMS") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
	if files := fetched(t, root); len(files) > 0 {
		t.Errorf("the refused binary was kept: %v", files)
	}
}

func TestThePluginRefusesAReleaseItCannotFetch(t *testing.T) {
	root := pluginFolder(t)
	server := release(t, "v9.9.9", fakeBinary, sumOf(fakeBinary))
	out, err := fetchRun(t, root, "v9.9.8", server.URL)
	if err == nil || !strings.Contains(out, "could not fetch") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
	if files := fetched(t, root); len(files) > 0 {
		t.Errorf("a binary was written: %v", files)
	}
}

func TestThePluginRefusesAPinThatIsNoRelease(t *testing.T) {
	root := pluginFolder(t)
	out, err := fetchRun(t, root, "main", "http://127.0.0.1:1")
	if err == nil || !strings.Contains(out, "names no release (main)") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
}

// TestThePluginFetchesTheWindowsBinaryUnderMSYS2 holds the fetch on Windows, where the journal runs under MSYS2: the
// release's .exe for the machine Windows names, checked against SHA256SUMS, written as the
// bin/commandments-release.exe MSYS2 runs for bin/commandments-release.
func TestThePluginFetchesTheWindowsBinaryUnderMSYS2(t *testing.T) {
	for _, host := range []struct {
		system string
		env    []string
		name   string
	}{
		{"MINGW64_NT-10.0-19045", []string{"PROCESSOR_ARCHITECTURE=AMD64", "PROCESSOR_ARCHITEW6432="}, "commandments-windows-amd64.exe"},
		{"MSYS_NT-10.0-22631", []string{"PROCESSOR_ARCHITECTURE=AMD64", "PROCESSOR_ARCHITEW6432=ARM64"}, "commandments-windows-arm64.exe"},
		{"MINGW64_NT-10.0-26100", []string{"PROCESSOR_ARCHITECTURE=ARM64", "PROCESSOR_ARCHITEW6432="}, "commandments-windows-arm64.exe"},
	} {
		t.Run(host.system, func(t *testing.T) {
			root := pluginFolder(t)
			server := releaseOf(t, "v9.9.9", host.name, fakeBinary, sumOf(fakeBinary))
			if out, err := fetchRun(t, root, "v9.9.9", server.URL, append(host.env, posing(t, host.system, "x86_64"))...); err != nil {
				t.Fatalf("the fetch said %q (%v)", out, err)
			}
			binary := filepath.Join(root, "bin", "commandments-release.exe")
			if files := fetched(t, root); len(files) != 1 || files[0] != binary {
				t.Fatalf("the fetch left %v, not %s", files, binary)
			}
			if content, _ := os.ReadFile(binary); string(content) != fakeBinary {
				t.Errorf("the fetched binary is %q", content)
			}
		})
	}
}

func TestThePluginRefusesAWindowsBinaryWhoseSumDiffers(t *testing.T) {
	root := pluginFolder(t)
	server := releaseOf(t, "v9.9.9", "commandments-windows-amd64.exe", fakeBinary, sumOf("another binary"))
	out, err := fetchRun(t, root, "v9.9.9", server.URL, "PROCESSOR_ARCHITECTURE=AMD64", "PROCESSOR_ARCHITEW6432=", posing(t, "MINGW64_NT-10.0-19045", "x86_64"))
	if err == nil || !strings.Contains(out, "does not match its SHA256SUMS") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
	if files := fetched(t, root); len(files) > 0 {
		t.Errorf("the refused binary was kept: %v", files)
	}
}

// TestThePluginRefusesCygwin holds the fetch to fail closed under Cygwin, which hands the Windows binary paths it
// cannot open, so no plugin is installed whose hooks cannot find the project.
func TestThePluginRefusesCygwin(t *testing.T) {
	root := pluginFolder(t)
	server := releaseOf(t, "v9.9.9", "commandments-windows-amd64.exe", fakeBinary, sumOf(fakeBinary))
	out, err := fetchRun(t, root, "v9.9.9", server.URL, "PROCESSOR_ARCHITECTURE=AMD64", posing(t, "CYGWIN_NT-10.0-22631", "x86_64"))
	if err == nil || !strings.Contains(out, "run the journal under WSL or MSYS2") {
		t.Errorf("the fetch said %q (%v)", out, err)
	}
	if files := fetched(t, root); len(files) > 0 {
		t.Errorf("a binary was written: %v", files)
	}
}

// TestThePluginFetchesTheLinuxBinaryUnderWSL holds the fetch on Windows under WSL, which is Linux to it.
func TestThePluginFetchesTheLinuxBinaryUnderWSL(t *testing.T) {
	root := pluginFolder(t)
	server := releaseOf(t, "v9.9.9", "commandments-linux-arm64", fakeBinary, sumOf(fakeBinary))
	if out, err := fetchRun(t, root, "v9.9.9", server.URL, "PROCESSOR_ARCHITECTURE=AMD64", posing(t, "Linux", "aarch64")); err != nil {
		t.Fatalf("the fetch said %q (%v)", out, err)
	}
	binary := filepath.Join(root, "bin", "commandments-release")
	if files := fetched(t, root); len(files) != 1 || files[0] != binary {
		t.Errorf("the fetch left %v, not %s", files, binary)
	}
}

// manifest is the part of the plugin's plugin.json that says what an install needs and runs.
type manifest struct {
	Requires map[string]any                  `json:"requires"`
	Env      map[string]string               `json:"env"`
	Setup    []struct{ Run string }          `json:"setup"`
	Refuse   string                          `json:"refuse"`
	On       map[string]string               `json:"on"`
	Install  string                          `json:"installed"`
	Services map[string]struct{ Run string } `json:"services"`
}

// commands is every command the journal runs for the plugin.
func (m manifest) commands() []string {
	commands := []string{m.Refuse, m.Install}
	for _, step := range m.Setup {
		commands = append(commands, step.Run)
	}
	for _, command := range m.On {
		commands = append(commands, command)
	}
	for _, service := range m.Services {
		commands = append(commands, service.Run)
	}

	return commands
}

// TestThePluginNeedsOnlyTheBinaryItFetches holds the plugin to the release rule: an install needs no PHP, composer,
// Go or Docker, fetches the release it pins, and every command it runs is that fetched binary. A command names it
// bin/commandments-release on every platform: the journal runs each through /bin/sh and has no command per OS, and
// MSYS2, which it runs under on Windows beside WSL, runs the bin/commandments-release.exe the fetch writes there.
func TestThePluginNeedsOnlyTheBinaryItFetches(t *testing.T) {
	content, err := os.ReadFile(filepath.Join("..", "..", ".journal-plugin", "plugin.json"))
	if err != nil {
		t.Fatal(err)
	}
	var plugin manifest
	if err := json.Unmarshal(content, &plugin); err != nil {
		t.Fatal(err)
	}

	if pinned := plugin.Env["COMMANDMENTS_RELEASE"]; !regexp.MustCompile(`^v\d+\.\d+\.\d+$`).MatchString(pinned) {
		t.Errorf("the plugin pins %q, no release", pinned)
	}
	if _, ok := plugin.Requires["curl"]; !ok {
		t.Error("the plugin does not require the curl its fetch runs")
	}
	for _, tool := range []string{"php", "composer", "go", "docker"} {
		if _, ok := plugin.Requires[tool]; ok {
			t.Errorf("the plugin requires %s", tool)
		}
	}
	runsTheBinary := regexp.MustCompile(`^(bin/commandments-release |sh \.journal-plugin/fetch$|journal check create .*\.journal/plugins/code-commandments/bin/commandments-release )`)
	forbidden := regexp.MustCompile(`\b(composer|php|go|docker)\b|scripts/(build|dev)|bin/commandments( |$)|\.exe\b`)
	for _, command := range plugin.commands() {
		if !runsTheBinary.MatchString(command) || forbidden.MatchString(command) {
			t.Errorf("the plugin runs %q, not the binary it fetches", command)
		}
	}
}
