package laravel

import (
	"encoding/json"
	"os"
	"path/filepath"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const (
	fortifyPackage       = "laravel/fortify"
	fortifyProvider      = `Laravel\Fortify\FortifyServiceProvider`
	fortifyRoutesFile    = "vendor/" + fortifyPackage + "/routes/routes.php"
	fortifyConfigFile    = "config/fortify.php"
	fortifyPackageConfig = "vendor/" + fortifyPackage + "/" + fortifyConfigFile
)

type composerDiscovery struct {
	Extra struct {
		Laravel struct {
			Providers    []string `json:"providers"`
			DontDiscover []string `json:"dont-discover"`
		} `json:"laravel"`
	} `json:"extra"`
}

func RouteContextFiles(root string) []string {
	if root == "" {
		return nil
	}
	project, ok := readComposerDiscovery(filepath.Join(root, "composer.json"))
	if !ok || slices.Contains(project.Extra.Laravel.DontDiscover, "*") || slices.Contains(project.Extra.Laravel.DontDiscover, fortifyPackage) {
		return nil
	}
	installed, ok := readComposerDiscovery(filepath.Join(root, "vendor", fortifyPackage, "composer.json"))
	if !ok || !slices.Contains(installed.Extra.Laravel.Providers, fortifyProvider) {
		return nil
	}

	return []string{filepath.Join(root, fortifyRoutesFile), filepath.Join(root, fortifyPackageConfig)}
}

func isFortifyRouteFile(file *engine.File) bool {
	return strings.HasSuffix(filepath.ToSlash(file.Path), "/"+fortifyRoutesFile)
}

func readComposerDiscovery(path string) (composerDiscovery, bool) {
	var manifest composerDiscovery
	content, err := os.ReadFile(path)
	if err != nil {
		return manifest, false
	}
	err = json.Unmarshal(content, &manifest)

	return manifest, err == nil
}

type fortifyRouteSettings struct {
	views    bool
	features map[string]bool
}

func readFortifyRouteSettings(codebase *engine.Codebase, installed *engine.File) fortifyRouteSettings {
	var project, defaults engine.Match
	projectConfigRead := false
	for _, file := range codebase.Of(contract.PHP).Files() {
		switch file.FromProject() {
		case fortifyConfigFile:
			project = returnedArray(file.Match(0))
			projectConfigRead = true
		case fortifyPackageConfig:
			defaults = returnedArray(file.Match(0))
		}
	}
	if projectConfigRead && !project.Exists() {
		return fortifyRouteSettings{}
	}
	if !projectConfigRead {
		root := strings.TrimSuffix(filepath.ToSlash(installed.Path), fortifyRoutesFile)
		if _, err := codebase.Read(filepath.Join(root, fortifyConfigFile)); err == nil {
			return fortifyRouteSettings{}
		}
	}
	settings := fortifyRouteSettings{features: map[string]bool{}}
	for _, configuration := range []engine.Match{defaults, project} {
		settings.read(configuration)
	}

	return settings
}

func (settings *fortifyRouteSettings) read(configuration engine.Match) {
	for _, item := range configuration.ChildrenIn("items") {
		key, _ := item.Child("key").Text()
		value := item.Child("value")
		switch key {
		case "views":
			settings.views = value.Kind() == "Expr_ConstFetch" && strings.EqualFold(value.Child("name").Name(), "true")
		case "features":
			settings.features = fortifyFeaturesOf(value)
		}
	}
}

func fortifyFeaturesOf(value engine.Match) map[string]bool {
	features := map[string]bool{}
	for _, feature := range value.ChildrenIn("items") {
		call := feature.Child("value")
		if isFortifyStaticCall(call, "Features") {
			features[routeCallName(call)] = true
		}
	}

	return features
}

func isFortifyStaticCall(call engine.Match, class string) bool {
	return call.Kind() == "Expr_StaticCall" && strings.TrimLeft(call.Child("class").Name(), `\`) == `Laravel\Fortify\`+class
}

func fortifyRoutesIgnored(codebase *engine.Codebase) bool {
	ignored := codebase.WhereKind("Expr_StaticCall").Where(func(call engine.Match) bool {
		return isFortifyStaticCall(call, "Fortify") && routeCallName(call) == "ignoreRoutes"
	}).First()

	return ignored.Exists()
}

func (settings fortifyRouteSettings) includes(route engine.Match) bool {
	child := route
	for _, parent := range route.Ancestors() {
		if parent.Kind() == "Stmt_If" && (child.Node().Field != "stmts" || !settings.allows(parent.Child("cond"))) {
			return false
		}
		child = parent
	}

	return true
}

func (settings fortifyRouteSettings) allows(condition engine.Match) bool {
	if isFortifyStaticCall(condition, "Features") && routeCallName(condition) == "enabled" {
		feature := firstArgumentValue(condition)

		return isFortifyStaticCall(feature, "Features") && settings.features[routeCallName(feature)]
	}

	return settings.views && readsFortifyViews(condition)
}

func readsFortifyViews(condition engine.Match) bool {
	if condition.Kind() != "Expr_Variable" {
		return false
	}
	function := (php.Node{Match: condition}).EnclosingFunctionLike()
	for _, statement := range slices.Backward(function.ChildrenIn("stmts")) {
		if !statement.EndsBefore(condition) {
			continue
		}
		assignment := statement.Child("expr")
		if assignment.Kind() != "Expr_Assign" || assignment.Child("var").Name() != condition.Name() {
			continue
		}
		read := assignment.Child("expr")
		key, _ := literalArgument(read)

		return (php.Node{Match: read}).CallsFunction("config") && key == "fortify.views"
	}

	return false
}
