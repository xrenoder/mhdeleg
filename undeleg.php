<?
//      /usr/local/bin/php /home/metahash/my_deleg/undeleg.php

        define('ROOT_DIR', __DIR__);

        require_once(ROOT_DIR . "/config.inc");

        $undelegAddr = '0x00ed9f5497ffffaea2bd87b8bdfaea22c3d864f242f7a49fc2'; // Node01
        $undelegCount = 1;

        Base::lock(LOCK_FILE);
        Base::setLogs(LOG_FILE, ERR_FILE);

try {
        require_once(ROOT_DIR . "/checker.inc");

        $ecdsa = new Ecdsa();
        $crypto = new Crypto($ecdsa);
        $crypto->net = MHNET;

        if (!filesize(WALLET_FILE)) {
                Base::err("Empty wallet file");
        }

        $tmp = file(WALLET_FILE);

        if (count($tmp) < 2) {
                Base::err("Wrong wallet file");
        }

        $privateKey = trim($tmp[1]);

        if (!$privateKey) {
                Base::err("Empty wallet private key");
        }

        $okStr = "OK   ";
        $failStr = "FAIL ";
        $testStr = "TEST ";

        $publicKey = $ecdsa->privateToPublic($privateKey);
        $walletAddress = $ecdsa->getAddress($publicKey);

        for($i = 0; $i < $undelegCount; $i++) {
                $balanceData = $crypto->fetchBalance($walletAddress);
                $nonce = (isset($balanceData['result']['count_spent'])) ? intval($balanceData['result']['count_spent']) + 1 : 1;
                $balance = $balanceData['result']['received'] - $balanceData['result']['spent'];

                while (!$balance) {
                        Base::log("FAIL Cannot get balance. Change torrent.");
                        sleep(SEND_SLEEP);
                        $crypto->cleanHosts('TORRENT');

                        $balanceData = $crypto->fetchBalance($walletAddress);
                        $nonce = (isset($balanceData['result']['count_spent'])) ? intval($balanceData['result']['count_spent']) + 1 : 1;
                        $balance = $balanceData['result']['received'] - $balanceData['result']['spent'];
                }

//      make TX & send

                $statusStr = $okStr;

                $to = $undelegAddr;

                $dataStr = '{"method":"undelegate"}';

                $fee = 0;
                $data = str2hex($dataStr);

                $signText = $crypto->makeSign($to, strval("0"), strval($nonce), strval($fee), $data);
                $sign = $crypto->sign($signText, $privateKey);

                $notResolved = 1;

                while($notResolved) {
                        $notResolved = 0;

                        if (!NODELEG) {
                                $res = $crypto->sendTx($to, 0, $fee, $nonce, $data, $publicKey, $sign);
                        } else {
                                $res = array();
                                $res['result'] = 'ok';
                                $res['params'] = '5bda789dee2675b0029cc6c19aeeba7c01c64123492417f21d377f34dab3acbd';
                                $statusStr = $testStr;
                        }

                        if ($res['result'] != 'ok') {
                                Base::log(var_export($res, true));

                                $statusStr = $failStr;

                                Base::log($statusStr . "Undelegation from $to fail (can't send)");

                                continue;
                        }

                        $txPay = $res['params'];

                        if (!NODELEG) {
                                sleep(SEND_SLEEP);
                        }

                        $res = $crypto->getTx($txPay);

                        if (isset($res['error'])) {
                                $historyData = $crypto->fetchHistory($walletAddress);

                                while(!isset($historyData['result'])) {
                                        Base::log("FAIL Cannot get fetch history. Change torrent.");
                                        sleep(SEND_SLEEP);
                                        $crypto->cleanHosts('TORRENT');
                                        $historyData = $crypto->fetchHistory($walletAddress);
                                }

                                $res = $crypto->getTx($txPay);

                                if (isset($res['error'])) {
                                        Base::log($statusStr . "Undelegation from $to fail (not resolved '$txPay')");

                                        $notResolved = 1;
                                        $crypto->cleanHosts('PROXY');
                                        continue;
                                }
                        }

                        Base::log($statusStr . "Undelegation from $to suss ($txPay)");
                        echo "Undelegation from $to suss ($txPay) ($i $balance\n";
                }
        }
} catch(Exception $e) {
        Base::err($e->getMessage());
}

