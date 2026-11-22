<?php // @contract role/file/modules -> [supportModule, shopModule]

// @label supportModule module
// @contract kind/module
// @contract role/module/declarations -> [supportMoney]
namespace Shop\Support;

// @label supportMoney type
final class Money {}

// @label shopModule module
// @contract role/module/imports -> [useMoney]
// @contract role/module/declarations -> [totalFn]
// @contract role/module/statements -> [countStmt]
namespace Shop;

// @label useMoney import
// @contract fact/import/form = "names"
// @contract fact/import/source = "Shop\\Support\\Money"
// @contract fact/import/resolves = "/sample/contract_php_module/php.php"
// @contract role/import/bindings -> [moneyBinding]
// @contract kind/import-binding
// @contract fact/*/refers on import-binding -> supportMoney
// @label moneyBinding import-binding
use Shop\Support\Money;

// @label totalFn function
function total(): int
{
    return 1;
}

// @label countStmt expression-statement
$count = total();
