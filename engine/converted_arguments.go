package engine

// CallConversion records which parameter slots a call supplies and its first conversion.
type CallConversion struct {
	Slots []string
	Key   string
}

func (m Match) isScalarType() bool {
	typed := m.Typed()
	if typed == nil {
		return false
	}
	return false
}
