import { parseProblem } from "../problem";

it("parses a problem+json body into a typed ApiError", async () => {
  const res = new Response(
    JSON.stringify({
      type: "https://wallet.test/problems/insufficient-funds",
      title: "Insufficient funds.",
      status: 422,
      detail: "Saldo insuficiente.",
      request_id: "req-1",
      available: "10.00",
      requested: "30.00",
      currency: "BRL",
    }),
    { status: 422, headers: { "Content-Type": "application/problem+json" } },
  );

  const err = await parseProblem(res);

  expect(err.status).toBe(422);
  expect(err.type).toBe("https://wallet.test/problems/insufficient-funds");
  expect(err.extra.available).toBe("10.00");
  expect(err.extra.requested).toBe("30.00");
});

it("falls back to a generic ApiError when the body isn't problem+json", async () => {
  const res = new Response("Internal Server Error", { status: 500 });
  const err = await parseProblem(res);
  expect(err.status).toBe(500);
  expect(err.type).toBe("about:blank");
});
