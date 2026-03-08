@props(['content' => ''])

<div
    class="prose prose-sm max-w-none text-gray-700 dark:prose-invert dark:text-gray-300 prose-headings:text-gray-900 dark:prose-headings:text-white prose-a:text-blue-600 dark:prose-a:text-blue-400 prose-strong:text-gray-900 dark:prose-strong:text-white prose-code:rounded prose-code:bg-gray-100 prose-code:px-1 prose-code:py-0.5 dark:prose-code:bg-gray-800"
    x-data="markdownEditor({ field: 'content' })"
    x-init="content = @js($content); await renderPreview(); preview = preview || '';"
    x-html="preview"
></div>
