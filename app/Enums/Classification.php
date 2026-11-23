<?php

namespace App\Enums;

/**
 * Label klasifikasi folder dan berkas (F-07, US-07).
 */
enum Classification: string
{
    case Umum = 'umum';
    case Internal = 'internal';
    case Rahasia = 'rahasia';

    public function label(): string
    {
        return match ($this) {
            self::Umum => 'Umum',
            self::Internal => 'Internal',
            self::Rahasia => 'Rahasia',
        };
    }

    /**
     * Whether changing from this label to the given one lowers the classification of a Rahasia folder.
     */
    public function isLoweredFromConfidential(self $newClassification): bool
    {
        return $this === self::Rahasia && $newClassification !== self::Rahasia;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $classification): array => ['value' => $classification->value, 'label' => $classification->label()],
            self::cases(),
        );
    }
}
