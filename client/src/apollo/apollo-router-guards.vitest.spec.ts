import { afterEach, describe, expect, it, vi } from "vitest"
import { beforeEachRequiresAuth } from "./apollo-router-guards"

const apolloMock = {
  query: vi.fn()
}

describe("requiresAuth router hook", () => {
  afterEach(() => {
    apolloMock.query.mockClear()
  })

  it("allows navigation when user is logged in", async () => {
    const to = {
      matched: [{ meta: { requiresAuth: true } }]
    }

    apolloMock.query.mockResolvedValue({
      data: {
        currentUser: {
          id: 1
        }
      }
    })

    const next = vi.fn()

    await beforeEachRequiresAuth(apolloMock, to, undefined, next)

    expect(apolloMock.query).toHaveBeenCalled()
    expect(next).toHaveBeenCalled()
    expect(next.mock.calls[0][0]).toBeUndefined()
  })

  it("redirects to login page when user is not logged in", async () => {
    const setItem = vi.spyOn(window.sessionStorage, "setItem")

    const to = {
      matched: [{ meta: { requiresAuth: true } }]
    }

    apolloMock.query.mockResolvedValue({
      data: {
        currentUser: null
      }
    })

    const next = vi.fn()

    await beforeEachRequiresAuth(apolloMock, to, undefined, next)

    expect(apolloMock.query).toHaveBeenCalled()
    expect(next).toHaveBeenCalled()
    expect(next.mock.calls[0][0]).toBe("/login")

    expect(window.sessionStorage.setItem).toHaveBeenCalled()
    setItem.mockReset()
  })

  it("allows navigation when route is missing the requiresAuth meta property", async () => {
    const to = {
      matched: [{ meta: {} }]
    }

    const next = vi.fn()

    await beforeEachRequiresAuth(apolloMock, to, undefined, next)

    expect(apolloMock.query).not.toHaveBeenCalled()
    expect(next).toHaveBeenCalled()
    expect(next.mock.calls[0][0]).toBeUndefined()
  })
})
