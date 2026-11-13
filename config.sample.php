<?php
// Manual setup (instead of install.php):
// 1. Import database.sql into an empty MySQL database.
// 2. Copy this file to config.local.php and fill in the values below.
// 3. Replace app_key with a random 64-character hex string (e.g. run: php -r "echo bin2hex(random_bytes(32));").
//    Keep it secret and backed up — it encrypts Aadhaar numbers. Never change it after real data exists.
return [
    'db_driver' => 'mysql',
    'db_host'   => 'localhost',
    'db_port'   => 3306,
    'db_name'   => 'your_database',
    'db_user'   => 'your_db_user',
    'db_pass'   => 'your_db_password',
    'app_key'   => 'REPLACE_WITH_64_HEX_CHARACTERS',
    'debug'     => false,
];
