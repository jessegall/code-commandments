package vue

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// reactive are the calls that wrap a value in a ref, whose type is what the call holds.
var reactive = []string{"ref", "computed", "shallowRef", "toRef", "customRef", "reactive"}

// Script is a component's `<script>` text, read as the PHP tool reads it: what an extracted component carries in,
// declares and types is read off the text, as the PHP tool does, so it comes out as the PHP tool writes it.
type Script struct {
	source string
	module typescript.Module
	tokens []scriptToken
}

// scriptToken is a lexeme of the script with numbers left out, as the PHP tool's Script keeps them.
type scriptToken struct {
	kind, value string
	start, end  int
}

// ReadScript reads a script's text.
func ReadScript(source string) Script {
	return Script{source: source, module: typescript.ParseModule(source), tokens: scriptTokens(source)}
}

// ImportStatement is an import as the extracted component carries it: the names it binds, and its statement.
type ImportStatement struct {
	Names     []string
	Statement string
}

// BindsAny says whether the import binds a name the test accepts.
func (i ImportStatement) BindsAny(test func(string) bool) bool {
	return slices.ContainsFunc(i.Names, test)
}

// Imports is every import of the script, its statement ended with a `;`.
func (s Script) Imports() []ImportStatement {
	imports := make([]ImportStatement, 0, len(s.module.Imports))
	for _, imported := range s.module.Imports {
		statement := imported.Raw
		if !strings.HasSuffix(statement, ";") {
			statement += ";"
		}
		imports = append(imports, ImportStatement{Names: imported.Names, Statement: statement})
	}

	return imports
}

// ImportSpecifier is where the script imports the name from.
func (s Script) ImportSpecifier(name string) (string, bool) {
	for _, imported := range s.module.Imports {
		if _, binds := imported.Bindings[name]; binds && imported.HasFrom {
			return imported.Source, true
		}
	}

	return "", false
}

// LocalNames is every name the script's top declares, once.
func (s Script) LocalNames() []string {
	var names []string
	for _, name := range s.module.LocalNames() {
		if !slices.Contains(names, name) {
			names = append(names, name)
		}
	}

	return names
}

// PropsVariable is the name the script holds its props under: `const props = defineProps(…)`.
func (s Script) PropsVariable() (string, bool) {
	return s.module.VariableNamedFrom(func(call typescript.Call) bool {
		return call.Callee == "defineProps" || call.Callee == "withDefaults" && call.FirstArgumentStartsWith("defineProps")
	})
}

// EmitName is the name the script holds its emit under: `const emit = defineEmits(…)`.
func (s Script) EmitName() (string, bool) {
	return s.module.VariableNamedFrom(func(call typescript.Call) bool { return call.Callee == "defineEmits" })
}

// TypeFields is the members of the type the script declares under the name.
func (s Script) TypeFields(name string) typescript.Fields {
	declaration, declared := s.module.TypeDeclaration(name)
	if !declared {
		return typescript.Fields{}
	}

	return declaration.Fields()
}

// LocalTypes is each type declaration the names reach within the script, printed.
func (s Script) LocalTypes(names []string) typescript.Fields {
	return s.module.LocalTypes(names)
}

// PropTypes is each prop the script's defineProps declares and its type, printed.
func (s Script) PropTypes() typescript.Fields {
	call, declares := s.definePropsCall()
	if !declares || len(call.TypeArguments) == 0 {
		return typescript.Fields{}
	}

	return call.TypeArguments[0].FieldsWith(s.TypeFields)
}

func (s Script) definePropsCall() (typescript.Call, bool) {
	if direct, ok := s.module.Call("defineProps"); ok {
		return direct, true
	}
	wrapped, ok := s.module.Call("withDefaults")
	if !ok || len(wrapped.Arguments) == 0 {
		return typescript.Call{}, false
	}

	return typescript.ParseModule(wrapped.Arguments[0]).Call("defineProps")
}

// DeclaredType is the type the script declares or soundly implies for a name: a function's signature, a variable's
// annotation, an arrow's signature, a reactive wrapper's value, or an initialiser's inferred type.
func (s Script) DeclaredType(name string) (string, bool) {
	if function, ok := s.module.Function(name); ok {
		return function.Signature().Render(), true
	}
	variable, ok := s.module.Variable(name)
	switch {
	case !ok:
		return "", false
	case variable.TypeAnnotation != nil:
		return variable.TypeAnnotation.Render(), true
	case variable.HasInitParams:
		var returns typescript.TypeNode = typescript.KeywordType{Name: "void"}
		if variable.InitReturnType != nil {
			returns = variable.InitReturnType
		}

		return typescript.FunctionType{Params: variable.InitParams, Returns: returns}.Render(), true
	case variable.InitCall != nil:
		return reactiveType(*variable.InitCall)
	case variable.HasInit:
		return typescript.ParseExpression(variable.InitRaw).InferType()
	}

	return "", false
}

// reactiveType is what a reactive wrapper holds: its type argument, a computed's return, or its value's type.
func reactiveType(call typescript.Call) (string, bool) {
	if !slices.Contains(reactive, call.Callee) {
		return "", false
	}
	if len(call.TypeArguments) > 0 {
		return call.TypeArguments[0].Render(), true
	}
	if len(call.Arguments) == 0 {
		return "", false
	}
	parsed := typescript.ParseExpression(call.Arguments[0])
	if call.Callee == "computed" {
		return parsed.ReturnType()
	}

	return parsed.InferType()
}

// StaticConst is a plain `const NAME = …` that calls nothing, as written again: a constant a component can carry in.
func (s Script) StaticConst(name string) (string, bool) {
	variable, ok := s.module.Variable(name)
	if !ok || variable.Keyword != "const" || variable.InitCall != nil || !variable.NamePattern {
		return "", false
	}

	return variable.Render(), true
}

// DestructuredCall is the call a destructured name comes from: `const { x } = useThing()`.
func (s Script) DestructuredCall(name string) (string, bool) {
	variable, ok := s.module.Variable(name)
	if !ok || !variable.ObjectPattern || variable.InitCall == nil {
		return "", false
	}

	return variable.InitCall.Callee, true
}

// ReturnTypeName is the return type a function declares, printed.
func (s Script) ReturnTypeName(function string) (string, bool) {
	if declared, ok := s.module.Function(function); ok {
		if declared.ReturnType == nil {
			return "", false
		}

		return declared.ReturnType.Render(), true
	}
	variable, ok := s.module.Variable(function)
	if !ok || variable.InitReturnType == nil {
		return "", false
	}

	return variable.InitReturnType.Render(), true
}

// FieldType is a declared type's field's type, its reactive wrapper taken off.
func (s Script) FieldType(typeName, field string) (string, bool) {
	typed, ok := s.TypeFields(typeName).Get(field)
	if !ok {
		return "", false
	}

	return typescript.UnwrapRefText(typed), true
}

// InferredReturnFields is the type of each field a composable returns, read from the locals it returns.
func (s Script) InferredReturnFields(function string) typescript.Fields {
	declared, ok := s.module.Function(function)
	if !ok || declared.ReturnObject == nil {
		return typescript.Fields{}
	}
	body := ReadScript(declared.BodySource)
	var fields typescript.Fields
	for _, field := range declared.ReturnObject.Names {
		local, _ := declared.ReturnObject.Get(field)
		if local == "" {
			continue
		}
		if typed, ok := body.DeclaredType(local); ok {
			fields.Set(field, typescript.UnwrapRefText(typed))
		}
	}

	return fields
}

// DeclaratorValue is the text a `const`/`let`/`var name =` is initialised with, up to its `;`.
func (s Script) DeclaratorValue(name string) (string, bool) {
	for at := 0; at < len(s.tokens); at++ {
		if !s.isDeclarator(at) || !s.isID(at+1, name) || !s.isPunct(at+2, "=") {
			continue
		}
		if at+3 >= len(s.tokens) {
			return "", false
		}
		from := s.tokens[at+3].start
		to := s.tokens[len(s.tokens)-1].end
		depth := 0
		for next := at + 3; next < len(s.tokens); next++ {
			value := s.tokens[next].value
			if depth == 0 && value == ";" {
				to = s.tokens[next].start

				break
			}
			switch value {
			case "(", "[", "{":
				depth++
			case ")", "]", "}":
				depth--
			}
		}

		return strings.Trim(s.source[from:max(from, to)], " \t\n\r\x00\x0b"), true
	}

	return "", false
}

func (s Script) isDeclarator(at int) bool {
	return s.isID(at, "const") || s.isID(at, "let") || s.isID(at, "var")
}

func (s Script) isID(at int, value string) bool {
	return at < len(s.tokens) && s.tokens[at].kind == "id" && s.tokens[at].value == value
}

func (s Script) isPunct(at int, value string) bool {
	return at < len(s.tokens) && s.tokens[at].kind == "punct" && s.tokens[at].value == value
}

// scriptTokens is the script's lexemes, numbers left out.
func scriptTokens(source string) []scriptToken {
	var tokens []scriptToken
	for _, token := range typescript.Lexemes(source) {
		if token.Kind != "num" {
			tokens = append(tokens, scriptToken{kind: token.Kind, value: token.Value, start: token.Start, end: token.End})
		}
	}

	return tokens
}

// ObjectAfter is the object literal written after `key:`, as written.
func (s Script) ObjectAfter(key string) (string, bool) {
	for at := 0; at < len(s.tokens); at++ {
		if !(s.isID(at, key) && s.isPunct(at+1, ":") && s.isPunct(at+2, "{")) {
			continue
		}
		closing := s.matchingParen(at + 2)

		return s.source[s.tokens[at+2].start:s.tokens[closing].end], true
	}

	return "", false
}

// ReExports is every module the script re-exports from, once.
func (s Script) ReExports() []string {
	var specifiers []string
	for at := 0; at < len(s.tokens); at++ {
		if !s.isID(at, "export") {
			continue
		}
		for next := at + 1; next < len(s.tokens) && !s.isPunct(next, ";"); next++ {
			if !s.isID(next, "from") {
				continue
			}
			if next+1 < len(s.tokens) && s.tokens[next+1].kind == "string" {
				value := s.tokens[next+1].value
				specifier := value
				if len(value) >= 2 {
					specifier = value[1 : len(value)-1]
				}
				if !slices.Contains(specifiers, specifier) {
					specifiers = append(specifiers, specifier)
				}
			}

			break
		}
	}

	return specifiers
}

// matchingParen is the token that closes the group opening at open, the last token when none does.
func (s Script) matchingParen(open int) int {
	depth := 0
	for at := open; at < len(s.tokens); at++ {
		switch s.tokens[at].value {
		case "(", "[", "{":
			depth++
		case ")", "]", "}":
			depth--
			if depth == 0 {
				return at
			}
		}
	}

	return len(s.tokens) - 1
}
