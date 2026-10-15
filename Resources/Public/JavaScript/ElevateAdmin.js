import Modal from '@typo3/backend/modal.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';
import { sudoModeInterceptor } from '@typo3/backend/security/sudo-mode-interceptor.js';

/**
 * Get localized string
 */
function getLabel(key) {
	return TYPO3.lang['ll_elevate_to_admin.' + key] || key;
}

const ElevateAdmin = {
	initialize: function() {
		const self = this;

		if (document.readyState === 'loading') {
			document.addEventListener('DOMContentLoaded', function() {
				self.bindDropdownEvents();
			});
		} else {
			self.bindDropdownEvents();
		}
	},

	bindDropdownEvents: function() {
		const self = this;
		const enterButton = document.getElementById('enter-admin-mode');
		const leaveButton = document.getElementById('exit-admin-mode');

			if (enterButton) {
				enterButton.addEventListener('click', function(e) {
					e.preventDefault();
					self.processElevation();
				});
			}

			if (leaveButton) {
				leaveButton.addEventListener('click', function(e) {
					e.preventDefault();
					self.processLeaveAdmin();
				});
			}

			if (!enterButton && !leaveButton) {
				setTimeout(function() {
					self.bindDropdownEvents();
				}, 100);
			}
		},

		processElevation: function() {
			// TYPO3 asks for the user's password (sudo mode) before the request is processed
			new AjaxRequest(TYPO3.settings.ajaxUrls.elevate_admin)
				.addMiddleware(sudoModeInterceptor)
				.post({})
				.then(async response => await response.resolve())
				.then(function (data) {
					if (data && data.success) {
						Notification.success(getLabel('js.success_title'), data.message || getLabel('success.elevated_to_admin'));
						if (data.reload) {
							setTimeout(function () { window.location.reload(); }, 1000);
						}
					} else {
						Notification.error(getLabel('js.error_title'), (data && data.message) || getLabel('js.elevation_failed'));
					}
				}).catch(function (error) {
					// 422 means the password dialog was closed without verifying
					if (error && error.response && error.response.status === 422) {
						return;
					}
					console.error('Elevation error:', error);
					Notification.error(getLabel('js.error_title'), getLabel('js.elevation_request_failed'));
				});
		},

		processLeaveAdmin: function() {
			const self = this;

			Modal.confirm(
				getLabel('modal.leave_admin_title'),
				getLabel('modal.leave_admin_message'),
				'warning',
				[
					{
						text: getLabel('modal.cancel'),
						btnClass: 'btn-default',
						trigger: function() {
							Modal.dismiss();
						}
					},
					{
						text: getLabel('modal.leave_admin_button'),
						btnClass: 'btn-warning',
						trigger: function() {
							self.performLeaveAdmin();
						}
					}
				]
			);
		},

		performLeaveAdmin: function() {
			new AjaxRequest(TYPO3.settings.ajaxUrls.elevate_admin_leave).post({})
				.then(async response => await response.resolve())
				.then(function (data) {
					Modal.dismiss();

					if (data && data.success) {
						Notification.success(getLabel('js.success_title'), data.message);
						if (data.reload) {
							setTimeout(function () { window.location.reload(); }, 500);
						}
					} else {
						Notification.error(getLabel('js.error_title'), (data && data.message) || getLabel('js.leave_admin_failed'));
					}
				}).catch(function (error) {
					console.error('Leave admin error:', error);
					Notification.error(getLabel('js.error_title'), getLabel('js.leave_admin_request_failed'));
				});
		}
	};

// Initialize when loaded
ElevateAdmin.initialize();

export default ElevateAdmin;
