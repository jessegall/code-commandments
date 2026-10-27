package engine

import "strings"

// Reading is how a node reads when the repeats of it are counted: which differences of spelling count as the same
// thing written twice. The zero value is the normalized reading, the one a duplicated body is read by, which blanks
// the names of locals and the text of data literals.
type Reading struct {
	// Exact keeps what normalising blanks. A repeated string literal needs it: normalising reads every string as
	// the same datum, which is what lets two functions differing only in their strings read as one body, and would
	// read every literal of the codebase as one repeat.
	Exact bool
	// EitherWay reads a question and its negation as one: `==` with `!=`, `is` with `is not`, `in` with `not in`.
	// A funnel is missing whichever way its question is asked.
	EitherWay bool
	// ByOwner reads the left side of a comparison as the type that declares what it reads, so one question about
	// the fields of two classes that share a name is two questions and not one. A node whose owner the scan cannot
	// name is read as nothing at all, since grouping those together would make one question of every field nobody
	// could resolve.
	ByOwner bool
}

// Repeats is how widely the node's reading occurs in the codebase: how many functions hold a node that reads the
// same, and how many files those functions are in. The node itself counts, so one that nothing repeats answers one
// and one. A node outside every function is no site, as a repeat is about the places that ask a question; a node
// its language cannot fingerprint, and one whose reading the choices refuse, answer none.
func (m Match) Repeats(as Reading) (functions, files int) {
	if m.file == nil {
		return 0, 0
	}

	read, ok := as.reads(m)
	if !ok {
		return 0, 0
	}

	// The member that names the question is neither one of the places asking it nor a place to report: its body is
	// where the answer lives, and a rule that flagged it would ask the reader to name what is already named.
	if names(m, m.EnclosingFunction()) {
		return 0, 0
	}

	held := repeats(m.file.Codebase(), m.Kind(), as)[read]

	return len(held.functions), len(held.files)
}

// spread is where one reading was found: the functions that hold it and the files those are in, each by its
// location, so the same function met twice counts once.
type spread struct {
	functions map[string]bool
	files     map[string]bool
}

// counted is the analysis one reading of one kind of node is counted by.
type counted struct {
	kind    string
	reading Reading
}

// repeats counts the codebase's nodes of that kind by how each reads. Only the nodes of the kind asked about are
// counted, so the map is bounded by the one construct a rule is about rather than by every node of the tree, and
// it is kept for as long as the codebase is: a rule asking this of a thousand comparisons walks once.
func repeats(c *Codebase, kind string, as Reading) map[string]spread {
	return Analysis(c, counted{kind, as}, func(c *Codebase) map[string]spread {
		found := map[string]spread{}

		for _, node := range c.WhereKind(kind).Get() {
			read, ok := as.reads(node)
			if !ok {
				continue
			}

			function := node.EnclosingFunction()
			if !function.Exists() || names(node, function) {
				continue
			}

			held, seen := found[read]
			if !seen {
				held = spread{functions: map[string]bool{}, files: map[string]bool{}}
			}

			held.functions[function.Location()] = true
			held.files[node.File()] = true
			found[read] = held
		}

		return found
	})
}

// names says whether the function was written to say this very node: a member OF THE TYPE that declares what the
// node reads, whose whole body returns it, IS the question's name, so it is not one of the places that ask it.
//
// Without this a funnel counts among the repeats it exists to remove: two call sites and the method that names
// them read as three places asking, and writing the funnel leaves the finding standing where it should settle it.
// The type matters as much as the shape: a free function whose body returns the same question is a place asking
// it, not a name for it, and reading every one-line function as a name would hide the very sites a rule is for.
func names(node, function Match) bool {
	declarer := node.Declarer()
	if declarer == "" || function.EnclosingType().Identity() != declarer {
		return false
	}

	body := function.Body()
	if len(body) != 1 || !body[0].Is(Return) {
		return false
	}

	returned := body[0].Children()

	return len(returned) == 1 && returned[0].Node() == node.Node()
}

// Answering is the member of the type that already asks the question this node asks: a method of it whose whole
// body returns the same thing, read the same way. It is what turns a finding into an instruction — use the name
// that exists — and it is empty where the type names the question nowhere.
func (m Match) Answering(as Reading) string {
	read, ok := as.reads(m)
	if !ok || m.file == nil {
		return ""
	}

	for _, declaration := range m.file.Codebase().Declarations(m.Declarer()) {
		if !declaration.Is(TypeDeclaration) {
			continue
		}

		for _, member := range declaration.Members() {
			// The member that NAMES the question is not answered by itself: a rule about the places asking a
			// question is about the call sites, and the method's own body is where the answer lives.
			if member.Node() == m.EnclosingFunction().Node() {
				continue
			}

			if asked, is := as.asked(member); is && asked == read {
				return declaration.Name() + "." + member.Name()
			}
		}
	}

	return ""
}

// asked is the question a member names, where its whole body returns one: the reading of the expression it
// returns, as the repeats of that expression are read.
func (as Reading) asked(member Match) (string, bool) {
	if !member.Is(Function) {
		return "", false
	}

	body := member.Body()
	if len(body) != 1 || !body[0].Is(Return) {
		return "", false
	}

	returned := body[0].Children()
	if len(returned) != 1 {
		return "", false
	}

	return as.reads(returned[0])
}

// reads is how the node reads under these choices, and whether it reads as anything at all.
func (as Reading) reads(node Match) (string, bool) {
	if !as.EitherWay && !as.ByOwner {
		hash := node.NodeHash(!as.Exact)

		return hash, hash != ""
	}

	return as.question(node)
}

// question is the node read as the question it asks rather than as the expression it is: its own kind, its
// operator with its negation folded in where either way is the same question, and each of its parts, the left side
// read as the type that declares it where the owner is what groups them.
//
// It is built from the parts rather than by changing how anything is fingerprinted, because the fingerprint is
// shared: the rules that find a function written twice must keep `==` and `!=` apart, where a missing funnel is
// missing whichever way it asks.
func (as Reading) question(node Match) (string, bool) {
	parts := []string{node.Kind(), as.operator(node.Operator())}

	for at, child := range node.Children() {
		read, ok := as.part(child, at == 0)
		if !ok {
			return "", false
		}
		parts = append(parts, read)
	}

	return "(" + strings.Join(parts, "|") + ")", true
}

// part is how one side of the question reads: the type that declares what the left side reads, where the owner is
// what groups them, and the side's own fingerprint otherwise.
func (as Reading) part(child Match, left bool) (string, bool) {
	if as.ByOwner && left {
		owner := child.Declarer()

		return "owner:" + owner, owner != ""
	}

	hash := child.NodeHash(!as.Exact)

	return hash, hash != ""
}

// negations are the operators that ask one question each way, by the one they fold into.
var negations = map[string]string{"!=": "==", "!==": "===", "is not": "is", "not in": "in", "<>": "=="}

// operator is the operator as the reading counts it: folded into its positive where either way asks the same
// question, and itself otherwise.
func (as Reading) operator(operator string) string {
	if !as.EitherWay {
		return operator
	}

	if folded, negated := negations[operator]; negated {
		return folded
	}

	return operator
}
