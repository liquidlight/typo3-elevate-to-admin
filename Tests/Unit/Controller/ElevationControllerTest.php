<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Unit\Controller;

use LiquidLight\ElevateToAdmin\Controller\ElevationController;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashInterface;
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

	private $passwordHashFactoryMock;

	private $passwordHashMock;

	protected function setUp(): void
	{
		parent::setUp();

		$this->subject = new ElevationController();
		$this->backendUserMock = $this->createMock(BackendUserAuthentication::class);
		$this->requestMock = $this->createMock(ServerRequestInterface::class);
		$this->languageServiceMock = $this->createMock(LanguageService::class);
		$this->connectionMock = $this->createMock(Connection::class);
		$this->connectionPoolMock = $this->createMock(ConnectionPool::class);
		$this->passwordHashFactoryMock = $this->createMock(PasswordHashFactory::class);
		$this->passwordHashMock = $this->createMock(PasswordHashInterface::class);

		$this->connectionPoolMock
			->method('getConnectionForTable')
			->willReturn($this->connectionMock)
		;

		$this->passwordHashFactoryMock
			->method('getDefaultHashInstance')
			->with('BE')
			->willReturn($this->passwordHashMock)
		;

		GeneralUtility::addInstance(ConnectionPool::class, $this->connectionPoolMock);
		GeneralUtility::addInstance(PasswordHashFactory::class, $this->passwordHashFactoryMock);

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
		unset($GLOBALS['BE_USER'], $GLOBALS['LANG']);
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
		$this->backendUserMock->user = ['tx_elevate_to_admin_is_possible_admin' => 0];

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
		$this->backendUserMock->user = ['tx_elevate_to_admin_is_possible_admin' => 1];

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
		$this->backendUserMock->user = ['tx_elevate_to_admin_is_possible_admin' => 1];

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
		$this->backendUserMock->user = [
			'tx_elevate_to_admin_is_possible_admin' => 1,
			'password' => 'hashed_password',
		];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$this->passwordHashMock
			->method('checkPassword')
			->with('wrong_password', 'hashed_password')
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
		$this->backendUserMock->user = [
			'uid' => 123,
			'tx_elevate_to_admin_is_possible_admin' => 1,
			'password' => 'hashed_password',
		];

		$this->backendUserMock
			->method('isAdmin')
			->willReturn(false)
		;

		$this->passwordHashMock
			->method('checkPassword')
			->with('correct_password', 'hashed_password')
			->willReturn(true)
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
			'tx_elevate_to_admin_admin_since' => 0,
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
			'tx_elevate_to_admin_admin_since' => 1234567890,
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
