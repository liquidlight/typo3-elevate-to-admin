<?php

// Test database configuration for functional tests

return [
	'BE' => [
		'debug' => false,
		'explicitADmode' => 'explicitAllow',
		'installToolPassword' => '$argon2i$v=19$m=65536,t=16,p=1$test',
		'passwordHashing' => [
			'className' => 'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
			'options' => [],
		],
		'cookieName' => 'be_typo_user',
		'lockIP' => 0,
		'lockIPv6' => 0,
		'sessionTimeout' => 28800,
	],
	'DB' => [
		'Connections' => [
			'Default' => [
				'charset' => 'utf8mb4',
				'driver' => 'pdo_sqlite',
				'path' => ':memory:',
				'tableoptions' => [
					'charset' => 'utf8mb4',
					'collate' => 'utf8mb4_unicode_ci',
				],
			],
		],
	],
	'EXTENSIONS' => [
		'elevate_to_admin' => [
			// Extension configuration
		],
	],
	'FE' => [
		'debug' => false,
	],
	'GFX' => [
		'processor' => '',
		'processor_allowTemporaryMasksAsPng' => false,
		'processor_colorspace' => 'RGB',
		'processor_effects' => false,
		'processor_enabled' => true,
		'processor_path' => '/usr/bin/',
	],
	'LOG' => [
		'TYPO3' => [
			'CMS' => [
				'deprecations' => [
					'writerConfiguration' => [
						'notice' => [
							'TYPO3\CMS\Core\Log\Writer\NullWriter' => [],
						],
					],
				],
			],
		],
	],
	'MAIL' => [
		'transport' => 'null',
	],
	'SYS' => [
		'availablePasswordHashAlgorithms' => [
			'TYPO3\\CMS\\Core\\Crypto\\PasswordHashing\\Argon2iPasswordHash',
		],
		'caching' => [
			'cacheConfigurations' => [
				'hash' => [
					'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend',
				],
				'pages' => [
					'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend',
				],
				'pagesection' => [
					'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend',
				],
				'rootline' => [
					'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend',
				],
				'database_schema' => [
					'backend' => 'TYPO3\\CMS\\Core\\Cache\\Backend\\NullBackend',
				],
			],
		],
		'devIPmask' => '*',
		'displayErrors' => 0,
		'encryptionKey' => 'test_encryption_key_for_functional_tests_only',
		'exceptionalErrors' => E_WARNING | E_RECOVERABLE_ERROR | E_DEPRECATED | E_USER_DEPRECATED,
		'features' => [
			'unifiedPageTranslationHandling' => true,
		],
		'sitename' => 'TYPO3 Elevate to Admin Test',
		'trustedHostsPattern' => '.*',
	],
];
