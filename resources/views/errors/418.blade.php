@extends('errors::minimal')

@section('title', __("I'm a teapot"))
@section('code', '418')
@section('message', __('Your request has been blocked for security reasons. Please try again.'))