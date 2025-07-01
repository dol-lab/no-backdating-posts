/**
 * Reduced frequency of backdate notice checks by implementing improved throttling
 * and debouncing mechanisms to minimize server requests.
 */
(function (wp) {
	var lastSaveTime = 0;
	var lastCheckTime = 0;
	var checkingNotice = false;
	var debounceTimer = null;
	var wasJustSaving = false;
	var MIN_CHECK_INTERVAL = 5000; // Minimum 5 seconds between checks
	var DEBOUNCE_DELAY = 1000; // 1 second debounce delay

	function checkForBackdateNotice() {
		if (checkingNotice) return;

		var currentTime = Date.now();
		// Enforce minimum interval between actual server requests
		if (currentTime - lastCheckTime < MIN_CHECK_INTERVAL) {
			return;
		}

		checkingNotice = true;
		lastCheckTime = currentTime;

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

	function debouncedCheck() {
		if (debounceTimer) {
			clearTimeout(debounceTimer);
		}
		debounceTimer = setTimeout(checkForBackdateNotice, DEBOUNCE_DELAY);
	}

	wp.data.subscribe(function () {
		var isSaving = wp.data.select('core/editor').isSavingPost();
		var isAutosaving = wp.data.select('core/editor').isAutosavingPost();
		var currentTime = Date.now();

		// Only trigger when transitioning from saving to not saving (post save completion)
		if (wasJustSaving && !isSaving && !isAutosaving && currentTime - lastSaveTime > 2000) {
			lastSaveTime = currentTime;
			debouncedCheck();
		}

		// Track saving state for next iteration
		wasJustSaving = isSaving && !isAutosaving;
	});

	// Check for notice on initial load (only once)
	wp.domReady(function () {
		setTimeout(checkForBackdateNotice, 1000); // Delay initial check to avoid conflicts
	});

})(window.wp);
