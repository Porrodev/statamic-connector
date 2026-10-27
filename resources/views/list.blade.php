@extends('statamic::layout')
@section('title', $list === 'safelist' ? __('Safelist') : __('Blocklist'))

@section('content')
    @include($v6 ? 'porro::v6.list' : 'porro::v5.list')
@endsection
