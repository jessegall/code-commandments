// Package laravel is what the engine knows of Laravel, stated once: its facades, routes, container, Eloquent and queues, and the analyses that read them across a codebase.
package laravel

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const (
	FacadeNamespace     = `Illuminate\Support\Facades\`
	ServiceProvider     = `Illuminate\Support\ServiceProvider`
	Model               = `Illuminate\Database\Eloquent\Model`
	DatabaseFacade      = FacadeNamespace + `DB`
	Request             = `Illuminate\Http\Request`
	FormRequest         = `Illuminate\Foundation\Http\FormRequest`
	McpRequest          = `Laravel\Mcp\Request`
	McpTool             = `Laravel\Mcp\Server\Tool`
	Inertia             = `Inertia\Inertia`
	InertiaHelper       = "inertia"
	Controller          = `Illuminate\Routing\Controller`
	Route               = `Illuminate\Support\Facades\Route`
	ShouldQueue         = `Illuminate\Contracts\Queue\ShouldQueue`
	Event               = `Illuminate\Support\Facades\Event`
	ConsoleCommand      = `Illuminate\Console\Command`
	EloquentBuilder     = `Illuminate\Database\Eloquent\Builder`
	EloquentRelation    = `Illuminate\Database\Eloquent\Relations\Relation`
	Factory             = `Illuminate\Database\Eloquent\Factories\Factory`
	HasFactory          = `Illuminate\Database\Eloquent\Factories\HasFactory`
	AuthGuard           = `Illuminate\Contracts\Auth\Guard`
	AuthUserProvider    = `Illuminate\Contracts\Auth\UserProvider`
	Arrayable           = `Illuminate\Contracts\Support\Arrayable`
	ValidationException = `Illuminate\Validation\ValidationException`
)

func BindsModelFactory(reference engine.Match) bool {
	if reference.Parent().Kind() == "UseItem" {
		return importsOnlyAFactoryBinding(reference)
	}

	return returnsBoundFactory(reference)
}

func importsOnlyAFactoryBinding(reference engine.Match) bool {
	program := php.ProgramOf(reference.Codebase())
	if !program.IsA(reference.Name(), Factory) {
		return false
	}
	bound := false
	for _, use := range reference.Root().Descendants() {
		if use.Name() != reference.Name() || use.Parent().Kind() == "UseItem" || !(php.Node{Match: use}).IsClassReference() {
			continue
		}
		if !returnsBoundFactory(use) {
			return false
		}
		bound = true
	}

	return bound
}

func returnsBoundFactory(reference engine.Match) bool {
	method := (php.Node{Match: reference}).EnclosingFunctionLike()
	if method.Kind() != "Stmt_ClassMethod" || method.Name() != "newFactory" || !method.HasModifier("static") || !method.HasModifier("protected") {
		return false
	}
	statements := method.Body()
	if len(statements) != 1 || !statements[0].Is(engine.Return) {
		return false
	}
	call := statements[0].Child("expr")
	factory := strings.TrimLeft(php.StaticCallClass(call), `\`)
	if factory != strings.TrimLeft(reference.Name(), `\`) || php.CallName(call) != "new" || len(php.Arguments(call)) != 0 {
		return false
	}
	program := php.ProgramOf(reference.Codebase())
	model := php.EnclosingClassName(method.Match)

	return program.IsA(factory, Factory) && program.IsA(model, Model) && program.Uses(model, HasFactory)
}

var CollectionTypes = []string{
	`Illuminate\Support\Collection`,
	`Illuminate\Database\Eloquent\Collection`,
	`Illuminate\Support\LazyCollection`,
}

func (n Node) HandsOutItsOwnCollection(typeOf func(engine.Match) string) bool {
	body := n.Match.ChildrenIn("stmts")
	if n.Match.Kind() != "Stmt_ClassMethod" || len(body) != 1 || body[0].Kind() != "Stmt_Return" {
		return false
	}

	returned := body[0].Child("expr")
	if returned.Kind() != "Expr_PropertyFetch" || !returned.Child("var").Is(engine.SelfReference) {
		return false
	}

	return slices.Contains(CollectionTypes, strings.TrimLeft(typeOf(returned), "?\\"))
}

var BuilderTypes = []string{
	EloquentBuilder,
	`Illuminate\Database\Query\Builder`,
	EloquentRelation,
	`Illuminate\Database\Eloquent\Relations\HasMany`,
	`Illuminate\Database\Eloquent\Relations\BelongsTo`,
	`Illuminate\Database\Eloquent\Relations\BelongsToMany`,
}

func CallReceiverClass(call engine.Match) string {
	if class := php.CallReceiverClass(call); class != "" {
		return class
	}
	receiver := call.Child("var")
	if !receiver.Exists() {
		return ""
	}
	program := php.ProgramOf(call.Codebase())
	if php.CallName(receiver) == "query" && program.IsA(php.StaticCallClass(receiver), Model) {
		return EloquentBuilder
	}
	if php.CallName(receiver) == "state" {
		if class := CallReceiverClass(receiver); program.IsA(class, Factory) {
			return class
		}
	}

	return ""
}

func CollectionMapConsumer(call engine.Match) engine.Match {
	if php.CallName(call) != "map" {
		return engine.Match{}
	}
	class := php.CallReceiverClass(call)
	program := php.ProgramOf(call.Codebase())
	if !slices.ContainsFunc(CollectionTypes, func(collection string) bool { return program.IsA(class, collection) }) {
		return engine.Match{}
	}

	mapped := call
	for mapped.Parent().Is(engine.Call) && mapped.Node().InRole() == "var" {
		next := mapped.Parent()
		if !slices.Contains([]string{"filter", "values", "all"}, php.CallName(next)) {
			return engine.Match{}
		}
		mapped = next
	}

	return php.ArgumentConsumer(mapped)
}

var (
	RouteVerbs         = []string{"get", "post", "put", "patch", "delete", "options", "match", "any"}
	RouterTypes        = []string{`Illuminate\Routing\Router`, `Illuminate\Contracts\Routing\Registrar`}
	ContainerResolvers = []string{"make", "makeWith", "get"}
	BindingMethods     = []string{"bind", "bindIf", "singleton", "singletonIf", "scoped", "scopedIf", "instance"}
	RelationMethods    = []string{"hasOne", "hasOneThrough", "hasMany", "hasManyThrough", "belongsTo", "belongsToMany", "morphOne", "morphMany", "morphToMany", "morphedByMany"}
	QueueHooks         = []string{"failed", "middleware", "retryUntil", "backoff", "uniqueId", "tags", "displayName"}
	EventDispatchers   = []string{"dispatch", "dispatchIf", "dispatchUnless", "broadcast"}
	RouteNameLookups   = []string{"route", "to_route", "signedRoute", "temporarySignedRoute"}
	URLGenerators      = []string{`Illuminate\Support\Facades\URL`, "URL", `Illuminate\Support\Facades\Redirect`, "Redirect"}
	URLHelpers         = []string{"redirect", "url"}
	RequestTypes       = []string{Request, FormRequest, McpRequest}
	CastContracts      = []string{`Illuminate\Contracts\Database\Eloquent\CastsAttributes`, `Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes`}
	BindingAttributes  = []string{
		`Illuminate\Database\Eloquent\Attributes\ObservedBy`,
		`Illuminate\Database\Eloquent\Attributes\ScopedBy`,
		`Illuminate\Database\Eloquent\Attributes\CollectedBy`,
		`Illuminate\Database\Eloquent\Attributes\UsePolicy`,
	}
)

// BoundaryKind is a kind of entry point a class is by what it extends, beside an HTTP route action.
type BoundaryKind struct {
	Name string
	Base string
}

var BoundaryKinds = []BoundaryKind{{"console", ConsoleCommand}, {"mcp", McpTool}}

// Node is a match read in Laravel's terms.
type Node struct {
	engine.Match
}

func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

func RendersInertiaPage(node engine.Match) bool {
	switch node.Kind() {
	case "Expr_StaticCall":
		class, name := node.Child("class"), node.Child("name")

		return isName(class) && strings.TrimLeft(class.Name(), `\`) == Inertia && name.Kind() == "Identifier" && name.Name() == "render"
	case "Expr_FuncCall":
		name := node.Child("name")

		return isName(name) && strings.TrimLeft(name.Name(), `\`) == InertiaHelper
	}

	return false
}

func (n Node) IsFacadeCall() bool {
	return strings.HasPrefix(php.StaticCallClass(n.Match), FacadeNamespace)
}

func (n Node) RouteNameReference() string {
	if !n.isRouteNameLookup() {
		return ""
	}

	return StringArgument(n.Match, 0)
}

func (n Node) isRouteNameLookup() bool {
	if name := n.Child("name"); n.Kind() == "Expr_FuncCall" && isName(name) {
		return slices.Contains(RouteNameLookups, name.Name())
	}
	if n.Kind() != "Expr_MethodCall" && n.Kind() != "Expr_StaticCall" {
		return false
	}
	if name := n.Child("name"); name.Kind() != "Identifier" || !slices.Contains(RouteNameLookups, name.Name()) {
		return false
	}
	receiver := n.Child("var")
	if n.Kind() == "Expr_StaticCall" {
		receiver = n.Child("class")
	}
	if isName(receiver) {
		return slices.Contains(URLGenerators, strings.TrimLeft(receiver.Name(), `\`))
	}

	return receiver.Kind() == "Expr_FuncCall" && isName(receiver.Child("name")) && slices.Contains(URLHelpers, receiver.Child("name").Name())
}

func (n Node) ListenedEventClass() string {
	class, name := n.Child("class"), n.Child("name")
	if n.Kind() != "Expr_StaticCall" || !isName(class) || !slices.Contains([]string{Event, "Event"}, strings.TrimLeft(class.Name(), `\`)) ||
		name.Kind() != "Identifier" || name.Name() != "listen" {
		return ""
	}

	return ClassArgument(n.Match, 0)
}

func (n Node) BoundAbstract() string {
	return BoundAbstractOf(n.Match)
}

func BoundAbstractOf(node engine.Match) string {
	if !slices.Contains(BindingMethods, CallName(node)) {
		return ""
	}

	return ClassArgument(node, 0)
}

func (n Node) IsRouteAction() bool {
	return RouteActionsOf(n.Codebase()).IsAction(php.EnclosingClassName(n.Match), php.EnclosingFunctionName(n.Match))
}

func (n Node) DelegatesToRouteAction() bool {
	call, ok := n.soleDelegationCall()
	if !ok || call.Child("name").Kind() != "Identifier" {
		return false
	}
	self := php.EnclosingClassName(n.Match)
	receiver := php.TypesOf(n.Codebase()).TypeIn(call.Child("var"), n.Match, self)
	if receiver == "" || strings.TrimLeft(receiver, `\`) == strings.TrimLeft(self, `\`) {
		return false
	}

	return RouteActionsOf(n.Codebase()).IsRegisteredAction(receiver, call.Child("name").Name())
}

func (n Node) ThinDelegationTarget() string {
	return n.delegationTarget(false)
}

func (n Node) DelegatedOperation() string {
	return n.delegationTarget(true)
}

func (n Node) delegationTarget(includeCalls bool) string {
	call, ok := n.soleDelegationCall()
	if !ok || call.Child("name").Kind() != "Identifier" {
		return ""
	}
	receiver := php.TypesOf(n.Codebase()).TypeIn(call.Child("var"), n.Match, php.EnclosingClassName(n.Match))
	if receiver == "" {
		return ""
	}

	return ActionKey(receiver, call.Child("name").Name()) + delegationArguments(call, includeCalls)
}

func delegationArguments(call engine.Match, includeCalls bool) string {
	var built []string
	for _, argument := range call.ChildrenIn("args") {
		value := argument.Child("value")
		if class := (php.Node{Match: value}).NewClassName(); class != "" {
			built = append(built, class)
		} else if receiver := php.ReceiverTypeOf(value); includeCalls && receiver != "" && value.Child("name").Kind() == "Identifier" {
			built = append(built, ActionKey(receiver, value.Child("name").Name()))
		}
	}
	if len(built) == 0 {
		return ""
	}

	return "(" + strings.Join(built, ",") + ")"
}

func (n Node) soleDelegationCall() (engine.Match, bool) {
	if n.Kind() != "Stmt_ClassMethod" {
		return engine.Match{}, false
	}
	var statements []engine.Match
	for _, statement := range n.Children() {
		if statement.Node().InRole() == "stmts" {
			statements = append(statements, statement)
		}
	}
	if len(statements) != 1 {
		return engine.Match{}, false
	}
	var expression engine.Match
	switch statements[0].Kind() {
	case "Stmt_Return", "Stmt_Expression":
		expression = statements[0].Child("expr")
	}

	return expression, expression.Kind() == "Expr_MethodCall"
}

func (n Node) InServiceProvider() bool {
	return php.ProgramOf(n.Codebase()).Extends(php.EnclosingClassName(n.Match), ServiceProvider)
}

func (n Node) ReplacesUnboundedBuilderRead() bool {
	if !slices.Contains([]string{"chunk", "each", "chunkById", "lazy", "lazyById"}, n.Name()) {
		return false
	}
	class := php.EnclosingClass(n.Match)
	if !php.ProgramOf(n.Codebase()).Extends(php.EnclosingClassName(n.Match), `Illuminate\Database\Eloquent\Builder`) {
		return false
	}
	for _, statement := range n.ChildrenIn("stmts") {
		throw := statement.Child("expr")
		call := throw.Child("expr")
		arguments := php.Arguments(call)
		if throw.Kind() != "Expr_Throw" || call.Kind() != "Expr_StaticCall" || len(arguments) != 2 {
			continue
		}
		refused, _ := arguments[0].Child("value").Node().Value.Text()
		replacement, _ := arguments[1].Child("value").Node().Value.Text()
		name, _, _ := strings.Cut(replacement, "(")
		if _, declared := php.Method(class, name); refused == n.Name() && name != refused && declared {
			return true
		}
	}

	return false
}

func (n Node) IsEloquentCast() bool {
	program := php.ProgramOf(n.Codebase())
	for _, contract := range CastContracts {
		if program.Implements(php.EnclosingClassName(n.Match), contract) {
			return true
		}
	}

	return false
}

func (n Node) InQueuedJobHook() bool {
	return php.ProgramOf(n.Codebase()).Implements(php.EnclosingClassName(n.Match), ShouldQueue) &&
		slices.Contains(QueueHooks, php.EnclosingFunctionName(n.Match))
}

func (n Node) ReceiverIsModel() bool {
	receiver := php.ReceiverTypeOf(n.Match)

	return receiver != "" && php.ProgramOf(n.Codebase()).Extends(receiver, Model)
}

func (n Node) QueriedModel() string {
	if n.Kind() != "Expr_MethodCall" || (n.Parent().Kind() == "Expr_MethodCall" && n.Node().InRole() == "var") {
		return ""
	}
	calls, root := 1, n.Child("var")
	for root.Kind() == "Expr_MethodCall" {
		calls, root = calls+1, root.Child("var")
	}
	model := strings.TrimLeft(php.StaticCallClass(root), `\`)
	if calls < 2 || model == "" || !php.ProgramOf(n.Codebase()).Extends(model, Model) {
		return ""
	}

	return model
}

func (n Node) IsMassArrayUpdate() bool {
	if name := n.Child("name"); n.Kind() != "Expr_MethodCall" || name.Kind() != "Identifier" || name.Name() != "update" {
		return false
	}
	if receiver := n.Child("var"); receiver.Kind() == "Expr_Variable" && receiver.Name() == "this" {
		return false
	}
	arguments := php.Arguments(n.Match)

	return len(arguments) > 0 && arguments[0].Child("value").Kind() == "Expr_Array"
}

func StringArgument(call engine.Match, position int) string {
	arguments := php.Arguments(call)
	if position >= len(arguments) {
		return ""
	}
	value := arguments[position].Child("value")
	if value.Kind() != "Scalar_String" {
		return ""
	}
	text, _ := value.Node().Value.Text()

	return text
}

func ClassArgument(call engine.Match, position int) string {
	arguments := php.Arguments(call)
	if position >= len(arguments) {
		return ""
	}

	return classConstant(arguments[position].Child("value"))
}

func classConstant(fetch engine.Match) string {
	return php.ClassLiteral(fetch)
}

func CallName(node engine.Match) string {
	name := node.Child("name")
	switch node.Kind() {
	case "Expr_MethodCall", "Expr_NullsafeMethodCall", "Expr_StaticCall":
		if name.Kind() == "Identifier" {
			return name.Name()
		}
	case "Expr_FuncCall":
		if isName(name) {
			return name.Name()
		}
	}

	return ""
}

func (n Node) ResolvesFromContainer() bool {
	call := php.Node{Match: n.Match}
	if n.Kind() == "Expr_FuncCall" {
		return (call.CallsFunction("app") || call.CallsFunction("resolve")) && call.FirstArgIsClassLiteral()
	}
	if n.Kind() != "Expr_MethodCall" || !slices.Contains(ContainerResolvers, call.MethodCallName()) {
		return false
	}

	return isClassLiteral(call.Argument(0).Match) && php.IsContainerCall(n.Match)
}

// BuildsWithParameters is a container resolution handed the constructor's runtime values, `make(X::class, [...])`: the
// container is used as a factory, since no constructor could be injected with values known only at the call.
func (n Node) BuildsWithParameters() bool {
	return php.Node{Match: n.Match}.ArgumentCount() >= 2
}

func (n Node) DropsEveryGlobalScope() bool {
	name := strings.ToLower(php.CallName(n.Match))
	if name != "withoutglobalscopes" && name != "withoutglobalscopesexcept" {
		return false
	}
	if !n.hasEloquentScopeReceiver() {
		return false
	}
	arguments := n.ChildrenIn("args")
	if len(arguments) == 0 {
		return true
	}
	if len(arguments) != 1 || arguments[0].Kind() != "Arg" {
		return false
	}
	value := php.Node{Match: arguments[0].Child("value")}
	if name == "withoutglobalscopes" {
		return value.IsNull()
	}

	return value.IsEmptyArrayLiteral()
}

func (n Node) hasEloquentScopeReceiver() bool {
	class := strings.TrimLeft(CallReceiverClass(n.Match), `\`)
	program := php.ProgramOf(n.Codebase())

	return program.IsA(class, Model) || program.IsA(class, EloquentBuilder) || program.IsA(class, EloquentRelation)
}

func isClassLiteral(value engine.Match) bool {
	return php.ClassLiteral(value) != ""
}

func isName(node engine.Match) bool {
	return strings.HasPrefix(node.Kind(), "Name")
}

var QueryRunners = []string{
	"get", "first", "firstOrFail", "firstOrCreate", "firstOr", "sole", "find", "findOrFail", "findOr",
	"findMany", "count", "exists", "doesntExist", "sum", "avg", "min", "max", "pluck", "value", "paginate",
	"simplePaginate", "cursorPaginate", "toArray", "save", "update", "delete", "increment", "decrement",
	"create", "updateOrCreate", "insert", "refresh",
}

func (n Node) RunsAQueryInALoop() bool {
	name := n.Child("name")
	if n.Kind() != "Expr_MethodCall" && n.Kind() != "Expr_StaticCall" {
		return false
	}
	if name.Kind() != "Identifier" || !slices.Contains(QueryRunners, name.Name()) {
		return false
	}
	walked, inAForeach := n.foreachAround()
	if !inAForeach {
		return false
	}
	if walked.isChunked() || n.loadsAWholeChunk(walked) || n.isInATransactionPerTurn() || n.queriesTheTableItWalks(walked) {
		return false
	}

	return n.reachesTheDatabase()
}

func (n Node) queriesTheTableItWalks(walked Node) bool {
	root := n.chainRoot()

	return root.Kind() == "Expr_StaticCall" && php.CallName(root.Match) == "table" && (php.Node{Match: root.Match}).Argument(0).SameSyntax(walked.Parent().Child("valueVar"))
}

func (n Node) loadsAWholeChunk(walked Node) bool {
	loop := walked.Parent()
	chunk := loop.Child("valueVar")
	if chunk.Kind() != "Expr_Variable" {
		return false
	}
	for call := n.Match; call.Kind() == "Expr_MethodCall"; {
		method := php.Node{Match: call}
		if php.CallName(call) == "whereIn" && method.Argument(1).SameSyntax(chunk) {
			return walked.readsAssignedChunks(loop)
		}
		call = call.Child("var")
	}
	return false
}

func (n Node) readsAssignedChunks(loop engine.Match) bool {
	for _, use := range slices.Backward(php.Trace(n.Match)) {
		assignment := use.Node.Parent()
		if !use.IsWrite() || !assignment.EndsBefore(loop) {
			continue
		}
		return assignment.Kind() == "Expr_Assign" && assignment.Parent().Parent() == loop.Parent() && isArrayChunk(assignment.Child("expr"))
	}
	return false
}

func (n Node) isInATransactionPerTurn() bool {
	for held := n.Parent(); held.Exists() && held.Kind() != "Stmt_Foreach"; held = held.Parent() {
		call := held.Parent().Parent()
		if (held.Kind() == "Expr_Closure" || held.Kind() == "Expr_ArrowFunction") && held.Parent().Kind() == "Arg" && call.Child("name").Name() == "transaction" {
			return true
		}
	}

	return false
}

func (n Node) foreachAround() (Node, bool) {
	for from, held := n.Match, n.Parent(); held.Exists(); from, held = held, held.Parent() {
		if held.Kind() == "Stmt_Foreach" && held.Child("expr").Node() != from.Node() {
			return Node{Match: held.Child("expr")}, true
		}
	}

	return Node{}, false
}

func (n Node) isChunked() bool {
	if isArrayChunk(n.Match) {
		return true
	}
	held := n
	for held.Exists() {
		if held.Kind() == "Expr_MethodCall" && held.Child("name").Name() == "chunk" {
			return true
		}
		if held.Kind() != "Expr_MethodCall" && held.Kind() != "Expr_StaticCall" {
			return false
		}
		held = Node{Match: held.Child("var")}
	}

	return false
}

func isArrayChunk(call engine.Match) bool {
	return call.Kind() == "Expr_FuncCall" && isName(call.Child("name")) && strings.TrimLeft(call.Child("name").Name(), `\`) == "array_chunk"
}

func (n Node) reachesTheDatabase() bool {
	root := n.chainRoot()
	program := php.ProgramOf(n.Codebase())
	if root.Kind() == "Expr_StaticCall" {
		class := root.Child("class")
		if !isName(class) {
			return false
		}
		name := strings.TrimLeft(class.Name(), `\`)

		return program.Extends(name, Model) || name == DatabaseFacade && php.CallName(root.Match) == "table"
	}
	held := strings.TrimLeft(php.TypesOf(n.Codebase()).TypeOf(root.Match), `?\`)
	if held == "" {
		return false
	}

	return program.Extends(held, Model) || slices.Contains(BuilderTypes, held)
}

func (n Node) chainRoot() Node {
	held := n
	for {
		receiver := held.Child("var")
		if held.Kind() == "Expr_StaticCall" {
			return held
		}
		if receiver.Kind() != "Expr_MethodCall" && receiver.Kind() != "Expr_NullsafeMethodCall" && receiver.Kind() != "Expr_StaticCall" {
			return Node{Match: receiver}
		}
		held = Node{Match: receiver}
	}
}

func (n Node) SpellsANamedRoutePath() bool {
	path := WrittenPath(n.Match)

	return len(path) > 0 && RoutePathsOf(n.Codebase()).Matches(path)
}

func (n Node) IsRouterArgument() bool {
	for at := n.Parent(); at.Exists() && !isFunctionLike(at); at = at.Parent() {
		if at.Kind() == "Arg" && rootsAtRouter(at.Parent()) {
			return true
		}
	}

	return false
}
