import type { Page } from "@playwright/test";
import { test, expect } from "@fixtures/base";
import { waitForGQLOperation } from "@helpers/graphql";
import { ensureInlineCommentsOpen } from "@helpers/review";
import type { SeededEmail } from "@helpers/users";

/**
 * Helper: login as a user, visit /feed, and assert the first notification
 * is unread and contains the expected message text.
 */
async function expectUnreadNotification(
  page: Page,
  loginAs: (email: SeededEmail, goto: string) => Promise<void>,
  email: SeededEmail,
  message: string,
) {
  await loginAs(email, "/feed");
  const firstItem = page.getByTestId("notification_list_item").nth(0);
  await expect(firstItem).toHaveClass(/unread/);
  await expect(firstItem).toContainText(message);
}

test("comment notifications: overall comment recipients", async ({
  page,
  loginAs,
}) => {

  const notificationMessage =
    "Reviewer for Submission added a new overall comment to Pilcrow Test Submission 1";

  await test.step("reviewer posts an overall comment", async () => {
    await loginAs("reviewer@meshresearch.net", "/submission/100/review");
    const createPromise = waitForGQLOperation(page, "CreateOverallComment");
    await page
      .getByTestId("overallCommentEditor")
      .locator("[contenteditable]")
      .click();
    await page.keyboard.type("Comment");
    await page
      .getByTestId("overallCommentEditor")
      .locator("button[type=submit]")
      .click();
    await createPromise;
    await expect(page.getByTestId("overallComment").last()).toContainText(
      "Comment",
    );
  });

  await test.step("submitter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "regularuser@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("review coordinator receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "reviewcoordinator@meshresearch.net",
      notificationMessage,
    );
  });
});

test("comment notifications: overall comment reply recipients", async ({
  page,
  loginAs,
}) => {

  const notificationMessage =
    "Reviewer for Submission replied to an overall comment for Pilcrow Test Submission 1";

  await test.step("reviewer posts an overall comment reply", async () => {
    await loginAs("reviewer@meshresearch.net", "/submission/100/review");
    const replyPromise = waitForGQLOperation(
      page,
      "CreateOverallComment",
    );
    await page.getByTestId("showRepliesButton").last().click();
    await page.getByTestId("overallCommentReplyButton").last().click();
    await page
      .getByTestId("overallCommentReplyEditor")
      .locator("[contenteditable]")
      .click();
    await page.keyboard.type("Comment");
    await page
      .getByTestId("overallCommentReplyEditor")
      .locator("button[type=submit]")
      .click();
    await replyPromise;
  });

  await test.step("submitter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "regularuser@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("parent commenter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "applicationadministrator@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("other commenter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "publicationadministrator@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("review coordinator receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "reviewcoordinator@meshresearch.net",
      notificationMessage,
    );
  });
});

test("comment notifications: inline comment reply recipients", async ({
  page,
  loginAs,
}) => {

  const notificationMessage =
    "Reviewer for Submission replied to an inline comment for Pilcrow Test Submission 1";

  await test.step("reviewer posts an inline comment reply", async () => {
    await loginAs("reviewer@meshresearch.net", "/submission/100/review");
    await ensureInlineCommentsOpen(page);

    await page
      .getByTestId("inlineComment")
      .first()
      .getByTestId("showRepliesButton")
      .click();
    await page.getByTestId("inlineCommentReplyButton").first().click();

    const replyPromise = waitForGQLOperation(
      page,
      "CreateInlineComment",
    );
    await page
      .getByTestId("inlineCommentReplyEditor")
      .locator("[contenteditable]")
      .click();
    await page.keyboard.type("Comment");
    await page
      .getByTestId("inlineCommentReplyEditor")
      .locator("button[type=submit]")
      .click();
    await replyPromise;
  });

  await test.step("submitter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "regularuser@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("parent commenter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "applicationadministrator@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("other commenter receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "publicationadministrator@meshresearch.net",
      notificationMessage,
    );
  });

  await test.step("review coordinator receives notification", async () => {
    await expectUnreadNotification(
      page,
      loginAs,
      "reviewcoordinator@meshresearch.net",
      notificationMessage,
    );
  });
});
