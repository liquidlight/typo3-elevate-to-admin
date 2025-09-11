<?php

return [
	'backend' => [
		'liquidlight/elevate-to-admin/admin-elevation' => [
			'target' => \LiquidLight\ElevateToAdmin\Middleware\AdminElevationMiddleware::class,
			'after' => [
				'typo3/cms-backend/authentication',
			],
			'before' => [
				'typo3/cms-backend/site-resolver',
			],
		],
	],
];
