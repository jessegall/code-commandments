package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/prose"
	"github.com/jessegall/code-commandments/sins"
	spatiesins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// FlatFieldClusterDetector finds a TypeScript Data class flattening the fields of a value object the project already declares into prefixed scalars: shippingStreet, shippingCity beside an Address.
type FlatFieldClusterDetector struct{}

func init() { detectors.Register(catalog.PHP, FlatFieldClusterDetector{}) }

const (
	minCluster           = 2
	maxValueObjectFields = 6
)

var clusterScalars = []string{"string", "int", "float", "bool"}

func (FlatFieldClusterDetector) Sin() sins.Sin { return spatiesins.FlatFieldCluster{} }

func (FlatFieldClusterDetector) Find(codebase *engine.Codebase) []engine.Match {
	shapes := valueObjectShapes(codebase)

	return php.In(codebase).
		WhereKind("Stmt_Class").
		Where(engine.As(spatienode.Node.IsTypeScriptData)).
		Where(engine.As(func(n php.Node) bool { return flattensAValueObject(n, shapes) })).
		Get()
}

func flattensAValueObject(class php.Node, shapes map[string][]string) bool {
	clusters, tokens := clustersByPrefix(class)
	for _, token := range tokens {
		cluster := clusters[token]
		if len(cluster) < minCluster || allBoolean(cluster) || isReference(cluster) || prose.IsNonEntity(token) {
			continue
		}
		shape, ok := shapes[token]
		if !ok {
			shape, ok = shapes[token+"data"]
		}
		if ok && remaindersMatch(cluster, shape) {
			return true
		}
	}

	return false
}

func remaindersMatch(cluster []php.Field, shape []string) bool {
	for _, field := range cluster {
		if !slices.Contains(shape, strings.ToLower(prose.AfterLeadingToken(field.Name))) {
			return false
		}
	}

	return true
}

func clustersByPrefix(class php.Node) (map[string][]php.Field, []string) {
	clusters := map[string][]php.Field{}
	var tokens []string
	for _, field := range php.Fields(class.Match) {
		if !field.IsPublic || !slices.Contains(clusterScalars, strings.ToLower(php.Written(field.Type).SimpleName())) {
			continue
		}
		token := prose.LeadingToken(field.Name)
		if token == "" || prose.AfterLeadingToken(field.Name) == "" {
			continue
		}
		if clusters[token] == nil {
			tokens = append(tokens, token)
		}
		clusters[token] = append(clusters[token], field)
	}

	return clusters, tokens
}

func valueObjectShapes(codebase *engine.Codebase) map[string][]string {
	shapes := map[string][]string{}
	for _, class := range php.In(codebase).WhereKind("Stmt_Class").Get() {
		short := strings.ToLower(php.ShortName(php.EnclosingClassName(class)))
		if short == "" {
			continue
		}
		var fields []string
		for _, name := range (php.Node{Match: class}).PublicFieldNames() {
			fields = append(fields, strings.ToLower(name))
		}
		if len(fields) == 0 || len(fields) > maxValueObjectFields {
			continue
		}
		shapes[short] = fields
		if strings.HasSuffix(short, "data") {
			shapes[strings.TrimSuffix(short, "data")] = fields
		}
	}

	return shapes
}

func isReference(cluster []php.Field) bool {
	return slices.ContainsFunc(cluster, func(field php.Field) bool {
		return strings.HasSuffix(field.Name, "Id") || strings.HasSuffix(field.Name, "Uuid")
	})
}

func allBoolean(cluster []php.Field) bool {
	return !slices.ContainsFunc(cluster, func(field php.Field) bool {
		return strings.ToLower(php.Written(field.Type).SimpleName()) != "bool"
	})
}

func (FlatFieldClusterDetector) CrossFile() {}
