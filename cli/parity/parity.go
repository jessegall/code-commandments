// Package parity proves the Go binary answers every invocation exactly as the PHP tool does: the same
// stdout, the same stderr, the same exit code. A case is an invocation in a prepared project; the recorder
// runs the PHP tool over each case into a golden, and the test runs the Go binary over the same case and
// diffs. What legitimately differs between two runs (the temporary project's path, the version) is
// normalised here, in one place, never per case.
package parity

import (
	"bytes"
	"crypto/sha256"
	"encoding/json"
	"errors"
	"fmt"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"regexp"
	"slices"
	"sort"
	"strconv"
	"strings"
)

// CasesDir is where the cases live, relative to the repository: one file per command family, so work on
// two commands never edits the same file.
const CasesDir = "cli/parity/testdata/cases"

// GoldenFile is where a case's recorded result lives, relative to the repository.
func GoldenFile(c Case) string {
	return filepath.Join("cli", "parity", "testdata", "golden", c.Name+".golden")
}

// Case is one invocation of the tool.
type Case struct {
	// Name names the golden file.
	Name string `json:"name"`

	// Args are the arguments after the program name.
	Args []string `json:"args"`

	// Project is a folder, relative to the repository, copied in as the project the tool runs in; empty
	// runs in an empty folder.
	Project string `json:"project,omitempty"`

	// Setup are shell commands run in the project before the tool, e.g. to make it a git repository;
	// $PARITY_PACKAGE names the package's own folder, to copy what the tool sees from an install (the stub
	// testdata/installed.json), and
	// $PARITY_TOOL the tool under test, to leave behind what an earlier run of it would.
	Setup []string `json:"setup,omitempty"`

	// Env is added to the fixed environment every case runs under.
	Env map[string]string `json:"env,omitempty"`

	// Stdin is fed to the tool.
	Stdin string `json:"stdin,omitempty"`

	// Digest are folders, under the project, whose files a golden records by their hash rather than their
	// contents: what a run publishes by the hundred is held just as exactly, in a golden a reviewer can read.
	Digest []string `json:"digest,omitempty"`

	// Pending names what the Go side still waits on. A pending case is skipped while it differs, and fails
	// the moment it matches, so the mark cannot outlive the gap.
	Pending string `json:"pending,omitempty"`
}

// HasComposer says whether the case's project has a composer.json: the folder it copies holds one, or its setup
// writes one.
func (c Case) HasComposer(repo string) bool {
	if c.Project != "" {
		if _, err := os.Stat(filepath.Join(repo, c.Project, "composer.json")); err == nil {
			return true
		}
	}

	for _, line := range c.Setup {
		if strings.Contains(line, "> composer.json") {
			return true
		}
	}

	return false
}

// Result is what one run printed and answered, and what it wrote into its project.
type Result struct {
	Exit   int
	Stdout string
	Stderr string
	// Files are the project files the run created, changed or deleted, each with its contents.
	Files string
}

// Cases reads every case from every file in dir. A name two files share is refused, since both would
// write one golden.
func Cases(dir string) ([]Case, error) {
	files, err := filepath.Glob(filepath.Join(dir, "*.json"))
	if err != nil {
		return nil, err
	}

	var cases []Case
	named := map[string]string{}

	for _, file := range files {
		raw, err := os.ReadFile(file)
		if err != nil {
			return nil, err
		}

		var some []Case

		if err := json.Unmarshal(raw, &some); err != nil {
			return nil, fmt.Errorf("%s: %w", file, err)
		}

		for _, c := range some {
			if other, taken := named[c.Name]; taken {
				return nil, fmt.Errorf("case %q is named in both %s and %s", c.Name, other, file)
			}

			named[c.Name] = file
		}

		cases = append(cases, some...)
	}

	return cases, nil
}

// Run prepares the case's project under scratch and runs command (the tool, then its fixed arguments)
// there, answering what it printed with everything run-specific normalised away.
func Run(c Case, repo, scratch string, command ...string) (Result, error) {
	project := filepath.Join(scratch, "project")
	home := filepath.Join(scratch, "home")

	if err := prepare(c, repo, project, home); err != nil {
		return Result{}, err
	}

	guard := filepath.Join(scratch, "guard")
	if err := os.MkdirAll(guard, 0o755); err != nil {
		return Result{}, err
	}

	for _, tool := range []string{"gh", "claude"} {
		if err := os.WriteFile(filepath.Join(guard, tool), []byte(guarded(tool)), 0o755); err != nil {
			return Result{}, err
		}
	}

	env := environment(c, home, strings.Join([]string{filepath.Join(project, "bin"), guard, os.Getenv("PATH")}, string(os.PathListSeparator)))

	for _, line := range c.Setup {
		setup := exec.Command("sh", "-c", line)
		setup.Dir, setup.Env = project, append(env, "PARITY_PACKAGE="+repo, "PARITY_TOOL="+command[0])

		if out, err := setup.CombinedOutput(); err != nil {
			return Result{}, fmt.Errorf("setup %q: %w\n%s", line, err, out)
		}
	}

	before := snapshot(project)

	var stdout, stderr bytes.Buffer
	run := exec.Command(command[0], append(command[1:], c.Args...)...)
	run.Dir, run.Env = project, env
	run.Stdin, run.Stdout, run.Stderr = strings.NewReader(c.Stdin), &stdout, &stderr

	result := Result{}
	err := run.Run()

	var exited *exec.ExitError

	switch {
	case errors.As(err, &exited):
		result.Exit = exited.ExitCode()
	case err != nil:
		return Result{}, err
	}

	result.Stdout = normalise(stdout.String(), repo, project)
	result.Stderr = normalise(stderr.String(), repo, project)
	result.Files = normalise(written(before, digested(snapshot(project), c.Digest)), repo, project)

	return result, nil
}

// caches are files one implementation keeps for itself, meaningless to the other: PHP's record of which of
// its own classes read beyond one file.
var caches = []string{".commandments/cross-file.json"}

// snapshot is every file under the project, less git's own and the caches, by its path under the project.
func snapshot(project string) map[string]string {
	files := map[string]string{}

	filepath.WalkDir(project, func(path string, entry fs.DirEntry, err error) error {
		switch {
		case err != nil:
			return nil
		case entry.IsDir() && entry.Name() == ".git":
			return filepath.SkipDir
		case entry.Type().IsRegular():
			contents, _ := os.ReadFile(path)
			relative, _ := filepath.Rel(project, path)

			if !slices.Contains(caches, relative) {
				files[relative] = string(contents)
			}
		}

		return nil
	})

	return files
}

// digested is the snapshot with every file under a digest folder standing as its hash.
func digested(files map[string]string, folders []string) map[string]string {
	for path, contents := range files {
		for _, folder := range folders {
			if strings.HasPrefix(path, strings.TrimSuffix(folder, "/")+"/") {
				files[path] = fmt.Sprintf("sha256 %x", sha256.Sum256([]byte(contents)))
			}
		}
	}

	return files
}

// written lists what changed between two snapshots, in path order: each created or changed file with its
// contents, each deleted file by name. A file whose only change is the moment stamped in it did not change: a run
// in the same second as its setup rewrites the same stamp, one a second later a new one, and which it is says
// nothing about the tool.
func written(before, after map[string]string) string {
	var paths []string

	for path, contents := range after {
		if was, existed := before[path]; !existed || unixStamp.ReplaceAllString(was, "") != unixStamp.ReplaceAllString(contents, "") {
			paths = append(paths, path)
		}
	}

	for path := range before {
		if _, kept := after[path]; !kept {
			paths = append(paths, path)
		}
	}

	sort.Strings(paths)

	var out strings.Builder

	for _, path := range paths {
		contents, kept := after[path]

		switch _, existed := before[path]; {
		case !kept:
			out.WriteString("=== deleted " + path + "\n")
		case !existed:
			out.WriteString("=== created " + path + "\n" + contents + "\n")
		default:
			out.WriteString("=== changed " + path + "\n" + contents + "\n")
		}
	}

	return out.String()
}

func prepare(c Case, repo, project, home string) error {
	if err := os.MkdirAll(home, 0o755); err != nil {
		return err
	}

	if c.Project == "" {
		return os.MkdirAll(project, 0o755)
	}

	return os.CopyFS(project, os.DirFS(filepath.Join(repo, c.Project)))
}

// guarded stands in for a tool whose real run reaches the outside world, gh filing an issue or claude a
// billed request, so no case can reach it; a case that needs one puts its own fake in its project's bin/,
// which comes first.
func guarded(tool string) string {
	return "#!/bin/sh\necho 'parity: the real " + tool + " is out of reach' >&2\nexit 97\n"
}

// recordedZone is the zone the PHP tool's goldens were recorded in, Europe/Amsterdam, spelled as a POSIX rule
// so a machine without zone data reads it too: a setup's `touch -t` means the same moment everywhere.
const recordedZone = "CET-1CEST,M3.5.0,M10.5.0/3"

// environment is the fixed world every case runs in, so nothing of the machine running it leaks in.
func environment(c Case, home, path string) []string {
	env := map[string]string{
		"PATH":           path,
		"HOME":           home,
		"COLUMNS":        "80",
		"TERM":           "dumb",
		"LANG":           "C.UTF-8",
		"TZ":             recordedZone,
		"XDG_CACHE_HOME": cache(),
		// The PHP tool coloured its output into a pipe too, so the goldens hold colour: the Go tool is asked for it.
		"CLICOLOR_FORCE": "1",
	}

	for key, value := range c.Env {
		env[key] = value
	}

	var list []string

	for key, value := range env {
		list = append(list, key+"="+value)
	}

	return list
}

// cache is the machine's own cache folder, kept across cases so a bridge's built environment is built once.
func cache() string {
	if folder := os.Getenv("XDG_CACHE_HOME"); folder != "" {
		return folder
	}

	home, _ := os.UserHomeDir()

	return filepath.Join(home, ".cache")
}

// stamp is a date and time a run writes: a task's log line, an archived checklist's name.
var stamp = regexp.MustCompile(`\d{4}-\d{2}-\d{2}[ _]\d{2}:?\d{2}(:?\d{2})?`)

var versionLine = regexp.MustCompile(`(?m)^code-commandments \S+$`)

// versionRow is the version as config's overview prints it, after the tool's bold name.
var versionRow = regexp.MustCompile("(code-commandments\x1b\\[0m  )\\S+")

// hashedIdentity is a key hashed from what includes the project's own path, such as a sin's identity.
var hashedIdentity = regexp.MustCompile(`"[0-9a-f]{40}":`)

// unixStamp is a state value that holds the moment it was written, in unix seconds.
var unixStamp = regexp.MustCompile(`(?m)^(marked-at|started-at): \d{9,}$`)

// normalise replaces what differs between two runs of the same case: where the project and the package
// live, and the installed version.
func normalise(text, repo, project string) string {
	for _, path := range []string{project, repo} {
		if real, err := filepath.EvalSymlinks(path); err == nil && real != path {
			text = strings.ReplaceAll(text, real, placeholder(path, project))
		}

		text = strings.ReplaceAll(text, path, placeholder(path, project))
	}

	text = versionLine.ReplaceAllString(text, "code-commandments <version>")
	text = versionRow.ReplaceAllString(text, "${1}<version>")

	text = unixStamp.ReplaceAllString(text, "$1: <time>")
	text = hashedIdentity.ReplaceAllString(text, `"<id>":`)

	return stamp.ReplaceAllString(text, "<time>")
}

func placeholder(path, project string) string {
	if path == project {
		return "<project>"
	}

	return "<package>"
}

// Golden writes a result as the text a reviewer reads in a diff.
func Golden(result Result) string {
	return "exit: " + strconv.Itoa(result.Exit) + "\n--- stdout\n" + result.Stdout + "\n--- stderr\n" + result.Stderr + "\n--- files\n" + result.Files
}

// ReadGolden reads a result back from its golden text.
func ReadGolden(text string) (Result, error) {
	head, rest, found := strings.Cut(text, "\n--- stdout\n")
	stdout, streams, split := strings.Cut(rest, "\n--- stderr\n")
	stderr, files, listed := strings.Cut(streams, "\n--- files\n")
	code, isCode := strings.CutPrefix(head, "exit: ")
	exit, err := strconv.Atoi(code)

	if !found || !split || !listed || !isCode || err != nil {
		return Result{}, errors.New("not a golden: want `exit: N`, `--- stdout`, `--- stderr`, `--- files`")
	}

	return Result{Exit: exit, Stdout: stdout, Stderr: stderr, Files: files}, nil
}
