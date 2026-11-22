import * as helpers from './helpers'
import type { Item } from './item'

export const run = (item: Item): string => helpers.name(item)
