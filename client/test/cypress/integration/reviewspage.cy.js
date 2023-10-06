/// <reference types="Cypress" />
/// <reference path="../support/index.d.ts" />

describe("Reviews Page", () => {
  it("should allow a review coordinator to accept a submission for review and permit reviewers access", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewcoordinator@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("coordinator_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Initially Submitted")
      .parent("tr")
      .findCy("submission_actions")
      .click()
    cy.dataCy("change_status").click()
    cy.dataCy("accept_for_review").click()
    cy.interceptGQLOperation("UpdateSubmissionStatus")
    cy.dataCy("dirtyYesChangeStatus").click()
    cy.wait("@UpdateSubmissionStatus")
    cy.dataCy("change_status_notify")
      .should("be.visible")
      .should("have.class", "bg-positive")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("submission/101/review")
    cy.url().should("not.include", "/error403")
  })

  it("should deny a review coordinator from changing the status of rejected submissions", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewcoordinator@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("coordinator_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Rejected").parent("tr").findCy("submission_actions").click()
    cy.dataCy("change_status").should("have.class", "disabled")
    cy.dataCy("change_status_item_section").trigger("mouseenter")
    cy.dataCy("cannot_change_submission_status_tooltip")
  })

  it("should allow a review coordinator to access rejected submissions", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewcoordinator@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("coordinator_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Rejected").parent("tr").findCy("submission_actions").click()
    cy.dataCy("submission_details_link").click()
    cy.url().should("include", "/submission/102/details")
    cy.dataCy("submission_review_btn").click()
    cy.url().should("include", "/submission/102/review")
    cy.dataCy("submission_title")
  })

  it("should allow a review coordinator to access submissions requested for resubmission", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewcoordinator@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("coordinator_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Resubmission Requested")
      .parent("tr")
      .findCy("submission_actions")
      .click()
    cy.dataCy("submission_details_link").click()
    cy.url().should("include", "/submission/103/details")
    cy.dataCy("submission_title")
  })

  it("should deny a reviewer from accepting a submission for review", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("reviewer_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.dataCy("reviewer_table").should(
      "not.include.text",
      "Initially Submitted"
    )
  })

  it("should deny a reviewer from rejecting a submission for review", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("reviews")
    cy.interceptGQLOperation("UpdateSubmissionStatus")
    cy.dataCy("reviewer_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Resubmission Requested")
      .parent("tr")
      .findCy("submission_actions")
      .click()
    cy.dataCy("change_status").should("not.exist")
  })

  it("should deny a reviewer from changing the status of rejected submissions", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("reviewer_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Rejected").parent("tr").findCy("submission_actions").click()
    cy.dataCy("change_status").should("not.exist")
  })

  it("should deny a reviewer from accessing rejected submissions", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("reviewer_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Rejected").parent("tr").findCy("submission_actions").click()
    cy.dataCy("submission_review_link").should("have.class", "disabled")
    cy.dataCy("submission_review_link").trigger("mouseenter")
    cy.dataCy("cannot_access_submission_tooltip")
    cy.visit("submission/102/review")
    cy.url().should("include", "/error403")
  })

  it("should deny a reviewer from changing the status of submissions requested for resubmission", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("reviewer_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Resubmission Requested")
      .parent("tr")
      .findCy("submission_actions")
      .click()
    cy.dataCy("change_status").should("not.exist")
  })

  it("should deny a reviewer from accessing submissions requested for resubmission", () => {
    cy.task("resetDb")
    cy.login({ email: "reviewer@meshresearch.net" })
    cy.visit("reviews")
    cy.dataCy("reviewer_table").contains("Records per page").next().click()
    cy.get("[role='listbox']").contains("All").click()
    cy.contains("Resubmission Requested")
      .parent("tr")
      .findCy("submission_actions")
      .click()
    cy.dataCy("submission_review_link").should("have.class", "disabled")
    cy.dataCy("submission_review_link").trigger("mouseenter")
    cy.dataCy("cannot_access_submission_tooltip")
    cy.visit("submission/103/review")
    cy.url().should("include", "/error403")
  })
})
