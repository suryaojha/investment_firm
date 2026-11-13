<?php
require __DIR__ . '/_layout.php'; require __DIR__ . '/_crud.php';
admin_header('Pages');
admin_crud([
    'table' => 'pages', 'title' => 'Pages (Terms, Privacy, …)', 'singular' => 'page', 'base' => 'pages.php', 'order' => 'id',
    'help' => 'Content is HTML (use &lt;h2&gt;, &lt;p&gt;, &lt;ul&gt;…). Scripts are stripped automatically. Public URL: <code>page.php?slug=your-slug</code>.',
    'fields' => [
        ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'required' => true, 'list' => true],
        ['name' => 'slug', 'label' => 'URL slug (e.g. terms)', 'type' => 'text', 'required' => true, 'list' => true],
        ['name' => 'content', 'label' => 'Content (HTML)', 'type' => 'html'],
        ['name' => 'show_in_footer', 'label' => 'Show link in website footer', 'type' => 'check', 'default' => 1, 'list' => true],
    ],
    'validate' => function ($d, $row) {
        $d['slug'] = slugify($d['slug']); $d['updated_at'] = now();
        $st = db()->prepare('SELECT id FROM pages WHERE slug = ?'); $st->execute([$d['slug']]); $x = $st->fetch();
        if ($x && (!$row || (int)$x['id'] !== (int)$row['id'])) throw new RuntimeException('That slug is already used.');
        return $d;
    },
]);
admin_footer();
