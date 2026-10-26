package engine

// Finding is one sin found at one place, named by the detector that found it and the skill that fixes it. A rule
// whose findings recur in groups names the group, and its twins are the other findings of the group.
type Finding struct {
	Detector string
	Skill    string
	Sin      string
	File     string
	// Line is the line the sin was found at, which Location names too. A reader wants the location; a scope
	// about the lines a change touched wants the number, and parsing it back out of the text would be reading
	// our own writing.
	Line     int
	Location string
	Scope    string
	Twins    []string
	Custom   bool
	Group    string
}

// Rule is the detector's name as a report prints it, marked when the project owns the rule.
func (f Finding) Rule() string {
	if f.Custom {
		return f.Detector + " (custom)"
	}

	return f.Detector
}
