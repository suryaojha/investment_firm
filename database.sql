-- Investment platform: MySQL / MariaDB schema + starter content
-- Import with phpMyAdmin (Import tab) into an EMPTY database, utf8mb4.
-- Default admin:  ID "admin"  /  password "admin"  (you are FORCED to change it at first login)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS settings (k VARCHAR(100) PRIMARY KEY, v TEXT);
CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150) NOT NULL, email VARCHAR(190) NOT NULL UNIQUE, phone VARCHAR(20), password VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'user', status VARCHAR(20) NOT NULL DEFAULT 'active',
            city VARCHAR(100), address TEXT, dob VARCHAR(20),
            pan VARCHAR(20), aadhaar_enc TEXT, aadhaar_last4 VARCHAR(4), pan_file VARCHAR(255), aadhaar_file VARCHAR(255),
            kyc_status VARCHAR(20) NOT NULL DEFAULT 'none', kyc_note TEXT, kyc_submitted_at VARCHAR(30),
            nominee_name VARCHAR(150), nominee_relation VARCHAR(60),
            bank_holder VARCHAR(150), bank_account VARCHAR(40), bank_ifsc VARCHAR(20),
            must_change_pw INT NOT NULL DEFAULT 0,
            reset_token VARCHAR(100), reset_expires VARCHAR(30), created_at VARCHAR(30), last_login VARCHAR(30)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS plans (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(120) NOT NULL, tagline VARCHAR(255), min_amount DECIMAL(16,2) NOT NULL DEFAULT 0, max_amount DECIMAL(16,2) NOT NULL DEFAULT 0,
            roi DECIMAL(6,2) NOT NULL DEFAULT 0, bonus DECIMAL(6,2) NOT NULL DEFAULT 0, tenure_months INT NOT NULL DEFAULT 24,
            payout_mode VARCHAR(20) NOT NULL DEFAULT 'quarterly', target_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
            badge VARCHAR(50), features TEXT, is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS projects (
            id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, location VARCHAR(200), summary TEXT, image VARCHAR(255), status_label VARCHAR(80),
            progress INT NOT NULL DEFAULT 0, completion_date VARCHAR(20), highlights TEXT, is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS investments (
            id INT AUTO_INCREMENT PRIMARY KEY, ref_no VARCHAR(40) NOT NULL UNIQUE, user_id INT NOT NULL, plan_id INT NOT NULL, amount DECIMAL(16,2) NOT NULL,
            roi DECIMAL(6,2) NOT NULL DEFAULT 0, tenure_months INT NOT NULL DEFAULT 24, payout_mode VARCHAR(20) NOT NULL DEFAULT 'quarterly',
            expected_return DECIMAL(16,2) NOT NULL DEFAULT 0, method VARCHAR(20), status VARCHAR(20) NOT NULL DEFAULT 'pending',
            gateway_order_id VARCHAR(100), gateway_payment_id VARCHAR(100), txn_id VARCHAR(100), proof_file VARCHAR(255), note TEXT,
            certificate_no VARCHAR(40), created_at VARCHAR(30), paid_at VARCHAR(30), maturity_date VARCHAR(20)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS payouts (
            id INT AUTO_INCREMENT PRIMARY KEY, investment_id INT NOT NULL, user_id INT NOT NULL, type VARCHAR(20) NOT NULL DEFAULT 'return', amount DECIMAL(16,2) NOT NULL,
            due_date VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL DEFAULT 'pending', paid_at VARCHAR(30), reference VARCHAR(100), note VARCHAR(255)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS videos (
            id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, description TEXT, source VARCHAR(20) NOT NULL DEFAULT 'youtube', url VARCHAR(500), file VARCHAR(255),
            thumb VARCHAR(255), is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS documents (
            id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(200) NOT NULL, description VARCHAR(500), file VARCHAR(255), category VARCHAR(60),
            is_public INT NOT NULL DEFAULT 1, is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS pages (
            id INT AUTO_INCREMENT PRIMARY KEY, slug VARCHAR(120) NOT NULL UNIQUE, title VARCHAR(200) NOT NULL, content TEXT, is_active INT NOT NULL DEFAULT 1, show_in_footer INT NOT NULL DEFAULT 1, updated_at VARCHAR(30)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS items (
            id INT AUTO_INCREMENT PRIMARY KEY, type VARCHAR(30) NOT NULL, title VARCHAR(255), body TEXT, extra VARCHAR(255), image VARCHAR(255), is_active INT NOT NULL DEFAULT 1, sort_order INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150), email VARCHAR(190), phone VARCHAR(30), message TEXT, is_read INT NOT NULL DEFAULT 0, created_at VARCHAR(30)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45), email VARCHAR(190), created_at VARCHAR(30)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS audit (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT, action VARCHAR(80), detail VARCHAR(500), ip VARCHAR(45), created_at VARCHAR(30)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO plans (name,tagline,min_amount,max_amount,roi,bonus,tenure_months,payout_mode,target_amount,badge,features,sort_order) VALUES ('Silver','A smart start in rental real estate',50000,499999,10,1,24,'quarterly',50000000,'','Quarterly return payouts
Principal returned at maturity
Investment certificate (PDF)
Live dashboard tracking',1);
INSERT INTO plans (name,tagline,min_amount,max_amount,roi,bonus,tenure_months,payout_mode,target_amount,badge,features,sort_order) VALUES ('Gold','Most popular · steady monthly income',500000,2499999,12,1,24,'monthly',200000000,'Most popular','Monthly return payouts
Principal returned at maturity
Priority investor support
Investment certificate (PDF)
Site-visit invitation',2);
INSERT INTO plans (name,tagline,min_amount,max_amount,roi,bonus,tenure_months,payout_mode,target_amount,badge,features,sort_order) VALUES ('Platinum','For serious wealth builders',2500000,0,14,1,24,'monthly',500000000,'Best returns','Highest return rate
Monthly return payouts
Dedicated relationship manager
Investment certificate (PDF)
Exclusive investor briefings',3);
INSERT INTO projects (title,location,status_label,image,progress,completion_date,summary,highlights,sort_order) VALUES ('MIDC Rental Township — Phase 1','MIDC Area, Maharashtra','Under construction','assets/img/project-tower.svg',25,'2028-12-31','A master-planned rental community with residential towers, commercial space and amenities, built next to an established industrial corridor with strong, continuous rental demand.','Premium residential & commercial rental units
Walking distance to MIDC industrial employers
Planned retail, schools & healthcare
Professional property management after completion',1);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('feature','Strong rental demand','Thousands of employees in the surrounding industrial belt need quality rental housing — creating steady occupancy.','🏢',0);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('feature','Structured returns','Returns are defined upfront per plan and paid on a published schedule, with every instalment visible in your dashboard.','📈',1);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('feature','Fully documented','Every investment is recorded and a PDF certificate with your transaction ID is issued instantly.','📄',2);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('feature','Secure & private','KYC data is encrypted. You can verify your PAN & Aadhaar securely right from your dashboard.','🔒',3);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('feature','Track everything online','Live dashboard shows investments, upcoming payouts, certificates and project progress — 24×7.','📊',4);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('feature','Start small','Begin from a modest amount and add more investments whenever you are ready.','🌱',5);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('stat','Project size','','₹15,000 Cr',6);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('stat','Completion year','','2028',7);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('stat','Returns up to','','14% p.a.',8);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('stat','Min. investment','','₹50,000',9);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('how','Create your account','Sign up free with your email and mobile number.','1',10);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('how','Choose a plan & pay','Pick a plan and pay via UPI, bank transfer or online gateway.','2',11);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('how','Get your certificate','Receive your PDF certificate and transaction ID by email instantly.','3',12);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('how','Verify KYC','Submit PAN & Aadhaar securely from your dashboard.','4',13);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('how','Earn returns','Receive returns on schedule and principal at maturity.','5',14);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('timeline','Land acquired & approvals','All statutory approvals and title verification completed.','2025',15);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('timeline','Construction begins','Foundation and structural work commence on Phase 1.','2026',16);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('timeline','Structure complete','Towers topped out; finishing and amenities underway.','2027',17);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('timeline','Handover & rentals start','Project completed and rental operations begin.','2028',18);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('faq','Who can invest?','Any Indian resident aged 18+ with a valid PAN and Aadhaar can invest. Create an account and choose a plan.','',19);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('faq','How do I receive my returns?','Returns are paid to your registered bank account on the schedule shown in your plan (monthly / quarterly / at maturity). You can see every payout date in your dashboard.','',20);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('faq','When is my principal returned?','Your principal is returned at maturity, as per the plan tenure and the Terms & Conditions you accepted.','',21);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('faq','Is my investment documented?','Yes. As soon as your payment is confirmed you receive a PDF certificate and a transaction ID by email, and can download them any time from your dashboard.','',22);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('faq','Why do you need my PAN and Aadhaar?','KYC is required for compliance and to make sure payouts reach the right person. Your Aadhaar number is stored encrypted and only the last 4 digits are displayed.','',23);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('faq','Is the return guaranteed?','Returns are projected under the plan terms. Real-estate projects carry risk — please read the Terms & Conditions and offer documents carefully before investing.','',24);
INSERT INTO items (type,title,body,extra,sort_order) VALUES ('testimonial','Sample investor','Replace this with a real investor testimonial from Admin → Testimonials. The dashboard and certificate process was clear and professional.','Investor, Pune',25);
INSERT INTO items (type,title,extra,image,sort_order) VALUES ('gallery','Signature towers','Artist impression — residential & commercial towers','assets/img/project-tower.svg',0);
INSERT INTO items (type,title,extra,image,sort_order) VALUES ('gallery','Master-planned township','Artist impression — aerial layout with parks and amenities','assets/img/township-aerial.svg',1);
INSERT INTO items (type,title,extra,image,sort_order) VALUES ('gallery','Landscaped gardens','Artist impression — green spaces and walking trails','assets/img/amenity-garden.svg',2);
INSERT INTO items (type,title,extra,image,sort_order) VALUES ('gallery','Resort-style pool','Artist impression — clubhouse and pool deck','assets/img/amenity-pool.svg',3);
INSERT INTO items (type,title,extra,image,sort_order) VALUES ('gallery','Modern interiors','Artist impression — bright, well-planned living spaces','assets/img/interior-living.svg',4);
INSERT INTO pages (slug,title,content,updated_at) VALUES ('terms','Terms & Conditions','<h2>1. Introduction</h2><p>These Terms &amp; Conditions govern your use of this website and any investment you make through it. By creating an account and investing you agree to them.</p><h2>2. Eligibility</h2><p>You must be an Indian resident aged 18 or above, with a valid PAN and Aadhaar, and legally able to enter a binding agreement.</p><h2>3. Investment & Returns</h2><p>Each investment plan specifies the investment amount, projected annual return, tenure, and payout schedule. Returns are projected, are paid on the published schedule, and the principal is returned at maturity as stated in the plan.</p><h2>4. Payments & Certificate</h2><p>Your investment is confirmed once the payment is received and verified. A PDF investment certificate and transaction ID are then issued to your registered email.</p><h2>5. KYC</h2><p>You agree to provide accurate PAN and Aadhaar details. Payouts may be withheld until KYC is approved.</p><h2>6. Risks</h2><p>Real-estate projects carry construction, market and regulatory risk. Please read all offer documents before investing.</p><h2>7. Contact</h2><p>For any questions, contact us using the details on the Contact section of the website.</p><p><em>Draft template — the company must have this reviewed by a qualified lawyer and replace it with its final terms from Admin → Pages.</em></p>','2026-11-14 00:01:04');
INSERT INTO pages (slug,title,content,updated_at) VALUES ('privacy','Privacy Policy','<h2>Information we collect</h2><p>Name, email, mobile number, address, PAN, Aadhaar (stored encrypted), bank details for payouts, and transaction records.</p><h2>How we use it</h2><p>To verify identity, process investments and payouts, issue certificates and communicate with you.</p><h2>Security</h2><p>Passwords are hashed, Aadhaar numbers are encrypted, uploaded KYC documents are stored privately and visible only to you and authorised administrators.</p><h2>Sharing</h2><p>We do not sell your data. We share it only with payment, banking and legal/regulatory partners where required.</p><p><em>Draft template — have it reviewed by a qualified lawyer.</em></p>','2026-11-14 00:01:04');
INSERT INTO pages (slug,title,content,updated_at) VALUES ('risk-disclosure','Risk Disclosure','<p>Real-estate investments are subject to market, construction and regulatory risks. Projected returns are not guaranteed. Please read all offer documents and consult an independent financial adviser if needed.</p>','2026-11-14 00:01:04');

INSERT INTO users (name,email,phone,password,role,status,kyc_status,must_change_pw,created_at) VALUES ('Admin','admin','','$2y$10$mHL0rZ/0EQs3R2gAbaGWje.QVQgFiyPc9X.30S1tCRJ5F4ftoqiUu','admin','active','approved',1,'2026-11-14 00:01:04');
INSERT INTO settings (k,v) VALUES ('site_name','Aurum Estates');
INSERT INTO settings (k,v) VALUES ('contact_email','admin@example.com');
INSERT INTO settings (k,v) VALUES ('notify_email','admin@example.com');

SET FOREIGN_KEY_CHECKS = 1;
