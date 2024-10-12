/**
 * @todo: the script is triggered to frequently, reduce the number of requests.
 */
(function (wp) {
	var lastSaveTime = 0;
	var checkingNotice = false;

	function checkForBackdateNotice() {
		if (checkingNotice) return;

		checkingNotice = true;
		wp.ajax.post(noBackdate.action, {
			nonce: noBackdate.nonce
		}).then(function (response) {
			if (response && response.message && response.type !== 'no-backdate') {
				wp.data.dispatch('core/notices').createNotice(
					response.type,
					response.message,
					{
						id: 'backdate-notice',
						isDismissible: true,
					}
				);
			} else {
				console.warn("No backdate notice", response);
			}
		}).catch(function (error) {
			console.error('Error checking for backdate notice:', error);
		}).always(function () {
			checkingNotice = false;
		});
	}

	wp.data.subscribe(function () {
		var isSaving = wp.data.select('core/editor').isSavingPost();
		var isAutosaving = wp.data.select('core/editor').isAutosavingPost();
		var currentTime = Date.now();

		if (!isSaving && !isAutosaving && currentTime - lastSaveTime > 1000) {
			// Post has finished saving (and it's not an autosave)
			lastSaveTime = currentTime;
			setTimeout(checkForBackdateNotice, 500); // Small delay to ensure server-side processing is complete
		}
	});

	// Also check for notice on initial load
	wp.domReady(checkForBackdateNotice);

})(window.wp);

