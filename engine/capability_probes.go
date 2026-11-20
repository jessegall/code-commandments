package engine

// CapabilityTest names the value a branch tests and any local bound to its narrowed type.
type CapabilityTest struct {
	Subject Match
	Alias   string
}

const minimumCapabilityProbes = 3

func (m Match) ProbesSeveralCapabilities() bool {
	grammar := m.grammar()
	if grammar.CapabilityTest == nil || m.TypeKind() == "trait" {
		return false
	}
	methods := m.Methods()
	if len(methods) < minimumCapabilityProbes {
		return false
	}
	probed := map[string]int{}
	for _, method := range methods {
		if field := probedCapability(method, &grammar); field != "" {
			probed[field]++
			if probed[field] >= minimumCapabilityProbes {
				return true
			}
		}
	}
	return false
}

func methodsByOwner(codebase *Codebase) map[string][]Match {
	return Analysis(codebase, "methods-by-type-owner", func(codebase *Codebase) map[string][]Match {
		methods := map[string][]Match{}
		for _, method := range codebase.Reporting().WhereFunction().Get() {
			if read := method.grammar().MethodOwner; read != nil {
				if owner := read(method); owner != "" {
					methods[owner] = append(methods[owner], method)
				}
			}
		}
		return methods
	})
}

func probedCapability(method Match, grammar *Grammar) string {
	if !method.Is(Function) || method.IsConstructor() {
		return ""
	}
	body := method.Body()
	if len(body) == 1 {
		if field := guardedCapability(body[0].returnedValue(), grammar); field != "" {
			return field
		}
	}
	test, answer, alternative := capabilityReturns(body, grammar.ConditionalArms)
	if !test.Exists() || !capabilityFallback(alternative, grammar) {
		return ""
	}
	return capabilityField(grammar.CapabilityTest(test, test.Condition()), answer)
}

func guardedCapability(value Match, grammar *Grammar) string {
	if !value.isAnd() {
		return ""
	}
	parts := value.andParts()
	if len(parts) < 2 {
		return ""
	}
	probe := grammar.CapabilityTest(Match{}, parts[0])
	for _, answer := range parts[1:] {
		if field := capabilityField(probe, answer); field != "" {
			return field
		}
	}
	return ""
}

func capabilityField(probe CapabilityTest, answer Match) string {
	if (!answer.Is(Call) && !answer.Is(MemberAccess)) || answer.Is(NullSafe) {
		return ""
	}
	field := fieldOnSelf(probe.Subject).NameRead()
	receiver := answer.Receiver()
	if field == "" || fieldOnSelf(receiver).NameRead() != field && (probe.Alias == "" || receiver.Name() != probe.Alias) {
		return ""
	}
	return field
}

func capabilityReturns(body []Match, arms func(Match) (Match, Match)) (Match, Match, Match) {
	if len(body) == 1 && arms != nil {
		choice := body[0].returnedValue()
		answer, alternative := arms(choice)
		if alternative.Exists() {
			return choice, answer, alternative
		}
	}
	if len(body) == 2 && body[0].Is(Branch) && !body[0].hasAlternative() && body[1].Is(Return) {
		branch := body[0].Body()
		if len(branch) == 1 && branch[0].Is(Return) {
			return body[0], branch[0].returnedValue(), body[1].returnedValue()
		}
	}
	return Match{}, Match{}, Match{}
}

func capabilityFallback(node Match, grammar *Grammar) bool {
	if read := grammar.CapabilityFallback; read != nil {
		return read(node)
	}
	return node.Is(Literal) || node.IsConstant() || node.IsEmptyCollection()
}
