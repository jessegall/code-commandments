package laravel

import (
	"slices"
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
	kept     map[string]string
	aliases  map[string]string
	resets   map[string]bool
	holds    map[string][]string
	outlives map[string]bool
}

var lifetimes = engine.Memoised(readLifetimes)

func LifetimesOf(codebase *engine.Codebase) *Lifetimes {
	return lifetimes.Of(codebase)
}

func (l *Lifetimes) IsSingleton(class string) bool {
	return l.lifetime(class) == singletonAttribute
}

func (l *Lifetimes) lifetime(class string) string {
	seen := map[string]bool{}
	for class = strings.TrimLeft(class, `\`); class != "" && !seen[class]; class = l.aliases[class] {
		if kept := l.kept[class]; kept != "" {
			return kept
		}
		seen[class] = true
	}

	return ""
}

func (l *Lifetimes) OutlivesAScope(class string) bool {
	return l.outlives[strings.TrimLeft(class, `\`)]
}

func (l *Lifetimes) heldThroughOwnersThatOutliveAScope() {
	var waiting []string
	for class, kept := range l.kept {
		if kept == singletonAttribute {
			waiting = append(waiting, class)
		}
	}
	for class := range l.resets {
		waiting = append(waiting, class)
	}
	for len(waiting) > 0 {
		class := waiting[0]
		waiting = waiting[1:]
		if l.outlives[class] {
			continue
		}
		l.outlives[class] = true
		waiting = append(waiting, l.holds[class]...)
	}
}

func readLifetimes(codebase *engine.Codebase) *Lifetimes {
	read := &Lifetimes{kept: map[string]string{}, aliases: map[string]string{}, resets: map[string]bool{}, holds: map[string][]string{}, outlives: map[string]bool{}}
	var classes []string
	var caches []engine.Match
	for _, file := range codebase.Of(contract.PHP).Files() {
		for _, node := range file.Nodes() {
			match := file.Match(node.ID)
			if match.IsTest() {
				break
			}
			if kept, bound := lifetimeOfBinding[CallName(match)]; bound {
				if abstract := BoundAbstractOf(match); abstract != "" {
					read.kept[strings.TrimLeft(abstract, `\`)] = kept
					if concrete := ClassArgument(match, 1); concrete != "" && kept == singletonAttribute {
						read.holds[abstract] = append(read.holds[abstract], concrete)
					}
				}
			}
			if CallName(match) == "alias" {
				original, alias := ClassArgument(match, 0), ClassArgument(match, 1)
				if original != "" && alias != "" {
					read.aliases[alias] = original
				}
			}
			if node.Kind == "Expr_MethodCall" && CallName(match) == scopeReset {
				read.resets[strings.TrimLeft(php.EnclosingClassName(match), `\`)] = true
			}
			if node.Kind == "Stmt_Class" && node.Symbol != "" {
				classes = append(classes, strings.TrimLeft(node.Symbol, `\`))
				for _, parameter := range php.ConstructorParams(match) {
					if held := strings.TrimLeft(php.Written(parameter.Node().Written).Class(), `\`); held != "" {
						owner := strings.TrimLeft(node.Symbol, `\`)
						read.holds[owner] = append(read.holds[owner], held)
					}
				}
				for _, attribute := range php.AttributeNames(match) {
					if attribute := strings.TrimLeft(attribute, `\`); attribute == singletonAttribute || attribute == scopedAttribute {
						read.kept[strings.TrimLeft(node.Symbol, `\`)] = attribute
					}
				}
			}
			if match.Kind() == "Expr_AssignOp_Coalesce" {
				caches = append(caches, match)
			}
		}
	}
	program := php.ProgramOf(codebase)
	for _, cache := range caches {
		if bound := cachedClassBound(cache); bound != "" {
			owner := php.EnclosingClassName(cache)
			for _, class := range classes {
				if program.IsA(class, bound) {
					read.holds[owner] = append(read.holds[owner], class)
				}
			}
		}
	}
	read.heldThroughOwnersThatOutliveAScope()

	return read
}

func cachedClassBound(cache engine.Match) string {
	index := cache.Child("var")
	key := index.Child("dim")
	if index.Kind() != "Expr_ArrayDimFetch" || key.Kind() != "Expr_Variable" || key.Name() == "" ||
		!(php.Node{Match: index.Child("var")}).IsOwnPropertyRead() {
		return ""
	}
	value := cache.Child("expr")
	resolvesKey := slices.ContainsFunc(append([]engine.Match{value}, value.OwnDescendants()...), func(call engine.Match) bool {
		argument := (php.Node{Match: call}).Argument(0)

		return call.Kind() == "Expr_MethodCall" && argument.Kind() == "Expr_Variable" && argument.Name() == key.Name() &&
			slices.Contains(ContainerResolvers, CallName(call)) && php.IsContainerCall(call)
	})
	if !resolvesKey {
		return ""
	}
	return php.ClassStringBound((php.Node{Match: cache}).EnclosingFunctionLike().Match, key.Name())
}

func (n Node) ResolvesLateInALongerLivedOwner() bool {
	lifetimes := LifetimesOf(n.Codebase())
	class := php.EnclosingClassName(n.Match)
	target := ClassArgument(n.Match, 0)
	function := n.Closest(engine.Function)
	program := php.ProgramOf(n.Codebase())

	return (lifetimes.OutlivesAScope(class) && !lifetimes.IsSingleton(target)) ||
		(lifetimes.lifetime(target) == scopedAttribute && (function.HasModifier("static") || n.Parent().Kind() == "Stmt_Return")) ||
		(function.HasModifier("static") && (function.Name() == "render" || function.Kind() == "Expr_ArrowFunction")) ||
		program.Extends(class, Factory) ||
		isArtisanTrait(program, class, map[string]bool{}) || resolvesInEventListener(n.Match) || n.replacesResolvedInstance(function, target)
}

func isArtisanTrait(program *php.Program, class string, seen map[string]bool) bool {
	if seen[class] {
		return false
	}
	seen[class] = true
	defer delete(seen, class)
	users := program.UsersOfTrait(class)

	return len(users) > 0 && !slices.ContainsFunc(users, func(user string) bool {
		return !program.Extends(user, ConsoleCommand) && !isArtisanTrait(program, user, seen)
	})
}

func resolvesInEventListener(resolve engine.Match) bool {
	closure := resolve.Closest(engine.Function)
	if closure.Kind() != "Expr_Closure" && closure.Kind() != "Expr_ArrowFunction" {
		return false
	}
	call := closure.Parent().Parent()
	if closure.Parent().Kind() != "Arg" || CallName(call) != "listen" {
		return false
	}
	dispatcher := php.ReceiverTypeOf(call)

	return dispatcher == `Illuminate\Contracts\Events\Dispatcher` || dispatcher == `Illuminate\Events\Dispatcher`
}

func (n Node) replacesResolvedInstance(function engine.Match, target string) bool {
	receiver := php.ReceiverTypeOf(n.Match)
	if receiver == "" || target == "" {
		return false
	}
	receiverText, _ := n.Child("var").Text()
	for _, candidate := range function.Descendants() {
		candidateReceiver, _ := candidate.Child("var").Text()
		if CallName(candidate) == "instance" && php.ReceiverTypeOf(candidate) == receiver &&
			candidateReceiver == receiverText && ClassArgument(candidate, 0) == target {
			return true
		}
	}

	return false
}
