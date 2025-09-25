<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Unit\Traits;

use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdminElevationTraitTest extends TestCase
{
	use AdminElevationTrait;

	private $backendUserMock;

	private $connectionMock;

	private $connectionPoolMock;

	protected function setUp(): void
	{
		parent::setUp();

		$this->backendUserMock = $this->createMock(BackendUserAuthentication::class);
		$this->connectionMock = $this->createMock(Connection::class);
		$this->connectionPoolMock = $this->createMock(ConnectionPool::class);

		$this->connectionPoolMock
			->method('getConnectionForTable')
			->with('be_users')
			->willReturn($this->connectionMock)
		;

		GeneralUtility::addInstance(ConnectionPool::class, $this->connectionPoolMock);
	}

	protected function tearDown(): void
	{
		unset($GLOBALS['BE_USER']);
		GeneralUtility::purgeInstances();
		parent::tearDown();
	}

	public function testGetBackendUserReturnsUserFromGlobals(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$result = $this->getBackendUser();

		$this->assertSame($this->backendUserMock, $result);
	}

	public function testGetBackendUserReturnsNullWhenNoUser(): void
	{
		unset($GLOBALS['BE_USER']);

		$result = $this->getBackendUser();

		$this->assertNull($result);
	}

	public function testUpdateUserRecordCallsConnectionUpdate(): void
	{
		$userId = 123;
		$fields = ['admin' => 1, 'test_field' => 'value'];

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				$fields,
				['uid' => $userId]
			)
		;

		$this->updateUserRecord($userId, $fields);
	}

	public function testUpdateGlobalUserDataUpdatesGlobalsForCurrentUser(): void
	{
		$userId = 123;
		$fields = ['admin' => 1, 'test_field' => 'value'];

		$this->backendUserMock->user = ['uid' => $userId, 'admin' => 0];
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->updateGlobalUserData($userId, $fields);

		$this->assertEquals(1, $GLOBALS['BE_USER']->user['admin']);
		$this->assertEquals('value', $GLOBALS['BE_USER']->user['test_field']);
	}

	public function testUpdateGlobalUserDataDoesNotUpdateForDifferentUser(): void
	{
		$userId = 123;
		$otherUserId = 456;
		$fields = ['admin' => 1];

		$this->backendUserMock->user = ['uid' => $otherUserId, 'admin' => 0];
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->updateGlobalUserData($userId, $fields);

		$this->assertEquals(0, $GLOBALS['BE_USER']->user['admin']);
	}

	public function testCanUserElevateReturnsTrueWhenUserHasPermission(): void
	{
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_is_possible_admin' => 1,
		];

		$result = $this->canUserElevate($this->backendUserMock);

		$this->assertTrue($result);
	}

	public function testCanUserElevateReturnsFalseWhenUserHasNoPermission(): void
	{
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_is_possible_admin' => 0,
		];

		$result = $this->canUserElevate($this->backendUserMock);

		$this->assertFalse($result);
	}

	public function testCanUserElevateReturnsFalseWhenNoUser(): void
	{
		$result = $this->canUserElevate(null);

		$this->assertFalse($result);
	}

	public function testCanUserElevateUsesGlobalUserWhenNotProvided(): void
	{
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_is_possible_admin' => 1,
		];
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$result = $this->canUserElevate();

		$this->assertTrue($result);
	}

	public function testClearAdminElevationUpdatesUserRecord(): void
	{
		$userId = 123;
		$expectedFields = [
			'admin' => 0,
			'options' => 3,
			'tx_elevatetoadmin_admin_since' => 0,
		];

		$this->backendUserMock->user = ['uid' => $userId];
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				$expectedFields,
				['uid' => $userId]
			)
		;

		$this->clearAdminElevation($userId);
	}

	public function testSetAdminElevationWithTimestamp(): void
	{
		$userId = 123;
		$timestamp = 1234567890;
		$expectedFields = [
			'admin' => 1,
			'options' => 0,
			'tx_elevatetoadmin_admin_since' => $timestamp,
			'tx_elevatetoadmin_is_possible_admin' => 1,
		];

		$this->backendUserMock->user = ['uid' => $userId];
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				$expectedFields,
				['uid' => $userId]
			)
		;

		$this->setAdminElevation($userId, $timestamp);
	}

	public function testSetAdminElevationWithoutTimestamp(): void
	{
		$userId = 123;

		$this->backendUserMock->user = ['uid' => $userId];
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->connectionMock
			->expects($this->once())
			->method('update')
			->with(
				'be_users',
				$this->callback(function ($fields) {
					return $fields['admin'] === 1
						&& $fields['options'] === 0
						&& $fields['tx_elevatetoadmin_is_possible_admin'] === 1
						&& is_int($fields['tx_elevatetoadmin_admin_since'])
						&& $fields['tx_elevatetoadmin_admin_since'] > 0;
				}),
				['uid' => $userId]
			)
		;

		$this->setAdminElevation($userId);
	}

	public function testGetAdminSinceReturnsTimestamp(): void
	{
		$timestamp = 1234567890;
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_admin_since' => $timestamp,
		];

		$result = $this->getAdminSince($this->backendUserMock);

		$this->assertEquals($timestamp, $result);
	}

	public function testGetAdminSinceReturnsZeroWhenFieldMissing(): void
	{
		$this->backendUserMock->user = [];

		$result = $this->getAdminSince($this->backendUserMock);

		$this->assertEquals(0, $result);
	}

	public function testGetAdminSinceReturnsZeroWhenNoUser(): void
	{
		$result = $this->getAdminSince(null);

		$this->assertEquals(0, $result);
	}

	public function testIsCurrentlyElevatedReturnsTrueWhenElevated(): void
	{
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_admin_since' => 1234567890,
		];
		$this->backendUserMock->method('isAdmin')->willReturn(true);

		$result = $this->isCurrentlyElevated($this->backendUserMock);

		$this->assertTrue($result);
	}

	public function testIsCurrentlyElevatedReturnsFalseWhenNotAdmin(): void
	{
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_admin_since' => 1234567890,
		];
		$this->backendUserMock->method('isAdmin')->willReturn(false);

		$result = $this->isCurrentlyElevated($this->backendUserMock);

		$this->assertFalse($result);
	}

	public function testIsCurrentlyElevatedReturnsFalseWhenAdminSinceIsZero(): void
	{
		$this->backendUserMock->user = [
			'tx_elevatetoadmin_admin_since' => 0,
		];
		$this->backendUserMock->method('isAdmin')->willReturn(true);

		$result = $this->isCurrentlyElevated($this->backendUserMock);

		$this->assertFalse($result);
	}

	public function testIsCurrentlyElevatedReturnsFalseWhenNoUser(): void
	{
		$result = $this->isCurrentlyElevated(null);

		$this->assertFalse($result);
	}
}
