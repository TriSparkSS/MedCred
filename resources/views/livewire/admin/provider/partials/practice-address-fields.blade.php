<div class="col-12">
    <label class="form-label fw-medium">Practice <span class="text-danger">*</span></label>
    <select wire:model.live="practiceId"
        class="form-select @error('practiceId') is-invalid @enderror">
        <option value="">Select an existing practice...</option>
        @foreach ($practices as $practice)
            <option value="{{ $practice->id }}">
                {{ $practice->legal_name }}@if($practice->client_code) ({{ $practice->client_code }})@endif
            </option>
        @endforeach
    </select>
    @error('practiceId')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
    @error('formData.practice')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="col-12">
    <label class="form-label fw-medium">Address from practice</label>
    <select wire:model.live="addressKey"
        class="form-select @error('addressKey') is-invalid @enderror"
        @disabled($addressOptions === [])>
        <option value="">{{ $addressOptions === [] ? 'Select a practice to see addresses...' : 'Select an address...' }}</option>
        @foreach ($addressOptions as $key => $option)
            <option value="{{ $key }}">{{ $option['label'] }}</option>
        @endforeach
    </select>
    <div class="form-text">Includes Primary Location Address, Alternative Address, Mailing Address, Billing Address, and all facility locations.</div>
    @error('addressKey')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>
