<?php
require __DIR__ . '/_layout.php'; require __DIR__ . '/_crud.php';
admin_header('Videos');
admin_crud([
    'table' => 'videos', 'title' => 'Videos', 'singular' => 'video', 'base' => 'videos.php',
    'help' => 'Paste a <b>YouTube</b> or <b>Vimeo</b> link, or upload an <b>MP4</b> file (keep uploads small; PHP’s upload limit applies — YouTube is recommended for big videos).',
    'fields' => [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'list' => true],
        ['name' => 'source', 'label' => 'Source', 'type' => 'select', 'options' => ['youtube' => 'YouTube link', 'vimeo' => 'Vimeo link', 'file' => 'Uploaded MP4/WebM'], 'list' => true],
        ['name' => 'url', 'label' => 'Video URL (YouTube / Vimeo)', 'type' => 'text', 'help' => 'e.g. https://www.youtube.com/watch?v=XXXXXXXXXXX'],
        ['name' => 'file', 'label' => 'Video file (only if source = Uploaded)', 'type' => 'file', 'dir' => 'uploads/videos', 'exts' => ['mp4', 'webm'], 'max' => 200],
        ['name' => 'thumb', 'label' => 'Poster image for uploaded video', 'type' => 'image'],
        ['name' => 'description', 'label' => 'Description', 'type' => 'textarea', 'rows' => 3],
        ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'default' => 0],
    ],
]);
admin_footer();
