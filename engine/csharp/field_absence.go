package csharp

import "github.com/jessegall/code-commandments/engine"

func nullableFields(class engine.Match) []engine.Match {
	var found []engine.Match
	for _, member := range class.Members() {
		switch member.Kind() {
		case "PropertyDeclaration":
			if member.AdmitsAbsence() {
				found = append(found, member)
			}
		case "FieldDeclaration":
			if (Node{Match: member}).DeclaresNullableState() {
				found = append(found, member.Child("Declaration").ChildrenIn("Variables")...)
			}
		}
	}
	return found
}

func keyedStore(call engine.Match) engine.Match {
	if !call.Is(engine.Call) {
		return engine.Match{}
	}
	target := call.Node().Target
	if target == nil || target.Type != "global::System.Collections.Generic.CollectionExtensions" || target.Name != "GetValueOrDefault" || len(call.Arguments()) != 1 {
		return engine.Match{}
	}
	return call.Receiver()
}
