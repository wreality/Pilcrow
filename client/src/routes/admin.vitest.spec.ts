import { installQuasarPlugin, installApolloClient } from "app/test/vitest/utils"
import { flushPromises, mount } from "@vue/test-utils"
import { beforeEach, describe, expect, test, vi } from "vitest"
import { CURRENT_USER } from "src/graphql/queries"
import AdminLayout from "./admin.vue"

vi.mock("vue-router", () => ({
  useRouter: () => ({ go: vi.fn() })
}))

installQuasarPlugin()
const mockClient = installApolloClient()

const currentUser = (admin_area: boolean) => ({
  data: {
    currentUser: {
      __typename: "User",
      id: "1",
      display_label: "Test",
      name: "Test",
      email: "test@example.com",
      username: "test",
      avatar_color: "blue",
      email_verified_at: null,
      highest_privileged_role: null,
      roles: [],
      abilities: admin_area ? ["admin_area"] : [],
      beta: false,
      feature_opt_ins: []
    }
  }
})

const handler = vi.fn()
mockClient.setRequestHandler(CURRENT_USER, handler)

describe("admin layout gate", () => {
  beforeEach(() => {
    handler.mockReset()
  })

  const makeWrapper = async () => {
    const wrapper = mount(AdminLayout, {
      global: {
        stubs: { "router-view": { template: `<div data-cy="admin_area" />` } }
      }
    })
    await flushPromises()
    return wrapper
  }

  test("renders the admin subtree when the viewer holds admin_area", async () => {
    handler.mockResolvedValue(currentUser(true))
    const wrapper = await makeWrapper()
    expect(wrapper.find('[data-cy="admin_area"]').exists()).toBe(true)
  })

  test("renders a 403 in place when the viewer lacks admin_area", async () => {
    handler.mockResolvedValue(currentUser(false))
    const wrapper = await makeWrapper()
    expect(wrapper.find('[data-cy="admin_area"]').exists()).toBe(false)
    expect(wrapper.text()).toContain("failures.error_403")
  })
})
