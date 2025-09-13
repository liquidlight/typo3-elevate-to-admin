<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Functional\Traits;

use LiquidLight\ElevateToAdmin\Constants\DatabaseConstants;
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
			DatabaseConstants::FIELD_ADMIN_SINCE => time(),
		];

		$this->updateUserRecord($this->testUserId, $fields);

		$record = $this->getDatabaseRecord(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(1, $record['admin']);
		$this->assertGreaterThan(0, $record[DatabaseConstants::FIELD_ADMIN_SINCE]);
	}

	public function testClearAdminElevationUpdatesDatabase(): void
	{
		// First set admin status
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable(DatabaseConstants::TABLE_BE_USERS)
		;

		$connection->update(
			DatabaseConstants::TABLE_BE_USERS,
			[
				'admin' => 1,
				DatabaseConstants::FIELD_ADMIN_SINCE => time(),
			],
			['uid' => $this->testUserId]
		);

		// Create a backend user with current data
		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
			'admin' => 1,
			DatabaseConstants::FIELD_ADMIN_SINCE => time(),
		]);
		$this->setGlobalBackendUser($backendUser);

		// Clear admin elevation
		$this->clearAdminElevation($this->testUserId);

		// Verify database state
		$record = $this->getDatabaseRecord(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(0, $record['admin']);
		$this->assertEquals(0, $record[DatabaseConstants::FIELD_ADMIN_SINCE]);
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

		$record = $this->getDatabaseRecord(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(1, $record['admin']);
		$this->assertEquals($timestamp, $record[DatabaseConstants::FIELD_ADMIN_SINCE]);
		$this->assertEquals(1, $record[DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN]);
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

		$record = $this->getDatabaseRecord(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);

		$this->assertNotNull($record);
		$this->assertEquals(1, $record['admin']);
		$this->assertGreaterThanOrEqual($beforeTime, $record[DatabaseConstants::FIELD_ADMIN_SINCE]);
		$this->assertLessThanOrEqual($afterTime, $record[DatabaseConstants::FIELD_ADMIN_SINCE]);
		$this->assertEquals(1, $record[DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN]);
	}

	public function testCanUserElevateWithDatabaseData(): void
	{
		// Test user with elevation permission
		$backendUser = $this->createBackendUser([
			'uid' => $this->testUserId,
			DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN => 1,
		]);

		$result = $this->canUserElevate($backendUser);
		$this->assertTrue($result);

		// Test user without elevation permission
		$backendUserNoPermission = $this->createBackendUser([
			'uid' => $this->testUserId + 1,
			DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN => 0,
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
			DatabaseConstants::FIELD_ADMIN_SINCE => time(),
		];

		$this->updateUserRecordAndGlobal($this->testUserId, $fields);

		// Check database was updated
		$record = $this->getDatabaseRecord(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);
		$this->assertEquals(1, $record['admin']);

		// Check global user data was updated
		$this->assertEquals(1, $GLOBALS['BE_USER']->user['admin']);
		$this->assertEquals($fields[DatabaseConstants::FIELD_ADMIN_SINCE], $GLOBALS['BE_USER']->user[DatabaseConstants::FIELD_ADMIN_SINCE]);
	}

	private function createTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable(DatabaseConstants::TABLE_BE_USERS)
		;

		$connection->insert(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
			'username' => 'functional_test_user',
			'password' => '$argon2i$v=19$m=65536,t=16,p=1$test',
			'admin' => 0,
			DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN => 1,
			DatabaseConstants::FIELD_ADMIN_SINCE => 0,
			'tstamp' => time(),
			'crdate' => time(),
		]);
	}

	private function cleanupTestUser(): void
	{
		$connection = GeneralUtility::makeInstance(ConnectionPool::class)
			->getConnectionForTable(DatabaseConstants::TABLE_BE_USERS)
		;

		$connection->delete(DatabaseConstants::TABLE_BE_USERS, [
			'uid' => $this->testUserId,
		]);
	}
}
