<?php

use LiquidLight\ElevateToAdmin\Backend\ToolbarItems\ElevateToolbarItem;
use LiquidLight\ElevateToAdmin\Hooks\LogoutHook;

defined('TYPO3') or die();

// Register toolbar item
$GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems']['elevateToAdmin'] = ElevateToolbarItem::class;

// Register logout hook to clear admin elevation on logout
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_userauth.php']['logoff_pre_processing'][] =
	LogoutHook::class . '->logoffPreProcessing';
