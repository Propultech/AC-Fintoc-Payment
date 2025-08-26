<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Block\Traits;

/**
 * Shared date-time formatting utilities for Fintoc blocks.
 */
trait DateTimeFormatterTrait
{
    /**
     * Format date-time into exact required pattern using store timezone.
     * Pattern: d/m/Y H:i:s (dd/MM/YYYY HH:ii:ss)
     *
     * @param mixed $value DateTimeInterface instance or parseable string
     * @return string
     */
    public function formatDateTimeExact($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        try {
            if ($value instanceof \DateTimeInterface) {
                $dt = $this->_localeDate->date($value);
            } else {
                $dt = $this->_localeDate->date((string)$value);
            }
            return $dt->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return is_scalar($value) ? (string)$value : '';
        }
    }
}
