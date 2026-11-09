<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Holiday;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Holiday\SaveHolidayService;
use Wesanox\Booking\Domain\Holiday\Holiday;
use Wesanox\Booking\Domain\Holiday\HolidayRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveHolidayServiceTest extends TestCase
{
    // --- Validation: date ---

    public function testItThrowsWhenDateIsEmpty(): void
    {
        $service = new SaveHolidayService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '', null, null, false, false);
    }

    public function testItThrowsWhenDateIsInvalidFormat(): void
    {
        $service = new SaveHolidayService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '31.12.2025', null, null, false, false);
    }

    public function testItThrowsWhenDateAlreadyExists(): void
    {
        $existing = new Holiday(5, '2025-12-25', null, null, false, true);
        $service  = new SaveHolidayService($this->makeRepository(existingByDate: $existing));

        $this->expectException(ValidationException::class);
        $service->execute(null, '2025-12-25', null, null, false, false);
    }

    public function testItAllowsUpdateWithSameDate(): void
    {
        // Updating id=5, the existing record for that date IS id=5 — not a conflict.
        $existing = new Holiday(5, '2025-12-25', null, null, false, true);
        $service  = new SaveHolidayService($this->makeRepository(existingByDate: $existing, updateWillReturn: true));

        $id = $service->execute(5, '2025-12-25', null, null, false, false);

        self::assertSame(5, $id);
    }

    // --- Validation: opening times ---

    public function testItThrowsWhenOpenFromIsInvalid(): void
    {
        $service = new SaveHolidayService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '2025-06-01', 'bad', '18:00', false, false);
    }

    public function testItThrowsWhenOpenToIsInvalid(): void
    {
        $service = new SaveHolidayService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '2025-06-01', '09:00', '99:99', false, false);
    }

    public function testItThrowsWhenToIsBeforeFrom(): void
    {
        $service = new SaveHolidayService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '2025-06-01', '18:00', '09:00', false, false);
    }

    public function testItAcceptsMidnightAsClosingTime(): void
    {
        $service = new SaveHolidayService($this->makeRepository(createWillReturn: 10));

        $id = $service->execute(null, '2025-06-01', '10:00', '00:00', false, false);

        self::assertSame(10, $id);
    }

    public function testItSkipsTimeValidationWhenClosed(): void
    {
        // Times are ignored when the day is closed.
        $service = new SaveHolidayService($this->makeRepository(createWillReturn: 11));

        $id = $service->execute(null, '2025-06-01', 'bad', 'bad', true, false);

        self::assertSame(11, $id);
    }

    // --- Create & Update ---

    public function testItReturnsNewIdOnCreate(): void
    {
        $service = new SaveHolidayService($this->makeRepository(createWillReturn: 7));

        $id = $service->execute(null, '2025-12-25', null, null, true, true);

        self::assertSame(7, $id);
    }

    public function testItReturnsExistingIdOnUpdate(): void
    {
        $service = new SaveHolidayService($this->makeRepository(updateWillReturn: true));

        $id = $service->execute(3, '2025-12-26', null, null, false, false);

        self::assertSame(3, $id);
    }

    // --- Helpers ---

    private function makeRepository(
        int      $createWillReturn = 1,
        bool     $updateWillReturn = true,
        ?Holiday $existingByDate   = null,
    ): HolidayRepositoryInterface {
        return new class ($createWillReturn, $updateWillReturn, $existingByDate) implements HolidayRepositoryInterface {
            public function __construct(
                private int      $createReturn,
                private bool     $updateReturn,
                private ?Holiday $byDate,
            ) {
            }

            public function findAll(): array        { return []; }
            public function findById(int $id): ?Holiday { return null; }
            public function findByDate(string $ymd, ?int $area_id = null): ?Holiday { return $this->byDate; }

            public function create(array $data): int        { return $this->createReturn; }
            public function update(int $id, array $data): bool { return $this->updateReturn; }
            public function delete(int $id): bool           { return true; }
        };
    }
}
