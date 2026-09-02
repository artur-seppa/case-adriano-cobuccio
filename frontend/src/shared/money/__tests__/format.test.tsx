import { centsToDecimalString, formatCentsBRL } from "../format";

it("converts integer cents to the API decimal string", () => {
  expect(centsToDecimalString(15000)).toBe("150.00");
  expect(centsToDecimalString(5)).toBe("0.05");
  expect(centsToDecimalString(0)).toBe("0.00");
});

it("formats cents as BRL for optimistic display", () => {
  expect(formatCentsBRL(15000)).toBe("R$ 150,00");
});
