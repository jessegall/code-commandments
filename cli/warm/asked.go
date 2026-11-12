package warm

// Request is a judging a run asks for: the folder the run was started in, which is what the project, the config and
// the scope are read from, the arguments as they were typed, and whether an agent asked.
type Request struct {
	Judge    []string `json:"judge"`
	In       string   `json:"in"`
	Progress bool     `json:"progress,omitempty"`
	Agent    bool     `json:"agent,omitempty"`
}
