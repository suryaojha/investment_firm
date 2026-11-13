<?php
require __DIR__ . '/_layout.php'; require __DIR__ . '/_crud.php';
$types = [
    'feature' => ['Why-invest cards', 'card', 'Title', 'Description', 'Icon (an emoji, e.g. 🏢)'],
    'stat' => ['Statistics strip', 'statistic', 'Label (e.g. Project size)', null, 'Value (e.g. ₹15,000 Cr)'],
    'how' => ['How it works steps', 'step', 'Step title', 'Step description', 'Step number'],
    'timeline' => ['Timeline / roadmap', 'milestone', 'Milestone title', 'Description', 'Year / date label (e.g. 2027)'],
    'testimonial' => ['Testimonials', 'testimonial', 'Investor name', 'Quote', 'Role / city'],
    'faq' => ['FAQ', 'question', 'Question', 'Answer', null],
    'gallery' => ['Photo gallery', 'photo', 'Photo title', null, 'Caption'],
];
$type = get('type', 'faq'); if (!isset($types[$type])) $type = 'faq';
[$title, $one, $tLabel, $bLabel, $eLabel] = $types[$type];
$fields = [['name' => 'title', 'label' => $tLabel, 'type' => 'text', 'required' => true, 'list' => true]];
if ($bLabel) $fields[] = ['name' => 'body', 'label' => $bLabel, 'type' => 'textarea', 'list' => true];
if ($eLabel) $fields[] = ['name' => 'extra', 'label' => $eLabel, 'type' => 'text', 'list' => true];
if ($type === 'gallery') array_splice($fields, 1, 0, [['name' => 'image', 'label' => 'Photo (JPG/PNG/WebP, wide 4:3 works best)', 'type' => 'image', 'list' => true]]);
$fields[] = ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'default' => 0];
admin_header($title);
admin_crud(['table' => 'items', 'title' => $title, 'singular' => $one, 'base' => 'items.php?type=' . $type, 'where' => ['type' => $type], 'fields' => $fields,
    'help' => 'Use the toggle to hide an item without deleting it. Lower “display order” shows first.']);
admin_footer();
