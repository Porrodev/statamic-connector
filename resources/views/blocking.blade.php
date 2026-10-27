@extends('statamic::layout')
@section('title', __('Blocking'))

@section('content')
    @include($v6 ? 'porro::v6.blocking' : 'porro::v5.blocking')
@endsection
