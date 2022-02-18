<?php
        if(!defined('WALLET_NAME'))
                throw new Exception('`WALLET_NAME` constant not defined');

        if(!defined('NODELEG'))
                throw new Exception('`NODELEG` constant not defined');

        if(!defined('FROM_MAIL'))
                throw new Exception('`FROM_MAIL` constant not defined');

        if(!defined('NODES_FILE'))
                throw new Exception('`NODES_FILE` constant not defined');

        if(!defined('WALLET_FILE'))
                throw new Exception('`WALLET_FILE` constant not defined');

        if(!defined('MHNET'))
                throw new Exception('`MHNET` constant not defined');

        if(!defined('MHDIV'))
                throw new Exception('`MHDIV` constant not defined');

        if(!defined('MHCRYPTO'))
                throw new Exception('`MHCRYPTO` constant not defined');

        if(!defined('SEND_SLEEP'))
                throw new Exception('`SEND_SLEEP` constant not defined');

        if(!defined('DAYSEC'))
                throw new Exception('`DAYSEC` constant not defined');

        if(!defined('MIN_DELEG'))
                throw new Exception('`MIN_DELEG` constant not defined');

        if(file_exists(NODES_FILE) == false)
                throw new Exception('`nodes` file not found');

        if(file_exists(WALLET_FILE) == false)
                throw new Exception('`wallet` file not found');

        if(floatval(phpversion()) < 7.1)
                throw new Exception('requirements PHP 7.1+, current ' . phpversion());

        if(extension_loaded('curl') == false)
                throw new Exception('php-curl extension not loaded ');

        if(in_array(MHNET, ['main', 'dev', 'test']) == false)
                throw new Exception('unsupported net value', 1);


        if(MHCRYPTO) {
                if(extension_loaded('mhcrypto') == false)
                        throw new Exception('mhcrypto extension not loaded');
        } else {
                if(extension_loaded('gmp') == false)
                        throw new Exception('php-gmp extension not loaded ');

                if(file_exists(SCRIPT_DIR . '/vendor/autoload.php') == false)
                        throw new Exception('`vendor/autoload.php` not found  in ' . SCRIPT_DIR);

                if(file_exists(SCRIPT_DIR . '/vendor/mdanter/ecc/src/EccFactory.php') == false)
                        throw new Exception('`mdanter/ecc` not found. Please run composer command `composer require mdanter/ecc:0.5.2`');

                require_once(SCRIPT_DIR  . '/vendor/autoload.php');
        }
