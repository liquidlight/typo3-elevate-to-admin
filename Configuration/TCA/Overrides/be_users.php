<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Add the new fields to be_users table
$tempColumns = [
	'tx_elevate_to_admin_is_possible_admin' => [
		'exclude' => 1,
		'label' => 'LLL:EXT:elevate_to_admin/Resources/Private/Language/locallang_db.xlf:be_users.tx_elevate_to_admin_is_possible_admin',
		'config' => [
			'type' => 'check',
			'renderType' => 'checkboxToggle',
			'items' => [
				[
					0 => '',
					1 => '',
				],
			],
		],
		'displayCond' => 'USER:LiquidLight\\ElevateToAdmin\\UserFunction\\DisplayCondition->isAdminAndNotSelf',
	],
	'tx_elevate_to_admin_admin_since' => [
		'exclude' => 1,
		'label' => 'LLL:EXT:elevate_to_admin/Resources/Private/Language/locallang_db.xlf:be_users.tx_elevate_to_admin_admin_since',
		'config' => [
			'type' => 'input',
			'renderType' => 'inputDateTime',
			'eval' => 'datetime,int',
			'default' => 0,
			'readOnly' => 1,
		],
		'displayCond' => 'USER:LiquidLight\\ElevateToAdmin\\UserFunction\\DisplayCondition->isAdmin',
	],
];

ExtensionManagementUtility::addTCAcolumns('be_users', $tempColumns);

// Add fields to the interface
ExtensionManagementUtility::addToAllTCAtypes(
	'be_users',
	'tx_elevate_to_admin_is_possible_admin,tx_elevate_to_admin_admin_since',
	'',
	'after:admin'
);
