import type { Page } from "@playwright/test";

/**
 * Ensure the inline comments panel is visible.
 * On wide viewports it's open by default; on narrow ones we toggle it.
 */
export async function ensureInlineCommentsOpen(page: Page) {
  await page.getByTestId("submission-content").waitFor({ state: "visible" });
  const visible = await page
    .getByTestId("inlineComment")
    .first()
    .isVisible()
    .catch(() => false);
  if (!visible) {
    await page.getByTestId("toggleInlineCommentsButton").click();
    await page
      .getByTestId("inlineComment")
      .first()
      .waitFor({ state: "visible" });
  }
}
