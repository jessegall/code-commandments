// Package laravel is what the engine knows of Laravel, stated once: its facades, routes, container, Eloquent and
// queues, and the analyses that read them across a codebase.
package laravel

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const (
	FacadeNamespace  = `Illuminate\Support\Facades\`
	ServiceProvider  = `Illuminate\Support\ServiceProvider`
	Model            = `Illuminate\Database\Eloquent\Model`
	Request          = `Illuminate\Http\Request`
	FormRequest      = `Illuminate\Foundation\Http\FormRequest`
	McpRequest       = `Laravel\Mcp\Request`
	McpTool          = `Laravel\Mcp\Server\Tool`
	Inertia          = `Inertia\Inertia`
	InertiaHelper    = "inertia"
	Controller       = `Illuminate\Routing\Controller`
	Route            = `Illuminate\Support\Facades\Route`
	ShouldQueue      = `Illuminate\Contracts\Queue\ShouldQueue`
	Event            = `Illuminate\Support\Facades\Event`
	ConsoleCommand   = `Illuminate\Console\Command`
	AuthGuard        = `Illuminate\Contracts\Auth\Guard`
	AuthUserProvider = `Illuminate\Contracts\Auth\UserProvider`
)

var (
	RouteVerbs        = []string{"get", "post", "put", "patch", "delete", "options", "match", "any"}
	RouterTypes       = []string{`Illuminate\Routing\Router`, `Illuminate\Contracts\Routing\Registrar`}
	BindingMethods    = []string{"bind", "bindIf", "singleton", "singletonIf", "scoped", "scopedIf", "instance"}
	RelationMethods   = []string{"hasOne", "hasOneThrough", "hasMany", "hasManyThrough", "belongsTo", "belongsToMany", "morphOne", "morphMany", "morphToMany", "morphedByMany"}
	QueueHooks        = []string{"failed", "middleware", "retryUntil", "backoff", "uniqueId", "tags", "displayName"}
	EventDispatchers  = []string{"dispatch", "dispatchIf", "dispatchUnless", "broadcast"}
	RouteNameLookups  = []string{"route", "to_route", "signedRoute", "temporarySignedRoute"}
	URLGenerators     = []string{`Illuminate\Support\Facades\URL`, "URL", `Illuminate\Support\Facades\Redirect`, "Redirect"}
	URLHelpers        = []string{"redirect", "url"}
	RequestTypes      = []string{Request, FormRequest, McpRequest}
	CastContracts     = []string{`Illuminate\Contracts\Database\Eloquent\CastsAttributes`, `Illuminate\Contracts\Database\Eloquent\CastsInboundAttributes`}
	BindingAttributes = []string{
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

// BoundaryKinds are the entry points a class is by its ancestry, in the order asked.
var BoundaryKinds = []BoundaryKind{{"console", ConsoleCommand}, {"mcp", McpTool}}

// Node is a match read in Laravel's terms.
type Node struct {
	engine.Match
}

// Decorate reads a match as a Node.
func (Node) Decorate(m engine.Match) Node {
	return Node{Match: m}
}

// RendersInertiaPage says whether the node renders an Inertia page: `Inertia::render(...)` or `inertia(...)`.
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

// IsFacadeCall says whether the node is a static call on a Laravel facade.
func (n Node) IsFacadeCall() bool {
	return strings.HasPrefix(php.StaticCallClass(n.Match), FacadeNamespace)
}

// RouteNameReference is the route name a `route('name')`, `URL::route('name')` or `redirect()->route('name')` asks
// for; empty for anything else, or a name not written as a literal.
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

// ListenedEventClass is the event class an `Event::listen(X::class, …)` registers for.
func (n Node) ListenedEventClass() string {
	class, name := n.Child("class"), n.Child("name")
	if n.Kind() != "Expr_StaticCall" || !isName(class) || !slices.Contains([]string{Event, "Event"}, strings.TrimLeft(class.Name(), `\`)) ||
		name.Kind() != "Identifier" || name.Name() != "listen" {
		return ""
	}

	return ClassArgument(n.Match, 0)
}

// BoundAbstract is the class a container binding (`bind`, `singleton`, …) registers.
func (n Node) BoundAbstract() string {
	return BoundAbstractOf(n.Match)
}

// BoundAbstractOf is the class the call binds in the container, when it is a binding.
func BoundAbstractOf(node engine.Match) string {
	if !slices.Contains(BindingMethods, CallName(node)) {
		return ""
	}

	return ClassArgument(node, 0)
}

// IsRouteAction says whether the method the node sits in handles a request.
func (n Node) IsRouteAction() bool {
	return RouteActionsOf(n.Codebase()).IsAction(php.EnclosingClassName(n.Match), php.EnclosingFunctionName(n.Match))
}

// DelegatesToRouteAction says whether the method does nothing but forward to another class's registered route
// action.
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

// ThinDelegationTarget is the `Class::method` the method does nothing but forward to.
func (n Node) ThinDelegationTarget() string {
	call, ok := n.soleDelegationCall()
	if !ok || call.Child("name").Kind() != "Identifier" {
		return ""
	}
	receiver := php.TypesOf(n.Codebase()).TypeIn(call.Child("var"), n.Match, php.EnclosingClassName(n.Match))
	if receiver == "" {
		return ""
	}

	return ActionKey(receiver, call.Child("name").Name())
}

// soleDelegationCall is the one method send a method's single statement returns or runs.
func (n Node) soleDelegationCall() (engine.Match, bool) {
	if n.Kind() != "Stmt_ClassMethod" {
		return engine.Match{}, false
	}
	var statements []engine.Match
	for _, statement := range n.Children() {
		if statement.Node().Field == "stmts" {
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

// InServiceProvider says whether the node sits in a service provider.
func (n Node) InServiceProvider() bool {
	return php.ProgramOf(n.Codebase()).Extends(php.EnclosingClassName(n.Match), ServiceProvider)
}

// IsEloquentCast says whether the node sits in a custom Eloquent cast.
func (n Node) IsEloquentCast() bool {
	program := php.ProgramOf(n.Codebase())
	for _, contract := range CastContracts {
		if program.Implements(php.EnclosingClassName(n.Match), contract) {
			return true
		}
	}

	return false
}

// InQueuedJobHook says whether the node sits in one of a queued job's framework hooks.
func (n Node) InQueuedJobHook() bool {
	return php.ProgramOf(n.Codebase()).Implements(php.EnclosingClassName(n.Match), ShouldQueue) &&
		slices.Contains(QueueHooks, php.EnclosingFunctionName(n.Match))
}

// ReceiverIsModel says whether a method send's receiver is declared an Eloquent model.
func (n Node) ReceiverIsModel() bool {
	receiver := php.ReceiverTypeOf(n.Match)

	return receiver != "" && php.ProgramOf(n.Codebase()).Extends(receiver, Model)
}

// IsMassArrayUpdate says whether the node is `$model->update([...])` on something other than `$this`.
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

// StringArgument is the literal string the call passes at the position.
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

// ClassArgument is the class a call passes as `X::class` at the position.
func ClassArgument(call engine.Match, position int) string {
	arguments := php.Arguments(call)
	if position >= len(arguments) {
		return ""
	}

	return classConstant(arguments[position].Child("value"))
}

// classConstant is the class an `X::class` names.
func classConstant(fetch engine.Match) string {
	class, name := fetch.Child("class"), fetch.Child("name")
	if fetch.Kind() != "Expr_ClassConstFetch" || !isName(class) || name.Kind() != "Identifier" || name.Name() != "class" {
		return ""
	}

	return strings.TrimLeft(class.Name(), `\`)
}

// CallName is the name a call is made by: a method send's, a static call's, or a named function's.
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

func isName(node engine.Match) bool {
	return strings.HasPrefix(node.Kind(), "Name")
}
