import { chromium, request } from "@playwright/test";

/**
 * Runs once before all workers start.
 * Fully resets the database with migrate:fresh --seed and
 * cleans up any leftover shadow tables from previous runs.
 */
async function globalSetup() {
  const baseURL =
    process.env.PLAYWRIGHT_BASE_URL ?? "https://pilcrow.lndo.site";

  const apiContext = await request.newContext({
    baseURL,
    ignoreHTTPSErrors: true,
  });

  try {
    const response = await apiContext.post("/graphql", {
      data: { query: "mutation { resetDatabase }" },
    });
    const json = await response.json();
    if (json.errors) {
      throw new Error(
        `Global setup: resetDatabase failed: ${JSON.stringify(json.errors)}`,
      );
    }
  } finally {
    await apiContext.dispose();
  }

  await warmUp(baseURL);
}

/**
 * Load the app once before any worker starts.
 *
 * Against the Quasar dev server the first page load makes Vite optimize
 * dependencies and then reload the page, which destroys the execution
 * context under whichever tests happen to run first. Paying for that here
 * keeps it out of the tests. Against a built bundle this is one fast load.
 */
async function warmUp(baseURL: string) {
  const browser = await chromium.launch();
  try {
    const page = await browser.newPage({ baseURL, ignoreHTTPSErrors: true });
    for (const path of ["/", "/login"]) {
      await page.goto(path);
      await page.locator("#q-app header").waitFor();
    }
  } finally {
    await browser.close();
  }
}

export default globalSetup;
