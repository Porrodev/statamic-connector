@extends('statamic::layout')
@section('title', __('Set up Cloudflare'))

@section('content')
    @include($v6 ? 'porro::v6.cloudflare' : 'porro::v5.cloudflare')
@endsection
