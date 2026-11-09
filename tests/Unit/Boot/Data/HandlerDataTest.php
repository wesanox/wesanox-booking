<?php

declare(strict_types=1);

namespace Wesanox\Booking\Tests\Unit\Boot\Data;

use PHPUnit\Framework\TestCase;
use Wesanox\Booking\Boot\Data\HandlerData;

/**
 * Tests that HandlerData uses parameterized queries (no SQL injection).
 */
final class HandlerDataTest extends TestCase
{
    private object $wpdb_mock;
    private HandlerData $handler;

    protected function setUp(): void
    {
        $this->wpdb_mock = new class {
            public string $last_prepared_sql = '';
            public string $charset_collate   = 'utf8mb4';
            public int    $insert_id         = 0;

            public function get_charset_collate(): string
            {
                return $this->charset_collate;
            }

            public function prepare(string $sql, mixed ...$args): string
            {
                // Record the SQL template to verify it uses placeholders.
                $this->last_prepared_sql = $sql;
                return vsprintf(str_replace(['%d', '%s', '%f'], ['%d', "'%s'", '%f'], $sql), $args);
            }

            public function get_row(string $sql): ?object
            {
                return null;
            }

            public function get_results(string $sql): array
            {
                return [];
            }

            public function insert(string $table, array $data): bool
            {
                $this->insert_id = 1;
                return true;
            }

            public function delete(string $table, array $where): bool
            {
                return true;
            }

            public function update(string $table, array $data, array $where): bool
            {
                return true;
            }
        };

        $this->handler = new HandlerData($this->wpdb_mock);
    }

    public function test_get_data_by_id_uses_prepare(): void
    {
        $this->handler->wesanox_get_data_by_id(42, 'wp_test_table');

        // SQL template must use a placeholder, not a raw integer concat.
        self::assertStringContainsString('%d', $this->wpdb_mock->last_prepared_sql);
        self::assertStringNotContainsString('WHERE id = 42', $this->wpdb_mock->last_prepared_sql);
    }

    public function test_get_data_by_mail_uses_prepare(): void
    {
        $this->handler->wesanox_get_data_by_mail("test@example.com'; DROP TABLE users;--", 'wp_test_table');

        // SQL template must use a placeholder, not the raw user value.
        self::assertStringContainsString('%s', $this->wpdb_mock->last_prepared_sql);
        self::assertStringNotContainsString('DROP TABLE', $this->wpdb_mock->last_prepared_sql);
    }

    public function test_get_data_by_username_uses_prepare(): void
    {
        $this->handler->wesanox_get_data_by_username("admin'--", 'wp_test_table');

        self::assertStringContainsString('%s', $this->wpdb_mock->last_prepared_sql);
    }

    public function test_insert_data_returns_insert_id_on_success(): void
    {
        $result = $this->handler->wesanox_insert_data(['area_name' => 'Test'], 'wp_test_table');

        self::assertSame(1, $result);
    }

    public function test_delete_data_returns_true_on_success(): void
    {
        $result = $this->handler->wesanox_delete_data(1, 'wp_test_table');

        self::assertTrue($result);
    }
}
