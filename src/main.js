import { translate as t } from '@nextcloud/l10n'
import { defineCustomElement } from 'vue'
import WorkflowOcr from './components/WorkflowOcr.vue'

const WorkflowOcrComponent = defineCustomElement(WorkflowOcr, {
	shadowRoot: false,
})
const customElementId = 'oca-workflow-ocr-settings'
globalThis.customElements.define(customElementId, WorkflowOcrComponent)

globalThis.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\WorkflowOcr\\Operation',
	name: t('workflow_ocr', 'OCR file'),
	description: t('workflow_ocr', 'OCR processing via workflow'),
	operation: '',
	element: customElementId,
	options: WorkflowOcr, // backward compatibility
})
