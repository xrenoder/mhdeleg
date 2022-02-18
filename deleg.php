<?
        define('ROOT_DIR', __DIR__);

        require_once(ROOT_DIR . "/config.inc");

//      /usr/local/bin/php /home/metahash/my_deleg/deleg.php

        Base::lock(LOCK_FILE);
        Base::setLogs(LOG_FILE, ERR_FILE);

try {
        require_once(ROOT_DIR . "/checker.inc");

        $now = time();
        $today = floor($now/DAYSEC) * DAYSEC;

        $todayDate = gmdate("Y-M-d", $today);

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

        $mail = trim($tmp[0]);

        if (!$mail) {
                Base::err("Empty mail");
        }

        $privateKey = trim($tmp[1]);

        if (!$privateKey) {
                Base::err("Empty wallet private key");
        }

        if (!filesize(NODES_FILE)) {
                Base::err("Empty nodes file");
        }

        $tmp = file(NODES_FILE);

        $nodes = array();

        $strNum = 0;

        foreach($tmp as $node) {
                $strNum++;
                $data = explode("\t", $node);

                if (count($data) < 4) {
                        Base::err("Wrong node format in string $strNum " . count($data));
                }

                $addr = trim($data[1]);

                $nodes[$addr] = array();
                $nodes[$addr]['name'] = trim($data[0]);
                $nodes[$addr]['hardcap'] = trim($data[2]);
                $nodes[$addr]['frozen'] = trim($data[3]);
                $nodes[$addr]['delegated'] = 0;
                $nodes[$addr]['free'] = 0;
                $nodes[$addr]['amount'] = 0;
                $nodes[$addr]['paid'] = 0;
                $nodes[$addr]['tx'] = '';
                $nodes[$addr]['desc'] = '';
                $nodes[$addr]['problem'] = '';
        }

        $publicKey = $ecdsa->privateToPublic($privateKey);
        $walletAddress = $ecdsa->getAddress($publicKey);

        $okStr = "OK   ";
        $failStr = "FAIL ";
        $testStr = "TEST ";

        $sumFree = 0;
        $sumDeleg = 0;
        $sumPaid = 0;
        $endBalance = 0;
        $problems = '';

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

        $startBalance = $balance;

        foreach($nodes as $addr => $val) {
                if ($balance < MHDIV) {
                        break;
                }

                if ($balance < MIN_DELEG) {
                        Base::log("Wallet have less than " . MIN_DELEG);
                        break;
                }

                $nodeName = $nodes[$addr]['name'];

                $nodeData = $crypto->fetchBalance($addr);
                $delegated = $nodeData['result']['delegated'] - $nodeData['result']['undelegated'];

                while (!$delegated) {
                        Base::log("FAIL Cannot get delegations. Change torrent.");
                        sleep(SEND_SLEEP);
                        $crypto->cleanHosts('TORRENT');

                        $nodeData = $crypto->fetchBalance($addr);
                        $delegated = $nodeData['result']['delegated'] - $nodeData['result']['undelegated'];
                }

                $nodes[$addr]['delegated'] = $delegated;

//              $freeForDeleg = $nodes[$addr]['hardcap'] - $nodes[$addr]['frozen'] - $delegated;
                $freeForDeleg = $nodes[$addr]['hardcap'] - $delegated;

                $nodes[$addr]['free'] = $freeForDeleg;

                if ($freeForDeleg < MIN_DELEG) {
                        Base::log("Free space on node $nodeName less than " . MIN_DELEG);
                        continue;
                }

                if ($balance <= $freeForDeleg) {
                        $amount = intval((floor($balance / MHDIV) * MHDIV));
                } else {
                        $amount = intval((floor($freeForDeleg / MHDIV) * MHDIV));
                }

//              $amount = 512000000;

                $nodes[$addr]['amount'] = $amount;

                $nodes[$addr]['desc'] .= " delegated " . ($amount / MHDIV) . " to $nodeName";

                $sumDeleg += $amount;

//      make TX & send

                $statusStr = $okStr;

                $to = $addr;

//              $data = array("method" => "delegate", "params" => ["value" => "$amount"]);
//              $dataStr = json_encode($data, JSON_PRETTY_PRINT);

                $dataStr = '{"method":"delegate","params":{"value":"' . $amount . '"}}';

                if (NODELEG) {
                        Base::log("TX   " . $dataStr);
                }

//              $fee = strlen($dataStr);
                $fee = 0;
                $data = str2hex($dataStr);

                if (!NODELEG && $balance < ($amount + $fee)) {
                        if ($balance < $amount) {
                                $statusStr = $failStr;
                                $nodes[$addr]['problem'] = "Not enough funds for delegation " . ($amount / MHDIV) . " to $nodeName";
                                $problems .= "\n" . $nodes[$addr]['problem'];

                                Base::log($statusStr . "Delegation $amount to $nodeName fail (no funds)");

                                continue;
                        }

                        $data = '';
                        $fee = 0;
                }

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

                                $nodes[$addr]['problem'] = "Delegation to $nodeName problem (" . $res['url'] . "): \n" . var_export($res, true);
                                $problems .= "\n" . $nodes[$addr]['problem'];

                                $statusStr = $failStr;

                                Base::log($statusStr . "Delegation $amount to $nodeName fail (can't send)");

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
                                        Base::log($statusStr . "Delegation $amount to $nodeName fail (not resolved '$txPay')");

//                                      $dataPay[$partner]['problem'] = "Sending to '$partner' from $node problem (" . $res['url'] . "): transaction '$txPay' not resolved";
//                                      $problems .= "\n" . $dataPay[$partner]['problem'];
//                                      $statusStr = $failStr;

                                        $notResolved = 1;
                                        $crypto->cleanHosts('PROXY');
                                        continue;
                                }
                        }

                        Base::log($statusStr . "Delegation $amount to $nodeName suss ($txPay)");

                        $nodes[$addr]['tx'] = $txPay;
                        $nodes[$addr]['desc'] .= " Tx ID: $txPay\n";

                        if (!NODELEG) {
                                $nodes[$addr]['paid'] += $amount;
                                $nodes[$addr]['free'] = $freeForDeleg - $amount;
                                $sumPaid += $amount;
                        }

                        $sumFree += $nodes[$addr]['free'];

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
                }
        }

        $endBalance = $balance;

        if (!$problems) {
                $problems = "NO PROBLEMS";
        }

// mail

        Mail::init(FROM_MAIL);
        Mail::setContentType(Mail::TYPE_PLAIN);
        $subjPrefix = WALLET_NAME . " $todayDate delegation ";

        $reportStr = "Report for " . $todayDate;
        $reportStr .= "\nWallet: " . WALLET_NAME . " ($walletAddress)";
        $reportStr .= "\nProblems: " . $problems;
        $reportStr .= "\nStart wallet balance: " . ($startBalance / MHDIV);
        $reportStr .= "\nDelegation amount: " . ($sumDeleg / MHDIV);
        $reportStr .= "\nDelegated: " . ($sumPaid / MHDIV);
        $reportStr .= "\nFinal wallet balance: " . ($endBalance / MHDIV);
        $reportStr .= "\nFree space on nodes: " . ($sumFree / MHDIV);

        $reportStr .= "\n\nDUMP: \n" . var_export($nodes, true);

        Mail::send($mail, $subjPrefix . $sumDeleg , $reportStr);
        Base::log("Mail sended to $mail");

        Base::end();

} catch(Exception $e) {
        Base::err($e->getMessage());
}

