@extends('errors::minimal')

@section('title', __('Server Error'))
@section('code', '500')
@section('message')
    Server error
    @if (!empty(Flare::sentReports()->latestUuid() && Flare::sentReports()->latestUrl()))
        <a href="{{ Flare::sentReports()->latestUrl() }}" style="display:block;font-family:monospace;font-size:75%;">
            {{ Flare::sentReports()->latestUuid() }}
        </a>
    @endif
@endsection
