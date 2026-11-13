<?php
require __DIR__ . '/_layout.php'; require __DIR__ . '/_crud.php';
admin_header('Projects');
admin_crud([
    'table' => 'projects', 'title' => 'Projects', 'singular' => 'project', 'base' => 'projects.php',
    'help' => 'Shown in the “The project” section. The first active project is also used on investor certificates.',
    'fields' => [
        ['name' => 'title', 'label' => 'Project title', 'type' => 'text', 'required' => true, 'list' => true],
        ['name' => 'location', 'label' => 'Location', 'type' => 'text', 'list' => true],
        ['name' => 'status_label', 'label' => 'Status label (e.g. Under construction)', 'type' => 'text', 'list' => true],
        ['name' => 'progress', 'label' => 'Construction progress %', 'type' => 'number', 'list' => true, 'step' => 1],
        ['name' => 'completion_date', 'label' => 'Completion date', 'type' => 'date'],
        ['name' => 'image', 'label' => 'Project image', 'type' => 'image', 'list' => true],
        ['name' => 'summary', 'label' => 'Summary', 'type' => 'textarea'],
        ['name' => 'highlights', 'label' => 'Highlights (one per line)', 'type' => 'textarea'],
        ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'default' => 0],
    ],
    'validate' => function ($d) { $d['progress'] = max(0, min(100, (int)$d['progress'])); return $d; },
]);
admin_footer();
