export function sign(n: number): string {
  if (n < 0) {
    return "negative";
  } else {
    return "positive";
  }
}
