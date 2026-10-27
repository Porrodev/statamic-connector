@extends('statamic::layout')
@section('title', $details['ip'])

@section('content')
    @include($v6 ? 'porro::v6.ip' : 'porro::v5.ip')
@endsection
