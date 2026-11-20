package engine

// CapabilityTest names the value a branch tests and any local bound to its narrowed type.
type CapabilityTest struct {
	Subject Match
	Alias   string
}

func (m Match) ProbesSeveralCapabilities() bool {
	grammar := m.grammar()
	if grammar.CapabilityHub != nil {
		return grammar.CapabilityHub(m)
	}
	if grammar.CapabilityTest == nil {
		return false
	}
	probed := map[string]int{}
	for _, method := range m.Methods() {
		if field := probedCapability(method, grammar.CapabilityTest); field != "" {
			probed[field]++
			if probed[field] >= 3 {
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

func probedCapability(method Match, read func(Match) CapabilityTest) string {
	if !method.Is(Function) || method.IsConstructor() {
		return ""
	}
	body := method.Body()
	if len(body) != 2 || !body[0].Is(Branch) || len(body[0].Otherwise()) > 0 || !body[1].Is(Return) {
		return ""
	}
	branch := body[0].Body()
	if len(branch) != 1 || !branch[0].Is(Return) {
		return ""
	}
	answer, alternative := branch[0].Children(), body[1].Children()
	if len(answer) != 1 || !answer[0].Is(Call) || len(alternative) != 1 ||
		!alternative[0].Is(Literal) && !alternative[0].IsConstant() && !alternative[0].IsEmptyCollection() {
		return ""
	}
	probe := read(body[0])
	field := fieldOnSelf(probe.Subject).NameRead()
	receiver := answer[0].Receiver()
	if field == "" || fieldOnSelf(receiver).NameRead() != field && (probe.Alias == "" || receiver.Name() != probe.Alias) {
		return ""
	}
	return field
}
