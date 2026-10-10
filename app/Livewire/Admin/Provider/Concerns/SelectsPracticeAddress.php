<?php

namespace App\Livewire\Admin\Provider\Concerns;

use App\Models\Practice;
use App\Services\AdminScopeService;
use Illuminate\Support\Facades\Auth;

trait SelectsPracticeAddress
{
    public string $practiceId = '';

    public string $addressKey = '';

    public array $addressOptions = [];

    public function updatedPracticeId($value): void
    {
        if ($value) {
            $this->assertPracticeInScope((int) $value);
        }

        $this->loadAddressOptions($value);
        $this->addressKey = (string) array_key_first($this->addressOptions);
        $this->applySelectedAddress();
    }

    public function updatedAddressKey(): void
    {
        $this->applySelectedAddress();
    }

    protected function loadAddressOptions(mixed $practiceId): void
    {
        $this->addressOptions = [];

        if (! $practiceId) {
            return;
        }

        $practice = Practice::query()
            ->with(['addresses', 'locations'])
            ->find($practiceId);

        if (! $practice) {
            return;
        }

        $this->formData['practice'] = $practice->legal_name;
        $this->addressOptions = $practice->selectableAddressOptions();
    }

    protected function applySelectedAddress(): void
    {
        $option = $this->addressOptions[$this->addressKey] ?? null;

        if (! $option) {
            return;
        }

        $this->formData['practice'] = $option['practice'];
        $this->formData['address'] = $option['address'];
        $this->formData['city'] = $option['city'];
        $this->formData['state'] = $option['state'];
        $this->formData['zip'] = $option['zip'];
    }

    protected function matchAddressKeyFromCurrentFields(): void
    {
        $street = trim((string) ($this->formData['address'] ?? ''));
        $zip = trim((string) ($this->formData['zip'] ?? ''));

        foreach ($this->addressOptions as $key => $option) {
            if ($option['address'] === $street && (string) $option['zip'] === $zip) {
                $this->addressKey = (string) $key;

                return;
            }
        }

        $this->addressKey = '';
    }

    protected function assertPracticeInScope(int $practiceId): void
    {
        $admin = Auth::guard('admin')->user();
        if ($admin && ! app(AdminScopeService::class)->canAccessPractice($admin, $practiceId)) {
            abort(403);
        }
    }
}
