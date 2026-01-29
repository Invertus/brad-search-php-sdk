<?php

declare(strict_types=1);

namespace BradSearch\SyncSdk\Tests\V2\ValueObjects\Search;

use BradSearch\SyncSdk\V2\ValueObjects\Search\BoostAlgorithm;
use PHPUnit\Framework\TestCase;

class BoostAlgorithmTest extends TestCase
{
    public function testLogarithmicValue(): void
    {
        $this->assertEquals('logarithmic', BoostAlgorithm::LOGARITHMIC->value);
    }

    public function testLinearValue(): void
    {
        $this->assertEquals('linear', BoostAlgorithm::LINEAR->value);
    }

    public function testSquareRootValue(): void
    {
        $this->assertEquals('square_root', BoostAlgorithm::SQUARE_ROOT->value);
    }

    public function testAllCasesExist(): void
    {
        $cases = BoostAlgorithm::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(BoostAlgorithm::LOGARITHMIC, $cases);
        $this->assertContains(BoostAlgorithm::LINEAR, $cases);
        $this->assertContains(BoostAlgorithm::SQUARE_ROOT, $cases);
    }

    /**
     * @dataProvider algorithmDataProvider
     */
    public function testCanBeCreatedFromString(string $value, BoostAlgorithm $expected): void
    {
        $algorithm = BoostAlgorithm::from($value);

        $this->assertEquals($expected, $algorithm);
    }

    /**
     * @return array<string, array{string, BoostAlgorithm}>
     */
    public static function algorithmDataProvider(): array
    {
        return [
            'logarithmic' => ['logarithmic', BoostAlgorithm::LOGARITHMIC],
            'linear' => ['linear', BoostAlgorithm::LINEAR],
            'square_root' => ['square_root', BoostAlgorithm::SQUARE_ROOT],
        ];
    }

    public function testTryFromReturnsNullForInvalidValue(): void
    {
        $this->assertNull(BoostAlgorithm::tryFrom('invalid'));
    }
}
