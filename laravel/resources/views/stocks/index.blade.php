@extends('layouts.app')

@push('meta')
    <meta name="toggle-favorite-url" content="{{ route('stocks.favorites.toggle') }}">
    <meta name="add-favorite-url" content="{{ route('stocks.favorites.add') }}">
    <meta name="remove-favorite-url" content="{{ route('stocks.favorites.remove') }}">
@endpush

@section('title', 'Vietnam Listed Stocks')
@section('page_id', 'stocks-favorites')

@section('content_header')
    <h1>Vietnam Listed Stocks</h1>
@endsection

@section('content')
    <div class="stock-search mb-3">
        <label for="stock-search" class="form-label">Search</label>
        <div class="input-group">
            <input
                type="text"
                id="stock-search"
                class="form-control"
                placeholder="Search by symbol, name, exchange..."
            >
            <button type="button" class="btn btn-outline-secondary" id="stock-search-clear">
                Clear
            </button>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-striped datatable">
            <thead>
                <tr>
                    <th data-column="0">Symbol <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th data-column="1">Company Name <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th data-column="2">Exchange <i class="fas fa-sort text-muted ms-1"></i></th>
                    <th>Favourite</th>
                </tr>
            </thead>
            <tbody>
                @foreach($stocks as $stock)
                    <tr>
                        <td>{{ $stock['symbol'] }}</td>
                        <td>{{ $stock['name'] }}</td>
                        <td>{{ $stock['exchange'] }}</td>
                        <td>
                            <a
                                href="#"
                                class="favorite-toggle"
                                data-symbol="{{ $stock['symbol'] }}"
                                data-favorited="{{ in_array($stock['symbol'], $favorites ?? []) ? '1' : '0' }}"
                            >
                                <i class="fa-heart fa {{ in_array($stock['symbol'], $favorites ?? []) ? 'fas text-danger' : 'far text-muted' }}"></i>
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
