@extends('layouts.app')

@push('meta')
    <meta name="toggle-favorite-url" content="{{ route('coins.favorites.toggle') }}">
@endpush

@section('title', 'Top Coins')
@section('page_id', 'coins-favorites')

@section('content_header')
    <h1>Top Coins (Source: {{ ucfirst($source) }})</h1>
@endsection

@section('content')
    <div class="table-responsive">
        <table class="table table-bordered table-striped datatable">
            <thead>
                <tr>
                    <th data-column="0">Symbol <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th data-column="1">Price <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th data-column="2">Volume <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th data-column="3">Change (%) <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th>Favourite</th>
                </tr>
            </thead>
            <tbody>
                @foreach($coins as $coin)
                    <tr>
                        <td>
                            <a href="{{ route('coins.show', ['symbol' => $coin['symbol']]) }}">
                                {{ $coin['symbol'] }}
                            </a>
                        </td>
                        <td>${{ number_format($coin['lastPrice'] ?? 0, 3) }}</td>
                        <td>${{ number_format($coin['quoteVolume'] ?? 0, 3) }}</td>
                        <td>{{ $coin['priceChangePercent'] ?? '-' }}</td>
                        <td>
                            <a href="#" class="favorite-toggle" data-symbol="{{ $coin['symbol'] }}">
                                <i class="fa-heart fa {{ in_array($coin['symbol'], $favorites ?? []) ? 'fas text-danger' : 'far text-muted' }}"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
