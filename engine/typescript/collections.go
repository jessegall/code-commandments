package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// IsModuleLevelDeclaration says whether the variable is declared at the module's own level, not inside a function.
func (n Node) IsModuleLevelDeclaration() bool {
	return n.Kind() == "VariableDeclaration" && !n.EnclosingFunction().Exists()
}

// declared is the value the variable is declared with, through a `new Set([…])` or `new Map([…])` to the array
// written inside it: what a module writes a collection of names as.
func (n Node) declared() Node {
	for _, child := range n.Children() {
		if child.Kind() == "ArrayLiteralExpression" {
			return Node{Match: child}
		}

		if child.Kind() == "NewExpression" {
			for _, held := range child.Children() {
				if held.Kind() == "ArrayLiteralExpression" {
					return Node{Match: held}
				}
			}
		}
	}

	return Node{}
}

// StringMembers is every member of the declared collection when ALL of them are string literals: the shape a
// collection of names takes. Nothing where the value is not written out, or holds anything else.
func (n Node) StringMembers() []string {
	var names []string

	for _, member := range n.declared().Children() {
		text, literal := member.Text()
		if member.Kind() != "StringLiteral" || !literal || text == "" {
			return nil
		}
		names = append(names, text)
	}

	return names
}

// KeyedInstanceNames is, for each property every object of the declared collection carries as a string, the value
// each gives it: the ways a registry of objects names its instances, found rather than assumed, so no key has to
// be spelled out here. Nothing where the members are not all objects, or fewer than two.
func (n Node) KeyedInstanceNames() map[string][]string {
	objects := n.declared().Children()
	if len(objects) < 2 {
		return nil
	}

	keyed := map[string][]string{}
	for _, object := range objects {
		if object.Kind() != "ObjectLiteralExpression" {
			return nil
		}

		for _, property := range object.Children() {
			value := property.Child("initializer")
			text, literal := value.Text()

			if property.Kind() != "PropertyAssignment" || !literal || text == "" {
				continue
			}
			keyed[property.Name()] = append(keyed[property.Name()], text)
		}
	}

	for key, values := range keyed {
		if len(values) != len(objects) {
			delete(keyed, key)
		}
	}

	return keyed
}

// membershipQuestions are how JavaScript asks a collection whether it holds one thing: a Set's and a Map's own
// question, and the one an array answers.
var membershipQuestions = []string{"has", "includes", "contains"}

// AsksAbountOne says whether the call asks a collection named `held` whether it holds a single thing — the
// question a trait answers. Walking a collection is deliberately not that question: a loop that acts on each
// member is a caller choosing what to act on, and nothing in the tree tells that from a trait.
func (n Node) AsksAboutOne(held string) bool {
	if n.Kind() != "CallExpression" {
		return false
	}

	for _, child := range n.Children() {
		if child.Kind() != "PropertyAccessExpression" {
			continue
		}

		asked := false
		for _, question := range membershipQuestions {
			asked = asked || child.Name() == question
		}

		for _, named := range child.Children() {
			if asked && named.Kind() == "Identifier" && named.Name() == held {
				return true
			}
		}
	}

	return false
}

// walkMutators are the methods that MOVE or DROP what a walk has not reached yet: an array's own, every one of
// which leaves the walk counting on positions that have shifted, so an element is skipped or read twice.
//
// What grows a collection is deliberately not here. `push`, `add` and `set` hand a walk entries it then visits,
// and a walk that grows as it goes is how JavaScript writes a work queue — excalidraw walks a Set of element ids
// and adds each one's bound elements to it as it goes, on purpose and with a comment saying so. Nothing in the
// tree tells that from the same lines written by mistake, so a rule over them would be a guess about intent.
//
// `delete` and `clear` are not here either: they belong to a Set and a Map alone, and the language says what a
// walk sees after them — the entries still there, and never the removed one again. Pruning while walking is how a
// map is pruned in JavaScript.
//
// A method that answers a NEW collection — `filter`, `map`, `concat`, `slice` — changes nothing and is not here.
var walkMutators = []string{"pop", "shift", "unshift", "splice", "sort", "reverse", "fill", "copyWithin"}

// ChangesTheCollectionItsLoopWalks says whether the call changes, in place, the very collection the walk around it
// is walking: one of the methods that change a collection, on an expression written exactly as the walk's own
// subject. A `for…of` reads an iterator that was taken when the walk began, so an element is skipped, read twice
// or never reached. Walking a copy is a different expression — `[...items]`, `items.slice()`, `Array.from(items)`,
// `items.filter(…)` — and is left alone, as is a change that ends the walk the moment it is made.
//
// A `forEach` walks the same way and is read the same way, with one difference: a `return` in its callback leaves
// that one call and not the walk, so nothing there ends it and the exemption cannot apply.
func (n Node) ChangesTheCollectionItsLoopWalks() bool {
	changed, mutates := n.mutatedCollection()
	if !mutates {
		return false
	}
	if loop, walks := n.loopWalking(changed); walks {
		return !n.leavesThatLoopAtOnce(loop)
	}

	return n.insideEachOver(changed)
}

// mutatedCollection is the collection the call changes in place, and whether it changes one.
func (n Node) mutatedCollection() (Node, bool) {
	callee := n.Child("expression")
	if n.Kind() != "CallExpression" || callee.Kind() != "PropertyAccessExpression" {
		return Node{}, false
	}
	if !slices.Contains(walkMutators, callee.Child("name").Written()) {
		return Node{}, false
	}

	return Node{callee.Child("expression")}, true
}

// loopWalking is the `for…of` around the node that walks the collection, and whether one does. The node has to sit
// in the loop's body: a change written in the subject itself runs once, before the walk.
func (n Node) loopWalking(collection Node) (Node, bool) {
	for held := n.Match; held.Exists(); held = held.Parent() {
		loop := held.Parent()
		if !loop.Is(engine.Loop) || loop.Kind() != "ForOfStatement" || held.Node().Field != "statement" {
			continue
		}
		if walked := loop.Child("expression"); walked.SameSyntax(collection.Match) {
			return Node{loop}, true
		}
	}

	return Node{}, false
}

// insideEachOver says whether the node stands in the callback of a `forEach` over the collection: the same walk,
// written as a call.
func (n Node) insideEachOver(collection Node) bool {
	for held := n.Match; held.Exists(); held = held.Parent() {
		each := Node{held.Parent()}
		if each.Kind() != "CallExpression" || held.Node().Field != "arguments" {
			continue
		}
		callee := each.Child("expression")
		if callee.Kind() != "PropertyAccessExpression" || callee.Child("name").Written() != "forEach" {
			continue
		}
		receiver := Node{callee.Child("expression")}
		if receiver.SameSyntax(collection.Match) {
			return true
		}
	}

	return false
}

// leavesThatLoopAtOnce says whether the change ends the walk where it stands: the last statement of the block it
// sits in hands control out of the loop, so the ground it moved is never read again.
func (n Node) leavesThatLoopAtOnce(loop Node) bool {
	block := n.blockHolding()
	if len(block) == 0 {
		return false
	}

	return block[len(block)-1].leaves(loop)
}

// blockHolding is the statements of the innermost block the node sits in, in source order.
func (n Node) blockHolding() []Node {
	for held := n.Match; held.Exists(); held = held.Parent() {
		if !held.Parent().Is(engine.Block) {
			continue
		}
		var statements []Node
		for _, each := range held.Parent().Children() {
			statements = append(statements, Node{each})
		}

		return statements
	}

	return nil
}

// leaves says whether the statement hands control out of the loop for good: a return or a throw leaves the
// function around it, and a break leaves the loop it sits in.
func (n Node) leaves(loop Node) bool {
	if slices.Contains([]string{"ReturnStatement", "ThrowStatement"}, n.Kind()) {
		return true
	}

	return n.Kind() == "BreakStatement" && n.Closest(engine.Loop).Node() == loop.Node()
}
