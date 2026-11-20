package php

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

type classStringParameter struct {
	name     string
	position int
}

func (t *Types) indexDocumentedProperties(class string, declaration engine.Match) {
	doc, ok := DocComment(declaration)
	if !ok {
		return
	}
	for _, line := range docblockContent(doc.Text) {
		parts := strings.Fields(line)
		if len(parts) < 3 || parts[0] != "@property" && parts[0] != "@property-read" {
			continue
		}
		if field, named := strings.CutPrefix(parts[2], "$"); named {
			set(t.propertyTypes, class, field, Resolve(parts[1], declaration.Source()))
		}
	}
}

func (t *Types) propertyRead(class, field string) held {
	declaring := t.DeclaringClassOf(class, field)
	native := t.fieldType[declaring][field]
	if actual := t.propertyTypes[class][field]; class != declaring && native.class != "" && engine.IsA(t.program, actual, native.class) && actual != native.class {
		return held{class: actual}
	}
	if declaring == "" {
		return typed(Written(t.program.codebase.PropertyType(contract.PHP, class, field)))
	}
	return native
}

func (t *Types) indexClassStringReturn(class string, method engine.Match) {
	parameter := returnedClassString(method)
	if parameter == "" {
		return
	}
	for position, declared := range Params(method) {
		if declared.Name() == parameter {
			set(t.classStringReturns, class, method.Name(), classStringParameter{parameter, position})
			return
		}
	}
}

func (t *Types) callReturn(call engine.Match, class, name string) held {
	native := t.returnType[class][name]
	parameter, specializes := t.classStringReturns[class][name]
	if !specializes || native.class == "" {
		return native
	}
	for at, arg := range Arguments(call) {
		argument := arg.Child("name").Name()
		if argument != parameter.name && (argument != "" || at != parameter.position) {
			continue
		}
		value := arg.Child("value")
		if !isNamedClassLiteral(value) {
			return native
		}
		if actual := value.Class(); actual != native.class && engine.IsA(t.program, actual, native.class) {
			return held{class: actual}
		}
		return native
	}
	return native
}

func returnedClassString(method engine.Match) string {
	doc, ok := DocComment(method)
	if !ok {
		return ""
	}
	templates, parameters := map[string]bool{}, map[string]string{}
	var returned string
	for _, line := range docblockContent(doc.Text) {
		parts := strings.Fields(line)
		if len(parts) < 2 {
			continue
		}
		switch parts[0] {
		case "@template":
			templates[parts[1]] = true
		case "@return":
			returned = parts[1]
		case "@param":
			if after, yes := strings.CutPrefix(parts[1], "class-string<"); yes && len(parts) >= 3 {
				if template, complete := strings.CutSuffix(after, ">"); complete {
					parameters[template] = strings.TrimPrefix(parts[2], "$")
				}
			}
		}
	}
	if !templates[returned] {
		return ""
	}
	return parameters[returned]
}
