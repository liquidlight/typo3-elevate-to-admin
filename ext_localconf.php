<?php

use LiquidLight\ElevateToAdmin\Backend\ToolbarItems\ElevateToolbarItem;

defined('TYPO3') or die();

// Register toolbar item
$GLOBALS['TYPO3_CONF_VARS']['BE']['toolbarItems']['elevateToAdmin'] = ElevateToolbarItem::class;
