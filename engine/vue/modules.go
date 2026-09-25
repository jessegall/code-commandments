package vue

import (
	"os"
	"path/filepath"
	"sort"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// moduleExtensions are what an import specifier may leave off, tried in order.
var moduleExtensions = []string{".ts", ".tsx", ".vue", ".js", "/index.ts", "/index.tsx", "/index.vue", "/index.js"}

// projectMarkers are the files a frontend project's root holds.
var projectMarkers = []string{"vite.config.ts", "vite.config.js", "vite.config.mjs", "vite.config.mts", "package.json"}

// moduleAlias is an import prefix and the folder it stands for.
type moduleAlias struct {
	prefix string
	dir    string
}

// ModuleResolver resolves an import specifier to the file it names, through the project's Vite aliases.
type ModuleResolver struct {
	aliases []moduleAlias
}

var resolvers sync.Map

// ResolverFor is the resolver of the project a file belongs to.
func ResolverFor(file string) *ModuleResolver {
	root := projectRoot(filepath.Dir(file))
	if held, ok := resolvers.Load(root); ok {
		return held.(*ModuleResolver)
	}
	resolver := newResolver(discoverAliases(root))
	held, _ := resolvers.LoadOrStore(root, resolver)

	return held.(*ModuleResolver)
}

func newResolver(aliases []moduleAlias) *ModuleResolver {
	resolver := &ModuleResolver{}
	for _, alias := range aliases {
		resolver.aliases = append(resolver.aliases, moduleAlias{prefix: alias.prefix, dir: strings.TrimRight(alias.dir, "/")})
	}
	sort.SliceStable(resolver.aliases, func(a, b int) bool {
		return len(resolver.aliases[a].prefix) > len(resolver.aliases[b].prefix)
	})

	return resolver
}

// projectRoot is the nearest folder above dir holding a project marker, else the filesystem root.
func projectRoot(dir string) string {
	last := dir
	for candidate := dir; ; {
		for _, marker := range projectMarkers {
			if info, err := os.Stat(candidate + "/" + marker); err == nil && info.Mode().IsRegular() {
				return candidate
			}
		}
		last = candidate
		parent := filepath.Dir(candidate)
		if parent == candidate {
			return last
		}
		candidate = parent
	}
}

// Resolve is the file a specifier imported from a file names, when it exists.
func (r *ModuleResolver) Resolve(fromFile, specifier string) (string, bool) {
	base, ok := r.base(fromFile, specifier)
	if !ok {
		return "", false
	}

	return existing(base)
}

func (r *ModuleResolver) base(fromFile, specifier string) (string, bool) {
	if specifier == "" {
		return "", false
	}
	if specifier[0] == '.' {
		return filepath.Dir(fromFile) + "/" + specifier, true
	}
	for _, alias := range r.aliases {
		if specifier == alias.prefix {
			return alias.dir, true
		}
		if strings.HasPrefix(specifier, alias.prefix+"/") {
			return alias.dir + specifier[len(alias.prefix):], true
		}
	}

	return "", false
}

func existing(base string) (string, bool) {
	for _, extension := range moduleExtensions {
		if path, ok := realFile(base + extension); ok {
			return path, true
		}
	}

	return realFile(base)
}

// realFile is the path's real form when it names a file.
func realFile(path string) (string, bool) {
	real, err := filepath.EvalSymlinks(path)
	if err != nil {
		return "", false
	}
	absolute, err := filepath.Abs(real)
	if err != nil {
		return "", false
	}
	info, err := os.Stat(absolute)

	return absolute, err == nil && info.Mode().IsRegular()
}

// discoverAliases reads the aliases of the Vite config at a project's root.
func discoverAliases(root string) []moduleAlias {
	for _, file := range []string{"vite.config.ts", "vite.config.js", "vite.config.mjs", "vite.config.mts"} {
		source, err := os.ReadFile(root + "/" + file)
		if err == nil {
			return aliasesFromSource(string(source), root)
		}
	}

	return nil
}

// aliasesFromSource is each alias a Vite config's `alias: { … }` declares and the folder it resolves to, in order.
func aliasesFromSource(source, root string) []moduleAlias {
	script := ReadScript(source)
	block, ok := script.ObjectAfter("alias")
	if !ok {
		return nil
	}
	var aliases []moduleAlias
	keys, entries := typescript.ParseExpression(block).ObjectEntries()
	for _, prefix := range keys {
		if relative, ok := relativeDir(entries[prefix], script, nil); ok {
			aliases = append(aliases, moduleAlias{prefix: prefix, dir: joinRoot(root, relative)})
		}
	}

	return aliases
}

func relativeDir(expression *typescript.Expr, script Script, seen []string) (string, bool) {
	switch expression.Kind {
	case typescript.IdentifierExpr:
		if expression.Name == "__dirname" {
			return "", true
		}
		for _, name := range seen {
			if name == expression.Name {
				return "", false
			}
		}
		value, ok := script.DeclaratorValue(expression.Name)
		if !ok {
			return "", false
		}

		return relativeDir(typescript.ParseExpression(value), script, append(seen, expression.Name))
	case typescript.LiteralExpr:
		return literalPath(expression), true
	case typescript.CallExpr:
		return callDir(expression, script, seen)
	}

	return "", false
}

func callDir(call *typescript.Expr, script Script, seen []string) (string, bool) {
	callee := call.CalleeName()
	switch callee {
	case "dirname", "fileURLToPath":
		return "", true
	case "resolve", "join":
	default:
		return "", false
	}
	arguments := call.Arguments()
	if len(arguments) == 0 {
		return "", true
	}
	first, _ := relativeDir(arguments[0], script, seen)
	segments := []string{first}
	for _, argument := range arguments[1:] {
		if argument.Kind == typescript.LiteralExpr {
			segments = append(segments, literalPath(argument))
		}
	}
	var parts []string
	for _, segment := range segments {
		if segment != "" {
			parts = append(parts, segment)
		}
	}

	return strings.Join(parts, "/"), true
}

func literalPath(literal *typescript.Expr) string {
	return strings.Trim(literal.Value(), "/")
}

func joinRoot(root, relative string) string {
	base := strings.TrimRight(root, "/")
	if relative == "" {
		return base
	}

	return base + "/" + relative
}

// ScriptOf is the script of a module file: a .vue file's script blocks, or a TypeScript file whole.
func ScriptOf(path string) Script {
	source, _ := os.ReadFile(path)
	if strings.HasSuffix(path, ".vue") {
		return ReadScript(ParseSfc(string(source), path).ScriptContent())
	}

	return ReadScript(string(source))
}

// TypeFieldsFrom is the fields of a type a file's script names, followed through its imports and re-exports.
func TypeFieldsFrom(typeName, file string, script Script) typescript.Fields {
	return resolveTypeFields(typeName, file, script, nil)
}

func resolveTypeFields(typeName, file string, script Script, seen []string) typescript.Fields {
	for _, visited := range seen {
		if visited == file {
			return typescript.Fields{}
		}
	}
	seen = append(seen, file)
	if local := script.TypeFields(typeName); len(local.Names) > 0 {
		return local
	}
	specifiers := script.ReExports()
	if imported, ok := script.ImportSpecifier(typeName); ok {
		specifiers = append([]string{imported}, specifiers...)
	}
	for _, specifier := range specifiers {
		path, ok := ResolverFor(file).Resolve(file, specifier)
		if !ok {
			continue
		}
		if fields := resolveTypeFields(typeName, path, ScriptOf(path), seen); len(fields.Names) > 0 {
			return fields
		}
	}

	return typescript.Fields{}
}
