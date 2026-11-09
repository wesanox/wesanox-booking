<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\Item;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\Item\SaveItemService;
use Wesanox\Booking\Domain\Item\Item;
use Wesanox\Booking\Domain\Item\ItemRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveItemServiceTest extends TestCase
{
    // --- Validation: name ---

    public function testItThrowsWhenNameIsEmpty(): void
    {
        $service = new SaveItemService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '', null, 1, false, null, null, null);
    }

    // --- Validation: category ---

    public function testItThrowsWhenCategoryIsMissing(): void
    {
        $service = new SaveItemService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Sauna', null, null, false, null, null, null);
    }

    public function testItThrowsWhenCategoryIsZero(): void
    {
        $service = new SaveItemService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Sauna', null, 0, false, null, null, null);
    }

    // --- Validation: inactive period ---

    public function testItThrowsWhenInactivToIsBeforeInactivFrom(): void
    {
        $service = new SaveItemService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Sauna', null, 1, true, '2025-06-30', '2025-06-01', null);
    }

    public function testItThrowsWhenInactivFromIsInvalidDate(): void
    {
        $service = new SaveItemService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Sauna', null, 1, true, 'not-a-date', null, null);
    }

    public function testItThrowsWhenInactivToIsInvalidDate(): void
    {
        $service = new SaveItemService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, 'Sauna', null, 1, true, null, '2025-13-01', null);
    }

    public function testItAcceptsValidPeriodWhereFromEqualsTo(): void
    {
        $repo    = $this->makeRepository(createWillReturn: 3);
        $service = new SaveItemService($repo);

        $id = $service->execute(null, 'Sauna', null, 1, true, '2025-06-01', '2025-06-01', null);

        self::assertSame(3, $id);
    }

    // --- Area assignment ---

    public function testItAcceptsNullAreaId(): void
    {
        $repo    = $this->makeRepository(createWillReturn: 5);
        $service = new SaveItemService($repo);

        $id = $service->execute(null, 'Sauna', null, 2, false, null, null, null);

        self::assertSame(5, $id);
    }

    public function testItAcceptsAreaId(): void
    {
        $repo    = $this->makeRepository(createWillReturn: 8);
        $service = new SaveItemService($repo);

        $id = $service->execute(null, 'Sauna', 3, 2, false, null, null, null);

        self::assertSame(8, $id);
    }

    // --- Create & Update ---

    public function testItReturnsNewIdOnCreate(): void
    {
        $repo    = $this->makeRepository(createWillReturn: 11);
        $service = new SaveItemService($repo);

        $id = $service->execute(null, 'Dampfbad', 1, 1, false, null, null, 'Hinweis');

        self::assertSame(11, $id);
    }

    public function testItReturnsExistingIdOnUpdate(): void
    {
        $repo    = $this->makeRepository(updateWillReturn: true);
        $service = new SaveItemService($repo);

        $id = $service->execute(7, 'Dampfbad', 1, 1, false, null, null, null);

        self::assertSame(7, $id);
    }

    // --- Helpers ---

    private function makeRepository(int $createWillReturn = 1, bool $updateWillReturn = true): ItemRepositoryInterface
    {
        return new class ($createWillReturn, $updateWillReturn) implements ItemRepositoryInterface {
            public function __construct(
                private int  $createReturn,
                private bool $updateReturn,
            ) {
            }

            public function findAll(?int $area_id = null, ?int $category_id = null, ?bool $inactive = null): array { return []; }
            public function findById(int $id): ?Item { return null; }
            public function create(array $data): int { return $this->createReturn; }
            public function update(int $id, array $data): bool { return $this->updateReturn; }
            public function delete(int $id): bool { return true; }
        };
    }
}
