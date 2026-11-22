import { Ledger } from "./ledger";

export class Account {
  private ledger: Ledger | null = null;

  settle(amounts: number[]): number {
    let total = 0;
    // @contract kind/loop
    // @contract fact/loop/form = "for-each"
    for (const amount of amounts) { // @label each loop
      // @contract role/if/condition -> both
      if (amount > 0 && amount < 100) { // @label both logical
        total += amount; // @contract kind/assignment
        continue; // @contract link/continue/destination -> each
      }
      break; // @contract link/break/destination -> each
    }
    try {
      this.ledger?.record(total);
    } catch (error) {
      throw new Error("not recorded");
    }
    return total;
  }
}
