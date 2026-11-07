// Package parity proves the Go binary answers every invocation exactly as the PHP tool does: the same stdout, the same stderr, the same exit code.
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

const CasesDir = "cli/parity/testdata/cases"

func GoldenFile(c Case) string {
	return filepath.Join("cli", "parity", "testdata", "golden", c.Name+".golden")
}

// Case is one invocation of the tool.
type Case struct {
	Name string `json:"name"`

	Args []string `json:"args"`

	Project string `json:"project,omitempty"`

	Setup []string `json:"setup,omitempty"`

	Env map[string]string `json:"env,omitempty"`

	Stdin string `json:"stdin,omitempty"`

	Digest []string `json:"digest,omitempty"`

	Pending string `json:"pending,omitempty"`
}

// Result is what one run printed and answered, and what it wrote into its project.
type Result struct {
	Exit   int
	Stdout string
	Stderr string
	Files  string
}

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

var caches = []string{".commandments/cross-file.json"}

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

func guarded(tool string) string {
	return "#!/bin/sh\necho 'parity: the real " + tool + " is out of reach' >&2\nexit 97\n"
}

const recordedZone = "CET-1CEST,M3.5.0,M10.5.0/3"

func environment(c Case, home, path string) []string {
	env := map[string]string{
		"PATH":           path,
		"HOME":           home,
		"COLUMNS":        "80",
		"TERM":           "dumb",
		"LANG":           "C.UTF-8",
		"TZ":             recordedZone,
		"XDG_CACHE_HOME": cache(),
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

func cache() string {
	if folder := os.Getenv("XDG_CACHE_HOME"); folder != "" {
		return folder
	}

	home, _ := os.UserHomeDir()

	return filepath.Join(home, ".cache")
}

var stamp = regexp.MustCompile(`\d{4}-\d{2}-\d{2}[ _T]\d{2}:?\d{2}(:?\d{2})?Z?`)

var versionLine = regexp.MustCompile(`(?m)^code-commandments \S+$`)

var versionRow = regexp.MustCompile("(code-commandments\x1b\\[0m  )\\S+")

var hashedIdentity = regexp.MustCompile(`"[0-9a-f]{40}":`)

var unixStamp = regexp.MustCompile(`(?m)^(marked-at|started-at): \d{9,}$`)

var tookLong = regexp.MustCompile(`\b\d+(\.\d+)?(ms|s|µs)\b`)

var heldBytes = regexp.MustCompile(`\b(heap|resident|withBridges)=\d+MB`)

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
	text = tookLong.ReplaceAllString(text, "<took>")
	text = heldBytes.ReplaceAllString(text, "${1}=<held>")

	return stamp.ReplaceAllString(text, "<time>")
}

func placeholder(path, project string) string {
	if path == project {
		return "<project>"
	}

	return "<package>"
}

func Golden(result Result) string {
	return "exit: " + strconv.Itoa(result.Exit) + "\n--- stdout\n" + result.Stdout + "\n--- stderr\n" + result.Stderr + "\n--- files\n" + result.Files
}

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
