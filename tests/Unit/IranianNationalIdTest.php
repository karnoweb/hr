<?php

namespace Karnoweb\Hr\Tests\Unit;

use Karnoweb\Hr\Support\IranianNationalId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IranianNationalIdTest extends TestCase
{
    #[DataProvider('validIds')]
    public function test_valid_national_ids(string $id): void
    {
        $this->assertTrue(IranianNationalId::isValid($id));
    }

    #[DataProvider('invalidIds')]
    public function test_invalid_national_ids(?string $id): void
    {
        $this->assertFalse(IranianNationalId::isValid($id));
    }

    public static function validIds(): array
    {
        return [
            ['0123456789'],
            ['0013542419'],
        ];
    }

    public static function invalidIds(): array
    {
        return [
            [null],
            [''],
            ['123'],
            ['01234567890'],
            ['012345678a'],
            ['0000000000'],
            ['1111111111'],
            ['0123456780'],
        ];
    }
}
