@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Note" />

    <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
        <h1 class="mb-4 text-xl font-semibold text-gray-900 dark:text-white">
            {{ $note->title ?? 'Untitled' }}
        </h1>

        @if($note->tags->isNotEmpty())
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach($note->tags as $tag)
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ $tag->tag }}
                    </span>
                @endforeach
            </div>
        @endif

        <div class="prose prose-sm dark:prose-invert max-w-none">
            {!! nl2br(e($note->content)) !!}
        </div>

        <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">
            Last updated {{ $note->updated_at->diffForHumans() }}
        </p>
    </div>

    <div class="mt-4">
        <a
            href="{{ route('notes.index') }}"
            class="text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            &larr; Back to notes
        </a>
    </div>
@endsection
