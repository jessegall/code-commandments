package csharp

import "github.com/jessegall/code-commandments/engine"

func decodesJSON(call engine.Match) bool {
	target := call.Node().Target
	if target == nil {
		return false
	}
	switch target.Type {
	case "global::System.Text.Json.JsonDocument", "global::Newtonsoft.Json.Linq.JObject", "global::Newtonsoft.Json.Linq.JToken":
		return target.Name == "Parse"
	case "global::System.Text.Json.JsonSerializer":
		return target.Name == "Deserialize"
	case "global::Newtonsoft.Json.JsonConvert":
		return target.Name == "DeserializeObject"
	}

	return false
}

func encodesJSON(call engine.Match) bool {
	if target := call.Node().Target; target != nil {
		return target.Type == "global::System.Text.Json.JsonSerializer" && target.Name == "Serialize" ||
			target.Type == "global::Newtonsoft.Json.JsonConvert" && target.Name == "SerializeObject"
	}
	return false
}

func returnsUntypedData(function engine.Match) bool {
	typed := function.ReturnType().Typed()
	if typed == nil {
		return false
	}
	switch typed.Name {
	case "global::System.Object", "global::System.Text.Json.JsonDocument", "global::System.Text.Json.JsonElement", "global::Newtonsoft.Json.Linq.JObject", "global::Newtonsoft.Json.Linq.JToken":
		return true
	}
	return typed.Text == "object" || typed.Text == "dynamic" || typed.Kind == "map" && engine.IsUntypedCollection(typed)
}
