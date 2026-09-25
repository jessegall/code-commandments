package engine

// Finding is one sin found at one place, named by the detector that found it and the skill that fixes it.
type Finding struct {
	Detector string
	Skill    string
	Sin      string
	File     string
	Location string
	Scope    string
	Twins    []string
	Custom   bool
}

// Rule is the detector's name as a report prints it, marked when the project owns the rule.
func (f Finding) Rule() string {
	if f.Custom {
		return f.Detector + " (custom)"
	}

	return f.Detector
}
