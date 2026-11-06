package engine

// Decorator is a package's own view of a match, such as a Laravel node that knows its facades.
type Decorator[D any] interface {
	Decorate(Match) D
}

func As[D Decorator[D]](check func(D) bool) Check {
	var decorator D

	return func(m Match) bool { return check(decorator.Decorate(m)) }
}
