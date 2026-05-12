@extends('layouts.app')

@section('title', 'Coin Alert Settings')
@section('page_id', 'coin-alert-settings')

@section('content_header')
    <h1>Coin Alert Settings</h1>
@endsection

@section('content')
<div class="table-responsive">
    <table class="table table-bordered table-striped datatable">
        <thead>
            <tr>
                <th data-column="0">% Threshold <i class="fas fa-sort text-muted ms-1"></i></th>
                <th data-column="1">Type <i class="fas fa-sort text-muted ms-1"></i></th>
                <th data-column="2">Direction <i class="fas fa-sort text-muted ms-1"></i></th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($settings as $setting)
                <tr data-id="{{ $setting->id }}">
                    <td>{{ $setting->threshold_percent ?? 'Custom' }}</td>
                    <td>{{ ucfirst($setting->type ?? 'preset') }}</td>
                    <td>{{ ucfirst($setting->direction ?? '-') }}</td>
                    <td>
                        <!-- Edit -->
                        <a href="{{ route('coins.price-alert-settings.edit', $setting->id) }}" 
                           class="btn btn-sm btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    
                        <!-- Toggle -->
                        <button type="button" 
                                class="btn btn-sm toggle-status {{ $setting->is_active ? 'btn-warning' : 'btn-success' }}"
                                data-url="{{ route('coins.price-alert-settings.toggle', $setting->id) }}">
                            <i class="fas {{ $setting->is_active ? 'fa-toggle-off' : 'fa-toggle-on' }}"></i>
                            {{ $setting->is_active ? 'On' : 'Off' }}
                        </button>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
