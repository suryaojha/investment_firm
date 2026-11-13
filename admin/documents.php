<?php
require __DIR__ . '/_layout.php'; require __DIR__ . '/_crud.php';
admin_header('Documents / PDFs');
admin_crud([
    'table' => 'documents', 'title' => 'Documents', 'singular' => 'document', 'base' => 'documents.php',
    'help' => 'Upload PDFs such as Terms &amp; Conditions, RERA certificate, approvals, brochure. “Public” documents can be downloaded by anyone; otherwise only logged-in investors.',
    'fields' => [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'list' => true],
        ['name' => 'category', 'label' => 'Category (e.g. Legal, Approvals, Brochure)', 'type' => 'text', 'list' => true],
        ['name' => 'description', 'label' => 'Short description', 'type' => 'text'],
        ['name' => 'file', 'label' => 'PDF file (max 20 MB)', 'type' => 'file', 'dir' => 'uploads/docs', 'exts' => ['pdf'], 'max' => 20, 'list' => true],
        ['name' => 'is_public', 'label' => 'Public (visible without login)', 'type' => 'check', 'default' => 1, 'list' => true],
        ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'default' => 0],
    ],
    'validate' => function ($d, $row) {
        if (empty($d['file']) && empty($row['file'])) throw new RuntimeException('Please upload a PDF file.');
        return $d;
    },
]);
admin_footer();
