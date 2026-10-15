<?php

use TYPO3\CMS\Backend\Security\SudoMode\Access\AccessLifetime;

return [
	'elevate_admin' => [
		'path' => '/elevate-to-admin/elevate',
		'target' => \LiquidLight\ElevateToAdmin\Controller\ElevationController::class . '::elevateAction',
		'sudoMode' => [
			'group' => 'll_elevate_to_admin',
			'lifetime' => AccessLifetime::veryShort,
		],
	],
	'elevate_admin_leave' => [
		'path' => '/elevate-to-admin/leave',
		'target' => \LiquidLight\ElevateToAdmin\Controller\ElevationController::class . '::leaveAction',
	],
];
