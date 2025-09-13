<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Functional\Traits;

use LiquidLight\ElevateToAdmin\Tests\Functional\FunctionalTestCase;
use LiquidLight\ElevateToAdmin\Traits\AdminElevationTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class AdminElevationTraitFunctionalTest extends FunctionalTestCase
{
	use AdminElevationTrait;

	private $testUserId = 999;

	protected function setUp(): void
	{
		parent::setUp();
		$this->createTestUser();
	}

	protected function tearDown(): void
	{
		$this->cleanupTestUser();
		parent::tearDown();
	}

	public function testUpdateUserRecordUpdatesDatabase(): void
	{
		$fields = [
			'admin' => 1,
			'tx_elevate_to_admin_admin_since' => time(),
		];

		$this->updateUserRecord($this->testUserId, $fields);

		$record = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(1, $record['admin']);
		$this->assertGreaterThan(0, $record['tx_elevate_to_admin_admin_since']);
	}

	public function testClearAdminElevationUpdatesDatabase(): void
	{
		// First set admin status
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->update(
			'be_users',
			[
				'admin' => 1,
				'tx_elevate_to_admin_admin_since' => time(),
			],
			['uid' => $this->testUserId]
		);

		// Create a backend user with current data
		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
			'admin' => 1,
			'tx_elevate_to_admin_admin_since' => time(),
		]);
		$this->setGlobalBackendUser($backendUser);

		// Clear admin elevation
		$this->clearAdminElevation($this->testUserId);

		// Verify database state
		$record = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(0, $record['admin']);
		$this->assertEquals(0, $record['tx_elevate_to_admin_admin_since']);
	}

	public function testSetAdminElevationUpdatesDatabase(): void
	{
		$timestamp = time();

		// Create a backend user
		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
		]);
		$this->setGlobalBackendUser($backendUser);

		$this->setAdminElevation($this->testUserId, $timestamp);

		$record = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(1, $record['admin']);
		$this->assertEquals($timestamp, $record['tx_elevate_to_admin_admin_since']);
		$this->assertEquals(1, $record['tx_elevate_to_admin_is_possible_admin']);
	}

	public function testSetAdminElevationWithCurrentTimestamp(): void
	{
		$beforeTime = time();

		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
		]);
		$this->setGlobalBackendUser($backendUser);

		$this->setAdminElevation($this->testUserId);

		$afterTime = time();

		$record = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(1, $record['admin']);
		$this->assertGreaterThanOrEqual($beforeTime, $record['tx_elevate_to_admin_admin_since']);
		$this->assertLessThanOrEqual($afterTime, $record['tx_elevate_to_admin_admin_since']);
		$this->assertEquals(1, $record['tx_elevate_to_admin_is_possible_admin']);
	}

	public function testCanUserElevateWithDatabaseData(): void
	{
		// Test user with elevation permission
		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
			'tx_elevate_to_admin_is_possible_admin' => 1,
		]);

		$result = $this->canUserElevate($backendUser);
		$this->assertTrue($result);

		// Test user without elevation permission
		$backendUserNoPermission = $this->createBackendUser([
			'uid' => $this->testUserId + 1,
			'tx_elevate_to_admin_is_possible_admin' => 0,
		]);

		$result = $this->canUserElevate($backendUserNoPermission);
		$this->assertFalse($result);
	}

	public function testGlobalUserDataSynchronization(): void
	{
		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
			'admin' => 0,
		]);
		$this->setGlobalBackendUser($backendUser);

		$fields = [
			'admin' => 1,
			'tx_elevate_to_admin_admin_since' => time(),
		];

		$this->updateUserRecordAndGlobal($this->testUserId, $fields);

		// Check database was updated
		$record = $this->getDatabaseRecord('be_users', [
			'uid' => $this->testUserId,
		]);
		$this->assertEquals(1, $record['admin']);

		// Check global user data was updated
		$this->assertEquals(1, $GLOBALS['BE_USER']->user['admin']);
		$this->assertEquals($fields['tx_elevate_to_admin_admin_since'], $GLOBALS['BE_USER']->user['tx_elevate_to_admin_admin_since']);
	}

	private function createTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->insert('be_users', [
			'uid' => $this->testUserId,
			'username' => 'functional_test_user',
			'password' => '$argon2i$v=19$m=65536,t=16,p=1$test',
			'admin' => 0,
			'tx_elevate_to_admin_is_possible_admin' => 1,
			'tx_elevate_to_admin_admin_since' => 0,
			'tstamp' => time(),
			'crdate' => time(),
		]);
	}

	private function cleanupTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable('be_users')
		;

		$connection->delete('be_users', [
			'uid' => $this->testUserId,
		]);
	}
}
