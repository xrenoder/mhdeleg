<?
//      /usr/local/bin/php /home/metahash/my_deleg/deleg_list.php

        $nodeAddress = '0x0037514ebf7fe64db8875a7a15755dc3e7ac1603ce126d78fa';

        define('ROOT_DIR', __DIR__);

        require_once(ROOT_DIR . "/config.inc");
        require_once(ROOT_DIR . "/loader.inc");

        $ecdsa = new Ecdsa();
        $crypto = new Crypto($ecdsa);
        $crypto->net = MHNET;

        $historyData = $crypto->fetchHistory($nodeAddress);

        while(!isset($historyData['result'])) {
                Base::log("FAIL Cannot get fetch history. Change torrent.");
                sleep(SEND_SLEEP);
                $crypto->cleanHosts('TORRENT');
                $historyData = $crypto->fetchHistory($nodeAddress);
        }

        $balanceData = $crypto->fetchBalance($nodeAddress);
        $nonce = (isset($balanceData['result']['count_spent'])) ? intval($balanceData['result']['count_spent']) + 1 : 1;
        $balance = $balanceData['result']['received'] - $balanceData['result']['spent'];

        while (!$balance) {
                Base::log("FAIL Cannot get balance. Change torrent.");
                sleep(SEND_SLEEP);
                $crypto->cleanHosts('TORRENT');

                $balanceData = $crypto->fetchBalance($nodeAddress);
                $nonce = (isset($balanceData['result']['count_spent'])) ? intval($balanceData['result']['count_spent']) + 1 : 1;
                $balance = $balanceData['result']['received'] - $balanceData['result']['spent'];
        }

        $delegData = array();

// delegations

        foreach($historyData['result'] as $txData) {
                if (!isset($txData['delegate_info'])) {
                        continue;
                }

                if ($txData['status'] != 'ok') {
                        continue;
                }

                $amount = $txData['delegate_info']['delegate'];
                $node = $txData['to'];

                if (!isset($delegData[$node])) {
                        $delegData[$node] = array();
                        $delegData[$node] = 0;
                }

                if ($txData['delegate_info']['isDelegate']) {
                        $delegData[$node] += $amount;
                } else  {
                        $delegData[$node] -= $amount;
                }
        }

        foreach($delegData as $node => $amount) {
                if (!$amount) {
                        continue;
                }

                $amount = $amount / MHDIV;

                echo $node . "\t\t" . $amount . "\n";
        }

        echo "This is all actual delegations\n";

