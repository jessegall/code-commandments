// @contract kind/import
// @contract role/import-binding/alias -> alias
import { Invoice as Bill } from "./billing"; // @label alias binding

export function total(bill: Bill): number {
  return 0;
}
