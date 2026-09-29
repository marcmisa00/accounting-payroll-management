@extends('layouts.app')



@section('content')
<link rel="stylesheet" href="{{ asset('css/create-payroll.css') }}">
<div class="centered-container">
    <form class="form-horizontal style-form" method="POST" action="{{ route('payroll.store') }}" style="width: 100%; max-width: 500px;">
        @csrf
        <div class="content-panel">
            <div class="panel-heading d-flex align-items-center justify-content-between" style="position: relative;">
                <h4 class="mb-0 mx-auto text-center" style="flex: 1;">CREATE PAYROLL</h4>
            </div>
            <div class="panel-body">
                <div class="form-group mb-3">
                    <label class="form-label">Period From</label>
                    <input type="date" class="form-control @error('startdate') is-invalid @enderror"
                           name="startdate" value="{{ old('startdate') }}" required>
                    @error('startdate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="form-group mb-3">
                    <label class="form-label">Period To</label>
                    <input type="date" class="form-control @error('enddate') is-invalid @enderror"
                           name="enddate" value="{{ old('enddate') }}" required>
                    @error('enddate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="panel-footer">
                    <input type="submit" id="submitBtn" class="btn-success" value="Proceed">
                </div>
            </div>
        </div>
    </form>
</div>
@endsection