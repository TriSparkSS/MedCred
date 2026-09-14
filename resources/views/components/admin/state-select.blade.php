@props([
    'placeholder' => 'Select state',
])

<select {{ $attributes->class(['form-select']) }}>
    <option value="">{{ $placeholder }}</option>
    @foreach (\App\Support\UsStates::all() as $code => $name)
        <option value="{{ $code }}">{{ $code }} — {{ $name }}</option>
    @endforeach
</select>
