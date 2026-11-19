package laravel

import (
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
)

const (
	singletonAttribute = `Illuminate\Container\Attributes\Singleton`
	scopedAttribute    = `Illuminate\Container\Attributes\Scoped`
	scopeReset         = "forgetScopedInstances"
)

var lifetimeOfBinding = map[string]string{
	"singleton": singletonAttribute, "singletonIf": singletonAttribute, "instance": singletonAttribute,
	"scoped": scopedAttribute, "scopedIf": scopedAttribute,
}

// Lifetimes is how long the container keeps each class it is told about: one for the whole run (a singleton) or one per scope, and which classes reset the scope themselves.
type Lifetimes struct {
	kept   map[string]string
	resets map[string]bool
}

var lifetimes = engine.Memoised(readLifetimes)

func LifetimesOf(codebase *engine.Codebase) *Lifetimes {
	return lifetimes.Of(codebase)
}

func (l *Lifetimes) IsSingleton(class string) bool {
	return l.kept[strings.TrimLeft(class, `\`)] == singletonAttribute
}

func (l *Lifetimes) OutlivesAScope(class string) bool {
	class = strings.TrimLeft(class, `\`)

	return l.kept[class] == singletonAttribute || l.resets[class]
}

func readLifetimes(codebase *engine.Codebase) *Lifetimes {
	read := &Lifetimes{kept: map[string]string{}, resets: map[string]bool{}}
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			match := file.Match(node.ID)
			if kept, bound := lifetimeOfBinding[CallName(match)]; bound {
				if abstract := BoundAbstractOf(match); abstract != "" {
					read.kept[strings.TrimLeft(abstract, `\`)] = kept
				}
			}
			if node.Kind == "Expr_MethodCall" && CallName(match) == scopeReset {
				read.resets[strings.TrimLeft(php.EnclosingClassName(match), `\`)] = true
			}
			if node.Kind == "Stmt_Class" && node.Symbol != "" {
				for _, attribute := range php.AttributeNames(match) {
					if attribute := strings.TrimLeft(attribute, `\`); attribute == singletonAttribute || attribute == scopedAttribute {
						read.kept[strings.TrimLeft(node.Symbol, `\`)] = attribute
					}
				}
			}
		}
	}

	return read
}

func (n Node) ResolvesLateInALongerLivedOwner() bool {
	lifetimes := LifetimesOf(n.Codebase())

	return lifetimes.OutlivesAScope(php.EnclosingClassName(n.Match)) && !lifetimes.IsSingleton(ClassArgument(n.Match, 0))
}
