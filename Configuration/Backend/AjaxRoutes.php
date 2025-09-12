<?php

return [
	'elevate_admin' => [
		'path' => '/elevate-to-admin/elevate',
		'target' => \LiquidLight\ElevateToAdmin\Controller\ElevationController::class . '::elevateAction',
	],
];
