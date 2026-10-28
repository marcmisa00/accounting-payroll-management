@extends('layouts.app')

@section('title', 'HSO Application - Accounting Portal')
@section('page-title', 'HSO Application')

@section('styles')
    @vite(['resources/css/leaves.css', 'resources/js/hso.js'])
@endsection

@section('content')
    @include('applications.partials.page', [
        'route'     => 'hso.index',
        'noun'      => 'HSO application',
        'dateLabel' => 'Start date',
        'dataId'    => 'hsoData',
        'ackUrl'    => route('hso.acknowledge', ['id' => '__ID__']),
        'showUrl'   => route('hso.show', ['id' => '__ID__']),
        'headers'   => [
            'ID' => '', 'Name' => '', 'Type' => '', 'Start Date' => '', 'End Date' => '', 'Reason' => '',
            'Schedule' => '', 'Applied On' => '', 'Status' => '', 'Acknowledgements' => '', 'Action' => 'ap-action-col',
        ],
    ])

    {{-- Details popup, filled by hso.js --}}
    <div class="ap-modal-backdrop" id="apModal" hidden>
        <div class="ap-modal" role="dialog" aria-modal="true" aria-labelledby="apModalTitle">
            <header class="ap-modal-head">
                <div>
                    <h3 id="apModalTitle">HSO Application Details</h3>
                    <span class="ap-modal-sub" data-modal-sub></span>
                </div>
                <button type="button" class="ap-modal-close" data-modal-close aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="ap-modal-body" data-modal-body></div>
        </div>
    </div>
@endsection
