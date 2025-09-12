<?php

declare(strict_types=1);

namespace LiquidLight\ElevateToAdmin\Tests\Unit\Constants;

use LiquidLight\ElevateToAdmin\Constants\DatabaseConstants;
use PHPUnit\Framework\TestCase;

class DatabaseConstantsTest extends TestCase
{
	public function testTableBeUsersConstant(): void
	{
		$this->assertEquals('be_users', DatabaseConstants::TABLE_BE_USERS);
	}

	public function testFieldIsPossibleAdminConstant(): void
	{
		$this->assertEquals(
			'tx_elevate_to_admin_is_possible_admin',
			DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN
		);
	}

	public function testFieldAdminSinceConstant(): void
	{
		$this->assertEquals(
			'tx_elevate_to_admin_admin_since',
			DatabaseConstants::FIELD_ADMIN_SINCE
		);
	}

	public function testConstantsAreStrings(): void
	{
		$this->assertIsString(DatabaseConstants::TABLE_BE_USERS);
		$this->assertIsString(DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN);
		$this->assertIsString(DatabaseConstants::FIELD_ADMIN_SINCE);
	}

	public function testConstantsAreNotEmpty(): void
	{
		$this->assertNotEmpty(DatabaseConstants::TABLE_BE_USERS);
		$this->assertNotEmpty(DatabaseConstants::FIELD_IS_POSSIBLE_ADMIN);
		$this->assertNotEmpty(DatabaseConstants::FIELD_ADMIN_SINCE);
	}
}
