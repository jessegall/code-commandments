package engine

import "strings"

// Reading is how a node reads when the repeats of it are counted: which differences of spelling count as the same thing written twice.
type Reading struct {
	Exact     bool
	EitherWay bool
	ByOwner   bool
}

func (m Match) Repeats(as Reading) (places, files int) {
	if m.file == nil {
		return 0, 0
	}

	read, ok := as.reads(m)
	if !ok {
		return 0, 0
	}

	if names(m, siteOf(m)) {
		return 0, 0
	}

	held := repeats(m.file.Codebase(), m.Kind(), as)[read]

	return len(held.places), len(held.files)
}

// spread is where one reading was found: the places that hold it and the files those are in, each by its location, so the same place met twice counts once.
type spread struct {
	places map[string]bool
	files  map[string]bool
}

func siteOf(node Match) Match {
	if function := node.EnclosingFunction(); function.Exists() {
		return function
	}

	return node.EnclosingType()
}

// counted is the analysis one reading of one kind of node is counted by.
type counted struct {
	kind    string
	reading Reading
}

func repeats(c *Codebase, kind string, as Reading) map[string]spread {
	return Analysis(c, counted{kind, as}, func(c *Codebase) map[string]spread {
		found := map[string]spread{}

		for _, node := range c.WhereKind(kind).Get() {
			read, ok := as.reads(node)
			if !ok {
				continue
			}

			site := siteOf(node)
			if !site.Exists() || names(node, site) {
				continue
			}

			held, seen := found[read]
			if !seen {
				held = spread{places: map[string]bool{}, files: map[string]bool{}}
			}

			held.places[site.Location()] = true
			held.files[node.File()] = true
			found[read] = held
		}

		return found
	})
}

func names(node, function Match) bool {
	if !function.Is(Function) {
		return false
	}

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

func (as Reading) reads(node Match) (string, bool) {
	if !as.EitherWay && !as.ByOwner {
		hash := node.NodeHash(!as.Exact)

		return hash, hash != ""
	}

	return as.question(node)
}

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

func (as Reading) part(child Match, left bool) (string, bool) {
	if as.ByOwner && left {
		owner := child.Declarer()

		return "owner:" + owner, owner != ""
	}

	hash := child.NodeHash(!as.Exact)

	return hash, hash != ""
}

var negations = map[string]string{"!=": "==", "!==": "===", "is not": "is", "not in": "in", "<>": "=="}

func (as Reading) operator(operator string) string {
	if !as.EitherWay {
		return operator
	}

	if folded, negated := negations[operator]; negated {
		return folded
	}

	return operator
}
