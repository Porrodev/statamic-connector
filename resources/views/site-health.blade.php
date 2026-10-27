@extends('statamic::layout')
@section('title', __('Porro Site Health'))

@section('content')
    @include($v6 ? 'porro::v6.site-health' : 'porro::v5.site-health')
@endsection
