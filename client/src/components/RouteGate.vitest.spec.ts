import { installQuasarPlugin } from "app/test/vitest/utils"
import { mount } from "@vue/test-utils"
import { describe, expect, test, vi } from "vitest"
import RouteGate from "./RouteGate.vue"

vi.mock("vue-router", () => ({
  useRouter: () => ({ go: vi.fn() })
}))

installQuasarPlugin()

describe("RouteGate", () => {
  const makeWrapper = (props: Record<string, unknown>) =>
    mount(RouteGate, {
      props,
      slots: {
        default: `<div data-cy="page">page</div>`
      }
    })

  test("shows the loading slot while loading", () => {
    const wrapper = makeWrapper({ loading: true, entity: null })
    expect(wrapper.find('[data-cy="route_gate_loading"]').exists()).toBe(true)
    expect(wrapper.find('[data-cy="page"]').exists()).toBe(false)
  })

  test("renders a 403 in place when the entity is null (server denied)", () => {
    const wrapper = makeWrapper({ loading: false, entity: null })
    expect(wrapper.text()).toContain("failures.error_403")
    expect(wrapper.find('[data-cy="page"]').exists()).toBe(false)
  })

  test("renders a 403 in place when not allowed", () => {
    const wrapper = makeWrapper({ loading: false, allowed: false })
    expect(wrapper.text()).toContain("failures.error_403")
    expect(wrapper.find('[data-cy="page"]').exists()).toBe(false)
  })

  test("renders the page when the entity is present", () => {
    const wrapper = makeWrapper({ loading: false, entity: { id: "1" } })
    expect(wrapper.find('[data-cy="page"]').exists()).toBe(true)
  })

  test("renders the page in allowed mode with no entity bound", () => {
    const wrapper = makeWrapper({ loading: false, allowed: true })
    expect(wrapper.find('[data-cy="page"]').exists()).toBe(true)
  })

  test("exposes the entity to the default slot", () => {
    const wrapper = mount(RouteGate, {
      props: { loading: false, entity: { id: "7" } },
      slots: {
        default: `<template #default="{ entity }"><div data-cy="page">{{ entity.id }}</div></template>`
      }
    })
    expect(wrapper.find('[data-cy="page"]').text()).toBe("7")
  })
})
