package laravel

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// RouteActions is every method that handles a request: the ones a route registers, and the public ones that take a request, return what a response binds, or render an Inertia page.
type RouteActions struct {
	actions    map[string]bool
	registered map[string]bool
}

var routeActions = engine.Memoised(readRouteActions)

func RouteActionsOf(codebase *engine.Codebase) *RouteActions {
	return routeActions.Of(codebase)
}

func ActionKey(fqcn, method string) string {
	return strings.TrimLeft(fqcn, `\`) + "::" + method
}

func (r *RouteActions) IsAction(fqcn, method string) bool {
	return fqcn != "" && method != "" && r.actions[ActionKey(fqcn, method)]
}

func (r *RouteActions) IsRegisteredAction(fqcn, method string) bool {
	return fqcn != "" && method != "" && r.registered[ActionKey(fqcn, method)]
}

func IsRegistration(node engine.Match) bool {
	return VerbOf(node) != "" && len(ActionsOf(node)) > 0
}

func VerbOf(node engine.Match) string {
	name := node.Child("name")
	if (node.Kind() != "Expr_MethodCall" && node.Kind() != "Expr_StaticCall") || name.Kind() != "Identifier" || !slices.Contains(RouteVerbs, name.Name()) {
		return ""
	}

	return name.Name()
}

// DefaultsOf is what a registration's chained `->defaults(...)` calls hand its action, as written: two registrations of
// one action with different defaults are two operations on one parameterised handler.
func DefaultsOf(registration engine.Match) []string {
	var defaults []string
	for current, parent := registration, registration.Parent(); parent.Kind() == "Expr_MethodCall" &&
		parent.Child("var").Node() == current.Node(); current, parent = parent, parent.Parent() {
		if name := parent.Child("name"); name.Kind() == "Identifier" && name.Name() == "defaults" {
			for _, argument := range parent.ChildrenIn("args") {
				defaults = append(defaults, argument.Written())
			}
		}
	}

	return defaults
}

func ActionsOf(node engine.Match) []string {
	actions := []string{}
	for _, argument := range node.Children() {
		if argument.Node().Field != "args" {
			continue
		}
		value := argument.Child("value")
		switch value.Kind() {
		case "Expr_Array":
			if action := arrayAction(value); action != "" {
				actions = append(actions, action)
			}
		case "Expr_ClassConstFetch":
			if class := classConstant(value); class != "" {
				actions = append(actions, ActionKey(class, "__invoke"))
			}
		}
	}

	return actions
}

func arrayAction(array engine.Match) string {
	var items []engine.Match
	for _, item := range array.Children() {
		if item.Kind() == "ArrayItem" {
			items = append(items, item)
		}
	}
	if len(items) < 2 || items[1].Child("value").Kind() != "Scalar_String" || items[0].Child("value").Kind() != "Expr_ClassConstFetch" {
		return ""
	}
	class := classConstant(items[0].Child("value"))
	if class == "" {
		return ""
	}
	method, _ := items[1].Child("value").Node().Value.Text()

	return ActionKey(class, method)
}

func readRouteActions(codebase *engine.Codebase) *RouteActions {
	read := &RouteActions{actions: map[string]bool{}, registered: map[string]bool{}}
	program := php.ProgramOf(codebase)
	surface := ResponseSurfaceOf(codebase)
	for _, file := range codebase.Of(contract.PHP).Files() {
		if isFortifyRouteFile(file) {
			continue
		}
		for _, node := range file.Nodes() {
			registration := file.Match(node.ID)
			if !IsRegistration(registration) {
				continue
			}
			for _, action := range ActionsOf(registration) {
				read.registered[action] = true
				read.actions[action] = true
			}
		}
		for _, node := range file.Nodes() {
			if node.Kind != "Stmt_Class" || node.Symbol == "" {
				continue
			}
			for _, method := range php.Methods(file.Match(node.ID)) {
				if !php.IsPublic(method.Node()) || !isRequestHandler(method, program, surface) {
					continue
				}
				read.actions[ActionKey(node.Symbol, method.Name())] = true
			}
		}
	}

	return read
}

func isRequestHandler(method engine.Match, program *php.Program, surface *ResponseSurface) bool {
	for _, param := range php.Params(method) {
		if class := php.Written(param.Node().Declared).Class(); class != "" && isRequestType(class, program) {
			return true
		}
	}
	if surface.IsResponseBound(php.Written(method.Node().Returns).Class()) {
		return true
	}
	for _, statement := range method.Children() {
		if statement.Node().Field != "stmts" {
			continue
		}
		for _, node := range append([]engine.Match{statement}, descendants(statement)...) {
			if RendersInertiaPage(node) {
				return true
			}
		}
	}

	return false
}

func isRequestType(fqcn string, program *php.Program) bool {
	for _, base := range RequestTypes {
		if fqcn == base || program.Extends(fqcn, base) {
			return true
		}
	}

	return false
}

// RouteNames is every route name the codebase registers: exact names, the families a dynamic suffix or a resource registers, and whether any name is built so it cannot be read at all.
type RouteNames struct {
	names    map[string]bool
	order    []string
	families []string
	dynamic  bool
}

var routeNames = engine.Memoised(readRouteNames)

func RouteNamesOf(codebase *engine.Codebase) *RouteNames {
	return routeNames.Of(codebase)
}

func (r *RouteNames) HasAny() bool {
	return len(r.names) > 0
}

func (r *RouteNames) IsRegistered(name string) bool {
	if r.dynamic || r.names[name] {
		return true
	}
	for _, family := range r.families {
		if strings.HasPrefix(name, family) {
			return true
		}
	}
	for _, registered := range r.order {
		if strings.HasSuffix(name, "."+registered) {
			return true
		}
	}

	return false
}

func (r *RouteNames) register(name string) {
	if !r.names[name] {
		r.names[name] = true
		r.order = append(r.order, name)
	}
}

var routesInAFile = new(int)

func readRouteNames(codebase *engine.Codebase) *RouteNames {
	read := &RouteNames{names: map[string]bool{}}
	var installed *engine.File

	for _, file := range codebase.Of(contract.PHP).Files() {
		if isFortifyRouteFile(file) {
			installed = file
			continue
		}
		read.merge(engine.Contribution(routesInAFile, file, routesOfOneFile))
	}

	if read.HasAny() && installed != nil && !fortifyRoutesIgnored(codebase) {
		read.addNames(routesPermittedInFile(installed, readFortifyRouteSettings(codebase, installed).includes))
	}

	return read
}

func (r *RouteNames) merge(held *RouteNames) {
	r.addNames(held)
	r.families = append(r.families, held.families...)
	r.dynamic = r.dynamic || held.dynamic
}

func (r *RouteNames) addNames(held *RouteNames) {
	for name := range held.names {
		r.names[name] = true
	}
}

func routesOfOneFile(file *engine.File) *RouteNames {
	return routesPermittedInFile(file, nil)
}

func routesPermittedInFile(file *engine.File, includes func(engine.Match) bool) *RouteNames {
	read := &RouteNames{names: map[string]bool{}}

	{
		var matches []engine.Match
		for _, node := range file.Nodes() {
			match := file.Match(node.ID)
			if includes == nil || includes(match) {
				matches = append(matches, match)
			}
		}
		for _, call := range matches {
			if !isRouteChainCall(call, "name") || isGroupCall(call.Parent()) {
				continue
			}
			if literal, ok := literalArgument(call); ok {
				read.register(prefixOf(call) + literal)
				continue
			}
			prefix := literalPrefix(call)
			if prefix == "" {
				read.dynamic = true
				continue
			}
			read.families = append(read.families, prefixOf(call)+prefix)
		}
		for _, group := range matches {
			if !isGroupCall(group) {
				continue
			}
			if accumulated := strings.TrimRight(prefixOf(group)+groupPrefix(group), "."); accumulated != "" {
				read.register(accumulated)
			}
		}
		for _, call := range matches {
			if !isResourceRegistration(call) {
				continue
			}
			literal, ok := literalArgument(call)
			if !ok {
				read.dynamic = true
				continue
			}
			read.families = append(read.families, prefixOf(call)+literal+".")
		}
	}

	return read
}

func prefixOf(node engine.Match) string {
	var segments []string
	for _, group := range groupsAround(node) {
		segments = append(segments, groupPrefix(group))
	}

	return strings.Join(segments, "")
}

func groupsAround(node engine.Match) []engine.Match {
	var groups []engine.Match
	for at := node; at.Exists(); at = at.Parent() {
		if !isFunctionLike(at) {
			continue
		}
		group := at.Parent()
		if group.Exists() && !isGroupCall(group) {
			group = group.Parent()
		}
		if isGroupCall(group) {
			groups = append(groups, group)
		}
	}
	slices.Reverse(groups)

	return groups
}

func groupPrefix(group engine.Match) string {
	for _, argument := range php.Arguments(group) {
		if value := argument.Child("value"); value.Kind() == "Expr_Array" {
			if as, ok := arrayValue(value, "as"); ok {
				return as
			}
		}
	}
	for link := group; link.Exists(); {
		if named := routeCallName(link); named == "name" || named == "as" {
			literal, _ := literalArgument(link)

			return literal
		}
		if link.Kind() != "Expr_MethodCall" && link.Kind() != "Expr_NullsafeMethodCall" {
			break
		}
		link = link.Child("var")
	}

	return ""
}

func isGroupCall(node engine.Match) bool {
	return isRouteChainCall(node, "group")
}

func isResourceRegistration(node engine.Match) bool {
	name := routeCallName(node)

	return (name == "resource" || name == "apiResource") && rootsAtRouter(node)
}

func isRouteChainCall(node engine.Match, method string) bool {
	return routeCallName(node) == method && rootsAtRouter(node)
}

func rootsAtRouter(node engine.Match) bool {
	root := node
	for root.Kind() == "Expr_MethodCall" || root.Kind() == "Expr_NullsafeMethodCall" {
		root = root.Child("var")
	}
	switch root.Kind() {
	case "Expr_StaticCall":
		class := root.Child("class")

		return isName(class) && slices.Contains([]string{Route, "Route"}, strings.TrimLeft(class.Name(), `\`))
	case "Expr_Variable":
		return isRouterVariable(root)
	}

	return false
}

func isRouterVariable(variable engine.Match) bool {
	if variable.Name() == "" {
		return false
	}
	for scope := variable; scope.Exists(); scope = scope.Parent() {
		if !isFunctionLike(scope) {
			continue
		}
		for _, param := range php.Params(scope) {
			if param.Child("var").Name() == variable.Name() {
				return slices.Contains(RouterTypes, php.Written(param.Node().Declared).Class())
			}
		}
	}

	return false
}

func routeCallName(node engine.Match) string {
	if name := node.Child("name"); (node.Kind() == "Expr_MethodCall" || node.Kind() == "Expr_StaticCall") && name.Kind() == "Identifier" {
		return name.Name()
	}

	return ""
}

func literalArgument(node engine.Match) (string, bool) {
	value := firstArgumentValue(node)
	if value.Kind() != "Scalar_String" {
		return "", false
	}

	return value.Node().Value.Text()
}

func literalPrefix(node engine.Match) string {
	value := firstArgumentValue(node)
	if value.Kind() != "Scalar_InterpolatedString" {
		return ""
	}
	parts := value.Children()
	if len(parts) == 0 || parts[0].Kind() != "InterpolatedStringPart" {
		return ""
	}
	head, _ := parts[0].Node().Value.Text()

	return head
}

func firstArgumentValue(node engine.Match) engine.Match {
	for _, argument := range node.Children() {
		if argument.Node().Field == "args" {
			return argument.Child("value")
		}
	}

	return engine.Match{}
}

func arrayValue(array engine.Match, key string) (string, bool) {
	for _, item := range array.Children() {
		if item.Kind() != "ArrayItem" || item.Child("key").Kind() != "Scalar_String" || item.Child("value").Kind() != "Scalar_String" {
			continue
		}
		if written, _ := item.Child("key").Node().Value.Text(); written == key {
			return item.Child("value").Node().Value.Text()
		}
	}

	return "", false
}

func isFunctionLike(node engine.Match) bool {
	return node.Is(engine.Neutral("function")) || (node.Kind() == "Stmt_ClassMethod")
}

func descendants(node engine.Match) []engine.Match {
	var all []engine.Match
	for _, child := range node.Children() {
		all = append(all, child)
		all = append(all, descendants(child)...)
	}

	return all
}
