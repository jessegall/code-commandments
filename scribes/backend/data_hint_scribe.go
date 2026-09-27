package backend

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/scribes"
)

// data is the class every Spatie Data extends.
const data = `Spatie\LaravelData\Data`

// collectHint documents Data::collect() as it answers: a Collection for a Collection, an array for anything else.
const collectHint = `@method static ($items is \Illuminate\Support\Collection ? \Illuminate\Support\Collection<int, static> : array<int, static>) collect(iterable $items)`

func init() {
	scribes.Maintains(catalog.Backend, DataHintScribe{})
}

// DataHintScribe keeps a Data class's magic surface honest: a factory that is not named from… is renamed from<Type>
// and its callers call ::from(), and the class docblock's @method lines say what from() and collect() take. A scoped
// run only rewrites docblocks, since a rename's callers may live outside the scope.
type DataHintScribe struct{}

// Name is the maintainer's name, as the PHP tool names it.
func (DataHintScribe) Name() string {
	return "DataHintScribe"
}

// Summary is what the scribe does, for the README.
func (DataHintScribe) Summary() string {
	return "Brings a Spatie `Data` class's magic surface in line with the spatie-data skill: renames object factories to `from<Type>`, regenerates `@method` docblock lines, and adds `@method collect()` when used."
}

// dataClass is a Data class and the object factories it declares.
type dataClass struct {
	file      string
	node      engine.Match
	factories []factory
}

// factory is a public static one-parameter method that returns and builds its own class.
type factory struct {
	name   string
	named  engine.Match
	params []engine.Match
	isFrom bool
}

// callSite is a static call on a class.
type callSite struct {
	class, method, file string
	named, node         engine.Match
}

// Maintain renames and documents every Data class's factories and rewrites the calls to renamed ones.
func (DataHintScribe) Maintain(codebase *engine.Codebase, pass scribes.Pass) (scribes.Rewrites, error) {
	docblockOnly := pass.Scope.IsScoped()
	classes, order := dataClasses(codebase)
	collectUsed, calls := scanCalls(codebase)
	renames := map[string]string{}
	if !docblockOnly {
		renames = planRenames(classes, order)
	}
	var files []string
	edits := map[string][]scribes.Edit{}
	add := func(file string, edit scribes.Edit) {
		if _, seen := edits[file]; !seen {
			files = append(files, file)
		}
		edits[file] = append(edits[file], edit)
	}
	for _, fqcn := range order {
		class := classes[fqcn]
		if docblockOnly && !pass.Scope.Includes(class.file) {
			continue
		}
		source := sourceText(class.node)
		if !docblockOnly {
			for _, made := range class.factories {
				if renamed, ok := renames[fqcn+"\x00"+made.name]; ok {
					add(class.file, over(made.named, renamed))
				}
			}
		}
		if edit, ok := docblockEdit(class.node, source, methodLines(class, source, collectUsed[fqcn], docblockOnly)); ok {
			add(class.file, edit)
		}
	}
	if !docblockOnly {
		for _, call := range calls {
			if _, renamed := renames[call.class+"\x00"+call.method]; renamed {
				add(call.file, rewriteCall(call))
			}
		}
	}

	rewrites := scribes.Rewrites{}
	for _, file := range files {
		source := sourceOfFile(codebase, file)
		if changed := scribes.ApplyEdits(source, edits[file]); changed != source {
			rewrites.Set(file, changed)
		}
	}

	return rewrites, nil
}

// rewriteCall is a call to a renamed factory as a call to from(): `X::from($value)` for a lone named argument, the
// method's name otherwise.
func rewriteCall(call callSite) scribes.Edit {
	arguments := call.node.ChildrenIn("args")
	if len(arguments) == 1 && arguments[0].Kind() == "Arg" && arguments[0].Child("name").Exists() {
		return over(call.node, textOf(call.node.Child("class"))+"::from("+textOf(arguments[0].Child("value"))+")")
	}

	return over(call.named, "from")
}

// planRenames is the from<Type> name each factory not named from… takes, keyed by class and factory, unless the class
// already has a method by that name.
func planRenames(classes map[string]dataClass, order []string) map[string]string {
	renames := map[string]string{}
	for _, fqcn := range order {
		class := classes[fqcn]
		var taken []string
		for _, method := range php.Methods(class.node) {
			taken = append(taken, strings.ToLower(method.Child("name").Name()))
		}
		for _, made := range class.factories {
			if made.isFrom {
				continue
			}
			short, typed := typeShortName(made.params[0].Child("type"))
			renamed := "from" + upperFirst(short)
			if !typed || slices.Contains(taken, strings.ToLower(renamed)) {
				continue
			}
			renames[fqcn+"\x00"+made.name] = renamed
			taken = append(taken, strings.ToLower(renamed))
		}
	}

	return renames
}

// methodLines is the @method lines a class's docblock owes: one from() per factory, the payload overload beside
// them, and collect() when anything collects the class.
func methodLines(class dataClass, source string, collectUsed, docblockOnly bool) []string {
	var lines []string
	for _, made := range class.factories {
		if docblockOnly && !made.isFrom {
			continue
		}
		first, last := made.params[0].Node().Span, made.params[len(made.params)-1].Node().Span
		lines = append(lines, "@method static static from("+source[first.Start:last.End]+")")
	}
	if len(lines) > 0 {
		lines = append(lines, "@method static static from(array $payload)")
	}
	if collectUsed {
		lines = append(lines, collectHint)
	}

	return lines
}

// docblockEdit writes the @method lines into the class docblock in place of the ones it had, its prose kept, or a
// docblock of them above a class that has none.
func docblockEdit(class engine.Match, source string, lines []string) (scribes.Edit, bool) {
	start := class.Node().Span.Start
	lineStart := engine.Source(source).LineStartAt(start)
	indent := source[lineStart:start]
	doc, documented := php.Node{Match: class}.DocComment()
	if !documented {
		if len(lines) == 0 {
			return scribes.Edit{}, false
		}
		block := indent + "/**\n"
		for _, line := range lines {
			block += indent + " * " + line + "\n"
		}

		return scribes.InsertAt(lineStart, block+indent+" */\n"), true
	}
	kept := php.DocblockWithoutTag(doc.Text, "method")
	closing := kept[len(kept)-1]
	kept = kept[:len(kept)-1]
	if len(lines) > 0 && len(kept) > 0 && strings.Trim(kept[len(kept)-1], " \t*") != "" {
		kept = append(kept, indent+" *")
	}
	for _, line := range lines {
		kept = append(kept, indent+" * "+line)
	}
	kept = append(kept, closing)

	return scribes.Edit{Start: doc.Span.Start, End: doc.Span.End, Text: strings.Join(kept, "\n")}, true
}

// dataClasses is every class extending Data, by name, and the names in the order the files declare them; a class
// declared twice is the one read last, standing where it was first declared.
func dataClasses(codebase *engine.Codebase) (map[string]dataClass, []string) {
	program := php.ProgramOf(codebase)
	classes := map[string]dataClass{}
	var order []string
	for _, file := range codebase.Files() {
		for _, node := range file.File.Nodes() {
			if node.Kind != "Stmt_Class" || node.Symbol == "" || !program.Extends(node.Symbol, data) {
				continue
			}
			class := file.Match(node.ID)
			var factories []factory
			for _, method := range php.Methods(class) {
				if !isObjectFactory(method, node.Symbol) {
					continue
				}
				name := method.Child("name").Name()
				factories = append(factories, factory{name: name, named: method.Child("name"), params: php.Params(method), isFrom: strings.HasPrefix(name, "from") && name != "from"})
			}
			if _, seen := classes[node.Symbol]; !seen {
				order = append(order, node.Symbol)
			}
			classes[node.Symbol] = dataClass{file: file.Path, node: class, factories: factories}
		}
	}

	return classes, order
}

// isObjectFactory says whether a method is public, static, takes one parameter, and returns and builds its class.
func isObjectFactory(method engine.Match, fqcn string) bool {
	modifiers := method.Node().Modifiers
	public := slices.Contains(modifiers, "public") || !slices.Contains(modifiers, "protected") && !slices.Contains(modifiers, "private")
	if !public || !slices.Contains(modifiers, "static") || len(php.Params(method)) != 1 {
		return false
	}

	return returnsSelf(method, fqcn) && constructsSelf(method)
}

// returnsSelf says whether a method declares it returns self, static, or its class by name.
func returnsSelf(method engine.Match, fqcn string) bool {
	returned := method.Child("returnType")
	lower := strings.ToLower(returned.Name())
	switch {
	case returned.Kind() == "Identifier":
		return lower == "self" || lower == "static"
	case strings.HasPrefix(returned.Kind(), "Name"):
		return lower == "self" || lower == "static" || strings.TrimLeft(returned.Name(), `\`) == fqcn
	}

	return false
}

// constructsSelf says whether a method's body builds its class: `new self`, `new static`, or `self::from(...)`.
func constructsSelf(method engine.Match) bool {
	for _, statement := range method.ChildrenIn("stmts") {
		for _, node := range append([]engine.Match{statement}, statement.Descendants()...) {
			class := node.Child("class")
			selfish := strings.HasPrefix(class.Kind(), "Name") && (strings.ToLower(class.Name()) == "self" || strings.ToLower(class.Name()) == "static")
			if node.Kind() == "Expr_New" && selfish {
				return true
			}
			if node.Kind() == "Expr_StaticCall" && selfish && node.Child("name").Kind() == "Identifier" && node.Child("name").Name() == "from" {
				return true
			}
		}
	}

	return false
}

// scanCalls is which classes anything calls collect() on, and every static call on a named class.
func scanCalls(codebase *engine.Codebase) (map[string]bool, []callSite) {
	collectUsed := map[string]bool{}
	var calls []callSite
	for _, file := range codebase.Files() {
		for _, node := range file.File.Nodes() {
			call := file.Match(node.ID)
			if node.Kind != "Expr_StaticCall" || call.Child("name").Kind() != "Identifier" {
				continue
			}
			class, named := callClass(call)
			if !named {
				continue
			}
			method := call.Child("name").Name()
			if method == "collect" {
				collectUsed[class] = true
			}
			calls = append(calls, callSite{class: class, method: method, file: file.Path, named: call.Child("name"), node: call})
		}
	}

	return collectUsed, calls
}

// callClass is the class a static call names: its own for self or static, as the enclosing class declares it.
func callClass(call engine.Match) (string, bool) {
	class := call.Child("class")
	if !strings.HasPrefix(class.Kind(), "Name") {
		return "", false
	}
	if lower := strings.ToLower(class.Name()); lower == "self" || lower == "static" {
		for ancestor := call.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
			if ancestor.Kind() == "Stmt_Class" {
				return ancestor.Node().Symbol, ancestor.Node().Symbol != ""
			}
		}

		return "", false
	}

	return strings.TrimLeft(class.Name(), `\`), true
}

// typeShortName is the short name a type is spelled by: a nullable's inner type, a union's first member, a keyword
// capitalised, a class's last segment.
func typeShortName(typed engine.Match) (string, bool) {
	switch kind := typed.Kind(); {
	case kind == "NullableType":
		return typeShortName(typed.Child("type"))
	case kind == "UnionType" || kind == "IntersectionType":
		members := typed.ChildrenIn("types")
		if len(members) == 0 {
			return "", false
		}

		return typeShortName(members[0])
	case kind == "Identifier":
		return upperFirst(typed.Name()), true
	case strings.HasPrefix(kind, "Name"):
		name := typed.Name()

		return name[strings.LastIndex(name, `\`)+1:], true
	}

	return "", false
}

func upperFirst(text string) string {
	if text == "" || text[0] < 'a' || text[0] > 'z' {
		return text
	}

	return string(text[0]-'a'+'A') + text[1:]
}

// over is an edit replacing the node's whole span.
func over(node engine.Match, text string) scribes.Edit {
	span := node.Node().Span

	return scribes.Edit{Start: span.Start, End: span.End, Text: text}
}

// sourceText is the text of the file a node sits in.
func sourceText(node engine.Match) string {
	source, _ := node.Source().Source()

	return string(source)
}

// sourceOfFile is the text of a file of the codebase, as the codebase reads it.
func sourceOfFile(codebase *engine.Codebase, path string) string {
	source, _ := codebase.Read(path)

	return string(source)
}
