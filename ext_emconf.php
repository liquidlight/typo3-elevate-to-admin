<?php

$EM_CONF[$_EXTKEY] = [
	'title' => 'Elevate to Admin',
	'description' => 'Allow backend users to elevate themselves to admin privileges',
	'category' => 'be',
	'author' => 'Mike Street',
	'author_email' => 'mike@liquidlight.co.uk',
	'state' => 'stable',
	'version' => '2.0.0',
	'constraints' => [
		'depends' => [
			'typo3' => '12.4.0-13.4.99',
		],
		'conflicts' => [],
		'suggests' => [],
	],
];
