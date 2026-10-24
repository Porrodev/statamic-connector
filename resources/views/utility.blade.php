@extends('statamic::layout')
@section('title', __('Porro Site Health'))

@section('content')
    @include($v6 ? 'porro::v6.utility' : 'porro::v5.utility')
@endsection
