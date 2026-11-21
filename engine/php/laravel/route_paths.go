package laravel

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

// RoutePaths is the path of every route the codebase registers under a name, each split into its segments.
type RoutePaths struct {
	paths [][]string
}

var routePaths = engine.Memoised(readRoutePaths)

func RoutePathsOf(codebase *engine.Codebase) *RoutePaths {
	return routePaths.Of(codebase)
}

func (r *RoutePaths) Matches(written []string) bool {
	for _, path := range r.paths {
		if engine.PathFits(path, written) {
			return true
		}
	}

	return false
}

func readRoutePaths(codebase *engine.Codebase) *RoutePaths {
	read := &RoutePaths{}
	for _, file := range codebase.Of(contract.PHP).Files() {
		if isFortifyRouteFile(file) {
			continue
		}
		for _, node := range file.Nodes() {
			call := file.Match(node.ID)
			if VerbOf(call) == "" || !rootsAtRouter(call) || !isNamedRegistration(call) {
				continue
			}
			uri, ok := literalArgument(call)
			if !ok {
				continue
			}
			if path := engine.PathSegments(groupPathOf(call) + "/" + uri); len(path) > 0 {
				read.paths = append(read.paths, path)
			}
		}
	}

	return read
}

func isNamedRegistration(call engine.Match) bool {
	for link := call.Parent(); link.Kind() == "Expr_MethodCall" && call.Node().InRole() == "var"; call, link = link, link.Parent() {
		if name := routeCallName(link); name == "name" || name == "as" {
			return true
		}
	}

	return false
}

func groupPathOf(node engine.Match) string {
	var segments []string
	for _, group := range groupsAround(node) {
		segments = append(segments, groupPath(group))
	}

	return strings.Join(segments, "/")
}

func groupPath(group engine.Match) string {
	for _, argument := range php.Arguments(group) {
		if value := argument.Child("value"); value.Kind() == "Expr_Array" {
			if prefix, ok := arrayValue(value, "prefix"); ok {
				return prefix
			}
		}
	}
	for link := group; link.Kind() == "Expr_MethodCall" || link.Kind() == "Expr_StaticCall"; link = link.Child("var") {
		if routeCallName(link) == "prefix" {
			prefix, _ := literalArgument(link)

			return prefix
		}
		if link.Kind() == "Expr_StaticCall" {
			break
		}
	}

	return ""
}

func WrittenPath(value engine.Match) []string {
	switch value.Kind() {
	case "Scalar_String":
		text, _ := value.Node().Value.Text()

		return engine.WrittenPath(text)
	case "Scalar_InterpolatedString":
		var text strings.Builder
		for _, part := range value.Children() {
			if part.Kind() != "InterpolatedStringPart" {
				text.WriteString(engine.AnySegment)
				continue
			}
			written, _ := part.Node().Value.Text()
			text.WriteString(written)
		}
		return engine.WrittenPath(text.String())
	}

	return nil
}
