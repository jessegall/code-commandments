package engine

// DependencyArrow is one reference from a part of a codebase to another, where it is written.
type DependencyArrow struct {
	At   Match
	From string
	To   string
}

// DependencyArrows is the references between the parts of a codebase, in the order the files are written: which part uses which, and the pairs of parts that use each other.
type DependencyArrows []DependencyArrow

// Pair is the two parts a reference joins: the part it is written in and the part it reaches.
type Pair struct {
	From string
	To   string
}

// ArrowCounts is references counted by the parts they join, without where they are written: the references of the parts of a program not held, beside the arrows of the part that is.
type ArrowCounts map[Pair]int
