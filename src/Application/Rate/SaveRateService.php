<?php

declare(strict_types=1);

namespace Wesanox\Booking\Application\Rate;

defined('ABSPATH') || exit;

use Wesanox\Booking\Domain\ItemCategory\ItemCategoryRepositoryInterface;
use Wesanox\Booking\Domain\Rate\Rate;
use Wesanox\Booking\Domain\Rate\RateRepositoryInterface;
use Wesanox\Booking\Support\ValidationException;

/**
 * Create or update a Rate within an Area + ItemCategory.
 * The area_id is always provided by the caller (controller) — never from form data.
 */
final class SaveRateService
{
    public function __construct(
        private RateRepositoryInterface             $repository,
        private WooCommerceProductProviderInterface $products,
        private ItemCategoryRepositoryInterface     $categories,
    ) {
    }

    /**
     * @param string[] $days  Weekday names (subset of Rate::WEEKDAYS)
     * @throws ValidationException
     */
    public function execute(
        ?int    $id,
        int     $area_id,
        int     $item_category_id,
        string  $name,
        string  $time_from,
        string  $time_to,
        array   $days,
        int     $wc_product_id,
        ?int    $wc_variation_id,
        bool    $is_active,
        int     $sort_order,
    ): int {
        $errors = [];

        if (trim($name) === '') {
            $errors[] = 'Der Name ist erforderlich.';
        }

        if ($area_id <= 0) {
            $errors[] = 'Eine Area ist erforderlich.';
        }

        // Validate item category exists (0 = global, no specific category).
        if ($item_category_id < 0) {
            $errors[] = 'Ungültige Item-Kategorie.';
        } elseif ($item_category_id > 0 && !$this->categories->findById($item_category_id)) {
            $errors[] = 'Die Item-Kategorie existiert nicht.';
        }

        // Sanitize and validate days.
        $days = array_values(array_intersect($days, Rate::WEEKDAYS));
        if (empty($days)) {
            $errors[] = 'Mindestens ein Wochentag muss ausgewählt werden.';
        }

        // Empty time = whole day (00:00–00:00).
        if (trim($time_from) === '') {
            $time_from = '00:00';
        }
        if (trim($time_to) === '') {
            $time_to = '00:00';
        }

        if (!$this->isValidTime($time_from)) {
            $errors[] = 'Von-Uhrzeit ist ungültig (Format HH:MM).';
        }

        if (!$this->isValidTime($time_to)) {
            $errors[] = 'Bis-Uhrzeit ist ungültig (Format HH:MM).';
        }

        // Time range check (only when both times are valid).
        if ($this->isValidTime($time_from) && $this->isValidTime($time_to)) {
            $from_min = $this->toMinutes($time_from);
            $to_min   = $this->toMinutes($time_to, true);

            if ($to_min <= $from_min) {
                $errors[] = 'Bis-Uhrzeit muss nach Von-Uhrzeit liegen.';
            }
        }

        if ($wc_product_id <= 0) {
            $errors[] = 'Ein WooCommerce-Produkt ist erforderlich.';
        } elseif (!$this->products->productExists($wc_product_id)) {
            $errors[] = 'Das ausgewählte WooCommerce-Produkt existiert nicht.';
        } elseif ($wc_variation_id !== null) {
            if (!$this->products->variationBelongsToProduct($wc_variation_id, $wc_product_id)) {
                $errors[] = 'Die Variation gehört nicht zum ausgewählten Produkt.';
            }
        }

        // Overlap check within area + item_category + shared days (only when active and no prior errors).
        if (
            empty($errors)
            && $area_id > 0
            && $item_category_id >= 0
            && !empty($days)
            && $this->isValidTime($time_from)
            && $this->isValidTime($time_to)
            && $is_active
        ) {
            $candidates = $this->repository->findOverlapping($area_id, $item_category_id, $time_from, $time_to, $id);

            // Only rates sharing at least one weekday with the new rate are actual conflicts.
            $overlapping = array_values(array_filter(
                $candidates,
                fn (Rate $r) => $r->sharesDay($days),
            ));

            if (!empty($overlapping)) {
                $names    = array_map(fn ($r) => '"' . $r->name . '"', $overlapping);
                $errors[] = 'Zeitraum überschneidet sich (gleiche Kategorie, gleiche Tage) mit: ' . implode(', ', $names) . '.';
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withErrors($errors);
        }

        $data = [
            'area_id'                  => $area_id,
            'item_category_id'         => $item_category_id,
            'name'                     => $name,
            'time_from'                => $time_from,
            'time_to'                  => $time_to,
            'days'                     => $days,
            'woocommerce_product_id'   => $wc_product_id,
            'woocommerce_variation_id' => $wc_variation_id,
            'is_active'                => $is_active ? 1 : 0,
            'sort_order'               => $sort_order,
        ];

        if ($id === null) {
            return $this->repository->create($data);
        }

        $this->repository->update($id, $data);

        return $id;
    }

    private function isValidTime(string $time): bool
    {
        return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time);
    }

    private function toMinutes(string $time, bool $midnight_as_1440 = false): int
    {
        [$h, $m] = array_map('intval', explode(':', $time, 2));
        $minutes = $h * 60 + $m;

        if ($midnight_as_1440 && $minutes === 0) {
            return 1440;
        }

        return $minutes;
    }
}
