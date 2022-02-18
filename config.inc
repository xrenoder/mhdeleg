<?
        define('WALLET_NAME', "DELEG OTH 01");
        define('NODELEG', false);

        define('FROM_MAIL', "xrenoder@gmail.com");

        define('NODES_FILE', ROOT_DIR . '/nodes');
        define('WALLET_FILE', ROOT_DIR . '/wallet');

        define('MHCRYPTO', true);

        define('MHNET', "main");                                        // test, dev, main
        define('MHDIV', 1000000);                                       // max coin division
        define('SEND_SLEEP', 10);
        define('DAYSEC', 86400);
        define('MIN_DELEG', 512000000);                                 // min delegation amount

        define('PARENT_DIR', ROOT_DIR . '/..');
        define('CLASS_DIR', PARENT_DIR . '/mhphplib');

        define('LOCK_FILE', ROOT_DIR . "/lock");
        define('LOG_FILE', ROOT_DIR . "/work.log");
        define('ERR_FILE', ROOT_DIR . "/work.err");

