import { test, expect } from "@fixtures/base";
import { checkA11y } from "@helpers/a11y";

test("guest: home page accessibility", async ({ page }) => {
  await page.goto("/");
  await page.getByTestId("vueIndex").waitFor();
  await expect(page).toHaveTitle(/Pilcrow/);
  await checkA11y(page);
});

test("regular user: dashboard accessibility", async ({
  page,
  loginAs,
}) => {
  await loginAs("regularuser@meshresearch.net", "/dashboard");
  await page.getByTestId("vueDashboard").waitFor();
  await checkA11y(page);
});
