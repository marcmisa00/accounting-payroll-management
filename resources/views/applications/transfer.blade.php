@extends('layouts.app')

@section('title', 'Site Transfer - Accounting Portal')
@section('page-title', 'Site Transfer')

@section('styles')
    @vite(['resources/css/leaves.css', 'resources/js/transfer.js'])
@endsection

@section('content')
    @include('applications.partials.page', [
        'route'     => 'transfer.index',
        'noun'      => 'transfer application',
        'dateLabel' => 'Transfer date',
        'dataId'    => 'transferData',
        'ackUrl'    => route('transfer.acknowledge', ['id' => '__ID__']),
        'headers'   => [
            'ID' => '', 'Name' => '', 'Transfer Date' => '', 'End Date' => '', 'Prev Location' => '', 'New Location' => '',
            'Reason' => '', 'Applied On' => '', 'Status' => '', 'Acknowledgements' => '', 'Action' => 'ap-action-col',
        ],
    ])
@endsection
