import { describe, expect, test } from "vitest"
import { canonicalRouteFor, canonicalScreenFor } from "./canonicalRoute"

describe("canonicalScreenFor", () => {
  test.each([
    ["DRAFT", "preview"],
    ["INITIALLY_SUBMITTED", "view"],
    ["UNDER_REVIEW", "review"],
    ["AWAITING_DECISION", "review"],
    ["REJECTED", "review"],
    ["ACCEPTED_AS_FINAL", "review"]
  ])("%s → %s", (status, screen) => {
    expect(canonicalScreenFor(status)).toBe(screen)
  })
})

describe("canonicalRouteFor", () => {
  test("builds the canonical route for the status", () => {
    expect(canonicalRouteFor({ id: "42", status: "DRAFT" })).toStrictEqual({
      name: "submission:preview",
      params: { id: "42" }
    })
    expect(
      canonicalRouteFor({ id: "42", status: "UNDER_REVIEW" })
    ).toStrictEqual({
      name: "submission:review",
      params: { id: "42" }
    })
  })
})
