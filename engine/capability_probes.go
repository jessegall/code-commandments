package engine

// CapabilityTest names the value a branch tests and any local bound to its narrowed type.
type CapabilityTest struct {
	Subject Match
	Alias   string
}

const minimumCapabilityProbes = 3
