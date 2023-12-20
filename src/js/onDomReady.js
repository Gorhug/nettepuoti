const onDomReady = (cb) => {
	if (document.readyState !== 'loading') {
		cb();
	} else {
		document.addEventListener('DOMContentLoaded', cb);
	}
};

export default onDomReady;
