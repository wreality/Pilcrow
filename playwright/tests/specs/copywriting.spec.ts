import path from "path";
import { test, expect } from "@fixtures/base";
import type { Page } from "@playwright/test";

/**
 * Not a test of behaviour: this produces the screenshots copy editors use
 * to match on-screen text to translation keys. Each page is captured twice,
 * once in en-US and once with every string replaced by its key.
 *
 * See docs/developers/copyediting.md. CI uploads the output as the
 * `screenshots` artifact. Runs in the chromium project only (see
 * playwright.config.ts): the layout is the same in every engine.
 */
const OUTPUT_DIR = path.join("screenshots", "copywriting");

async function setLocale(page: Page, locale: "en-US" | "copy") {
  await page.locator("#locale-switch").evaluate((el, value) => {
    (el as HTMLInputElement).value = value;
    el.dispatchEvent(new Event("input", { bubbles: true }));
  }, locale);
}

async function screenshotPath(page: Page, url: string, waitOn = "#q-app") {
  await test.step(url, async () => {
    await page.goto(url);
    await page.locator(waitOn).first().waitFor();
    const name = url.replace(/\//g, "#");

    await setLocale(page, "en-US");
    await page.screenshot({
      path: path.join(OUTPUT_DIR, `${name} (Copy).png`),
      fullPage: true,
    });

    await setLocale(page, "copy");
    await page.screenshot({
      path: path.join(OUTPUT_DIR, `${name} (Keys).png`),
      fullPage: true,
    });
  });
}

test("copywriting screenshots", async ({ page, loginAs }) => {
  test.setTimeout(120_000);

  await loginAs("applicationadministrator@meshresearch.net", "/dashboard");
  await screenshotPath(page, "/");
  await screenshotPath(page, "/login");
  await screenshotPath(page, "/register");
  await screenshotPath(page, "/dashboard");
  await screenshotPath(page, "/publications", "[data-cy=publications_list]");
  await screenshotPath(page, "/account/profile", "[data-cy=position_title]");
  await screenshotPath(page, "/account/settings", "[data-cy=update_user_email]");
  await screenshotPath(page, "/publication/1/setup/basic", "[data-cy=visibility_field]");
  await screenshotPath(page, "/publication/1/setup/users", "[data-cy=editors_list]");
  await screenshotPath(
    page,
    "/publication/1/setup/content",
    "[data-cy=content_block_select]",
  );
  await screenshotPath(
    page,
    "/publication/1/setup/criteria",
    "[data-cy=add-criteria-button]",
  );
  await screenshotPath(page, "/feed");
  await screenshotPath(page, "/publication/1", "[data-cy=publication_home_content]");
  await screenshotPath(page, "/submissions", "[data-cy=submissions_table]");
  await screenshotPath(page, "/submission/100/details", "[data-cy=reviewers_list]");
  await screenshotPath(
    page,
    "/submission/100/review",
    "[data-cy=submission_review_layout]",
  );
  await screenshotPath(
    page,
    "/publication/1/create",
    "[data-cy=submission_create_subheading]",
  );

  await loginAs("regularuser@meshresearch.net", "/dashboard");
  await screenshotPath(page, "/submission/104/draft", "[data-cy=submission_title]");
  await screenshotPath(
    page,
    "/submission/104/content",
    "[data-cy=submission_content_title]",
  );

  await expect(page.locator("#locale-switch")).toHaveValue("copy");
});
