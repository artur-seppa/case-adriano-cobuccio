import { render, screen } from "@testing-library/react";
import userEvent from "@testing-library/user-event";
import { NuqsTestingAdapter } from "nuqs/adapters/testing";
import { TransactionFiltersBar } from "../TransactionFiltersBar";

function renderBar(searchParams?: string) {
  return render(
    <NuqsTestingAdapter searchParams={searchParams} hasMemory>
      <TransactionFiltersBar />
    </NuqsTestingAdapter>,
  );
}

it("reflects a filter already present in the URL, instead of resetting every select to its default option", () => {
  renderBar("?status=reversed");

  expect(screen.getByLabelText("Status")).toHaveValue("reversed");
  expect(screen.getByLabelText("Tipo")).toHaveValue("");
  expect(screen.getByLabelText("Direção")).toHaveValue("");
});

it("keeps a select showing its chosen option after the filter changes", async () => {
  const user = userEvent.setup();
  renderBar();

  expect(screen.getByLabelText("Tipo")).toHaveValue("");

  await user.selectOptions(screen.getByLabelText("Tipo"), "deposit");

  expect(screen.getByLabelText("Tipo")).toHaveValue("deposit");
});
