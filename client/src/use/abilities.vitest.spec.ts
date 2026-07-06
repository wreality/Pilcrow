import { describe, expect, test } from "vitest"
import { computed, ref } from "vue"
import { useCan } from "./abilities"

type TestAbility = "update_status" | "review"

const entity = (abilities: TestAbility[] | null) => ({ abilities })

describe("useCan", () => {
  test("true only for a granted ability", () => {
    const can = useCan(ref(entity(["update_status"])))
    expect(can("update_status")).toBe(true)
    expect(can("review")).toBe(false)
  })

  test("falls closed when the entity is absent", () => {
    const can = useCan<TestAbility>(ref(null))
    expect(can("update_status")).toBe(false)
  })

  test("falls closed when abilities are absent", () => {
    const can = useCan(ref(entity(null)))
    expect(can("update_status")).toBe(false)
  })

  test("tracks a changing entity ref", () => {
    const source = ref<{ abilities: TestAbility[] | null } | null>(null)
    const can = useCan(source)
    const gate = computed(() => can("review"))

    expect(gate.value).toBe(false)
    source.value = entity(["review"])
    expect(gate.value).toBe(true)
  })
})
