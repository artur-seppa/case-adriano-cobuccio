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

it("parses a problem-shaped body served as application/json (e.g. an idempotency replay)", async () => {
  const res = new Response(
    JSON.stringify({
      type: "https://wallet.test/problems/validation-failed",
      title: "The given data was invalid.",
      status: 422,
      errors: { recipient: ["No user matches that email or id."] },
    }),
    { status: 422, headers: { "Content-Type": "application/json" } },
  );

  const err = await parseProblem(res);

  expect(err.status).toBe(422);
  expect(err.type).toBe("https://wallet.test/problems/validation-failed");
  expect(err.errors?.recipient?.[0]).toContain("No user");
});

it("stays generic for an application/json body that is null (guest /api/user, 401)", async () => {
  const res = new Response(JSON.stringify(null), {
    status: 401,
    headers: { "Content-Type": "application/json" },
  });

  const err = await parseProblem(res);

  expect(err.status).toBe(401);
  expect(err.type).toBe("about:blank");
});

it("stays generic for a plain application/json error that isn't problem-shaped", async () => {
  const res = new Response(JSON.stringify({ message: "nope" }), {
    status: 400,
    headers: { "Content-Type": "application/json" },
  });

  const err = await parseProblem(res);

  expect(err.type).toBe("about:blank");
  expect(err.errors).toBeUndefined();
});

it("falls back to a generic ApiError when the body isn't problem+json", async () => {
  const res = new Response("Internal Server Error", { status: 500 });
  const err = await parseProblem(res);
  expect(err.status).toBe(500);
  expect(err.type).toBe("about:blank");
});

it("falls back to a generic ApiError when the problem+json body is malformed", async () => {
  const res = new Response("not json", {
    status: 500,
    headers: { "Content-Type": "application/problem+json" },
  });
  const err = await parseProblem(res);
  expect(err.status).toBe(500);
  expect(err.type).toBe("about:blank");
});
