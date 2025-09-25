<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Unit\Controller;

use LiquidLight\ElevateToAdmin\Controller\ElevationController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\PasswordHashing\Argon2iPasswordHash;
use TYPO3\CMS\Core\Crypto\PasswordHashing\BcryptPasswordHash;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class ElevationControllerTest extends TestCase
{
	private ElevationController $subject;

	private $backendUserMock;

	private $requestMock;

	private $languageServiceMock;

	private $connectionMock;

	private $connectionPoolMock;

	private $loggerMock;

	protected function setUp(): void
	{
		parent::setUp();

		// Set up minimal TYPO3 configuration for password hashing
		$GLOBALS['TYPO3_CONF_VARS']['SYS']['availablePasswordHashAlgorithms'] = [
			Argon2iPasswordHash::class,
			BcryptPasswordHash::class,
		];
		$GLOBALS['TYPO3_CONF_VARS']['BE']['passwordHashing'] = [
			'className' => Argon2iPasswordHash::class,
			'options' => [],
		];
		$GLOBALS['TYPO3_CONF_VARS']['FE']['passwordHashing'] = [
			'className' => Argon2iPasswordHash::class,
			'options' => [],
		];

		$this->loggerMock = $this->createMock(LoggerInterface::class);
		$this->subject = new ElevationController($this->loggerMock);
		$this->backendUserMock = $this->createMock(BackendUserAuthentication::class);
		$this->requestMock = $this->createMock(ServerRequestInterface::class);
		$this->languageServiceMock = $this->createMock(LanguageService::class);
		$this->connectionMock = $this->createMock(Connection::class);
		$this->connectionPoolMock = $this->createMock(ConnectionPool::class);

		$this->connectionPoolMock
			->method('getConnectionForTable')
			->willReturn($this->connectionMock)
		;

		GeneralUtility::addInstance(ConnectionPool::class, $this->connectionPoolMock);

		$GLOBALS['LANG'] = $this->languageServiceMock;

		$this->languageServiceMock
			->method('sL')
			->willReturnCallback(function ($key) {
				return 'translated_' . $key;
			})
		;
	}

	protected function tearDown(): void
	{
		unset($GLOBALS['BE_USER'], $GLOBALS['LANG'], $GLOBALS['TYPO3_CONF_VARS']);
		GeneralUtility::purgeInstances();
		parent::tearDown();
	}

	public function testElevateActionReturnsErrorWhenNoBackendUser(): void
	{
		unset($GLOBALS['BE_USER']);

		$this->requestMock
			->method('getParsedBody')
			->willReturn([])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testElevateActionCallsLeaveAdminModeWhenActionIsLeave(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['action' => 'leave'])
		;

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
	}

	public function testElevateActionReturnsErrorWhenUserCannotElevate(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = ['tx_elevatetoadmin_is_possible_admin' => 0];

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['password' => 'test'])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testElevateActionReturnsErrorWhenUserAlreadyAdmin(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = ['tx_elevatetoadmin_is_possible_admin' => 1];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(true)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['password' => 'test'])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testElevateActionReturnsErrorWhenPasswordEmpty(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = ['tx_elevatetoadmin_is_possible_admin' => 1];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['password' => ''])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testElevateActionReturnsErrorWhenPasswordInvalid(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		// Create a real hashed password using TYPO3's password hashing
		$passwordHashFactory = new PasswordHashFactory();
		$hasher = $passwordHashFactory->getDefaultHashInstance('BE');
		$hashedPassword = $hasher->getHashedPassword('correct_password');

		$this->backendUserMock->user = [
			'tx_elevatetoadmin_is_possible_admin' => 1,
			'password' => $hashedPassword,
		];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['password' => 'wrong_password'])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testElevateActionSucceedsWithValidPassword(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		// Create a real hashed password using TYPO3's password hashing
		$passwordHashFactory = new PasswordHashFactory();
		$hasher = $passwordHashFactory->getDefaultHashInstance('BE');
		$hashedPassword = $hasher->getHashedPassword('correct_password');

		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevatetoadmin_is_possible_admin' => 1,
			'password' => $hashedPassword,
		];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['password' => 'correct_password'])
		;

		$this->connectionMock
			->expects($this->once())
			->method('update')
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($responseData['success']);
		$this->assertTrue($responseData['reload']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testLeaveAdminModeReturnsErrorWhenNotAdmin(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['action' => 'leave'])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testLeaveAdminModeReturnsErrorWhenCannotLeavePermanent(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevatetoadmin_admin_since' => 0,
		];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(true)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['action' => 'leave'])
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertFalse($responseData['success']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}

	public function testLeaveAdminModeSucceedsWhenCurrentlyElevated(): void
	{
		$GLOBALS['BE_USER'] = $this->backendUserMock;
		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevatetoadmin_admin_since' => 1234567890,
		];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(true)
		;

		$this->requestMock
			->method('getParsedBody')
			->willReturn(['action' => 'leave'])
		;

		$this->connectionMock
			->expects($this->once())
			->method('update')
		;

		$response = $this->subject->elevateAction($this->requestMock);

		$this->assertInstanceOf(JsonResponse::class, $response);
		$responseData = json_decode($response->getBody()->getContents(), true);
		$this->assertTrue($responseData['success']);
		$this->assertTrue($responseData['reload']);
		$this->assertStringStartsWith('translated_', $responseData['message']);
	}
}
