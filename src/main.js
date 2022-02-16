import WorkflowOcr from './components/WorkflowOcr'

window.OCA.WorkflowEngine.registerOperator({
	id: 'OCA\\WorkflowOcr\\Operation',
	name: t('workflow_ocr'),
	description: t('workflow_ocr'),
	operation: '',
	options: WorkflowOcr,
})
