import { describe, expect, test } from "vitest"
import { computed, ref } from "vue"
import { useCan } from "./abilities"

interface TestAbilities extends Record<string, boolean> {
  update_status: boolean
  review: boolean
}

const entity = (abilities: TestAbilities | null) => ({ abilities })

describe("useCan", () => {
  test("true only for a flag the server resolved true", () => {
    const can = useCan(ref(entity({ update_status: true, review: false })))
    expect(can("update_status")).toBe(true)
    expect(can("review")).toBe(false)
  })

  test("falls closed when the entity is absent", () => {
    const can = useCan<TestAbilities>(ref(null))
    expect(can("update_status")).toBe(false)
  })

  test("falls closed when abilities are absent", () => {
    const can = useCan<TestAbilities>(ref(entity(null)))
    expect(can("update_status")).toBe(false)
  })

  test("tracks a changing entity ref", () => {
    const source = ref<{ abilities: TestAbilities | null } | null>(null)
    const can = useCan(source)
    const gate = computed(() => can("review"))

    expect(gate.value).toBe(false)
    source.value = entity({ update_status: false, review: true })
    expect(gate.value).toBe(true)
  })
})
