package config

import (
	"fmt"
	"os"
	"regexp"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/walk"
)

// File edits a project's config file through its tree: a call's arguments are rewritten one per line in the file's own indentation, and everything else the project wrote is left as it was.
type File struct {
	path string
}

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

func (f File) Enable(class string) (bool, error) {
	if !walk.Exists(f.path) {
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
	scribe := Scribe{f.path}
	_, err := scribe.Scaffold(DetectRoots(scribe.Root()))

	return err
}

func (f File) disableCall() (engine.Match, error) {
	call, found, err := Scribe{f.path}.first("disable")

	if err == nil && !found {
		err = &Unrecognizable{f.path}
	}

	return call, err
}

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

	return atomic.Write(f.path, source[:from]+text+source[to:])
}

var lineBreak = regexp.MustCompile(`\r\n|[\n\r\x0B\f]`)

func lineStartAt(source string, position int) int {
	return strings.LastIndex(source[:position], "\n") + 1
}

func indentAt(source string, position int) string {
	prefix := source[lineStartAt(source, position):position]

	return prefix[:len(prefix)-len(strings.TrimLeft(prefix, " \t\n\r\x00\x0B"))]
}

// Layer is one declared layer and the namespaces it may use.
type Layer struct {
	Namespace string
	MayUse    []string
}

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

	return true, atomic.Write(f.path, source[:from]+chain+source[to:])
}

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

func (f File) layerCalls() ([]engine.Match, error) {
	if !walk.Exists(f.path) {
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

func (f File) RegisterDetector(class string) (bool, error) {
	if err := f.scaffoldIfMissing(); err != nil {
		return false, err
	}

	class = strings.TrimLeft(class, `\`)
	call, found, err := Scribe{f.path}.first("detector")
	if err != nil {
		return false, err
	}

	if found {
		current := classesIn(call)
		if slices.Contains(current, class) {
			return false, nil
		}

		return true, f.rewrite(call, append(current, class), nil)
	}

	return true, f.appendStatement(`$config->detector(\` + class + `::class);`)
}

func (f File) appendStatement(statement string) error {
	anchor, found, err := f.lastStatement()
	if err != nil {
		return err
	}

	if !found {
		return &Unrecognizable{f.path}
	}

	raw, err := os.ReadFile(f.path)
	if err != nil {
		return err
	}

	source := string(raw)
	at := anchor.Node().Span.End
	start := anchor.Node().Span.Start
	indent := source[lineStartAt(source, start):start]

	return atomic.Write(f.path, source[:at]+"\n\n"+indent+statement+source[at:])
}

func (f File) lastStatement() (engine.Match, bool, error) {
	stream, err := php.Here().Stream(f.path)
	if err != nil {
		return engine.Match{}, false, err
	}

	var last engine.Match
	found := false

	for _, call := range engine.Load(stream).WhereKind("Expr_MethodCall").Get() {
		statement := call.Parent()

		if statement.Kind() == "Stmt_Expression" && (!found || statement.Node().Span.Start > last.Node().Span.Start) {
			last, found = statement, true
		}
	}

	return last, found, nil
}
