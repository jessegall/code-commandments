package engine

// Decorator is a package's own view of a match, such as a Laravel node that knows its facades. It
// embeds Match and states its package's names once.
type Decorator[D any] interface {
	Decorate(Match) D
}

// As asks a check in a decorator's terms: Where(engine.As(laravel.Node.IsFacadeCall)). The
// decorator is inferred from the check, so the node type stands in the check as a PHP type hint does.
func As[D Decorator[D]](check func(D) bool) Check {
	var decorator D

	return func(m Match) bool { return check(decorator.Decorate(m)) }
}
