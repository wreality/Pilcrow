import type { APIRequestContext, Locator, Page, Response } from "@playwright/test";

interface GqlOperation {
  operationName?: string;
  variables?: Record<string, unknown>;
}

/**
 * Wait for a GraphQL response matching the given operation name.
 * Handles both single and batched GraphQL requests.
 *
 * Start waiting BEFORE the action that sends the request, then await the
 * returned promise afterwards. A wait registered after the response has
 * arrived never resolves.
 *
 * Pass `variables` to match one specific request, e.g. the search for the
 * full term rather than a keystroke on the way to it.
 */
export function waitForGQLOperation(
  page: Page,
  operationName: string,
  options?: {
    timeout?: number;
    variables?: (variables: Record<string, unknown>) => boolean;
  },
): Promise<Response> {
  const matches = (op: GqlOperation) =>
    op.operationName === operationName &&
    (options?.variables?.(op.variables ?? {}) ?? true);

  return page.waitForResponse(
    (response) => {
      if (!response.url().endsWith("/graphql")) return false;
      const request = response.request();
      const postData = request.postData();
      if (!postData) return false;

      try {
        const body = JSON.parse(postData) as GqlOperation | GqlOperation[];
        if (Array.isArray(body)) {
          return body.some(matches);
        }
        return matches(body);
      } catch {
        return false;
      }
    },
    { timeout: options?.timeout ?? 10_000 },
  );
}

/**
 * Type a search term into a locator and wait for results to appear.
 * The search may be served from Apollo's cache (no network request),
 * so we don't wait for a specific GQL response.
 */
export async function userSearch(
  locator: Locator,
  searchTerm: string,
): Promise<void> {
  await locator.click();
  await locator.page().keyboard.type(searchTerm, { delay: 50 });
}

/**
 * Seed a test submission via GraphQL. It is written to the worker's shadow
 * tables and disappears when the next test recreates them.
 *
 * @param status - GraphQL SubmissionStatus enum value, e.g. "UNDER_REVIEW", "REJECTED"
 * @returns The created submission's ID
 */
export async function seedSubmission(
  apiContext: APIRequestContext,
  options?: {
    title?: string;
    status?: string;
    publicationId?: number;
    withContent?: boolean;
  },
): Promise<string> {
  const response = await apiContext.post("/graphql", {
    data: {
      query: `mutation SeedSubmission(
        $title: String
        $status: SubmissionStatus
        $publication_id: ID
        $with_content: Boolean
      ) {
        seedSubmission(
          title: $title
          status: $status
          publication_id: $publication_id
          with_content: $with_content
        ) {
          id
        }
      }`,
      variables: {
        title: options?.title,
        status: options?.status,
        publication_id: options?.publicationId,
        with_content: options?.withContent,
      },
    },
  });

  const json = await response.json();
  if (json.errors) {
    throw new Error(
      `seedSubmission failed: ${JSON.stringify(json.errors).substring(0, 300)}`,
    );
  }

  return json.data.seedSubmission.id;
}
