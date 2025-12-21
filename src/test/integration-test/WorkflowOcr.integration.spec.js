import { mount } from '@vue/test-utils'
import { describe, it, expect, vi, beforeEach } from 'vitest'
import { nextTick } from 'vue'
import WorkflowOcr from '../../components/WorkflowOcr.vue'
import { tesseractLanguageMapping } from '../../constants.js'

// Use real Nextcloud Vue components
import { NcCheckboxRadioSwitch, NcSelect, NcSelectTags, NcTextField } from '@nextcloud/vue'
import SettingsItem from '../../components/SettingsItem.vue'
import HelpTextWrapper from '../../components/HelpTextWrapper.vue'

// Mock the backend info service used in the component's beforeMount
vi.mock('../../service/ocrBackendInfoService.js', () => ({
  // Use tesseract lang codes
  getInstalledLanguages: vi.fn(() => Promise.resolve(['eng', 'deu'])),
}))

describe('WorkflowOcr.vue (integration)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })
  it('consolidated UI changes produce expected serialized modelValue', async () => {
    // Create a real DOM container to mimic mounting inside Nextcloud HTML
    const container = document.createElement('div')
    container.id = 'nextcloud-integration-test-root'
    document.body.appendChild(container)

    const wrapper = mount(WorkflowOcr, {
      attachTo: container,
      props: { modelValue: '' },
      global: {
        components: {
          NcTextField,
          NcSelect,
          NcSelectTags,
          NcCheckboxRadioSwitch,
          SettingsItem,
          HelpTextWrapper,
        },
      },
    })

    // wait for beforeMount to resolve getInstalledLanguages
    await nextTick()

    // 1) Select languages: eng + deu
    wrapper.vm.selectedLanguages = [
      tesseractLanguageMapping.find((l) => l.langCode === 'eng'),
      tesseractLanguageMapping.find((l) => l.langCode === 'deu'),
    ]

    // 2) Set tags to add and remove via NcSelectTags components
    const selectTags = wrapper.findAllComponents(NcSelectTags)
    expect(selectTags.length).toBeGreaterThanOrEqual(2)
    await selectTags[0].vm.$emit('update:modelValue', [1, 2]) // tagsToAddAfterOcr
    await selectTags[1].vm.$emit('update:modelValue', [3]) // tagsToRemoveAfterOcr

    // 3) Toggle various switches by updating the reactive model directly
    wrapper.vm.model.keepOriginalFileVersion = true
    wrapper.vm.model.keepOriginalFileDate = true
    wrapper.vm.model.createSidecarFile = true
    wrapper.vm.model.skipNotificationsOnInvalidPdf = true
    wrapper.vm.model.skipNotificationsOnEncryptedPdf = true
    wrapper.vm.model.sendSuccessNotification = true
    await nextTick()

    // 4) Custom CLI args (set directly on model)
    wrapper.vm.model.customCliArgs = '--dpi 300'
    await nextTick()

    // 5) Test redo OCR behavior: set removeBackground true then set ocrMode to 1
    // enable removeBackground via model
    wrapper.vm.model.removeBackground = true
    await nextTick()

    const ocrRedo = wrapper.findComponent({ ref: 'ocrMode1' })
    expect(ocrRedo.exists()).toBe(true)
    await ocrRedo.vm.$emit('update:modelValue', '1')
    await nextTick()

    // After redo OCR, removeBackground should be false. Now set ocrMode to 2 and re-enable removeBackground
    wrapper.vm.ocrMode = '2'
    wrapper.vm.model.removeBackground = true
    await nextTick()

    // Allow reactivity to settle
    await nextTick()

    // Collect last emitted serialized modelValue
    const emitted = wrapper.emitted()['update:modelValue']
    expect(emitted).toBeTruthy()
    const last = emitted[emitted.length - 1][0]
    const parsed = JSON.parse(last)

    const expected = {
      languages: ['eng', 'deu'],
      tagsToAddAfterOcr: [1, 2],
      tagsToRemoveAfterOcr: [3],
      removeBackground: true,
      keepOriginalFileVersion: true,
      keepOriginalFileDate: true,
      sendSuccessNotification: true,
      ocrMode: 2,
      customCliArgs: '--dpi 300',
      createSidecarFile: true,
      skipNotificationsOnInvalidPdf: true,
      skipNotificationsOnEncryptedPdf: true,
    }

    expect(parsed).toEqual(expected)

    // Cleanup: unmount and remove container to avoid leaking nodes between tests
    wrapper.unmount()
    if (container.parentNode) {
      container.parentNode.removeChild(container)
    }
  })
})
