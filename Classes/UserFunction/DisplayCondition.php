<?php

namespace LiquidLight\ElevateToAdmin\UserFunction;

class DisplayCondition
{
	/**
	 * Check if current user is admin and not editing their own record
	 *
	 */
	public function isAdminAndNotSelf(array $parameters): bool
	{
		$backendUser = $GLOBALS['BE_USER'] ?? null;

		if (!$backendUser || !$backendUser->isAdmin()) {
			return false;
		}

		// Get the record being edited
		$record = $parameters['record'] ?? null;
		if (!$record) {
			return false;
		}

		// Check if this is the current user's own record
		$currentUserId = (int)$backendUser->user['uid'];
		$editingUserId = (int)($record['uid'] ?? 0);

		// Show field only if admin is NOT editing their own record
		return $currentUserId !== $editingUserId;
	}

	/**
	 * Check if current user is admin
	 *
	 */
	public function isAdmin(array $parameters): bool
	{
		$backendUser = $GLOBALS['BE_USER'] ?? null;

		return $backendUser && $backendUser->isAdmin();
	}
}
