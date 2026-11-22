export function visit(values: number[]): void {
  // @contract kind/function
  values.forEach((value) => {
    // @contract kind/return
    // @contract role/return/values -> []
    return;
  });
}
