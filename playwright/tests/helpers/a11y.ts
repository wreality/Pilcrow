import AxeBuilder from "@axe-core/playwright";
import type { Page } from "@playwright/test";
import { expect } from "@playwright/test";

/**
 * Global a11y rule overrides. Centralizes rules that are disabled or
 * excluded across all tests so they can be managed in one place.
 *
 * Add rules here only when they represent known app-wide issues that
 * should be fixed but shouldn't block test runs. Prefer a per-page
 * override so the rule keeps protecting every other page.
 */
const GLOBAL_DISABLED_RULES: string[] = [];

/**
 * Rules to disable on the submission review page only. Its comment drawer
 * is an <aside> nested inside <main>, which axe reports as
 * landmark-complementary-is-top-level. Pass as `disableRules` there.
 */
export const REVIEW_LAYOUT_DISABLED_RULES = [
  "landmark-complementary-is-top-level",
];

const GLOBAL_EXCLUDES = [
  "vite-error-overlay",
];

interface A11yOptions {
  /** Additional selectors to exclude (merged with global excludes) */
  exclude?: string[];
  /** Additional rules to disable (merged with global disabled rules) */
  disableRules?: string[];
}

/**
 * Run axe accessibility checks on the current page.
 * Fails the test if any violations are found.
 *
 * Global excludes and disabled rules are always applied.
 * Pass additional overrides via options for page-specific needs.
 */
export async function checkA11y(
  page: Page,
  options?: A11yOptions,
): Promise<void> {
  let builder = new AxeBuilder({ page });

  const excludes = [...GLOBAL_EXCLUDES, ...(options?.exclude ?? [])];
  for (const selector of excludes) {
    builder = builder.exclude(selector);
  }

  const disabledRules = [
    ...GLOBAL_DISABLED_RULES,
    ...(options?.disableRules ?? []),
  ];
  if (disabledRules.length > 0) {
    builder = builder.disableRules(disabledRules);
  }

  const results = await builder.analyze();

  if (results.violations.length > 0) {
    const summary = results.violations
      .map((v) => {
        const targets = v.nodes.map((n) => n.target.join(" > ")).join(", ");
        return `  ${v.id} (${v.impact}): ${v.help} — ${targets}`;
      })
      .join("\n");
    expect(
      results.violations,
      `Accessibility violations found:\n${summary}`,
    ).toEqual([]);
  }
}
