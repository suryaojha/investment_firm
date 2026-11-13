<?php
require __DIR__ . '/_layout.php'; require __DIR__ . '/_crud.php';
admin_header('Investment plans');
admin_crud([
    'table' => 'plans', 'title' => 'Plans', 'singular' => 'plan', 'base' => 'plans.php',
    'help' => 'Plans appear on the home page, calculator and invest page. <b>Total return = ROI + early bonus.</b> Existing investments keep the terms they were made under.',
    'fields' => [
        ['name' => 'name', 'label' => 'Plan name', 'type' => 'text', 'required' => true, 'list' => true],
        ['name' => 'tagline', 'label' => 'Tagline', 'type' => 'text'],
        ['name' => 'min_amount', 'label' => 'Minimum amount (₹)', 'type' => 'number', 'list' => true],
        ['name' => 'max_amount', 'label' => 'Maximum amount (₹) — 0 = no limit', 'type' => 'number'],
        ['name' => 'roi', 'label' => 'Annual return % (ROI)', 'type' => 'number', 'list' => true],
        ['name' => 'bonus', 'label' => 'Early-investor bonus % (added to ROI)', 'type' => 'number'],
        ['name' => 'tenure_months', 'label' => 'Tenure (months)', 'type' => 'number', 'list' => true, 'default' => 24],
        ['name' => 'payout_mode', 'label' => 'Return payout frequency', 'type' => 'select', 'options' => ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'maturity' => 'At maturity'], 'list' => true],
        ['name' => 'target_amount', 'label' => 'Allocation target (₹) — shows a “% subscribed” bar; 0 hides it', 'type' => 'number'],
        ['name' => 'badge', 'label' => 'Ribbon text (e.g. Most popular)', 'type' => 'text', 'list' => true],
        ['name' => 'features', 'label' => 'Features (one per line)', 'type' => 'textarea'],
        ['name' => 'sort_order', 'label' => 'Display order', 'type' => 'number', 'default' => 0],
    ],
    'validate' => function ($d) {
        if ($d['tenure_months'] < 1 || $d['tenure_months'] > 240) throw new RuntimeException('Tenure must be between 1 and 240 months.');
        if ($d['roi'] < 0 || $d['roi'] > 100) throw new RuntimeException('ROI must be between 0 and 100.');
        $d['tenure_months'] = (int)$d['tenure_months'];
        return $d;
    },
]);
admin_footer();
