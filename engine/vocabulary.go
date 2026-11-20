package engine

// vocabularySlot identifies one parameter position at a resolved target.
type vocabularySlot struct {
	target   string
	position int
}

func UnnamedVocabularyLiterals(codebase *Codebase) []Match {
	for _, file := range codebase.Files() {
		grammar := spokenIn(file.Language()).grammar
		if grammar != nil && grammar.UnnamedVocabulary != nil {
			return grammar.UnnamedVocabulary(codebase)
		}
	}
	words := Analysis(codebase, "argument-vocabulary", vocabularyOf)
	var findings []Match
	for _, call := range codebase.WhereCall().Reject(Match.IsTest).Get() {
		for position, argument := range call.Arguments() {
			if argument.Node().Literal != "string" {
				continue
			}
			word, ok := argument.Text()
			if ok && words[vocabularySlot{call.TargetSymbol(), position}][word] {
				findings = append(findings, argument)
			}
		}
	}

	return findings
}

func vocabularyOf(codebase *Codebase) map[vocabularySlot]map[string]bool {
	words := map[vocabularySlot]map[string]bool{}
	for _, call := range codebase.Reporting().WhereCall().Reject(Match.IsTest).Get() {
		target, read := call.TargetSymbol(), call.grammar().VocabularyWords
		if target == "" || read == nil {
			continue
		}
		for position, argument := range call.Arguments() {
			values := read(argument)
			if len(values) == 0 {
				continue
			}
			slot := vocabularySlot{target, position}
			if words[slot] == nil {
				words[slot] = map[string]bool{}
			}
			for _, word := range values {
				words[slot][word] = true
			}
		}
	}

	return words
}
