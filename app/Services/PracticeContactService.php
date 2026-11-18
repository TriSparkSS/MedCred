<?php

namespace App\Services;

use App\Models\PracticeContact;
use Illuminate\Support\Arr;

/**
 * Single source of truth for practice contact rows. Used by the Edit page section
 * (PracticeContactsSection) and by Add Practice so both save identical data.
 */
class PracticeContactService
{
    public const FIELDS = ['name', 'title', 'email', 'phone', 'fax', 'is_primary', 'notes'];

    /**
     * Validation rules for one contact. $prefix is the array path, e.g. 'formData' or 'contacts.*'.
     */
    public static function rules(string $prefix = 'formData'): array
    {
        return [
            "{$prefix}.name" => 'required|string|max:255',
            "{$prefix}.title" => 'nullable|string|max:255',
            "{$prefix}.email" => 'nullable|email|max:255',
            "{$prefix}.phone" => 'nullable|string|max:30',
            "{$prefix}.fax" => 'nullable|string|max:30',
            "{$prefix}.is_primary" => 'boolean',
            "{$prefix}.notes" => 'nullable|string|max:1000',
        ];
    }

    public static function emptyRow(): array
    {
        return [
            'name' => '',
            'title' => '',
            'email' => '',
            'phone' => '',
            'fax' => '',
            'is_primary' => false,
            'notes' => '',
        ];
    }

    public function create(int $practiceId, array $data): PracticeContact
    {
        $payload = $this->payload($data);

        if ($payload['is_primary'] ?? false) {
            $this->clearPrimary($practiceId);
        }

        return PracticeContact::create([...$payload, 'practice_id' => $practiceId]);
    }

    public function update(PracticeContact $contact, array $data): PracticeContact
    {
        $payload = $this->payload($data);

        if ($payload['is_primary'] ?? false) {
            $this->clearPrimary((int) $contact->practice_id);
        }

        $contact->update($payload);

        return $contact->fresh();
    }

    protected function clearPrimary(int $practiceId): void
    {
        PracticeContact::where('practice_id', $practiceId)->update(['is_primary' => false]);
    }

    protected function payload(array $data): array
    {
        $payload = Arr::only($data, self::FIELDS);

        if (array_key_exists('is_primary', $payload)) {
            $payload['is_primary'] = (bool) $payload['is_primary'];
        }

        return $payload;
    }
}
