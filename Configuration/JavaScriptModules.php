<?php

return [
	'dependencies' => [
		'core',
		'backend',
	],
	'tags' => [
		'backend.module',
	],
	'imports' => [
		'@liquidlight/elevate-to-admin/' => 'EXT:ll_elevate_to_admin/Resources/Public/JavaScript/',
	],
];
