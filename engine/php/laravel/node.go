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

var BuilderTypes = []string{
	EloquentBuilder,
	`Illuminate\Database\Query\Builder`,
	EloquentRelation,
	`Illuminate\Database\Eloquent\Relations\HasMany`,
	`Illuminate\Database\Eloquent\Relations\BelongsTo`,
	`Illuminate\Database\Eloquent\Relations\BelongsToMany`,
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
