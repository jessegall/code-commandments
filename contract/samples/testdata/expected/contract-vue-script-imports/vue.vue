<script lang="ts">
// @contract kind/import
// @contract fact/import/form = "names"
// @contract fact/import/source = "./item"
// @contract role/import/bindings -> [itemBinding]
// @label itemBinding import-binding
// @contract fact/*/name on import-binding = "Item"
// @contract role/import-binding/alias -> entryAlias
// @label entryAlias binding 18
import { Item as Entry } from './item'
// @contract kind/import
// @contract fact/import/form = "side-effect"
import './polyfill'

export function make(entry: Entry): Entry {
    return entry
}
</script>
