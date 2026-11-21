package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func VocabularyWords(argument engine.Match) []string {
	if argument.Kind() != "PropertyAccessExpression" {
		return nil
	}
	reference := argument.Child("expression").Refers()
	if reference == "" {
		return nil
	}
	name := argument.Child("name").Name()
	for _, owner := range argument.Codebase().Declarations(reference) {
		members := owner.ChildrenIn("members")
		if !slices.ContainsFunc(members, func(member engine.Match) bool {
			_, named := stringConstant(member)
			return named && member.Name() == name
		}) {
			continue
		}
		var words []string
		for _, member := range members {
			if word, named := stringConstant(member); named {
				words = append(words, word)
			}
		}

		return words
	}

	return nil
}

func stringConstant(member engine.Match) (string, bool) {
	if member.Kind() != "EnumMember" && (member.Kind() != "PropertyDeclaration" || !member.HasModifier("static") || !member.HasModifier("readonly")) {
		return "", false
	}
	if member.HasModifier("private") || member.HasModifier("protected") {
		return "", false
	}
	value := member.Child("initializer")
	if !value.Exists() || value.Node().LiteralKind != "string" {
		return "", false
	}

	return value.Text()
}
