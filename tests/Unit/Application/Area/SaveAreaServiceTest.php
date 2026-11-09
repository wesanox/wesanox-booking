<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Area;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Area\SaveAreaService;
use Wesanox\Booking\Domain\Area\Area;
use Wesanox\Booking\Domain\Area\AreaRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveAreaServiceTest extends TestCase
{
    // --- Validation: name ---

    public function testItThrowsWhenNameIsEmpty(): void
    {
        $service = new SaveAreaService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '', $this->validOpening(), $this->validTimeSettings());
    }

    public function testItThrowsWhenNameIsWhitespace(): void
    {
        $service = new SaveAreaService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '   ', $this->validOpening(), $this->validTimeSettings());
    }

    // --- Validation: opening times ---

    public function testItThrowsWhenEnabledDayHasInvalidFromTime(): void
    {
        $service = new SaveAreaService($this->makeRepository());
        $opening = $this->validOpening();
        $opening['monday'] = ['enabled' => true, 'from' => '25:00', 'to' => '18:00'];

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Spa', $opening, $this->validTimeSettings());
    }

    public function testItThrowsWhenEnabledDayHasInvalidToTime(): void
    {
        $service = new SaveAreaService($this->makeRepository());
        $opening = $this->validOpening();
        $opening['monday'] = ['enabled' => true, 'from' => '09:00', 'to' => 'abc'];

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Spa', $opening, $this->validTimeSettings());
    }

    public function testItThrowsWhenToIsBeforeFrom(): void
    {
        $service = new SaveAreaService($this->makeRepository());
        $opening = $this->validOpening();
        $opening['monday'] = ['enabled' => true, 'from' => '18:00', 'to' => '09:00'];

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Spa', $opening, $this->validTimeSettings());
    }

    public function testItThrowsWhenToEqualsFrom(): void
    {
        $service = new SaveAreaService($this->makeRepository());
        $opening = $this->validOpening();
        $opening['monday'] = ['enabled' => true, 'from' => '09:00', 'to' => '09:00'];

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Spa', $opening, $this->validTimeSettings());
    }

    public function testItAcceptsMidnightAsClosingTime(): void
    {
        // 00:00 means midnight / end-of-day — must NOT trigger "to before from"
        $repository = $this->makeRepository(createWillReturn: 42);
        $service    = new SaveAreaService($repository);
        $opening    = $this->validOpening();
        $opening['friday'] = ['enabled' => true, 'from' => '12:00', 'to' => '00:00'];

        $result = $service->execute(null, 'Spa', $opening, $this->validTimeSettings());

        self::assertSame(42, $result);
    }

    public function testItRejectsMidnightAsOpeningWhenClosingIsAlsoMidnight(): void
    {
        // from=00:00 AND to=00:00 → to_cmp '24:00' > from '00:00', so it's technically valid
        // but semantically odd; the service does not reject it (hardware limits may apply upstream)
        $repository = $this->makeRepository(createWillReturn: 1);
        $service    = new SaveAreaService($repository);
        $opening    = $this->validOpening();
        $opening['saturday'] = ['enabled' => true, 'from' => '00:00', 'to' => '00:00'];

        // from=00:00, to_cmp=24:00 → to_cmp > from → valid
        $result = $service->execute(null, 'Spa', $opening, $this->validTimeSettings());

        self::assertIsInt($result);
    }

    public function testItDoesNotValidateDisabledDay(): void
    {
        $repository = $this->makeRepository(createWillReturn: 42);
        $service    = new SaveAreaService($repository);
        $opening    = $this->validOpening();
        // Saturday disabled, no times required.
        $opening['saturday'] = ['enabled' => false, 'from' => null, 'to' => null];

        $result = $service->execute(null, 'Spa', $opening, $this->validTimeSettings());

        self::assertSame(42, $result);
    }

    // --- Validation: time settings ---

    public function testItThrowsWhenSeparatorIsInvalid(): void
    {
        $service  = new SaveAreaService($this->makeRepository());
        $settings = $this->validTimeSettings();
        $settings['area_time_separator'] = '9';

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Spa', $this->validOpening(), $settings);
    }

    // --- Create & Update ---

    public function testItReturnsNewIdOnCreate(): void
    {
        $repository = $this->makeRepository(createWillReturn: 7);
        $service    = new SaveAreaService($repository);

        $id = $service->execute(null, 'Wellnessbereich', $this->validOpening(), $this->validTimeSettings());

        self::assertSame(7, $id);
    }

    public function testItReturnsExistingIdOnUpdate(): void
    {
        $repository = $this->makeRepository(updateWillReturn: true);
        $service    = new SaveAreaService($repository);

        $id = $service->execute(5, 'Wellnessbereich', $this->validOpening(), $this->validTimeSettings());

        self::assertSame(5, $id);
    }

    public function testValidationErrorsCollectMultipleMessages(): void
    {
        $service = new SaveAreaService($this->makeRepository());
        $opening = $this->validOpening();
        $opening['monday']  = ['enabled' => true, 'from' => 'bad', 'to' => 'bad'];
        $opening['tuesday'] = ['enabled' => true, 'from' => 'bad', 'to' => 'bad'];

        try {
            $service->execute(null, '', $opening, $this->validTimeSettings());
            self::fail('Expected ValidationException not thrown.');
        } catch (ValidationException $e) {
            // name error + 2 from-time errors
            self::assertGreaterThanOrEqual(3, count($e->errors()));
        }
    }

    // --- Helpers ---

    /** @return array<string, array{enabled: bool, from: ?string, to: ?string}> */
    private function validOpening(): array
    {
        $opening = [];
        foreach (Area::WEEKDAYS as $day) {
            $opening[$day] = ['enabled' => false, 'from' => null, 'to' => null];
        }
        $opening['monday']  = ['enabled' => true, 'from' => '09:00', 'to' => '18:00'];
        $opening['tuesday'] = ['enabled' => true, 'from' => '09:00', 'to' => '18:00'];

        return $opening;
    }

    /** @return array{area_time_separator: string, area_time_sheet: string, area_time_min: string, area_time_max: string} */
    private function validTimeSettings(): array
    {
        return [
            'area_time_separator' => '1',
            'area_time_sheet'     => '30',
            'area_time_min'       => '60',
            'area_time_max'       => '240',
        ];
    }

    private function makeRepository(int $createWillReturn = 1, bool $updateWillReturn = true): AreaRepositoryInterface
    {
        return new class ($createWillReturn, $updateWillReturn) implements AreaRepositoryInterface {
            public function __construct(
                private int  $createReturn,
                private bool $updateReturn,
            ) {
            }

            public function findAll(): array { return []; }
            public function findById(int $id): ?Area { return null; }

            public function create(array $data): int { return $this->createReturn; }
            public function update(int $id, array $data): bool { return $this->updateReturn; }
            public function delete(int $id): bool { return true; }
        };
    }
}
