package csharp

import ()

// keyed is the record of a candidate a rule groups: the key it reads as, when it reads as one, its exact reading, and the owner it is declared in.
type keyed struct {
	key   string
	read  bool
	exact string
	owner string
}

// supply is how many calls a codebase holds fill a parameter, `Shop.Pricing.Quote(…)#1`: a record no finding is made of, counted across every codebase a verdict weighs.
type supply struct {
	slot  string
	count int
}

// unresolved is a name some call the compiler could not resolve spells.
type unresolved struct {
	name string
}
