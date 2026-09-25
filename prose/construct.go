package prose

// Construct is a kind of code construct, and the plain-English words a reader uses for it, so "loop over the
// orders" measures against a loop and "fail when empty" against a throw, in any language. Each engine maps its own
// nodes onto a construct and adds the keyword its language spells it with.
type Construct string

// The constructs every language's nodes map onto.
const (
	Loop            Construct = "loop"
	ConditionalLoop Construct = "conditional-loop"
	Condition       Construct = "condition"
	Otherwise       Construct = "otherwise"
	Return          Construct = "return"
	Break           Construct = "break"
	Continue        Construct = "continue"
	Branch          Construct = "branch"
	Attempt         Construct = "attempt"
	Recovery        Construct = "recovery"
	Failure         Construct = "failure"
	Removal         Construct = "removal"
	Output          Construct = "output"
	Type            Construct = "type"
	Contract        Construct = "contract"
	Method          Construct = "method"
	Field           Construct = "field"
	Constant        Construct = "constant"
	Creation        Construct = "creation"
	Assignment      Construct = "assignment"
	Accumulation    Construct = "accumulation"
	Import          Construct = "import"
	Scope           Construct = "scope"
)

// Words is the words a reader uses for the construct.
func (c Construct) Words() []string {
	return map[Construct][]string{
		Loop:            {"loop", "iterate", "every", "each"},
		ConditionalLoop: {"loop", "until", "repeat"},
		Condition:       {"if", "when", "check", "whether", "otherwise"},
		Otherwise:       {"else", "otherwise"},
		Return:          {"return", "give", "yield", "result"},
		Break:           {"stop", "leave"},
		Continue:        {"skip", "next"},
		Branch:          {"match", "case", "branch"},
		Attempt:         {"try", "catch", "handle"},
		Recovery:        {"catch", "handle", "error"},
		Failure:         {"throw", "raise", "fail", "error"},
		Removal:         {"remove", "drop", "clear"},
		Output:          {"print", "output"},
		Type:            {"class", "type"},
		Contract:        {"interface", "contract"},
		Method:          {"method", "function"},
		Field:           {"property", "field"},
		Constant:        {"const", "constant"},
		Creation:        {"new", "create", "make", "build"},
		Assignment:      {"set", "assign", "store"},
		Accumulation:    {"add", "increase", "update"},
		Import:          {"import"},
		Scope:           {"with", "open"},
	}[c]
}

// Spelled is a construct as one language spells it: the construct, and the keywords that language writes it with.
type Spelled struct {
	Construct Construct
	Keywords  []string
}

// Words is the keywords, then the words a reader uses for the construct.
func (s Spelled) Words() []string {
	return append(append([]string{}, s.Keywords...), s.Construct.Words()...)
}
