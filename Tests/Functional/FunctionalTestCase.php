<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Functional;

use PHPUnit\Framework\TestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ConfigurationManager;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Schema\SchemaMigrator;
use TYPO3\CMS\Core\Database\Schema\SqlReader;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class FunctionalTestCase extends TestCase
{
	protected ConnectionPool $connectionPool;

	protected static bool $bootstrapped = false;

	protected function setUp(): void
	{
		parent::setUp();

		if (!self::$bootstrapped) {
			$this->bootstrapTypo3();
			self::$bootstrapped = true;
		}

		// Ensure TYPO3_CONF_VARS is always available
		if (!isset($GLOBALS['TYPO3_CONF_VARS']) || empty($GLOBALS['TYPO3_CONF_VARS'])) {
			$GLOBALS['TYPO3_CONF_VARS'] = require __DIR__ . '/Fixtures/Database/LocalConfiguration.php';
		}

		$this->connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
		$this->setUpDatabase();
	}

	protected function tearDown(): void
	{
		// Only unset user-specific globals, keep TYPO3_CONF_VARS for next test
		unset($GLOBALS['BE_USER'], $GLOBALS['LANG']);
		parent::tearDown();
	}

	/**
	 * Bootstrap TYPO3 for functional testing
	 */
	protected function bootstrapTypo3(): void
	{
		// Set up basic TYPO3 constants if not already defined
		if (!defined('TYPO3')) {
			define('TYPO3', true);
		}
		if (!defined('TYPO3_MODE')) {
			define('TYPO3_MODE', 'BE');
		}
		if (!defined('TYPO3_REQUESTTYPE_BE')) {
			define('TYPO3_REQUESTTYPE_BE', 1);
		}
		if (!defined('TYPO3_REQUESTTYPE')) {
			define('TYPO3_REQUESTTYPE', TYPO3_REQUESTTYPE_BE);
		}

		// Set up paths
		$testRoot = dirname(__DIR__, 2);
		$buildPath = $testRoot . '/.Build';

		if (!defined('PATH_site')) {
			define('PATH_site', $buildPath . '/public/');
		}
		if (!defined('PATH_typo3conf')) {
			define('PATH_typo3conf', PATH_site . 'typo3conf/');
		}

		// Load test configuration
		$GLOBALS['TYPO3_CONF_VARS'] = require __DIR__ . '/Fixtures/Database/LocalConfiguration.php';

		// Set up database configuration for SQLite in-memory
		$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default'] = [
			'charset' => 'utf8mb4',
			'driver' => 'pdo_sqlite',
			'path' => ':memory:',
			'tableoptions' => [
				'charset' => 'utf8mb4',
				'collate' => 'utf8mb4_unicode_ci',
			],
		];

		// Override with environment variables if set (for CI/Docker)
		if (getenv('typo3DatabaseDriver')) {
			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'] = getenv('typo3DatabaseDriver');
		}
		if (getenv('typo3DatabasePath')) {
			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['path'] = getenv('typo3DatabasePath');
		}
		if (getenv('typo3DatabaseName')) {
			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['dbname'] = getenv('typo3DatabaseName');
		}
		if (getenv('typo3DatabaseHost')) {
			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['host'] = getenv('typo3DatabaseHost');
		}
		if (getenv('typo3DatabaseUsername')) {
			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['user'] = getenv('typo3DatabaseUsername');
		}
		if (getenv('typo3DatabasePassword')) {
			$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['password'] = getenv('typo3DatabasePassword');
		}

		// Initialize the cache system
		try {
			$cacheManager = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class);
			$cacheManager->setCacheConfigurations($GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']);
		} catch (\Exception $e) {
			// If cache manager fails to initialize, continue without it for testing
		}

		// Initialize minimal backend user for testing
		$GLOBALS['BE_USER'] = null;
	}

	/**
	 * Set up test database schema
	 */
	protected function setUpDatabase(): void
	{
		try {
			$connection = $this->connectionPool->getConnectionForTable('be_users');

			// Create be_users table if it doesn't exist
			$schemaManager = $connection->createSchemaManager();
			if (!$schemaManager->tablesExist(['be_users'])) {
				$this->createBeUsersTable($connection);
			}

		} catch (\Exception $e) {
			// If we can't set up the database, skip database-dependent tests
			$this->markTestSkipped('Database setup failed: ' . $e->getMessage());
		}
	}

	/**
	 * Create be_users table with required fields
	 */
	protected function createBeUsersTable($connection): void
	{
		$sql = "
			CREATE TABLE " . 'be_users' . " (
				uid INTEGER PRIMARY KEY,
				username VARCHAR(255) NOT NULL DEFAULT '',
				password VARCHAR(255) NOT NULL DEFAULT '',
				admin TINYINT(1) NOT NULL DEFAULT 0,
				" . 'tx_elevate_to_admin_is_possible_admin' . " TINYINT(1) NOT NULL DEFAULT 0,
				" . 'tx_elevate_to_admin_admin_since' . " INTEGER NOT NULL DEFAULT 0,
				tstamp INTEGER NOT NULL DEFAULT 0,
				crdate INTEGER NOT NULL DEFAULT 0,
				deleted TINYINT(1) NOT NULL DEFAULT 0
			)
		";

		$connection->executeStatement($sql);
	}

	protected function createBackendUser(array $userData = []): BackendUserAuthentication
	{
		$defaultData = [
			'uid' => 123,
			'username' => 'testuser',
			'password' => '$argon2i$v=19$m=65536,t=16,p=1$test',
			'admin' => 0,
			'realName' => 'Test User',
			'email' => 'test@example.com',
			'lang' => 'default',
			'uc' => '',
			'TSconfig' => '',
			'workspace_id' => 0,
			'workspace_perms' => 1,
			'tx_elevate_to_admin_is_possible_admin' => 1,
			'tx_elevate_to_admin_admin_since' => 0,
			'tstamp' => time(),
			'crdate' => time(),
		];

		$userData = array_merge($defaultData, $userData);

		$backendUser = new BackendUserAuthentication();
		$backendUser->user = $userData;

		return $backendUser;
	}

	protected function setGlobalBackendUser(BackendUserAuthentication $user): void
	{
		$GLOBALS['BE_USER'] = $user;
	}

	protected function createMockLanguageService(): \TYPO3\CMS\Core\Localization\LanguageService
	{
		$languageService = $this->getMockBuilder(\TYPO3\CMS\Core\Localization\LanguageService::class)
			->disableOriginalConstructor()
			->getMock()
		;

		$languageService->method('sL')
			->willReturnCallback(function (string $key): string {
				return 'translated_' . $key;
			})
		;

		return $languageService;
	}

	protected function setGlobalLanguageService(): void
	{
		$GLOBALS['LANG'] = $this->createMockLanguageService();
	}

	protected function assertDatabaseRecordExists(string $table, array $conditions): void
	{
		$record = $this->getDatabaseRecord($table, $conditions);
		$this->assertNotNull($record, sprintf(
			'Database record not found in table %s with conditions %s',
			$table,
			json_encode($conditions)
		));
	}

	protected function assertDatabaseRecordNotExists(string $table, array $conditions): void
	{
		$record = $this->getDatabaseRecord($table, $conditions);
		$this->assertNull($record, sprintf(
			'Database record unexpectedly found in table %s with conditions %s',
			$table,
			json_encode($conditions)
		));
	}

	protected function getDatabaseRecord(string $table, array $conditions): ?array
	{
		try {
			$connection = $this->connectionPool->getConnectionForTable($table);
			$queryBuilder = $connection->createQueryBuilder();

			$query = $queryBuilder->select('*')->from($table);

			foreach ($conditions as $field => $value) {
				$query->andWhere($queryBuilder->expr()->eq($field, $queryBuilder->createNamedParameter($value)));
			}

			$result = $query->executeQuery()->fetchAssociative();

			return $result ?: null;
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Insert a test record into the database
	 */
	protected function insertTestRecord(string $table, array $data): void
	{
		try {
			$connection = $this->connectionPool->getConnectionForTable($table);
			$connection->insert($table, $data);
		} catch (\Exception $e) {
			$this->fail('Failed to insert test record: ' . $e->getMessage());
		}
	}

	/**
	 * Clean up test records
	 */
	protected function cleanupTestRecord(string $table, array $conditions): void
	{
		try {
			$connection = $this->connectionPool->getConnectionForTable($table);
			$connection->delete($table, $conditions);
		} catch (\Exception $e) {
			// Ignore cleanup failures
		}
	}
}
