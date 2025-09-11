<?php

defined('TYPO3') or die();

$EM_CONF[$_EXTKEY] = [
	'title' => 'Elevate to Admin',
	'description' => 'Allow backend users to elevate themselves to admin privileges',
	'category' => 'be',
	'author' => 'Mike Street',
	'author_email' => 'mike@liquidlight.co.uk',
	'state' => 'stable',
	'version' => '0.0.0',
	'constraints' => [
		'depends' => [
			'typo3' => '11.5.0-12.4.99',
		],
		'conflicts' => [],
		'suggests' => [],
	],
];
