package laravel

import (
	"encoding/json"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/internal/shop"
)

func TestTheLaravelDecoratorAnswersAsPhpDoes(t *testing.T) {
	codebase := shop.Codebase(t)
	actions := RouteActionsOf(codebase)
	names := RouteNamesOf(codebase)
	surface := ResponseSurfaceOf(codebase)
	bindings := ContainerBindingsOf(codebase)
	boundaries := BoundaryOperationsOf(codebase)
	shop.Parity(t, "laravel", func(answer shop.Answer, node engine.Match) any {
		var ask string
		if err := json.Unmarshal(answer.Ask, &ask); err != nil {
			t.Fatal(err)
		}
		laravel := Node{}.Decorate(node)
		switch ask {
		case "call":
			return map[string]any{
				"rendersInertiaPage": RendersInertiaPage(node),
				"isFacadeCall":       laravel.IsFacadeCall(),
				"routeNameReference": orNil(laravel.RouteNameReference()),
				"listenedEventClass": orNil(laravel.ListenedEventClass()),
				"boundAbstract":      orNil(laravel.BoundAbstract()),
				"receiverIsModel":    laravel.ReceiverIsModel(),
				"isMassArrayUpdate":  laravel.IsMassArrayUpdate(),
				"isRegistration":     IsRegistration(node),
				"verbOf":             orNil(VerbOf(node)),
				"actionsOf":          ActionsOf(node),
			}
		case "method":
			class := php.EnclosingClassName(node)
			twins := boundaries.TwinsOf(class, node.Name())
			if twins == nil {
				twins = []string{}
			}

			return map[string]any{
				"isRouteAction":          laravel.IsRouteAction(),
				"isRegisteredAction":     actions.IsRegisteredAction(class, node.Name()),
				"delegatesToRouteAction": laravel.DelegatesToRouteAction(),
				"thinDelegationTarget":   orNil(laravel.ThinDelegationTarget()),
				"inServiceProvider":      laravel.InServiceProvider(),
				"isEloquentCast":         laravel.IsEloquentCast(),
				"inQueuedJobHook":        laravel.InQueuedJobHook(),
				"twins":                  twins,
			}
		case "named":
			named := node.Name()
			switch node.Kind() {
			case "Scalar_String":
				named, _ = node.Node().Value.Text()
			case "Stmt_Class", "Stmt_Interface", "Stmt_Trait", "Stmt_Enum":
				named = node.Node().Symbol
			}

			return map[string]any{
				"routeRegistered":   names.IsRegistered(named),
				"routeNamesAny":     names.HasAny(),
				"responseBound":     surface.IsResponseBound(named),
				"resolvedSomewhere": bindings.IsResolvedSomewhere(named),
				"declaredHere":      bindings.IsDeclaredHere(named),
			}
		}
		t.Fatalf("an unknown ask %s", answer.Ask)

		return nil
	})
}

func orNil(text string) any {
	if text == "" {
		return nil
	}

	return text
}
