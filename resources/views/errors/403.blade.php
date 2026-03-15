@extends('errors.layout')

@section('code', '403')
@section('title', 'The Way Is Shut')
@section('message', $exception->getMessage() ?: 'It was made by those who are Dead, and the Dead keep it. You don\'t have permission to access this path.')
