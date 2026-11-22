export function load(): string {
  try {
    return read();
  // @contract kind/handler
  } catch (error) {
    console.warn("read failed", error);
    // @contract role/return/values -> [empty]
    return ""; // @label empty literal
  }
}
