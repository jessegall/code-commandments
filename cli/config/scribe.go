package config

import (
	"os"
	"path/filepath"
	"strings"

	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// template is the config a project starts with; %ROOTS% becomes its detected source roots.
const template = "<?php\n\ndeclare(strict_types=1);\n\nuse JesseGall\\CodeCommandments\\Config;\n\n/*\n" +
	" | code-commandments configuration.\n" +
	" |\n" +
	" | paths()   — the source roots judge and repent scan (auto-detected on first run; edit to\n" +
	" |             adjust scope, or run `commandments config reindex`).\n" +
	" | exclude() — subtract explicit paths ON TOP of paths(): a dir or file listed here is never a\n" +
	" |             target (never reported, never rewritten), e.g. $config->exclude('app/Generated').\n" +
	" | disable() — silence a rule by its Sin, Detector, or whole Skill class, or a Claude Code\n" +
	" |             hook by its class (or use `commandments disable/enable <sin>`). The\n" +
	" |             $disabledSkills / $disabledSins / $disabledHooks menus below list every one.\n" +
	" |\n" +
	" | Extend it inside the closure:\n" +
	" |   $config->detector(\\App\\Commandments\\NoRawSqlDetector::class);        — add your own finder\n" +
	" |   $config->package(\\App\\Commandments\\MyFrameworkPackage::class);       — register its exemptions\n" +
	" |   $config->configure(fn (DeepNestedDetector $d) => $d->maxDepth(10));  — tune a threshold\n" +
	" */\n\n" +
	"return function (Config $config): void {\n" +
	"    $config->paths(%ROOTS%);\n\n" +
	"    $config->disable(\n" +
	"        // \\JesseGall\\CodeCommandments\\Sins\\Backend\\SwallowCatch::class,\n" +
	"    );\n" +
	"};\n"

// Scribe writes a project's config file through its tree, so the file stays valid PHP and every line the
// project wrote itself is kept.
type Scribe struct {
	path string
}

// ScribeIn is the scribe of the config of the project at dir.
func ScribeIn(dir string) Scribe {
	return Scribe{workspace.Config(dir)}
}

// Render is the starting config with these roots.
func Render(roots []string) string {
	return strings.Replace(template, "%ROOTS%", renderRoots(roots), 1)
}

// Scaffold writes the starting config when the project has none; false when it already has one.
func (s Scribe) Scaffold(roots []string) (bool, error) {
	if _, err := os.Stat(s.path); err == nil {
		return false, nil
	}

	if err := os.MkdirAll(filepath.Dir(s.path), 0o777); err != nil {
		return false, err
	}

	return true, os.WriteFile(s.path, []byte(Render(roots)), 0o644)
}

// EnsurePaths fills an empty paths() with the roots.
func (s Scribe) EnsurePaths(roots []string) error {
	call, found, err := s.first("paths")
	if err != nil || !found || len(call.ChildrenIn("args")) > 0 {
		return err
	}

	return s.splice(call, roots)
}

// RewritePaths writes the roots into paths(), scaffolding the config when there is none.
func (s Scribe) RewritePaths(roots []string) error {
	if _, err := os.Stat(s.path); err != nil {
		_, err := s.Scaffold(roots)

		return err
	}

	call, found, err := s.first("paths")
	if err != nil || !found {
		return err
	}

	return s.splice(call, roots)
}

// splice puts the roots in place of the call's arguments, or between its empty parentheses.
func (s Scribe) splice(call engine.Match, roots []string) error {
	source, err := os.ReadFile(s.path)
	if err != nil {
		return err
	}

	args := call.ChildrenIn("args")
	from, to := call.Node().Span.End-1, call.Node().Span.End-1

	if len(args) > 0 {
		from, to = args[0].Child("value").Node().Span.Start, args[len(args)-1].Child("value").Node().Span.End
	}

	return os.WriteFile(s.path, []byte(string(source[:from])+renderRoots(roots)+string(source[to:])), 0o644)
}

// first is the first call of the method in the config, and whether it makes one.
func (s Scribe) first(method string) (engine.Match, bool, error) {
	stream, err := php.Here().Stream(s.path)
	if err != nil {
		return engine.Match{}, false, err
	}

	for _, call := range engine.Load(stream).WhereKind("Expr_MethodCall").Get() {
		if call.Child("name").Name() == method {
			return call, true, nil
		}
	}

	return engine.Match{}, false, nil
}

func renderRoots(roots []string) string {
	quoted := make([]string, len(roots))

	for i, root := range roots {
		quoted[i] = "'" + addSlashes(root) + "'"
	}

	return strings.Join(quoted, ", ")
}

// addSlashes escapes as PHP's addslashes does: quotes, backslashes and NUL.
func addSlashes(text string) string {
	return strings.NewReplacer(`\`, `\\`, `'`, `\'`, `"`, `\"`, "\x00", `\0`).Replace(text)
}

// EnsureLayers writes the declaration in before the config's paths() call, and imports the detector it
// configures; false when the config already declares layers, or has no call to write it before.
func (s Scribe) EnsureLayers(declaration string) (bool, error) {
	if _, err := os.Stat(s.path); err != nil {
		return false, nil
	}

	if _, declared, err := s.first("layer"); err != nil || declared {
		return false, err
	}

	anchor, found, err := s.first("paths")
	if err == nil && !found {
		anchor, found, err = s.first("disable")
	}

	if err != nil || !found {
		return false, err
	}

	raw, err := os.ReadFile(s.path)
	if err != nil {
		return false, err
	}

	source := string(raw)
	at := anchor.Node().Span.Start
	source = source[:at] + strings.TrimLeft(declaration, " \t\n\r\x00\x0B") + "\n\n    " + source[at:]

	return true, os.WriteFile(s.path, []byte(importDetector(source)), 0o644)
}

// importDetector is the source with the NamespaceDependencyDetector imported after Config, unless it is
// already imported or Config is not.
func importDetector(source string) string {
	existing := `use JesseGall\CodeCommandments\Config;`
	detector := `use JesseGall\CodeCommandments\Detectors\Backend\NamespaceDependencyDetector;`

	if strings.Contains(source, detector) || !strings.Contains(source, existing) {
		return source
	}

	return strings.ReplaceAll(source, existing, existing+"\n"+detector)
}
