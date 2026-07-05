import { installQuasarPlugin } from "app/test/vitest/utils"
import { mount } from "@vue/test-utils"
import { beforeEach, describe, expect, test, vi } from "vitest"
import { defineComponent } from "vue"
import SubmissionGate from "./SubmissionGate.vue"
import { SubmissionStatus } from "src/graphql/generated/graphql"
import type { SubmissionScreen } from "src/router/canonicalRoute"
import {
  useSubmission,
  type SubmissionContext
} from "src/use/submissionContext"

const replace = vi.fn()
vi.mock("vue-router", () => ({
  useRouter: () => ({ replace, go: vi.fn() })
}))

installQuasarPlugin()

const Probe = defineComponent({
  setup() {
    const submission = useSubmission()
    return { submission }
  },
  template: `<div data-cy="probe">{{ submission?.title }}</div>`
})

const submission = (overrides: Partial<SubmissionContext> = {}) =>
  ({
    id: "42",
    title: "A Modest Proposal",
    status: SubmissionStatus.UNDER_REVIEW,
    ...overrides
  }) as SubmissionContext

interface GateProps {
  loading: boolean
  submission: SubmissionContext | null
  screen: SubmissionScreen
}

describe("SubmissionGate", () => {
  beforeEach(() => {
    replace.mockClear()
  })

  const makeWrapper = (props: GateProps) =>
    mount(SubmissionGate, {
      props,
      slots: { default: Probe }
    })

  test("null submission renders a 403 in place", () => {
    const wrapper = makeWrapper({
      loading: false,
      submission: null,
      screen: "review"
    })
    expect(wrapper.text()).toContain("failures.error_403")
    expect(replace).not.toHaveBeenCalled()
  })

  test("right canonical screen renders and provides the submission", () => {
    const wrapper = makeWrapper({
      loading: false,
      submission: submission(),
      screen: "review"
    })
    expect(wrapper.find('[data-cy="probe"]').text()).toBe("A Modest Proposal")
    expect(replace).not.toHaveBeenCalled()
  })

  test("wrong canonical screen replaces to the canonical route", () => {
    const wrapper = makeWrapper({
      loading: false,
      submission: submission({ status: SubmissionStatus.DRAFT }),
      screen: "review"
    })
    expect(replace).toHaveBeenCalledWith({
      name: "submission:preview",
      params: { id: "42" }
    })
    expect(wrapper.find('[data-cy="probe"]').exists()).toBe(false)
  })

  test("action screens never canonicalize", () => {
    const wrapper = makeWrapper({
      loading: false,
      submission: submission({ status: SubmissionStatus.DRAFT }),
      screen: "draft"
    })
    expect(replace).not.toHaveBeenCalled()
    expect(wrapper.find('[data-cy="probe"]').exists()).toBe(true)
  })
})
