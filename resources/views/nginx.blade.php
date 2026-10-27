@extends('statamic::layout')
@section('title', __('Set up nginx'))

@section('content')
    @include($v6 ? 'porro::v6.nginx' : 'porro::v5.nginx')
@endsection
