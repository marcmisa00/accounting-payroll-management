@extends('layouts.app')

@section('title', 'Dashboard - Accounting Portal')
@section('page-title', 'Dashboard')

@section('styles')
<style>
    .cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }

    .card {
        background: #fff;
        border-radius: 14px;
        padding: 22px 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    }

    .card .label {
        font-size: 13px;
        color: #888;
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .card .value {
        font-size: 26px;
        font-weight: 700;
        color: #222;
    }

    .panel {
        background: #fff;
        border-radius: 14px;
        padding: 24px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        color: #777;
        text-align: center;
    }
</style>
@endsection

@section('content')

    <div class="cards">
        <div class="card">
            <div class="label">Total Revenue</div>
            <div class="value">--</div>
        </div>
        <div class="card">
            <div class="label">Total Expenses</div>
            <div class="value">--</div>
        </div>
        <div class="card">
            <div class="label">Net Balance</div>
            <div class="value">--</div>
        </div>
        <div class="card">
            <div class="label">Pending Invoices</div>
            <div class="value">--</div>
        </div>
    </div>

    <div class="panel">
        More sections (charts, recent transactions, etc.) go here.
    </div>

@endsection