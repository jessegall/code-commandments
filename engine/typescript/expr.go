package typescript

import (
	"slices"
	"strconv"
	"strings"
)

// An expression written as a string, read by the PHP tool's own expression parser: the reads a component
// extraction makes of a template binding or a script initialiser, taken the way the PHP tool takes them.

// ExprKind is what an expression is.
type ExprKind string

const (
	IdentifierExpr  ExprKind = "identifier"
	LiteralExpr     ExprKind = "literal"
	MemberExpr      ExprKind = "member"
	IndexExpr       ExprKind = "index"
	CallExpr        ExprKind = "call"
	UnaryExpr       ExprKind = "unary"
	BinaryExpr      ExprKind = "binary"
	ConditionalExpr ExprKind = "conditional"
	ArrayExpr       ExprKind = "array"
	ObjectExpr      ExprKind = "object"
	ArrowExpr       ExprKind = "arrow"
	ForExpr         ExprKind = "for"
	AssignExpr      ExprKind = "assign"
	UnknownExpr     ExprKind = "unknown"
)

// Expr is an expression as the PHP tool's parser reads it.
type Expr struct {
	Kind     ExprKind
	Name     string
	Raw      string
	Op       string
	Property string
	Optional bool
	Aliases  []string
	Keys     []string
	HasKey   []bool
	children map[string][]*Expr
	order    []string
}

func newExpr(kind ExprKind) *Expr {
	return &Expr{Kind: kind, children: map[string][]*Expr{}}
}

func (e *Expr) set(key string, children ...*Expr) *Expr {
	if _, seen := e.children[key]; !seen {
		e.order = append(e.order, key)
	}
	e.children[key] = children

	return e
}

// child is the one expression under the key, an unknown expression when there is none.
func (e *Expr) child(key string) *Expr {
	if children := e.children[key]; len(children) > 0 && children[0] != nil {
		return children[0]
	}

	return newExpr(UnknownExpr)
}

func (e *Expr) list(key string) []*Expr {
	var children []*Expr
	for _, child := range e.children[key] {
		if child != nil {
			children = append(children, child)
		}
	}

	return children
}

// Is says whether the expression is of the kind.
func (e *Expr) Is(kind ExprKind) bool { return e.Kind == kind }

// Target is an assignment's target.
func (e *Expr) Target() *Expr { return e.child("target") }

// Iterable is what a v-for loops over.
func (e *Expr) Iterable() *Expr { return e.child("iterable") }

// SubExpressions is every expression directly under this one, in the order it holds them.
func (e *Expr) SubExpressions() []*Expr {
	var all []*Expr
	for _, key := range e.order {
		all = append(all, e.list(key)...)
	}

	return all
}

// Roots is each name the expression reads data from, once.
func (e *Expr) Roots() []string {
	var roots []string
	switch e.Kind {
	case IdentifierExpr:
		roots = []string{e.Name}
	case MemberExpr:
		roots = e.child("object").Roots()
	case IndexExpr:
		roots = append(e.child("object").Roots(), e.child("index").Roots()...)
	case CallExpr:
		roots = append(e.child("callee").Roots(), rootsOf(e.list("arguments"))...)
	case UnaryExpr:
		roots = e.child("argument").Roots()
	case BinaryExpr:
		roots = append(e.child("left").Roots(), e.child("right").Roots()...)
	case ConditionalExpr:
		roots = append(append(e.child("test").Roots(), e.child("then").Roots()...), e.child("else").Roots()...)
	case ArrayExpr:
		roots = rootsOf(e.list("elements"))
	case ObjectExpr:
		roots = rootsOf(e.list("values"))
	case ArrowExpr:
		params := rootsOf(e.list("params"))
		for _, root := range e.child("body").Roots() {
			if !slices.Contains(params, root) {
				roots = append(roots, root)
			}
		}
	case AssignExpr:
		roots = append(e.child("target").Roots(), e.child("value").Roots()...)
	}

	return unique(roots)
}

func rootsOf(expressions []*Expr) []string {
	var roots []string
	for _, expression := range expressions {
		roots = append(roots, expression.Roots()...)
	}

	return roots
}

func unique(names []string) []string {
	var kept []string
	for _, name := range names {
		if !slices.Contains(kept, name) {
			kept = append(kept, name)
		}
	}

	return kept
}

// CalledFunctions is each name the expression calls as a plain function, once.
func (e *Expr) CalledFunctions() []string {
	var names []string
	var gather func(*Expr)
	gather = func(node *Expr) {
		if callee := node.child("callee"); node.Kind == CallExpr && callee.Kind == IdentifierExpr {
			names = append(names, callee.Name)
		}
		for _, child := range node.SubExpressions() {
			gather(child)
		}
	}
	gather(e)

	return unique(names)
}

// Chains is every member chain of two or more names the expression reads, a call's method left out.
func (e *Expr) Chains() [][]string {
	var chains [][]string
	var gather func(*Expr)
	gather = func(node *Expr) {
		if node.Kind == CallExpr {
			callee := node.child("callee")
			receiver := callee
			if callee.Is(MemberExpr) || callee.Is(IndexExpr) {
				receiver = callee.child("object")
			}
			gather(receiver)
			for _, argument := range node.list("arguments") {
				gather(argument)
			}

			return
		}
		if segments, ok := node.AsChain(); ok {
			if len(segments) >= 2 {
				chains = append(chains, segments)
			}

			return
		}
		for _, child := range node.SubExpressions() {
			gather(child)
		}
	}
	gather(e)

	return chains
}

// AsChain is the names a plain data path reads: `order.customer.name`.
func (e *Expr) AsChain() ([]string, bool) {
	switch e.Kind {
	case IdentifierExpr:
		return []string{e.Name}, true
	case MemberExpr:
		if base, ok := e.child("object").AsChain(); ok {
			return append(base, e.Property), true
		}
	}

	return nil, false
}

// Callee is the name a call calls, when it calls a plain name.
func (e *Expr) Callee() (string, bool) {
	if callee := e.child("callee"); e.Kind == CallExpr && callee.Kind == IdentifierExpr {
		return callee.Name, true
	}

	return "", false
}

// Argument is a call's argument at the index.
func (e *Expr) Argument(index int) (*Expr, bool) {
	if arguments := e.list("arguments"); e.Kind == CallExpr && index < len(arguments) {
		return arguments[index], true
	}

	return nil, false
}

// ObjectEntries is an object literal's `key: value` pairs, in order, a key written twice holding its last value.
func (e *Expr) ObjectEntries() ([]string, map[string]*Expr) {
	if e.Kind != ObjectExpr {
		return nil, nil
	}
	values := e.list("values")
	var keys []string
	entries := map[string]*Expr{}
	for index, value := range values {
		if index >= len(e.Keys) || !e.HasKey[index] {
			continue
		}
		key := e.Keys[index]
		if _, seen := entries[key]; !seen {
			keys = append(keys, key)
		}
		entries[key] = value
	}

	return keys, entries
}

// ObjectShape is an object literal's type, each field its inferred type or unknown.
func (e *Expr) ObjectShape() (string, bool) {
	keys, entries := e.ObjectEntries()
	if e.Kind != ObjectExpr || len(keys) == 0 {
		return "", false
	}
	fields := make([]string, 0, len(keys))
	for _, key := range keys {
		typed, inferred := entries[key].InferType()
		if !inferred {
			typed = "unknown"
		}
		fields = append(fields, key+": "+typed)
	}

	return "{ " + strings.Join(fields, "; ") + " }", true
}

// CallExpression is a call to a plain name with arguments that are each a plain read or a literal.
type CallExpression struct {
	Name      string
	Arguments []string
}

// Arity is how many arguments the call passes.
func (c CallExpression) Arity() int { return len(c.Arguments) }

// TrailingArguments is the arguments as they follow a first one: `, a, b`, or nothing.
func (c CallExpression) TrailingArguments() string {
	if len(c.Arguments) == 0 {
		return ""
	}

	return ", " + strings.Join(c.Arguments, ", ")
}

// AsCall is the expression as a call to a plain name with plainly written arguments.
func (e *Expr) AsCall() (CallExpression, bool) {
	callee := e.child("callee")
	if e.Kind != CallExpr || callee.Kind != IdentifierExpr {
		return CallExpression{}, false
	}
	var arguments []string
	for _, argument := range e.list("arguments") {
		source := argument.Source()
		if source == "" || strings.Contains(source, "…") {
			return CallExpression{}, false
		}
		arguments = append(arguments, source)
	}

	return CallExpression{Name: callee.Name, Arguments: arguments}, true
}

// Source is the expression written again from what the parser keeps: a name, a literal, a member, an index, or a
// call as `callee(…)`; empty for anything else.
func (e *Expr) Source() string {
	switch e.Kind {
	case IdentifierExpr:
		return e.Name
	case LiteralExpr:
		return e.Raw
	case MemberExpr:
		dot := "."
		if e.Optional {
			dot = "?."
		}

		return e.child("object").Source() + dot + e.Property
	case IndexExpr:
		return e.child("object").Source() + "[" + e.child("index").Source() + "]"
	case CallExpr:
		return e.child("callee").Source() + "(…)"
	}

	return ""
}

// literalType is the type a literal is of.
func (e *Expr) literalType() (string, bool) {
	if e.Kind != LiteralExpr {
		return "", false
	}
	first := byte(0)
	if e.Raw != "" {
		first = e.Raw[0]
	}
	switch {
	case e.Raw == "true" || e.Raw == "false":
		return "boolean", true
	case e.Raw == "null":
		return "null", true
	case e.Raw == "undefined":
		return "undefined", true
	case first == '"' || first == '\'' || first == '`':
		return "string", true
	case isNumeric(e.Raw):
		return "number", true
	}

	return "", false
}

// isNumeric answers as PHP's is_numeric does on a literal: an optional sign, digits with at most one point, an
// optional exponent; leading whitespace allowed, nothing else.
func isNumeric(text string) bool {
	trimmed := strings.TrimLeft(text, " \t\n\r\v\f")
	trimmed = strings.TrimRight(trimmed, " \t\n\r\v\f")
	if trimmed == "" || strings.ContainsAny(trimmed, "_xXbBoO") || strings.ContainsAny(trimmed, "iInN") {
		return false
	}
	_, err := strconv.ParseFloat(trimmed, 64)

	return err == nil || strings.Contains(err.Error(), "value out of range")
}

// InferType is the type the expression is of, where it can be told for certain from the expression alone.
func (e *Expr) InferType() (string, bool) {
	switch e.Kind {
	case LiteralExpr:
		return e.literalType()
	case UnaryExpr:
		switch e.Op {
		case "!", "delete":
			return "boolean", true
		case "typeof":
			return "string", true
		case "-", "+", "++", "--":
			return "number", true
		case "void":
			return "undefined", true
		}

		return "", false
	case BinaryExpr:
		switch {
		case slices.Contains([]string{"===", "!==", "==", "!=", "<", ">", "<=", ">=", "instanceof", "in"}, e.Op):
			return "boolean", true
		case slices.Contains([]string{"-", "*", "/", "%", "**"}, e.Op):
			return "number", true
		case slices.Contains([]string{"&&", "||", "??"}, e.Op):
			return unionType(e.child("left"), e.child("right"))
		}

		return "", false
	case ConditionalExpr:
		return unionType(e.child("then"), e.child("else"))
	case ArrayExpr:
		elements := e.list("elements")
		if len(elements) == 0 {
			return "", false
		}
		element := ""
		for _, node := range elements {
			typed, ok := node.InferType()
			if !ok || element != "" && typed != element {
				return "", false
			}
			element = typed
		}

		return element + "[]", true
	}

	return "", false
}

// ReturnType is the type an arrow function's expression body is of.
func (e *Expr) ReturnType() (string, bool) {
	if e.Kind != ArrowExpr {
		return "", false
	}

	return e.child("body").InferType()
}

func unionType(nodes ...*Expr) (string, bool) {
	var types []string
	for _, node := range nodes {
		typed, ok := node.InferType()
		if !ok {
			return "", false
		}
		if !slices.Contains(types, typed) {
			types = append(types, typed)
		}
	}

	return strings.Join(types, "|"), len(types) > 0
}

// exprPunctuation is every operator the expression lexer cuts, longest first where they share a start.
var exprPunctuation = []string{
	"...", "??=", "||=", "&&=", "**=", "?.", "===", "!==", "==", "!=", "<=", ">=", "&&", "||", "??", "=>",
	"**", "+=", "-=", "*=", "/=", "%=", "++", "--",
	".", "(", ")", "[", "]", "{", "}", ",", "?", ":", "!", "<", ">", "+", "-", "*", "/", "%", "=",
}

// lexExpression cuts an expression as the PHP tool's expression lexer does: a byte no operator starts is dropped.
func lexExpression(source string) []lexeme {
	var tokens []lexeme
	for at := 0; at < len(source); {
		c := source[at]
		switch {
		case isCtypeSpace(c):
			at++
		case c == '"' || c == '\'' || c == '`':
			end := skipString(source, at, c)
			tokens = append(tokens, lexeme{kind: stringToken, value: source[at:end], start: at, end: end})
			at = end
		case isAlpha(c) || c == '_' || c == '$':
			end := at
			for end < len(source) && (isAlnum(source[end]) || source[end] == '_' || source[end] == '$') {
				end++
			}
			tokens = append(tokens, lexeme{kind: identifierToken, value: source[at:end], start: at, end: end})
			at = end
		case c >= '0' && c <= '9':
			end := at
			for end < len(source) && (isAlnum(source[end]) || source[end] == '.' || source[end] == '_') {
				end++
			}
			tokens = append(tokens, lexeme{kind: numberToken, value: source[at:end], start: at, end: end})
			at = end
		default:
			matched := false
			for _, punct := range exprPunctuation {
				if strings.HasPrefix(source[at:], punct) {
					tokens = append(tokens, lexeme{kind: punctToken, value: punct, start: at, end: at + len(punct)})
					at += len(punct)
					matched = true

					break
				}
			}
			if !matched {
				at++
			}
		}
	}

	return tokens
}

var exprPrecedence = map[string]int{
	"??": 1, "||": 2, "&&": 3, "===": 4, "!==": 4, "==": 4, "!=": 4,
	"<": 5, ">": 5, "<=": 5, ">=": 5, "+": 6, "-": 6, "*": 7, "/": 7, "%": 7,
}

var (
	prefixOperators  = []string{"!", "-", "+", "++", "--", "..."}
	prefixKeywords   = []string{"typeof", "await", "new", "yield", "void", "delete"}
	keywordOperators = map[string]int{"instanceof": 5, "in": 5}
	assignments      = []string{"=", "+=", "-=", "*=", "/=", "%=", "**=", "??=", "||=", "&&="}
	postfixUpdates   = []string{"++", "--"}
)

type exprParser struct {
	tokens []lexeme
	pos    int
}

// ParseExpression reads an expression.
func ParseExpression(source string) *Expr {
	return (&exprParser{tokens: lexExpression(source)}).expression()
}

// ParseFor reads a v-for's value: the names it binds, and what it loops over.
func ParseFor(source string) *Expr {
	parser := &exprParser{tokens: lexExpression(source)}
	loop := newExpr(ForExpr)
	loop.Aliases = parser.forAliases()
	if token := parser.peek(); token.isIdentifier("in") || token.isIdentifier("of") {
		parser.next()
	}

	return loop.set("iterable", parser.expression())
}

func (p *exprParser) peek() lexeme {
	if p.pos < len(p.tokens) {
		return p.tokens[p.pos]
	}

	return lexeme{kind: noToken}
}

func (p *exprParser) tokenAt(at int) lexeme {
	if at < len(p.tokens) {
		return p.tokens[at]
	}

	return lexeme{kind: noToken}
}

func (p *exprParser) next()                     { p.pos++ }
func (p *exprParser) isPunct(value string) bool { return p.peek().isPunct(value) }
func (p *exprParser) eof() bool                 { return p.peek().isNone() }

func (p *exprParser) expect(value string) {
	if p.isPunct(value) {
		p.next()
	}
}

func (p *exprParser) inside(closer string) bool { return !p.isPunct(closer) && !p.eof() }

func groupDepthChange(token lexeme) int {
	switch {
	case token.isGroupOpener():
		return 1
	case token.isGroupCloser():
		return -1
	}

	return 0
}

func typeDepthChange(token lexeme) int {
	switch {
	case token.isTypeOpener():
		return 1
	case token.isTypeCloser():
		return -1
	}

	return 0
}

func (p *exprParser) forAliases() []string {
	var aliases []string
	bracket, destructure := 0, 0
	for !p.eof() {
		token := p.peek()
		if bracket == 0 && (token.isIdentifier("in") || token.isIdentifier("of")) {
			break
		}
		step := groupDepthChange(token)
		bracket += step
		if !token.isPunct("(") && !token.isPunct(")") {
			destructure += step
		}
		if step == 0 && destructure == 0 && token.isIdentifier("") {
			aliases = append(aliases, token.value)
		}
		p.next()
	}

	return aliases
}

func (p *exprParser) expression() *Expr {
	left := p.ternary()
	if operator := p.peek(); operator.kind == punctToken && slices.Contains(assignments, operator.value) {
		p.next()
		assign := newExpr(AssignExpr)
		assign.Op = operator.value

		return assign.set("target", left).set("value", p.expression())
	}

	return left
}

func (p *exprParser) ternary() *Expr {
	test := p.binary(0)
	if !p.isPunct("?") {
		return test
	}
	p.next()
	then := p.expression()
	p.expect(":")

	return newExpr(ConditionalExpr).set("test", test).set("then", then).set("else", p.expression())
}

func (p *exprParser) binary(minPrecedence int) *Expr {
	left := p.unary()
	for p.peek().isIdentifier("as") {
		p.next()
		p.skipTypeAnnotation()
	}
	for {
		operator := p.peek().value
		precedence, binds := p.binaryPrecedence()
		if !binds || precedence < minPrecedence {
			return left
		}
		p.next()
		right := p.binary(precedence + 1)
		binary := newExpr(BinaryExpr)
		binary.Op = operator
		left = binary.set("left", left).set("right", right)
	}
}

func (p *exprParser) binaryPrecedence() (int, bool) {
	token := p.peek()
	switch token.kind {
	case punctToken:
		precedence, ok := exprPrecedence[token.value]

		return precedence, ok
	case identifierToken:
		precedence, ok := keywordOperators[token.value]

		return precedence, ok
	}

	return 0, false
}

func (p *exprParser) unary() *Expr {
	token := p.peek()
	if p.isPrefixOperator(token) {
		p.next()
		unary := newExpr(UnaryExpr)
		unary.Op = token.value

		return unary.set("argument", p.unary())
	}
	operand := p.postfix()
	if update := p.peek(); update.kind == punctToken && slices.Contains(postfixUpdates, update.value) {
		p.next()
		unary := newExpr(UnaryExpr)
		unary.Op = update.value

		return unary.set("argument", operand)
	}

	return operand
}

func (p *exprParser) isPrefixOperator(token lexeme) bool {
	if token.kind == punctToken {
		return slices.Contains(prefixOperators, token.value)
	}

	return token.kind == identifierToken && slices.Contains(prefixKeywords, token.value) && p.beginsOperand(p.pos+1)
}

func (p *exprParser) beginsOperand(at int) bool {
	token := p.tokenAt(at)

	return token.isIdentifier("") || token.is(stringToken, "") || token.is(numberToken, "") || token.isGroupOpener() ||
		token.kind == punctToken && slices.Contains(prefixOperators, token.value)
}

func (p *exprParser) skipTypeAnnotation() {
	depth := 0
	for !p.eof() {
		token := p.peek()
		if token.isTypeCloser() && depth == 0 {
			return
		}
		if depth == 0 && token.kind == punctToken && !token.isTypeOpener() && !token.isPunct(".") && !token.isPunct("|") && !token.isPunct("&") {
			return
		}
		depth += typeDepthChange(token)
		p.next()
	}
}

func (p *exprParser) postfix() *Expr {
	node := p.primary()
	for {
		extended := p.extended(node)
		if extended == nil {
			return node
		}
		node = extended
	}
}

func (p *exprParser) extended(node *Expr) *Expr {
	switch {
	case p.isPunct("."):
		p.next()
		member := newExpr(MemberExpr)
		member.Property = p.name()

		return member.set("object", node)
	case p.isPunct("?."):
		p.next()
		if p.isPunct("[") || p.isPunct("(") {
			return p.tail(node, true)
		}
		member := newExpr(MemberExpr)
		member.Property, member.Optional = p.name(), true

		return member.set("object", node)
	case p.isPunct("["):
		p.next()
		index := p.expression()
		p.expect("]")

		return newExpr(IndexExpr).set("object", node).set("index", index)
	case p.isPunct("("):
		return newExpr(CallExpr).set("callee", node).set("arguments", p.arguments()...)
	case p.isPunct("!"):
		p.next()

		return node
	}

	return nil
}

func (p *exprParser) tail(node *Expr, optional bool) *Expr {
	if p.isPunct("[") {
		p.next()
		index := p.expression()
		p.expect("]")
		indexed := newExpr(IndexExpr).set("object", node).set("index", index)
		indexed.Optional = optional

		return indexed
	}
	call := newExpr(CallExpr).set("callee", node).set("arguments", p.arguments()...)
	call.Optional = optional

	return call
}

func (p *exprParser) primary() *Expr {
	token := p.peek()
	if token.value == "async" && p.beginsFunction(p.pos+1) {
		p.next()

		return p.primary()
	}
	if token.isIdentifier("function") {
		return p.functionExpression()
	}
	if token.isIdentifier("") {
		p.next()
		if slices.Contains([]string{"true", "false", "null", "undefined"}, token.value) {
			literal := newExpr(LiteralExpr)
			literal.Raw = token.value

			return literal
		}
		if p.isPunct("=>") {
			p.next()
			param := newExpr(IdentifierExpr)
			param.Name = token.value

			return p.arrowBody(newExpr(ArrowExpr).set("params", param))
		}
		identifier := newExpr(IdentifierExpr)
		identifier.Name = token.value

		return identifier
	}
	if token.is(numberToken, "") || token.is(stringToken, "") {
		p.next()
		literal := newExpr(LiteralExpr)
		literal.Raw = token.value

		return literal
	}
	switch {
	case p.isPunct("("):
		return p.group()
	case p.isPunct("["):
		return p.arrayLiteral()
	case p.isPunct("{"):
		return p.objectLiteral()
	}
	if !token.isNone() {
		p.next()
	}

	return newExpr(UnknownExpr)
}

func (p *exprParser) group() *Expr {
	p.expect("(")
	if p.closingParenLeadsToArrow(p.pos) {
		params := p.arrowParameters()
		p.expect(")")
		p.skipReturnType()
		if p.isPunct("=>") {
			p.next()
		}

		return p.arrowBody(newExpr(ArrowExpr).set("params", params...))
	}
	if p.isPunct(")") {
		p.next()

		return newExpr(UnknownExpr)
	}
	inner := p.expression()
	p.expect(")")

	return inner
}

// arrowBody reads an arrow's body: an expression, or a block, which it holds as an unknown expression.
func (p *exprParser) arrowBody(arrow *Expr) *Expr {
	if !p.isPunct("{") {
		return arrow.set("body", p.expression())
	}
	depth := 0
	for {
		depth += groupDepthChange(p.peek())
		p.next()
		if depth <= 0 || p.eof() {
			break
		}
	}

	return arrow.set("body", newExpr(UnknownExpr))
}

func (p *exprParser) functionExpression() *Expr {
	p.next()
	if p.isPunct("*") {
		p.next()
	}
	if p.peek().isIdentifier("") {
		p.next()
	}
	p.expect("(")
	params := p.arrowParameters()
	p.expect(")")
	p.skipReturnType()

	return p.arrowBody(newExpr(ArrowExpr).set("params", params...))
}

func (p *exprParser) skipReturnType() {
	if !p.isPunct(":") {
		return
	}
	p.next()
	depth := 0
	previous := lexeme{kind: noToken}
	for !p.eof() {
		token := p.peek()
		endsType := previous.isIdentifier("") || previous.isTypeCloser()
		if depth == 0 && (token.isPunct("=>") || token.isPunct("{") && endsType) {
			return
		}
		depth += typeDepthChange(token)
		previous = token
		p.next()
	}
}

func (p *exprParser) beginsFunction(at int) bool {
	token := p.tokenAt(at)
	switch {
	case token.isIdentifier("function"):
		return true
	case token.isIdentifier(""):
		return p.tokenAt(at + 1).isPunct("=>")
	}

	return token.isPunct("(") && p.closingParenLeadsToArrow(at+1)
}

func (p *exprParser) closingParenLeadsToArrow(from int) bool {
	depth := 0
	for at := from; at < len(p.tokens); at++ {
		token := p.tokens[at]
		if token.isGroupCloser() && depth == 0 {
			after := p.tokenAt(at + 1)

			return after.isPunct("=>") || after.isPunct(":") && p.returnTypeLeadsToArrow(at+2)
		}
		depth += groupDepthChange(token)
	}

	return false
}

func (p *exprParser) returnTypeLeadsToArrow(from int) bool {
	depth := 0
	for at := from; at < len(p.tokens); at++ {
		token := p.tokens[at]
		if depth == 0 && token.isPunct("=>") {
			return true
		}
		if depth == 0 && (token.isTypeCloser() || token.isPunct(",") || token.isPunct(";") || token.isPunct("?") || token.isPunct(":") || token.isPunct("=")) {
			return false
		}
		depth += typeDepthChange(token)
	}

	return false
}

func (p *exprParser) arrowParameters() []*Expr {
	var params []*Expr
	for p.inside(")") {
		switch {
		case p.isPunct("{") || p.isPunct("["):
			for _, name := range p.patternNames() {
				param := newExpr(IdentifierExpr)
				param.Name = name
				params = append(params, param)
			}
		case p.peek().isIdentifier(""):
			param := newExpr(IdentifierExpr)
			param.Name = p.peek().value
			params = append(params, param)
			p.next()
		}
		p.skipToParamBoundary()
		if p.isPunct(",") {
			p.next()
		}
	}

	return params
}

func (p *exprParser) patternNames() []string {
	var names []string
	depth := 0
	for {
		change := groupDepthChange(p.peek())
		depth += change
		if change == 0 && p.peek().isIdentifier("") {
			names = append(names, p.peek().value)
		}
		p.next()
		if depth <= 0 || p.eof() {
			return names
		}
	}
}

func (p *exprParser) skipToParamBoundary() {
	depth := 0
	for !p.eof() {
		if depth == 0 && (p.isPunct(",") || p.isPunct(")")) {
			return
		}
		depth += groupDepthChange(p.peek())
		p.next()
	}
}

func (p *exprParser) arrayLiteral() *Expr {
	p.expect("[")
	var elements []*Expr
	for !p.isPunct("]") && !p.eof() {
		elements = append(elements, p.expression())
		if !p.isPunct(",") {
			break
		}
		p.next()
	}
	p.expect("]")

	return newExpr(ArrayExpr).set("elements", elements...)
}

func (p *exprParser) objectLiteral() *Expr {
	p.expect("{")
	object := newExpr(ObjectExpr)
	var values []*Expr
	for !p.isPunct("}") && !p.eof() {
		key, keyed := objectKey(p.peek())
		p.next()
		if p.isPunct(":") {
			p.next()
			object.Keys = append(object.Keys, key)
			object.HasKey = append(object.HasKey, keyed)
			values = append(values, p.expression())
		}
		if !p.isPunct(",") {
			break
		}
		p.next()
	}
	p.expect("}")

	return object.set("values", values...)
}

func objectKey(token lexeme) (string, bool) {
	switch {
	case token.is(stringToken, ""):
		return unquote(token.value), true
	case token.isIdentifier("") || token.is(numberToken, ""):
		return token.value, true
	}

	return "", false
}

func unquote(raw string) string {
	if len(raw) >= 2 {
		return raw[1 : len(raw)-1]
	}

	return raw
}

func (p *exprParser) arguments() []*Expr {
	p.expect("(")
	var arguments []*Expr
	for p.inside(")") {
		arguments = append(arguments, p.expression())
		if !p.isPunct(",") {
			break
		}
		p.next()
	}
	p.expect(")")

	return arguments
}

func (p *exprParser) name() string {
	token := p.peek()
	if !token.isIdentifier("") {
		return ""
	}
	p.next()

	return token.value
}

// Interpolations is the expression inside each `{{ }}` of a text, trimmed, the empty ones left out.
func Interpolations(text string) []string {
	var bodies []string
	for at := 0; ; {
		open := strings.Index(text[at:], "{{")
		if open < 0 {
			return bodies
		}
		open += at
		closing := strings.Index(text[open+2:], "}}")
		if closing < 0 {
			return bodies
		}
		closing += open + 2
		if body := strings.Trim(text[open+2:closing], " \t\n\r\x00\x0b"); body != "" {
			bodies = append(bodies, body)
		}
		at = closing + 2
	}
}

// CalleeName is the name a call calls: a plain name, or the method a member call names.
func (e *Expr) CalleeName() string {
	callee := e.child("callee")
	switch callee.Kind {
	case IdentifierExpr:
		return callee.Name
	case MemberExpr:
		return callee.Property
	}

	return ""
}

// Arguments is a call's arguments.
func (e *Expr) Arguments() []*Expr {
	return e.list("arguments")
}

// Value is a literal's value: a string's text without its quotes, anything else as written.
func (e *Expr) Value() string {
	if e.Raw != "" && (e.Raw[0] == '"' || e.Raw[0] == '\'' || e.Raw[0] == '`') {
		return unquote(e.Raw)
	}

	return e.Raw
}
