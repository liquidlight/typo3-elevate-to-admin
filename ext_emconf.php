<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Elevate to Admin',
	'description' => 'Allow backend users to elevate themselves to admin privileges',
	'category' => 'be',
	'author' => 'Liquid Light',
	'author_email' => 'info@liquidlight.co.uk',
	'author_company' => 'Liquid Light Ltd',
	'state' => 'stable',
	'version' => '2.2.0',
	'constraints' => [
		'depends' => [
			'typo3' => '12.4.0-13.4.99',
		],
		'conflicts' => [],
		'suggests' => [],
	],
];
