@extends('statamic::layout')
@section('title', __('Site health'))

@section('content')
    @include($v6 ? 'porro::v6.utility' : 'porro::v5.utility')
@endsection
