import AvatarImage from "./AvatarImage.vue"
import { installQuasarPlugin } from "@quasar/quasar-app-extension-testing-unit-jest"
import { mount } from "@vue/test-utils"

installQuasarPlugin()
describe("AvatarImage Component", () => {
  const factory = (email) => {
    return mount(AvatarImage, {
      props: {
        user: {
          email,
        },
      },
    })
  }

  it("returns a deterministic value", () => {
    const wrapper = factory("test@pilcrow.dev")
    expect(wrapper.vm.avatarSrc).toBe("avatar-magenta.png")
  })
})
