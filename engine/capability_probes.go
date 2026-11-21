package engine

// CapabilityTest names the value a branch tests and any local bound to its narrowed type.
type CapabilityTest struct {
	Subject Match
	Alias   string
}

const minimumCapabilityProbes = 3

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
