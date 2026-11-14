<?php
/**
 * Database connection (SQLite or MySQL), schema creation and seed data.
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    $c = $GLOBALS['CFG'];
    $opt = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false];
    if (($c['db_driver'] ?? 'sqlite') === 'mysql') {
        $dsn = 'mysql:host=' . $c['db_host'] . ';port=' . ($c['db_port'] ?? 3306) . ';dbname=' . $c['db_name'] . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $c['db_user'], $c['db_pass'], $opt);
        $pdo->exec("SET time_zone = '+05:30'");
    } else {
        $dir = ROOT . '/storage/db';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $pdo = new PDO('sqlite:' . $dir . '/app.sqlite', null, null, $opt);
        $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA journal_mode = WAL; PRAGMA busy_timeout = 5000;');
    }
    return $pdo;
}
function db_is_mysql(): bool { return ($GLOBALS['CFG']['db_driver'] ?? 'sqlite') === 'mysql'; }

function create_schema(PDO $pdo, bool $mysql): void {
    $pk = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $tail = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';
    $t = [
        "CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) PRIMARY KEY, v TEXT)",
        "CREATE TABLE IF NOT EXISTS users (
            id $pk, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, phone VARCHAR(20), password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user', status VARCHAR(20) NOT NULL DEFAULT 'active',
            city VARCHAR(100), address TEXT, dob VARCHAR(20), referral_code VARCHAR(32) UNIQUE, referred_by INT, wallet_balance DECIMAL(16,2) NOT NULL DEFAULT 0,
            pan VARCHAR(20), aadhaar_enc TEXT, aadhaar_last4 VARCHAR(4), pan_file VARCHAR(255), aadhaar_file VARCHAR(255),
            kyc_status VARCHAR(20) NOT NULL DEFAULT 'none', kyc_note TEXT, kyc_submitted_at VARCHAR(30),
            nominee_name VARCHAR(150), nominee_relation VARCHAR(60),
            bank_holder VARCHAR(150), bank_account VARCHAR(40), bank_ifsc VARCHAR(20),
            must_change_pw INT NOT NULL DEFAULT 0,
            reset_token VARCHAR(100), reset_expires VARCHAR(30), created_at VARCHAR(30), last_login VARCHAR(30)
        )$tail",
        "CREATE TABLE IF NOT EXISTS plans (
            id $pk, name VARCHAR(120) NOT NULL, tagline VARCHAR(255), min_amount DECIMAL(16,2) NOT NULL DEFAULT 0, max_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            roi DECIMAL(6,2) NOT NULL DEFAULT 0, bonus DECIMAL(6,2) NOT NULL DEFAULT 0, tenure_months INT NOT NULL DEFAULT 24,
            payout_mode VARCHAR(20) NOT NULL DEFAULT 'quarterly', target_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            badge VARCHAR(50), features TEXT, is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        )$tail",
        "CREATE TABLE IF NOT EXISTS projects (
            id $pk, title VARCHAR(200) NOT NULL, location VARCHAR(200), summary TEXT, image VARCHAR(255), status_label VARCHAR(80),
            progress INT NOT NULL DEFAULT 0, completion_date VARCHAR(20), highlights TEXT, is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        )$tail",
        "CREATE TABLE IF NOT EXISTS investments (
            id $pk, ref_no VARCHAR(40) NOT NULL UNIQUE, user_id INT NOT NULL, plan_id INT NOT NULL, amount DECIMAL(16,2) NOT NULL,
            roi DECIMAL(6,2) NOT NULL DEFAULT 0, tenure_months INT NOT NULL DEFAULT 24, payout_mode VARCHAR(20) NOT NULL DEFAULT 'quarterly',
            expected_return DECIMAL(16,2) NOT NULL DEFAULT 0, method VARCHAR(20), status VARCHAR(20) NOT NULL DEFAULT 'pending',
            gateway_order_id VARCHAR(100), gateway_payment_id VARCHAR(100), txn_id VARCHAR(100), proof_file VARCHAR(255), note TEXT, wallet_used DECIMAL(16,2) NOT NULL DEFAULT 0,
            certificate_no VARCHAR(40), created_at VARCHAR(30), paid_at VARCHAR(30), maturity_date VARCHAR(20)
        )$tail",
        "CREATE TABLE IF NOT EXISTS payouts (
            id $pk, investment_id INT NOT NULL, user_id INT NOT NULL, type VARCHAR(20) NOT NULL DEFAULT 'return', amount DECIMAL(16,2) NOT NULL,
            due_date VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', paid_at VARCHAR(30), reference VARCHAR(100), note VARCHAR(255)
        )$tail",
        "CREATE TABLE IF NOT EXISTS videos (
            id $pk, title VARCHAR(200) NOT NULL, description TEXT, source VARCHAR(20) NOT NULL DEFAULT 'youtube', url VARCHAR(500), file VARCHAR(255),
            thumb VARCHAR(255), is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        )$tail",
        "CREATE TABLE IF NOT EXISTS documents (
            id $pk, title VARCHAR(200) NOT NULL, description VARCHAR(500), file VARCHAR(255), category VARCHAR(60),
            is_public INT NOT NULL DEFAULT 1, is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        )$tail",
        "CREATE TABLE IF NOT EXISTS pages (
            id $pk, slug VARCHAR(120) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, content TEXT, is_active INT NOT NULL DEFAULT 1, show_in_footer INT NOT NULL DEFAULT 1, updated_at VARCHAR(30)
        )$tail",
        "CREATE TABLE IF NOT EXISTS items (
            id $pk, type VARCHAR(30) NOT NULL, title VARCHAR(255), body TEXT, extra VARCHAR(255), image VARCHAR(255), is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        )$tail",
        "CREATE TABLE IF NOT EXISTS messages (
            id $pk, name VARCHAR(150), email VARCHAR(190), phone VARCHAR(30), message TEXT, is_read INT NOT NULL DEFAULT 0, created_at VARCHAR(30)
        )$tail",
        "CREATE TABLE IF NOT EXISTS login_attempts (id $pk, ip VARCHAR(45), email VARCHAR(190), created_at VARCHAR(30))$tail",
        "CREATE TABLE IF NOT EXISTS audit (id $pk, user_id INT, action VARCHAR(80), detail VARCHAR(500), ip VARCHAR(45), created_at VARCHAR(30))$tail",
        "CREATE TABLE IF NOT EXISTS referral_commissions (id $pk, referrer_id INT NOT NULL, referred_user_id INT NOT NULL, investment_id INT NOT NULL UNIQUE, amount DECIMAL(16,2) NOT NULL, rate DECIMAL(6,2) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'credited', created_at VARCHAR(30))$tail",
        "CREATE TABLE IF NOT EXISTS wallet_transactions (id $pk, user_id INT NOT NULL, investment_id INT, type VARCHAR(20) NOT NULL, amount DECIMAL(16,2) NOT NULL, description VARCHAR(255), created_at VARCHAR(30))$tail",
    ];
    foreach ($t as $sql) $pdo->exec($sql);
}

function seed_content(PDO $pdo): void {
    $ins = function (string $table, array $row) use ($pdo) {
        $cols = array_keys($row);
        $pdo->prepare("INSERT INTO $table (" . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($row));
    };

    // Sample plans (edit freely in Admin → Plans)
    $ins('plans', ['name' => 'Silver', 'tagline' => 'A smart start in rental real estate', 'min_amount' => 50000, 'max_amount' => 499999, 'roi' => 10, 'bonus' => 1, 'tenure_months' => 24,
        'payout_mode' => 'quarterly', 'target_amount' => 50000000, 'badge' => '', 'features' => "Quarterly return payouts\nPrincipal returned at maturity\nInvestment certificate (PDF)\nLive dashboard tracking", 'sort_order' => 1]);
    $ins('plans', ['name' => 'Gold', 'tagline' => 'Most popular · steady monthly income', 'min_amount' => 500000, 'max_amount' => 2499999, 'roi' => 12, 'bonus' => 1, 'tenure_months' => 24,
        'payout_mode' => 'monthly', 'target_amount' => 200000000, 'badge' => 'Most popular', 'features' => "Monthly return payouts\nPrincipal returned at maturity\nPriority investor support\nInvestment certificate (PDF)\nSite-visit invitation", 'sort_order' => 2]);
    $ins('plans', ['name' => 'Platinum', 'tagline' => 'For serious wealth builders', 'min_amount' => 2500000, 'max_amount' => 0, 'roi' => 14, 'bonus' => 1, 'tenure_months' => 24,
        'payout_mode' => 'monthly', 'target_amount' => 500000000, 'badge' => 'Best returns', 'features' => "Highest return rate\nMonthly return payouts\nDedicated relationship manager\nInvestment certificate (PDF)\nExclusive investor briefings", 'sort_order' => 3]);

    $ins('projects', ['title' => 'MIDC Rental Township — Phase 1', 'location' => 'MIDC Area, Maharashtra', 'status_label' => 'Under construction', 'image' => 'assets/img/project-tower.svg', 'progress' => 25, 'completion_date' => '2028-12-31',
        'summary' => 'A master-planned rental community with residential towers, commercial space and amenities, built next to an established industrial corridor with strong, continuous rental demand.',
        'highlights' => "Premium residential & commercial rental units\nWalking distance to MIDC industrial employers\nPlanned retail, schools & healthcare\nProfessional property management after completion", 'sort_order' => 1]);

    foreach ([
        ['feature', 'Strong rental demand', 'Thousands of employees in the surrounding industrial belt need quality rental housing — creating steady occupancy.', '🏢'],
        ['feature', 'Structured returns', 'Returns are defined upfront per plan and paid on a published schedule, with every instalment visible in your dashboard.', '📈'],
        ['feature', 'Fully documented', 'Every investment is recorded and a PDF certificate with your transaction ID is issued instantly.', '📄'],
        ['feature', 'Secure & private', 'KYC data is encrypted. You can verify your PAN & Aadhaar securely right from your dashboard.', '🔒'],
        ['feature', 'Track everything online', 'Live dashboard shows investments, upcoming payouts, certificates and project progress — 24×7.', '📊'],
        ['feature', 'Start small', 'Begin from a modest amount and add more investments whenever you are ready.', '🌱'],
        ['stat', 'Project size', '₹15,000 Cr', ''],
        ['stat', 'Completion year', '2028', ''],
        ['stat', 'Returns up to', '14% p.a.', ''],
        ['stat', 'Min. investment', '₹50,000', ''],
        ['how', 'Create your account', 'Sign up free with your email and mobile number.', '1'],
        ['how', 'Choose a plan & pay', 'Pick a plan and pay via UPI, bank transfer or online gateway.', '2'],
        ['how', 'Get your certificate', 'Receive your PDF certificate and transaction ID by email instantly.', '3'],
        ['how', 'Verify KYC', 'Submit PAN & Aadhaar securely from your dashboard.', '4'],
        ['how', 'Earn returns', 'Receive returns on schedule and principal at maturity.', '5'],
        ['timeline', 'Land acquired & approvals', 'All statutory approvals and title verification completed.', '2025'],
        ['timeline', 'Construction begins', 'Foundation and structural work commence on Phase 1.', '2026'],
        ['timeline', 'Structure complete', 'Towers topped out; finishing and amenities underway.', '2027'],
        ['timeline', 'Handover & rentals start', 'Project completed and rental operations begin.', '2028'],
        ['faq', 'Who can invest?', 'Any Indian resident aged 18+ with a valid PAN and Aadhaar can invest. Create an account and choose a plan.', ''],
        ['faq', 'How do I receive my returns?', 'Returns are paid to your registered bank account on the schedule shown in your plan (monthly / quarterly / at maturity). You can see every payout date in your dashboard.', ''],
        ['faq', 'When is my principal returned?', 'Your principal is returned at maturity, as per the plan tenure and the Terms & Conditions you accepted.', ''],
        ['faq', 'Is my investment documented?', 'Yes. As soon as your payment is confirmed you receive a PDF certificate and a transaction ID by email, and can download them any time from your dashboard.', ''],
        ['faq', 'Why do you need my PAN and Aadhaar?', 'KYC is required for compliance and to make sure payouts reach the right person. Your Aadhaar number is stored encrypted and only the last 4 digits are displayed.', ''],
        ['faq', 'Is the return guaranteed?', 'Returns are projected under the plan terms. Real-estate projects carry risk — please read the Terms & Conditions and offer documents carefully before investing.', ''],
        ['testimonial', 'Sample investor', 'Replace this with a real investor testimonial from Admin → Testimonials. The dashboard and certificate process was clear and professional.', 'Investor, Pune'],
    ] as $i => $r) {
        $isImg = in_array($r[0], ['feature']);
        if ($r[0] === 'stat') { $r[3] = $r[2]; $r[2] = ''; } // stats keep their value in "extra"
        $ins('items', ['type' => $r[0], 'title' => $r[1], 'body' => $r[2], 'extra' => $r[3], 'sort_order' => $i]);
    }

    foreach ([
        ['Signature towers', 'Artist impression — residential & commercial towers', 'assets/img/project-tower.svg'],
        ['Master-planned township', 'Artist impression — aerial layout with parks and amenities', 'assets/img/township-aerial.svg'],
        ['Landscaped gardens', 'Artist impression — green spaces and walking trails', 'assets/img/amenity-garden.svg'],
        ['Resort-style pool', 'Artist impression — clubhouse and pool deck', 'assets/img/amenity-pool.svg'],
        ['Modern interiors', 'Artist impression — bright, well-planned living spaces', 'assets/img/interior-living.svg'],
    ] as $i => $g) $ins('items', ['type' => 'gallery', 'title' => $g[0], 'extra' => $g[1], 'image' => $g[2], 'sort_order' => $i]);

    $terms = "<h2>1. Introduction</h2><p>These Terms &amp; Conditions govern your use of this website and any investment you make through it. By creating an account and investing you agree to them.</p>"
        . "<h2>2. Eligibility</h2><p>You must be an Indian resident aged 18 or above, with a valid PAN and Aadhaar, and legally able to enter a binding agreement.</p>"
        . "<h2>3. Investment & Returns</h2><p>Each investment plan specifies the investment amount, projected annual return, tenure, and payout schedule. Returns are projected, are paid on the published schedule, and the principal is returned at maturity as stated in the plan.</p>"
        . "<h2>4. Payments & Certificate</h2><p>Your investment is confirmed once the payment is received and verified. A PDF investment certificate and transaction ID are then issued to your registered email.</p>"
        . "<h2>5. KYC</h2><p>You agree to provide accurate PAN and Aadhaar details. Payouts may be withheld until KYC is approved.</p>"
        . "<h2>6. Risks</h2><p>Real-estate projects carry construction, market and regulatory risk. Please read all offer documents before investing.</p>"
        . "<h2>7. Contact</h2><p>For any questions, contact us using the details on the Contact section of the website.</p>"
        . "<p><em>Draft template — the company must have this reviewed by a qualified lawyer and replace it with its final terms from Admin → Pages.</em></p>";
    $privacy = "<h2>Information we collect</h2><p>Name, email, mobile number, address, PAN, Aadhaar (stored encrypted), bank details for payouts, and transaction records.</p>"
        . "<h2>How we use it</h2><p>To verify identity, process investments and payouts, issue certificates and communicate with you.</p>"
        . "<h2>Security</h2><p>Passwords are hashed, Aadhaar numbers are encrypted, uploaded KYC documents are stored privately and visible only to you and authorised administrators.</p>"
        . "<h2>Sharing</h2><p>We do not sell your data. We share it only with payment, banking and legal/regulatory partners where required.</p>"
        . "<p><em>Draft template — have it reviewed by a qualified lawyer.</em></p>";
    $ins('pages', ['slug' => 'terms', 'title' => 'Terms & Conditions', 'content' => $terms, 'updated_at' => now()]);
    $ins('pages', ['slug' => 'privacy', 'title' => 'Privacy Policy', 'content' => $privacy, 'updated_at' => now()]);
    $ins('pages', ['slug' => 'risk-disclosure', 'title' => 'Risk Disclosure', 'content' => '<p>Real-estate investments are subject to market, construction and regulatory risks. Projected returns are not guaranteed. Please read all offer documents and consult an independent financial adviser if needed.</p>', 'updated_at' => now()]);
}

function upgrade_schema(PDO $pdo): void {
    $mysql = db_is_mysql();
    $columns = ['users' => ['referral_code' => 'VARCHAR(32) NULL', 'referred_by' => 'INT NULL', 'wallet_balance' => 'DECIMAL(16,2) NOT NULL DEFAULT 0'], 'investments' => ['wallet_used' => 'DECIMAL(16,2) NOT NULL DEFAULT 0']];
    foreach ($columns as $table => $defs) foreach ($defs as $column => $definition) {
        if ($mysql) { $st = $pdo->prepare('SHOW COLUMNS FROM ' . $table . ' LIKE ?'); $st->execute([$column]); $exists = (bool)$st->fetch(); }
        else { $exists = false; foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll() as $info) if ($info['name'] === $column) { $exists = true; break; } }
        if (!$exists) {
            if (!$mysql && $column === 'referral_code') $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' TEXT');
            elseif (!$mysql && $column === 'referred_by') $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' INTEGER');
            elseif (!$mysql) $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' NUMERIC NOT NULL DEFAULT 0');
            else $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
        }
    }
}
