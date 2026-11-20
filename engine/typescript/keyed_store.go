package typescript

import "github.com/jessegall/code-commandments/engine"

func KeyedStore(read engine.Match) engine.Match {
	if read.Kind() == "ElementAccessExpression" && read.AdmitsAbsence() {
		store := read.Receiver()
		if typed := store.Typed(); typed != nil && typed.Kind == "map" {
			return store
		}
	}
	if symbol := read.TargetSymbol(); symbol == "Map.get" || symbol == "ReadonlyMap.get" {
		return read.Receiver()
	}
	return engine.Match{}
}
