package config

import (
	"fmt"
	"os"
	"path/filepath"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// File edits a project's config file through its tree: a call's arguments are rewritten one per line in the
// file's own indentation, and everything else the project wrote is left as it was.
type File struct {
	path string
}

// FileIn is the config file of the project at dir.
func FileIn(dir string) File {
	return File{workspace.Config(dir)}
}

// Unrecognizable is a config with no `disable(...)` call to edit.
type Unrecognizable struct {
	Path string
}

func (e *Unrecognizable) Error() string {
	return e.Path + " does not return a `function (Config $config)` closure. Restore it, or delete the file to regenerate."
}

// Disable adds the class to the config's disable() call, scaffolding the config first when there is none;
// false when it is already disabled.
func (f File) Disable(class string) (bool, error) {
	if err := f.scaffoldIfMissing(); err != nil {
		return false, err
	}

	class = strings.TrimLeft(class, `\`)
	call, err := f.disableCall()
	if err != nil {
		return false, err
	}

	current := classesIn(call)
	if slices.Contains(current, class) {
		return false, nil
	}

	return true, f.rewrite(call, append(current, class), languagesIn(call))
}

// Enable takes the class out of the config's disable() call; false when it was not disabled.
func (f File) Enable(class string) (bool, error) {
	if _, err := os.Stat(f.path); err != nil {
		return false, nil
	}

	class = strings.TrimLeft(class, `\`)
	call, err := f.disableCall()
	if err != nil {
		return false, err
	}

	current := classesIn(call)
	remaining := slices.DeleteFunc(slices.Clone(current), func(each string) bool { return each == class })

	if len(remaining) == len(current) {
		return false, nil
	}

	return true, f.rewrite(call, remaining, languagesIn(call))
}

func (f File) scaffoldIfMissing() error {
	root := filepath.Dir(filepath.Dir(f.path))
	_, err := Scribe{f.path}.Scaffold(DetectRoots(root))

	return err
}

func (f File) disableCall() (engine.Match, error) {
	call, found, err := Scribe{f.path}.first("disable")

	if err == nil && !found {
		err = &Unrecognizable{f.path}
	}

	return call, err
}

// classesIn are the classes the call names with `::class`, leading backslash dropped.
func classesIn(call engine.Match) []string {
	var classes []string

	for _, arg := range call.ChildrenIn("args") {
		value := arg.Child("value")

		if value.Kind() == "Expr_ClassConstFetch" && value.Child("name").Name() == "class" {
			classes = append(classes, strings.TrimLeft(value.Child("class").Name(), `\`))
		}
	}

	return classes
}

// languagesIn are the `Language::Case` arguments of the call, by case name.
func languagesIn(call engine.Match) []string {
	var languages []string

	for _, arg := range call.ChildrenIn("args") {
		value := arg.Child("value")
		class := value.Child("class").Name()

		if value.Kind() == "Expr_ClassConstFetch" && value.Child("name").Name() != "class" && lastSegment(class) == "Language" {
			if language, known := caseNamed(value.Child("name").Name()); known {
				languages = append(languages, language)
			}
		}
	}

	return languages
}

func caseNamed(name string) (string, bool) {
	for _, each := range []string{"Php", "Vue", "TypeScript", "Python", "CSharp"} {
		if each == name {
			return each, true
		}
	}

	return "", false
}

func lastSegment(class string) string {
	return class[strings.LastIndex(class, `\`)+1:]
}

// rewrite writes the call's arguments one per line, languages first, in the indentation of its closing line.
func (f File) rewrite(call engine.Match, classes, languages []string) error {
	var args []string

	for _, language := range languages {
		args = append(args, `\`+languageEnum+"::"+language)
	}

	for _, class := range classes {
		args = append(args, `\`+class+"::class")
	}

	raw, err := os.ReadFile(f.path)
	if err != nil {
		return err
	}

	source := string(raw)
	closing := call.Node().Span.End - 1
	indent := indentAt(source, closing)
	var rendered strings.Builder

	for _, arg := range args {
		fmt.Fprintf(&rendered, "%s    %s,\n", indent, arg)
	}

	first := closing
	if existing := call.ChildrenIn("args"); len(existing) > 0 {
		first = existing[0].Child("value").Node().Span.Start
	}

	from, to, text := lineStartAt(source, first), lineStartAt(source, closing), rendered.String()

	if lineStartAt(source, closing) == lineStartAt(source, call.Node().Span.Start) {
		from, to, text = first, closing, "\n"+rendered.String()+indent
	}

	return os.WriteFile(f.path, []byte(source[:from]+text+source[to:]), 0o644)
}

// lineBreak is any line ending, as PHP's \R reads one.
var lineBreak = regexp.MustCompile(`\r\n|[\n\r\x0B\f]`)

// lineStartAt is where the line holding position begins.
func lineStartAt(source string, position int) int {
	return strings.LastIndex(source[:position], "\n") + 1
}

// indentAt is the whitespace the line holding position opens with, up to the position.
func indentAt(source string, position int) string {
	prefix := source[lineStartAt(source, position):position]

	return prefix[:len(prefix)-len(strings.TrimLeft(prefix, " \t\n\r\x00\x0B"))]
}

// Layer is one declared layer and the namespaces it may use.
type Layer struct {
	Namespace string
	MayUse    []string
}

// Layers are the layers the config's ->layer(...) chain declares, in the order it is written.
func (f File) Layers() ([]Layer, error) {
	calls, err := f.layerCalls()
	if err != nil {
		return nil, err
	}

	var layers []Layer

	for i := len(calls) - 1; i >= 0; i-- {
		args := calls[i].ChildrenIn("args")

		if len(args) == 0 || args[0].Child("value").Kind() != "Scalar_String" {
			continue
		}

		namespace, _ := args[0].Child("value").Text()
		layers = append(layers, Layer{namespace, mayUseOf(args)})
	}

	return layers, nil
}

// RewriteLayers writes the layers in place of the config's ->layer(...) chain, in its own indentation;
// false when the config declares no layers to rewrite.
func (f File) RewriteLayers(layers []Layer) (bool, error) {
	calls, err := f.layerCalls()
	if err != nil || len(calls) == 0 {
		return false, err
	}

	raw, err := os.ReadFile(f.path)
	if err != nil {
		return false, err
	}

	source := string(raw)
	from := calls[len(calls)-1].Child("var").Node().Span.End
	to := calls[0].Node().Span.End
	chain := RenderChain(layers, indentOfChain(source[from:]))

	return true, os.WriteFile(f.path, []byte(source[:from]+chain+source[to:]), 0o644)
}

// RenderChain is the layers as ->layer(...) links, one a line.
func RenderChain(layers []Layer, indent string) string {
	var chain strings.Builder

	for _, layer := range layers {
		may := ""

		if len(layer.MayUse) > 0 {
			quoted := make([]string, len(layer.MayUse))

			for i, use := range layer.MayUse {
				quoted[i] = quote(use)
			}

			may = ", mayUse: [" + strings.Join(quoted, ", ") + "]"
		}

		chain.WriteString("\n" + indent + "->layer(" + quote(layer.Namespace) + may + ")")
	}

	return chain.String()
}

// layerCalls are the config's ->layer(...) calls, the last link written first.
func (f File) layerCalls() ([]engine.Match, error) {
	if _, err := os.Stat(f.path); err != nil {
		return nil, nil
	}

	stream, err := php.Here().Stream(f.path)
	if err != nil {
		return nil, err
	}

	var calls []engine.Match

	for _, call := range engine.Load(stream).WhereKind("Expr_MethodCall").Get() {
		if call.Child("name").Name() == "layer" {
			calls = append(calls, call)
		}
	}

	return calls, nil
}

// mayUseOf are the strings of the first array argument.
func mayUseOf(args []engine.Match) []string {
	for _, arg := range args {
		value := arg.Child("value")

		if value.Kind() != "Expr_Array" {
			continue
		}

		var uses []string

		for _, item := range value.ChildrenIn("items") {
			if text, isString := item.Child("value").Text(); isString && item.Child("value").Kind() == "Scalar_String" {
				uses = append(uses, text)
			}
		}

		return uses
	}

	return nil
}

// indentOfChain is the indentation of the first line after the chain's receiver that holds anything.
func indentOfChain(rest string) string {
	for _, line := range lineBreak.Split(rest, -1) {
		if strings.Trim(line, " \t\n\r\x00\x0B") != "" {
			return line[:len(line)-len(strings.TrimLeft(line, " \t\n\r\x00\x0B"))]
		}
	}

	return "        "
}

func quote(namespace string) string {
	return "'" + strings.ReplaceAll(namespace, `\`, `\\`) + "'"
}
