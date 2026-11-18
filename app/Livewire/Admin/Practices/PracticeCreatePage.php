<?php

namespace App\Livewire\Admin\Practices;

use App\Livewire\Admin\Practices\Concerns\ManagesPracticeForm;
use App\Livewire\Concerns\ManagesPendingDocuments;
use App\Livewire\Concerns\ManagesPendingPortalLogins;
use App\Models\Practice;
use App\Models\User;
use App\Services\AdminScopeService;
use App\Services\PracticeContactService;
use App\Services\PracticeFacilityLocationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

#[Layout('layouts::admin', ['title' => 'Add Practice'])]
class PracticeCreatePage extends Component
{
    use ManagesPendingDocuments, ManagesPendingPortalLogins, ManagesPracticeForm, WithFileUploads;

    public const MANAGE_PERMISSION = 'admin.practices.manage';

    public $formData = [
        'status' => 'pending',
    ];

    public $userData = [
        'password' => '',
    ];

    public $addressData = [];

    public $alternativeAddressData = [];

    public $mailingAddressData = [];

    public $billingAddressData = [];

    public $document;

    /** Bank Documents rows (type locked to "Bank Document"). */
    public array $bankDocuments = [];

    /** Additional License & Documents rows (on top of the single license upload above). */
    public array $licenseDocuments = [];

    /** Practice contact rows, saved through PracticeContactService. */
    public array $contacts = [];

    /** Facility location rows, saved through PracticeFacilityLocationService. */
    public array $facilityLocations = [];

    public function mount(): void
    {
        $this->addressData = $this->emptyAddressDefaults();
        $this->alternativeAddressData = $this->emptyAddressDefaults();
        $this->mailingAddressData = $this->emptyAddressDefaults();
        $this->billingAddressData = $this->emptyAddressDefaults();
    }

    protected function pendingDocumentGroups(): array
    {
        return [
            'bankDocuments' => ['locked_type' => 'Bank Document', 'state_aware' => false],
            'licenseDocuments' => ['locked_type' => null, 'state_aware' => false],
        ];
    }

    protected function rules(): array
    {
        return array_merge(
            $this->basePracticeRules(),
            [
                'userData.password' => 'required|string|min:6',
                'formData.client_code' => Practice::clientCodeValidationRule().'|unique:practices,client_code',
                'formData.ein_tin' => 'nullable|string|max:50|unique:practices,ein_tin',
                'formData.group_npi' => 'nullable|string|max:50|unique:practices,group_npi',
                'formData.email' => 'required|email|max:255|unique:practices,email|unique:users,email',
            ],
            $this->addressRules('addressData', true),
            $this->addressRules('alternativeAddressData'),
            $this->addressRules('mailingAddressData'),
            $this->addressRules('billingAddressData'),
            $this->contacts === [] ? [] : PracticeContactService::rules('contacts.*'),
            $this->facilityLocations === [] ? [] : PracticeFacilityLocationService::rules('facilityLocations.*'),
            $this->portalLoginRowRules(),
            $this->pendingDocumentRules(),
        );
    }

    protected function validationAttributes(): array
    {
        return array_merge(
            [
                'contacts.*.name' => 'contact name',
                'contacts.*.title' => 'contact title',
                'contacts.*.email' => 'contact email',
                'contacts.*.phone' => 'contact phone',
                'contacts.*.fax' => 'contact fax',
                'contacts.*.notes' => 'contact notes',
                'facilityLocations.*.name' => 'location name',
                'facilityLocations.*.address1' => 'address',
                'facilityLocations.*.address2' => 'suite / unit',
                'facilityLocations.*.city' => 'city',
                'facilityLocations.*.state' => 'state',
                'facilityLocations.*.zip_code' => 'zip code',
                'facilityLocations.*.county' => 'county',
                'facilityLocations.*.country' => 'country',
                'facilityLocations.*.npi' => 'NPI',
                'facilityLocations.*.taxonomy_code' => 'taxonomy code',
                'facilityLocations.*.status' => 'status',
            ],
            $this->portalLoginRowAttributes(),
            $this->pendingDocumentAttributes(),
        );
    }

    protected function canManagePracticeRows(): bool
    {
        return (bool) Auth::guard('admin')->user()?->can(self::MANAGE_PERMISSION);
    }

    public function addContactRow(): void
    {
        abort_unless($this->canManagePracticeRows(), 403);

        $this->contacts[] = PracticeContactService::emptyRow();
    }

    public function removeContactRow(int $index): void
    {
        unset($this->contacts[$index]);
        $this->contacts = array_values($this->contacts);
        $this->resetValidation();
    }

    public function addFacilityLocationRow(): void
    {
        abort_unless($this->canManagePracticeRows(), 403);

        $this->facilityLocations[] = PracticeFacilityLocationService::emptyRow();
    }

    public function removeFacilityLocationRow(int $index): void
    {
        unset($this->facilityLocations[$index]);
        $this->facilityLocations = array_values($this->facilityLocations);
        $this->zipLookupMessages = [];
        $this->resetValidation();
    }

    public function save()
    {
        $canManagePortalCredentials = Auth::guard('admin')->user()?->can('admin.portal-credentials.manage') ?? false;
        $rules = $this->rules();

        if (! $canManagePortalCredentials) {
            $rules['userData.password'] = 'nullable|string|min:6';
        }

        if ($this->contacts !== [] || $this->facilityLocations !== []) {
            abort_unless($this->canManagePracticeRows(), 403);
        }
        $this->assertCanSavePendingPortalLogins();
        $this->assertCanSavePendingDocuments();

        // 1) Validate every field and every row before anything is written.
        $this->validate($rules);
        // 2) Malware-scan the new document rows so a bad file is reported on its own row.
        $this->scanPendingDocuments();

        try {
            $practice = DB::transaction(function () use ($canManagePortalCredentials) {
                $password = $canManagePortalCredentials && filled($this->userData['password'])
                    ? $this->userData['password']
                    : Str::password(16);

                $user = User::create([
                    'name' => $this->formData['legal_name'],
                    'email' => $this->formData['email'],
                    'phone' => $this->formData['phone'] ?? null,
                    'password' => $password,
                ]);

                if (method_exists($user, 'assignRole')) {
                    $user->assignRole('practice');
                }

                $documentData = $this->storePracticeDocument($this->document);

                $practice = Practice::create([
                    ...$this->formData,
                    ...$documentData,
                    'user_id' => $user->id,
                ]);

                $this->syncPracticeAddress($practice, 'primary', $this->addressData);
                $this->syncPracticeAddress($practice, 'alternative', $this->alternativeAddressData);
                $this->syncPracticeAddress($practice, 'mailing', $this->mailingAddressData);
                $this->syncPracticeAddress($practice, 'billing', $this->billingAddressData);

                $contactService = app(PracticeContactService::class);
                foreach ($this->contacts as $row) {
                    $contactService->create($practice->id, $row);
                }

                $locationService = app(PracticeFacilityLocationService::class);
                foreach ($this->facilityLocations as $row) {
                    $locationService->create($practice->id, $row);
                }

                $this->persistPendingPortalLogins($practice);

                $admin = Auth::guard('admin')->user();
                if ($admin && app(AdminScopeService::class)->isPracticeScoped($admin)) {
                    $admin->practices()->syncWithoutDetaching([$practice->id]);
                }

                // 3) Files last: every database row above is already saved inside this transaction.
                $this->syncPracticeLicenseDocument($practice->fresh(), $this->document);
                $this->persistPendingDocuments($practice->id, null);

                return $practice;
            });
        } catch (ValidationException|HttpExceptionInterface $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
            $message = 'The practice could not be created, so nothing was saved. Please check the details and try again. If it keeps happening, contact support.';
            $this->addError('save', $message);
            flash()->error($message);

            return null;
        }

        flash()->success('Practice and user created successfully!');

        return redirect()->route('admin.practices.show', [
            'practice' => $practice->id,
            'tab' => 'locations',
        ]);
    }

    public function render()
    {
        $admin = Auth::guard('admin')->user();

        return view('livewire.admin.practices.practice-create-page', [
            'canManagePortalLogins' => $this->canManagePendingPortalLogins(),
            'canUploadDocuments' => $this->canUploadPendingDocuments(),
            'canManagePracticeRows' => (bool) $admin?->can(self::MANAGE_PERMISSION),
            'documentTypes' => $this->pendingDocumentTypes(false),
        ]);
    }
}
