<?php

namespace App\Support;

class CartLineSignature
{
    public static function make(array $modifierIds, ?string $note): string
    {
        $ids = collect($modifierIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->sort()
            ->values()
            ->implode(',');

        $normalizedNote = trim(preg_replace('/\s+/', ' ', (string) $note) ?? '');

        return sha1($ids.'|'.$normalizedNote);
    }
}
