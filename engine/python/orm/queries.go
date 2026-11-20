package orm

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var djangoQueries = []string{"get", "create", "get_or_create", "update_or_create", "count", "exists", "first", "last", "aggregate", "update", "delete"}
var sqlalchemyQueries = []string{"execute", "scalar", "scalars", "get", "all", "one", "one_or_none", "first", "count"}

func ExecutesQuery(call engine.Match) bool {
	name := call.Child("func").Name()
	if slices.Contains(djangoQueries, name) && fromDjangoManager(call.Receiver()) {
		return true
	}
	return slices.Contains(sqlalchemyQueries, name) && fromSQLAlchemy(call)
}

func fromDjangoManager(receiver engine.Match) bool {
	for target := receiver; target.Exists(); target = target.Receiver() {
		if target.Is(engine.MemberAccess) && target.Name() == "objects" && target.Receiver().Exists() {
			return true
		}
	}
	return false
}

func fromSQLAlchemy(call engine.Match) bool {
	for target := call.Receiver(); target.Exists(); target = target.Receiver() {
		if typed := target.Typed(); typed != nil && (typed.Name == "sqlalchemy.orm.Session" || typed.Name == "sqlalchemy.orm.Query" ||
			typed.Name == "sqlalchemy.ext.asyncio.AsyncSession") {
			return true
		}
	}
	return false
}
