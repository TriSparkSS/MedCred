<?php

use App\Enums\ProviderCredentialStatus;
use App\Enums\ProviderCredentialType;
use App\Models\DocumentType;
use App\Models\ProviderCredential;
use App\Models\ProviderDetails;
use App\Support\UsStates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_types', function (Blueprint $table) {
            if (! Schema::hasColumn('document_types', 'is_state_specific')) {
                $table->boolean('is_state_specific')->default(false)->after('is_active');
            }
        });

        if (! Schema::hasTable('provider_credentials')) {
            Schema::create('provider_credentials', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_id')->constrained('provider_details')->cascadeOnDelete();
                $table->string('credential_type');
                $table->string('state', 2);
                $table->string('number');
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('status')->default(ProviderCredentialStatus::Active->value);
                $table->boolean('is_primary')->default(false);
                $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['provider_id', 'credential_type', 'state'], 'provider_cred_type_state_unique');
            });
        }

        $this->markStateSpecificDocumentTypes();
        $this->backfillProviderCredentials();
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_credentials');

        Schema::table('document_types', function (Blueprint $table) {
            if (Schema::hasColumn('document_types', 'is_state_specific')) {
                $table->dropColumn('is_state_specific');
            }
        });
    }

    protected function markStateSpecificDocumentTypes(): void
    {
        if (! Schema::hasTable('document_types')) {
            return;
        }

        DocumentType::query()
            ->whereIn('name', ['State License', 'DEA Certificate', 'CDS Certificate'])
            ->update(['is_state_specific' => true]);
    }

    protected function backfillProviderCredentials(): void
    {
        if (! Schema::hasTable('provider_details') || ! Schema::hasTable('provider_credentials')) {
            return;
        }

        ProviderDetails::query()->each(function (ProviderDetails $provider) {
            $this->backfillOne($provider, ProviderCredentialType::License, $provider->license_number, $provider->license_state, true);
            $this->backfillOne($provider, ProviderCredentialType::Dea, $provider->dea, $provider->license_state ?: $provider->state, false);
            $this->backfillOne($provider, ProviderCredentialType::Cds, $provider->cds_number, $provider->cds_state ?: $provider->license_state, false);
        });
    }

    protected function backfillOne(ProviderDetails $provider, ProviderCredentialType $type, ?string $number, ?string $state, bool $primary): void
    {
        $number = trim((string) $number);
        $state = UsStates::normalize($state);

        if ($number === '' || $state === null) {
            return;
        }

        ProviderCredential::query()->firstOrCreate(
            [
                'provider_id' => $provider->id,
                'credential_type' => $type->value,
                'state' => $state,
            ],
            [
                'number' => $number,
                'status' => ProviderCredentialStatus::Active->value,
                'is_primary' => $primary && $type === ProviderCredentialType::License,
            ]
        );
    }
};
