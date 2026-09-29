@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="{{ asset('css/manage-payroll.css') }}">
<div class="centered-container">
    <form class="form-horizontal style-form" method="POST" action="{{ route('payroll.manageSelectSubmit') }}" style="width: 100%; max-width: 500px;">
        @csrf
        <div class="content-panel">
            <div class="panel-heading d-flex align-items-center justify-content-between" style="position: relative;">
                <h4 class="mb-0 mx-auto text-center" style="flex: 1;">MANAGE PAYROLL</h4>
            </div>
            <div class="panel-body">
                <div class="form-group mb-3">
                    <label class="form-label">Payroll Period</label>
                    <div class="custom-select-wrapper">
                        <select name="period" class="form-control custom-select @error('period') is-invalid @enderror"
                                style="cursor: pointer; font-family: monospace;" required>
                            <option value="">-- Select Period --</option>
                            @foreach ($periods as $period)
                                <option value="{{ $period->id }}" @selected(old('period') == $period->id)>
                                    {{ $period->periodfrom->format('M d, Y') }} to {{ $period->periodto->format('M d, Y') }}
                                </option>
                            @endforeach
                        </select>
                        <span class="custom-dropdown-icon">&#9662;</span>
                    </div>
                    @error('period')
                        <div class="invalid-feedback d-block">{{ $message }}</div>
                    @enderror
                </div>
                <div class="panel-footer">
                    <input type="submit" id="submitBtn" class="btn-success" value="Select">
                </div>
            </div>
        </div>
    </form>
</div>
@endsection