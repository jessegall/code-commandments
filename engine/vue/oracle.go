package vue

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/workspace"
)

// probeMarker opens the name of each probe's impossible type, which carries the local it asks about.
const probeMarker = "__CcNo_"

// maxOracleType is the longest resolved type worth writing: a composable's whole structure spelt inline reads worse
// than unknown.
const maxOracleType = 200

// TypeQuery is a component and the locals whose types no reading of its source could tell.
type TypeQuery struct {
	Sfc   *Sfc
	Names []string
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

// Run runs the program, and is empty when it could not start.
func (ShellRunner) Run(binary string, arguments []string, cwd string) string {
	var stdout, stderr bytes.Buffer
	command := exec.Command(binary, arguments...)
	command.Dir = cwd
	command.Stdout, command.Stderr = &stdout, &stderr
	_ = command.Run()

	return stdout.String() + stderr.String()
}

// VueTscOracle asks a project's own vue-tsc: each query's component is copied beside itself with a probe that
// assigns every asked local to a type nothing is assignable to, and the checker's complaint names the local's type.
type VueTscOracle struct {
	root   string
	runner ProcessRunner
}

// NewVueTscOracle is the oracle of the project at root, running vue-tsc through the runner.
func NewVueTscOracle(root string, runner ProcessRunner) *VueTscOracle {
	return &VueTscOracle{root: root, runner: runner}
}

// LocateVueTsc is the oracle of the nearest project at or above the path that ships vue-tsc.
func LocateVueTsc(path string, runner ProcessRunner) (*VueTscOracle, bool) {
	from := filepath.Dir(path)
	if info, err := os.Stat(path); err == nil && info.IsDir() {
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

// VueTscAvailable says whether the project at root ships vue-tsc.
func VueTscAvailable(root string) bool {
	info, err := os.Stat(vueTscBinary(root))

	return err == nil && info.Mode().IsRegular()
}

func vueTscBinary(root string) string {
	return strings.TrimRight(root, "/") + "/node_modules/.bin/vue-tsc"
}

// ResolveAll writes every probe, runs the checker once over the project, removes the probes, and reads each
// component's types from the complaints about its own probe.
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
		source, ok := ProbeSource(query.Sfc, query.Names)
		if !ok {
			continue
		}
		probe, err := writeProbe(query.Sfc, source)
		if err != nil {
			continue
		}
		paths, probes = append(paths, query.Sfc.Path), append(probes, probe)
	}

	return paths, probes
}

func writeProbe(component *Sfc, source string) (string, error) {
	file, err := os.CreateTemp(filepath.Dir(component.Path), "__cc_probe_*.vue")
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

// buildInfo is where the checker keeps what it learned for the next run, its folder made.
func (o *VueTscOracle) buildInfo() string {
	path := workspace.At(o.root).Cache(".vue-tsc.tsbuildinfo")
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

// ProbeSource is the component with a probe written at the end of its `<script setup>`, where its locals are in
// scope: each name assigned to a string-branded type nothing is assignable to, so every value, of any shape, fails
// the same way, with the complaint naming its type. Nothing to probe without names or a setup script.
func ProbeSource(component *Sfc, names []string) (string, bool) {
	setup, ok := setupBlock(component)
	if !ok || len(names) == 0 {
		return "", false
	}
	at := setup.Start + len(setup.Content)
	probes := "\n"
	for _, name := range names {
		typed := probeMarker + name
		probes += "type " + typed + " = string & { readonly __ccBrand: '" + name + "' };\n"
		probes += "const __cc_" + name + ": " + typed + " = " + name + ";\n"
	}

	return component.Source[:at] + probes + component.Source[at:], true
}

func setupBlock(component *Sfc) (Block, bool) {
	for _, block := range component.Blocks {
		if block.Tag == "script" && block.HasAttribute("setup") {
			return block, true
		}
	}

	return Block{}, false
}

// CheckerTypes is each probed local and the type the checker resolved it to, read from its complaints; a type the
// checker could not tell either, or one too long to be worth writing, is left out.
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
