import { centsToDecimalString, formatCentsBRL, formatDecimalBRL } from "../format";

it("converts integer cents to the API decimal string", () => {
  expect(centsToDecimalString(15000)).toBe("150.00");
  expect(centsToDecimalString(5)).toBe("0.05");
  expect(centsToDecimalString(0)).toBe("0.00");
});

it("formats cents as BRL for optimistic display", () => {
  expect(formatCentsBRL(15000)).toBe("R$ 150,00");
});

it("formats a server-provided decimal string without float parsing", () => {
  expect(formatDecimalBRL("10.00")).toBe("R$ 10,00");
  expect(formatDecimalBRL("1500.00")).toBe("R$ 1.500,00");
  expect(formatDecimalBRL("-50.00")).toBe("-R$ 50,00");
});
