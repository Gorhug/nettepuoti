import onDomReady from './onDomReady';

export class SpinnerExtension {
	constructor(fallbackSelector) {
		this.fallbackSelector = fallbackSelector;
	}

	initialize(naja) {
		onDomReady(() => {
			const mainContent = document.querySelector(this.fallbackSelector);

			naja.uiHandler.addEventListener('interaction', (event) => {
				event.detail.options.spinnerTarget = event.detail.element.closest('[data-spinner]');
			});

			naja.addEventListener('start', (event) => (event.detail.options.spinnerTarget ?? mainContent).classList.remove('hidden'));
			naja.addEventListener('complete', (event) => (event.detail.options.spinnerTarget ?? mainContent).classList.add('hidden'));
		});
	}
}
