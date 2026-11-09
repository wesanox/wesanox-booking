<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Domain\Booking;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Domain\Booking\DuplicateGuestDetector;

final class DuplicateGuestDetectorTest extends TestCase
{
    private DuplicateGuestDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new DuplicateGuestDetector();
    }

    public function testDifferentNamesAreNoDuplicates(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
            2 => ['firstname' => 'Erika', 'lastname' => 'Muster', 'email' => ''],
        ];

        self::assertSame([], $this->detector->duplicates($guests));
    }

    public function testSameNameAndSameEmailIsDuplicate(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
            2 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
        ];

        self::assertSame([[1, 2]], $this->detector->duplicates($guests));
    }

    public function testSameNameWithEmptyEmailIsDuplicate(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
            2 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => ''],
        ];

        self::assertSame([[1, 2]], $this->detector->duplicates($guests));
    }

    public function testSameNameWithDifferentEmailsIsAllowed(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
            2 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max.junior@example.com'],
        ];

        self::assertSame([], $this->detector->duplicates($guests));
    }

    public function testComparisonIgnoresCaseAndWhitespace(): void
    {
        $guests = [
            1 => ['firstname' => ' Max ', 'lastname' => 'MUSTER', 'email' => 'Max@Example.com'],
            3 => ['firstname' => 'max', 'lastname' => 'muster ', 'email' => 'max@example.com '],
        ];

        self::assertSame([[1, 3]], $this->detector->duplicates($guests));
    }

    public function testSwappedFirstAndLastNameIsDuplicate(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
            2 => ['firstname' => 'Muster', 'lastname' => 'Max', 'email' => ''],
        ];

        self::assertSame([[1, 2]], $this->detector->duplicates($guests));
    }

    public function testOnlySameFirstOrLastNameIsNoDuplicate(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster'],
            2 => ['firstname' => 'Max', 'lastname' => 'Schmidt'],
            3 => ['firstname' => 'Erika', 'lastname' => 'Muster'],
        ];

        self::assertSame([], $this->detector->duplicates($guests));
    }

    public function testUmlautsAreComparedCaseInsensitive(): void
    {
        $guests = [
            2 => ['firstname' => 'Jürgen', 'lastname' => 'Öztürk'],
            4 => ['firstname' => 'JÜRGEN', 'lastname' => 'ÖZTÜRK'],
        ];

        self::assertSame([[2, 4]], $this->detector->duplicates($guests));
    }

    public function testEmptyNamesAreIgnored(): void
    {
        $guests = [
            2 => ['firstname' => '', 'lastname' => '', 'email' => ''],
            3 => ['firstname' => '', 'lastname' => '', 'email' => ''],
        ];

        self::assertSame([], $this->detector->duplicates($guests));
    }

    public function testReportsEveryDuplicatePair(): void
    {
        $guests = [
            1 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => 'max@example.com'],
            2 => ['firstname' => 'Max', 'lastname' => 'Muster', 'email' => ''],
            3 => ['firstname' => 'Anna', 'lastname' => 'Muster', 'email' => ''],
            4 => ['firstname' => 'Anna', 'lastname' => 'Muster', 'email' => ''],
        ];

        self::assertSame([[1, 2], [3, 4]], $this->detector->duplicates($guests));
    }
}
