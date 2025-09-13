import Modal from '@typo3/backend/modal.js';
import AjaxRequest from '@typo3/core/ajax/ajax-request.js';
import Notification from '@typo3/backend/notification.js';

/**
 * Get localized string
 */
function getLabel(key) {
	return TYPO3.lang['elevate_to_admin.' + key] || key;
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
					self.showPasswordModal();
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

		showPasswordModal: function() {
			const self = this;
			const htmlString =
				'<div class="form-group">' +
					'<label for="admin-password">' + getLabel('modal.password_label') + '</label>' +
					'<input type="password" class="form-control" id="admin-password" placeholder="' + getLabel('modal.password_placeholder') + '" />' +
				'</div>';
			const html = document.createElement('div');
			html.innerHTML = htmlString;

			const modal = Modal.show(
				getLabel('modal.enter_admin_title'),
				html,
				Modal.sizes.small,
				[
					{
						text: getLabel('modal.cancel'),
						btnClass: 'btn-default',
						trigger: function() {
							Modal.dismiss();
						}
					},
					{
						text: getLabel('modal.enter'),
						btnClass: 'btn-danger',
						trigger: function() {
							self.processElevation();
						}
					}
				]
			);

			// Use setTimeout to ensure modal is rendered before focusing
			setTimeout(function() {
				const passwordField = document.getElementById('admin-password');
				if (passwordField) {
					passwordField.focus();
					passwordField.addEventListener('keypress', function(e) {
						if (e.key === 'Enter' || e.keyCode === 13) {
							self.processElevation();
						}
					});
				}
			}, 100);
		},

		processElevation: function() {
			const field = document.getElementById('admin-password');
			const password = field ? field.value : '';
			if (!password) { Notification.error(getLabel('js.error_title'), getLabel('error.password_required')); return; }
			new AjaxRequest(TYPO3.settings.ajaxUrls.elevate_admin).post({ password: password })
				.then(async response => await response.resolve())
				.then(function (data) {
					try {
						Modal.dismiss();
					} catch (e) { console.log('Modal hide error:', e); }

					if (data && data.success) {
						Notification.success(getLabel('js.success_title'), data.message || getLabel('success.elevated_to_admin'));
						if (data.reload) {
							setTimeout(function () { window.location.reload(); }, 1000);
						}
					} else {
						Notification.error(getLabel('js.error_title'), (data && data.message) || getLabel('js.elevation_failed'));
					}
				}).catch(function (error) {
					try {
						Modal.dismiss();
					} catch (e) { }
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
			new AjaxRequest(TYPO3.settings.ajaxUrls.elevate_admin).post({ action: 'leave' })
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
