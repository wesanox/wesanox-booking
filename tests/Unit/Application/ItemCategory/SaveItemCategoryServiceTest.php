<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Application\ItemCategory;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Application\ItemCategory\SaveItemCategoryService;
use Wesanox\Booking\Domain\ItemCategory\ItemCategory;
use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

final class SaveItemCategoryServiceTest extends TestCase
{
    public function testItThrowsWhenNameIsEmpty(): void
    {
        $service = new SaveItemCategoryService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '');
    }

    public function testItThrowsWhenNameIsWhitespace(): void
    {
        $service = new SaveItemCategoryService($this->makeRepository());

        $this->expectException(ValidationException::class);
        $service->execute(null, '   ');
    }

    public function testItReturnsNewIdOnCreate(): void
    {
        $repo    = $this->makeRepository(createWillReturn: 4);
        $service = new SaveItemCategoryService($repo);

        $id = $service->execute(null, 'Wellness');

        self::assertSame(4, $id);
    }

    public function testItReturnsExistingIdOnUpdate(): void
    {
        $repo    = $this->makeRepository(updateWillReturn: true);
        $service = new SaveItemCategoryService($repo);

        $id = $service->execute(9, 'Wellness');

        self::assertSame(9, $id);
    }

    public function testValidationExceptionCarriesMessage(): void
    {
        $service = new SaveItemCategoryService($this->makeRepository());

        try {
            $service->execute(null, '');
            self::fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            self::assertNotEmpty($e->errors());
            self::assertStringContainsString('erforderlich', $e->errors()[0]);
        }
    }

    // --- Helpers ---

    private function makeRepository(int $createWillReturn = 1, bool $updateWillReturn = true): ItemCategoryRepositoryInterface
    {
        return new class ($createWillReturn, $updateWillReturn) implements ItemCategoryRepositoryInterface {
            public function __construct(
                private int  $createReturn,
                private bool $updateReturn,
            ) {
            }

            public function findAll(): array { return []; }
            public function findById(int $id): ?ItemCategory { return null; }
            public function create(array $data): int { return $this->createReturn; }
            public function update(int $id, array $data): bool { return $this->updateReturn; }
            public function delete(int $id): bool { return true; }
        };
    }
}
