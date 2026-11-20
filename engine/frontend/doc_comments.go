package frontend

import (
	"regexp"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
	"github.com/jessegall/code-commandments/prose"
)

var docLink = regexp.MustCompile(`(?:\{@link(?:code|plain)?\s+|^@see\s+)([A-Za-z_$][\w$]*(?:[.#][A-Za-z_$][\w$]*)*)(?:[\s|}]|$)`)

func commentReferences(comment engine.Commented) []contract.Ref {
	owner := comment.Documents()
	module := owner.Namespace()
	if module == "" {
		return nil
	}
	codebase := owner.Codebase()
	var refs []contract.Ref
	for _, line := range comment.ProseLines() {
		for _, found := range docLink.FindAllStringSubmatch(line, -1) {
			name := found[1]
			head, _, _ := strings.Cut(strings.ReplaceAll(name, "#", "."), ".")
			if importedDocName(owner, head) {
				continue
			}
			symbol := module + "#" + strings.ReplaceAll(name, "#", ".")
			owned := true
			ref := contract.Ref{Text: name, OwnedHere: &owned}
			if len(codebase.Declarations(symbol)) > 0 {
				ref.Symbol = symbol
			}
			refs = append(refs, ref)
		}
	}

	return refs
}

func importedDocName(owner engine.Match, name string) bool {
	for source := owner; source.Exists(); source = source.Parent() {
		if source.Kind() == "SourceFile" {
			_, imported := typescript.ModuleOf(source.ChildrenIn("statements")).ImportOf(name)
			return imported
		}
	}
	return false
}

var keywords = map[string]prose.Spelled{
	"IfStatement":         {Construct: prose.Condition},
	"ReturnStatement":     {Construct: prose.Return},
	"ForOfStatement":      {Construct: prose.Loop, Keywords: []string{"for", "over"}},
	"ForStatement":        {Construct: prose.Loop, Keywords: []string{"for"}},
	"WhileStatement":      {Construct: prose.ConditionalLoop, Keywords: []string{"while"}},
	"VariableStatement":   {Construct: prose.Assignment},
	"ThrowStatement":      {Construct: prose.Failure},
	"NewExpression":       {Construct: prose.Creation},
	"FunctionDeclaration": {Construct: prose.Method},
}

func ceremonyDoc(function engine.Match) bool {
	if !function.Is(engine.Function) {
		return false
	}
	for _, comment := range function.Comments() {
		if comment.Kind == "doc" && signatureTagsOnly(function, (engine.Commented{Comment: comment}).ProseLines()) {
			return true
		}
	}

	return false
}

func signatureTagsOnly(function engine.Match, lines []string) bool {
	parameters := map[string]engine.Match{}
	for _, parameter := range function.Parameters() {
		parameters[parameter.Name()] = parameter.ParameterType()
	}
	tags := 0
	for _, line := range lines {
		if line == "" {
			continue
		}
		tag, body, _ := strings.Cut(line, " ")
		body = strings.TrimSpace(body)
		written := ""
		if content, hasType := strings.CutPrefix(body, "{"); hasType {
			var closed bool
			written, body, closed = strings.Cut(content, "}")
			if !closed || written == "" {
				return false
			}
			body = strings.TrimSpace(body)
		}
		switch tag {
		case "@param":
			if !sameWrittenType(parameters[body], written) {
				return false
			}
		case "@return", "@returns":
			if body != "" || !sameWrittenType(function.ReturnType(), written) {
				return false
			}
		default:
			return false
		}
		tags++
	}

	return tags > 0
}

func sameWrittenType(declared engine.Match, documented string) bool {
	return declared.Exists() && (documented == "" || strings.TrimSpace(declared.Written()) == strings.TrimSpace(documented))
}
