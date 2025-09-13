<?php

return [
	'dependencies' => [
		'core',
	],
	'tags' => [
		'backend.module',
	],
	'imports' => [
		'@typo3/core/' => [
			'path' => 'EXT:core/Resources/Public/JavaScript/',
			'exclude' => [
				'EXT:core/Resources/Public/JavaScript/Contrib/',
			],
		],
		'@typo3/backend/' => [
			'path' => 'EXT:backend/Resources/Public/JavaScript/',
			'exclude' => [
				'EXT:backend/Resources/Public/JavaScript/Contrib/',
			],
		],
		'@liquidlight/elevate-to-admin/' => 'EXT:elevate_to_admin/Resources/Public/JavaScript/',
	],
];
