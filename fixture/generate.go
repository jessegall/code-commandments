package fixture

//go:generate sh -c "php ../bridge/php/bridge.php testdata/toy | sed \"s|$(cd testdata/toy && pwd -P)|/toy|g\" > testdata/toy.jsonl"
