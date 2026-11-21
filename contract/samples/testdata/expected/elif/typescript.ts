export function size(n: number): string {
  if (n < 10) {
    return "small";
  } else if (n < 100) {
    return "medium";
  } else {
    return "large";
  }
}
