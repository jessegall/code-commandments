package vue

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/walk"
)

const probeMarker = "__CcNo_"

const maxOracleType = 200

// TypeQuery is a component and the locals whose types no reading of its source could tell.
type TypeQuery struct {
	Component Component
	Names     []string
}

// TypeOracle is a real type checker: it resolves, in one pass, the types of every query's names, by component path.
type TypeOracle interface {
	ResolveAll(queries []TypeQuery) map[string]map[string]string
}

// ProcessRunner runs a program in a folder and hands back what it printed, its output then its errors.
type ProcessRunner interface {
	Run(binary string, arguments []string, cwd string) string
}

// ShellRunner runs the program as a process of its own.
type ShellRunner struct{}

func (ShellRunner) Run(binary string, arguments []string, cwd string) string {
	var stdout, stderr bytes.Buffer
	command := exec.Command(binary, arguments...)
	command.Dir = cwd
	command.Stdout, command.Stderr = &stdout, &stderr
	_ = command.Run()

	return stdout.String() + stderr.String()
}

// VueTscOracle asks a project's own vue-tsc: each query's component is copied beside itself with a probe that assigns every asked local to a type nothing is assignable to, and the checker's complaint names the local's type.
type VueTscOracle struct {
	root   string
	runner ProcessRunner
}

func NewVueTscOracle(root string, runner ProcessRunner) *VueTscOracle {
	return &VueTscOracle{root: root, runner: runner}
}

func LocateVueTsc(path string, runner ProcessRunner) (*VueTscOracle, bool) {
	from := filepath.Dir(path)
	if walk.IsFolder(path) {
		from = strings.TrimRight(path, "/")
	}
	for dir := from; ; dir = filepath.Dir(dir) {
		if VueTscAvailable(dir) {
			return NewVueTscOracle(dir, runner), true
		}
		if filepath.Dir(dir) == dir {
			return nil, false
		}
	}
}

func VueTscAvailable(root string) bool {
	info, err := os.Stat(vueTscBinary(root))

	return err == nil && info.Mode().IsRegular()
}

func vueTscBinary(root string) string {
	return strings.TrimRight(root, "/") + "/node_modules/.bin/vue-tsc"
}

func (o *VueTscOracle) ResolveAll(queries []TypeQuery) map[string]map[string]string {
	paths, probes := o.writeProbes(queries)
	if len(probes) == 0 {
		return map[string]map[string]string{}
	}
	output := func() string {
		defer func() {
			for _, probe := range probes {
				os.Remove(probe)
			}
		}()

		return o.runner.Run(vueTscBinary(o.root), o.arguments(), o.root)
	}()
	resolved := map[string]map[string]string{}
	for index, path := range paths {
		resolved[path] = CheckerTypes(linesNaming(output, filepath.Base(probes[index])))
	}

	return resolved
}

func (o *VueTscOracle) writeProbes(queries []TypeQuery) ([]string, []string) {
	var paths, probes []string
	for _, query := range queries {
		source, ok := ProbeSource(query.Component, query.Names)
		if !ok {
			continue
		}
		probe, err := writeProbe(query.Component.File(), source)
		if err != nil {
			continue
		}
		paths, probes = append(paths, query.Component.File()), append(probes, probe)
	}

	return paths, probes
}

func writeProbe(beside, source string) (string, error) {
	file, err := os.CreateTemp(filepath.Dir(beside), "__cc_probe_*.vue")
	if err != nil {
		return "", err
	}
	defer file.Close()
	if _, err := file.WriteString(source); err != nil {
		os.Remove(file.Name())

		return "", err
	}

	return file.Name(), nil
}

func (o *VueTscOracle) arguments() []string {
	return []string{
		"--noEmit",
		"--skipLibCheck",
		"--pretty", "false",
		"--noErrorTruncation",
		"--incremental",
		"--tsBuildInfoFile", o.buildInfo(),
	}
}

func (o *VueTscOracle) buildInfo() string {
	path := strings.TrimRight(o.root, "/") + "/node_modules/.cache/code-commandments/vue-tsc.tsbuildinfo"
	os.MkdirAll(filepath.Dir(path), 0o777)

	return path
}

func linesNaming(output, file string) string {
	var lines []string
	for _, line := range strings.Split(output, "\n") {
		if strings.Contains(line, file) {
			lines = append(lines, line)
		}
	}

	return strings.Join(lines, "\n")
}

func ProbeSource(component Component, names []string) (string, bool) {
	setup, ok := setupScript(component)
	if !ok || len(names) == 0 {
		return "", false
	}
	content, err := setup.Child("children").Span()
	if err != nil {
		return "", false
	}
	source, at := string(content.Source), content.End
	probes := "\n"
	for _, name := range names {
		typed := probeMarker + name
		probes += "type " + typed + " = string & { readonly __ccBrand: '" + name + "' };\n"
		probes += "const __cc_" + name + ": " + typed + " = " + name + ";\n"
	}

	return source[:at] + probes + source[at:], true
}

func setupScript(component Component) (engine.Match, bool) {
	for _, block := range component.ChildrenIn("blocks") {
		if block.Name() != "script" {
			continue
		}
		for _, attribute := range block.ChildrenIn("attributes") {
			if attribute.Name() == "setup" {
				return block, true
			}
		}
	}

	return engine.Match{}, false
}

func CheckerTypes(output string) map[string]string {
	pivot := "' is not assignable to type '" + probeMarker
	const lead = "Type '"
	types := map[string]string{}
	for _, line := range strings.Split(output, "\n") {
		pivotAt, leadAt := strings.Index(line, pivot), strings.Index(line, lead)
		if pivotAt < 0 || leadAt < 0 || leadAt >= pivotAt {
			continue
		}
		resolved := line[leadAt+len(lead) : pivotAt]
		rest := line[pivotAt+len(pivot):]
		end := strings.Index(rest, "'")
		if end >= 0 && usableCheckerType(resolved) {
			types[rest[:end]] = resolved
		}
	}

	return types
}

func usableCheckerType(typed string) bool {
	return typed != "" && typed != "unknown" && typed != "any" && len(typed) <= maxOracleType
}
