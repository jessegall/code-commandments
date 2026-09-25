package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/prose"
	spatienode "github.com/jessegall/code-commandments/engine/php/spatie"
	"github.com/jessegall/code-commandments/sins"
	spatiesins "github.com/jessegall/code-commandments/sins/backend/spatie"
)

// FlatFieldClusterDetector finds a TypeScript Data class flattening the fields of a value object the project
// already declares into prefixed scalars: shippingStreet, shippingCity beside an Address.
type FlatFieldClusterDetector struct{}

func init() { detectors.Register(catalog.Backend, FlatFieldClusterDetector{}) }

const (
	// minCluster is how many prefixed fields make a cluster.
	minCluster = 2
	// maxValueObjectFields is the most fields a class may have to count as a value object's shape.
	maxValueObjectFields = 6
)

// clusterScalars are the scalar types a flattened field is written in.
var clusterScalars = []string{"string", "int", "float", "bool"}

// Sin is the sin the detector finds.
func (FlatFieldClusterDetector) Sin() sins.Sin { return spatiesins.FlatFieldCluster{} }

// Find is every TypeScript Data class whose prefixed scalar fields are the fields of a value object named for the
// prefix.
func (FlatFieldClusterDetector) Find(codebase *engine.Codebase) []engine.Match {
	shapes := valueObjectShapes(codebase)

	return codebase.
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

// clustersByPrefix groups the class's public scalar fields by their leading camel-case word, in the order the
// words first appear.
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

// valueObjectShapes is the lower-cased public field names of every named class with at most six, by its lower-cased
// short name, and again without a Data suffix.
func valueObjectShapes(codebase *engine.Codebase) map[string][]string {
	shapes := map[string][]string{}
	for _, class := range codebase.WhereKind("Stmt_Class").Get() {
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
