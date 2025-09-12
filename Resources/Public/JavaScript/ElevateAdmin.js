define([
	'TYPO3/CMS/Backend/Modal',
	'TYPO3/CMS/Core/Ajax/AjaxRequest',
	'TYPO3/CMS/Backend/Notification'
], function (Modal, AjaxRequest, Notification) {
	'use strict';

	var ElevateAdmin = {

		initialize: function() {
			var self = this;

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', function() {
					self.bindDropdownEvents();
				});
			} else {
				self.bindDropdownEvents();
			}
		},

		bindDropdownEvents: function() {
			var self = this;
			var enterButton = document.getElementById('enter-admin-mode');
			var leaveButton = document.getElementById('exit-admin-mode');

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
			var self = this,
				html = $(
					'<div class="form-group">' +
						'<label for="admin-password">Please enter your password to enter admin mode:</label>' +
						'<input type="password" class="form-control" id="admin-password" placeholder="Password" />' +
					'</div>'
				);

			var modal = Modal.show(
				'Enter admin mode',
				html,
				Modal.sizes.small,
				[
					{
						text: 'Cancel',
						btnClass: 'btn-default',
						trigger: function() {
							Modal.dismiss();
						}
					},
					{
						text: 'Enter',
						btnClass: 'btn-danger',
						trigger: function() {
							self.processElevation();
						}
					}
				]
			);

			modal.on('shown.bs.modal', function() {
				var passwordField = document.getElementById('admin-password');
				if (passwordField) {
					passwordField.focus();
					passwordField.addEventListener('keypress', function(e) {
						if (e.key === 'Enter' || e.keyCode === 13) {
							self.processElevation();
						}
					});
				}
			});
		},

		processElevation: function() {
			var field = document.getElementById('admin-password');
			var password = field ? field.value : '';
			if (!password) { Notification.error('Error', 'Password is required'); return; }
			new AjaxRequest(TYPO3.settings.ajaxUrls.elevate_admin).post({ password: password })
				.then(async response => await response.resolve())
				.then(function (data) {
					try {
						Modal.dismiss();
					} catch (e) { console.log('Modal hide error:', e); }

					if (data && data.success) {
						Notification.success('Success', data.message || 'Successfully elevated to admin');
						if (data.reload) {
							setTimeout(function () { window.location.reload(); }, 1000);
						}
					} else {
						Notification.error('Error', (data && data.message) || 'Elevation failed');
					}
				}).catch(function (error) {
					try {
						Modal.dismiss();
					} catch (e) { }
					console.error('Elevation error:', error);
					Notification.error('Error', 'Failed to process elevation request');
				});
		},

		processLeaveAdmin: function() {
			var self = this;

			Modal.confirm(
				'Leave Admin Mode',
				'Are you sure you want to leave admin mode? You will need to re-authenticate to regain admin privileges.',
				'warning',
				[
					{
						text: 'Cancel',
						btnClass: 'btn-default',
						trigger: function() {
							Modal.dismiss();
						}
					},
					{
						text: 'Leave Admin Mode',
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
						Notification.success('Success', data.message);
						if (data.reload) {
							setTimeout(function () { window.location.reload(); }, 500);
						}
					} else {
						Notification.error('Error', (data && data.message) || 'Failed to leave admin mode');
					}
				}).catch(function (error) {
					console.error('Leave admin error:', error);
					Notification.error('Error', 'Failed to process leave admin request');
				});
		}
	};

	// Initialize when loaded
	ElevateAdmin.initialize();

	return ElevateAdmin;
});
