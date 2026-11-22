namespace Shop; // @contract role/module/imports -> [textImport, jsonImport]

// @label textImport import
// @contract fact/import/source = "System.Text"
// @contract fact/import/form = "wildcard"
using System.Text;

// @label jsonImport import
// @label jsonBinding import-binding
// @contract kind/import-binding
// @contract role/import/bindings -> [jsonBinding]
// @contract fact/*/name on binding = "Json"
using Json = System.Text.Json.JsonSerializer;

public class Marker { }
