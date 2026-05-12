@extends('layouts.app')

@section('title', 'coins Detail')

@section('content_header')
    <h1>{{ $coins['symbol'] }} Detail</h1>
@endsection

@section('content')
    <ul class="list-group">
        <li class="list-group-item"><strong>Symbol:</strong> {{ $coins['symbol'] }}</li>
        <li class="list-group-item"><strong>Price:</strong> ${{ number_format($coins['lastPrice'], 3) }}</li>
        <li class="list-group-item"><strong>Volume:</strong> ${{ number_format($coins['quoteVolume'], 3) }}</li>
        <li class="list-group-item"><strong>Change (%):</strong> {{ $coins['priceChangePercent'] }}%</li>
        <li class="list-group-item"><strong>High:</strong> {{ $coins['highPrice'] }}</li>
        <li class="list-group-item"><strong>Low:</strong> {{ $coins['lowPrice'] }}</li>
        <li class="list-group-item"><strong>Open:</strong> {{ $coins['openPrice'] }}</li>
        <li class="list-group-item"><strong>Close Time:</strong> {{ \Carbon\Carbon::createFromTimestampMs($coins['closeTime'])->toDateTimeString() }}</li>
    </ul>
@endsection
