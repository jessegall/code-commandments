<?php

declare(strict_types=1);

namespace CodeCommandments\Oracle;

use JesseGall\CodeCommandments\Ast\Codebase;
use JesseGall\CodeCommandments\Ast\Laravel\BoundaryOperations;
use JesseGall\CodeCommandments\Ast\Laravel\ContainerBindings;
use JesseGall\CodeCommandments\Ast\Laravel\LaravelNode;
use JesseGall\CodeCommandments\Ast\Laravel\ResponseSurface;
use JesseGall\CodeCommandments\Ast\Laravel\RouteActions;
use JesseGall\CodeCommandments\Ast\Laravel\RouteNames;
use JesseGall\CodeCommandments\Ast\ParsedFile;
use PhpParser\Node;

/** The Laravel decorator's answers, and those of the Laravel analyses it and the detectors stand on. */
final class Laravel implements Question
{
    public function name(): string
    {
        return 'laravel';
    }

    public function answers(Codebase $codebase, ParsedFile $file): iterable
    {
        $actions = RouteActions::forCodebase($codebase);
        $names = RouteNames::forCodebase($codebase);
        $surface = ResponseSurface::forCodebase($codebase);
        $bindings = ContainerBindings::forCodebase($codebase);
        $boundaries = BoundaryOperations::forCodebase($codebase);
        foreach (nodes($file->ast) as $node) {
            $laravel = $codebase->wrap($node, $file, LaravelNode::class);
            if ($node instanceof Node\Expr\CallLike) {
                yield [$node, 'call', [
                    'rendersInertiaPage' => LaravelNode::rendersInertiaPage($node),
                    'isFacadeCall' => $laravel->isFacadeCall(),
                    'routeNameReference' => $laravel->routeNameReference(),
                    'listenedEventClass' => $laravel->listenedEventClass(),
                    'boundAbstract' => $laravel->boundAbstract(),
                    'receiverIsModel' => $laravel->receiverIsModel(),
                    'isMassArrayUpdate' => $laravel->isMassArrayUpdate(),
                    'isRegistration' => RouteActions::isRegistration($node),
                    'verbOf' => RouteActions::verbOf($node),
                    'actionsOf' => RouteActions::actionsOf($node),
                ]];
            }
            if ($node instanceof Node\Stmt\ClassMethod) {
                $class = $laravel->enclosingClassName();
                $method = $node->name->toString();
                yield [$node, 'method', [
                    'isRouteAction' => $laravel->isRouteAction(),
                    'isRegisteredAction' => $actions->isRegisteredAction($class, $method),
                    'delegatesToRouteAction' => $laravel->delegatesToRouteAction(),
                    'thinDelegationTarget' => $laravel->thinDelegationTarget(),
                    'inServiceProvider' => $laravel->inServiceProvider(),
                    'isEloquentCast' => $laravel->isEloquentCast(),
                    'inQueuedJobHook' => $laravel->inQueuedJobHook(),
                    'twins' => $boundaries->twinsOf($class, $method),
                ]];
            }
            $named = match (true) {
                $node instanceof Node\Name => $node->toString(),
                $node instanceof Node\Scalar\String_ => $node->value,
                $node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null => $node->namespacedName->toString(),
                default => null,
            };
            if ($named !== null && $named !== '') {
                yield [$node, 'named', [
                    'routeRegistered' => $names->isRegistered($named),
                    'routeNamesAny' => $names->hasAny(),
                    'responseBound' => $surface->isResponseBound($named),
                    'resolvedSomewhere' => $bindings->isResolvedSomewhere($named),
                    'declaredHere' => $bindings->isDeclaredHere($named),
                ]];
            }
        }
    }
}
