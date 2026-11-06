package engine

// Finding is one sin found at one place, named by the detector that found it and the skill that fixes it.
type Finding struct {
	Detector string
	Skill    string
	Sin      string
	File     string
	Line     int
	Location string
	Scope    string
	Twins    []string
	Custom   bool
	Group    string
}

func (f Finding) Rule() string {
	if f.Custom {
		return f.Detector + " (custom)"
	}

	return f.Detector
}
