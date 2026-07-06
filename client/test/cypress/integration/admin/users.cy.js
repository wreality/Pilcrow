/// <reference types="Cypress" />
/// <reference path="../../support/index.d.ts" />
// Use `cy.dataCy` custom command for more robust tests
// See https://docs.cypress.io/guides/references/best-practices.html#Selecting-Elements

import { a11yLogViolations } from "../../support/helpers"

describe("Admin Users Index", () => {
  beforeEach(() => {
    cy.task("resetDb")
  })

  it("restricts access based on role", () => {
    cy.login({ email: "regularuser@meshresearch.net" })
    cy.visit("/admin/users")
    // Denial renders a 403 in place; the URL is preserved.
    cy.dataCy("error403")
    cy.url().should("include", "/admin/users")
  })

  it("allows access based on role", () => {
    cy.login({ email: "applicationadministrator@meshresearch.net" })
    cy.visit("/admin/users")
    cy.contains("h2", /./)
    cy.dataCy("error403").should("not.exist")
  })

  it("should assert the page is accessible", () => {
    cy.login({ email: "applicationadministrator@meshresearch.net" })
    cy.visit("/admin/users")
    cy.contains("h2", /./)
    cy.injectAxe()
    cy.checkA11y(null, null, a11yLogViolations)
  })
})
