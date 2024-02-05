<?php
//USAGE: run as a cron script at every hour
//run with "week" or "month" argument to send data summary to telegram channel

ini_set('default_socket_timeout', 15);

$tg_bot_token = "";
$tg_conversation_id = "";

$meters['shellyplug1'] = "192.168.1.108";
$meters['shellyplug2'] = "192.168.1.111";
$meters['shellyplug3']="192.168.1.106";

//price per kwn
$price=1;

//currency
$currency="EUR";

$workdir = dirname(__FILE__) . "/villanyora/";
if (!file_exists($workdir)) {
    mkdir($workdir);
}

$data = array();
foreach ($meters as $k => $m) {
    echo "Meter: $m - $k:\n";
    $kwh = get_meter($m, $workdir);
    echo "Current value: $kwh kwh\n";
    $data[$k] = $kwh;
}
echo "\n";

$cdate = $workdir . date("Y-m");

if (!file_exists($cdate)) {
    mkdir($cdate);
}

file_put_contents($cdate . "/meters_" . date("d_H") . ".json", json_encode($data));

$msg = "";

$period = strtotime("-1 month", time());

if (!empty($argv[1]) && $argv[1] == "week") {

    $period = strtotime("-7 days", time());
    $msg = "Querying last week!\n";
}

$day = date("d", $period);
$hour = date("H", $period);

if (!empty($argv[1]) && ($argv[1] == "week" || $argv[1] == "month")) {

    $msg .= "Meters:\n";
    $msg .= print_r($meters, true) . "\n";

    $lm = file_get_contents($workdir . date("Y-m", $period) . "/meters_" . $day . "_$hour.json");
    $lmd = json_decode($lm, true);

    if (empty($lmd)) {
        $msg .= "Missing last period data...\n";
    } else {
        $msg .= "Found last data@" . ($workdir . date("Y-m", $period) . "/meters_" . $day . "_$hour.json") . ": " . print_r($lmd, true) . "\n";
    }


    $msg .= "Current data: " . print_r($data, true) . "\n";

    $r = file_get_contents("https://open.er-api.com/v6/latest/USD");
    $rt = json_decode($r, true);
    if (empty($rt['rates'][$currency])) {
        $msg .= "Missing coversion rate info...\n";
    };

    foreach ($meters as $k => $m) {

        if (!empty($lmd[$k])) {

            $monthly = $data[$k] - $lmd[$k];
            $msg .= "$k => timeperiod(" . date("Y-m-d", $period) . " - now()): " . number_format($data[$k], 2) . "-" . number_format($lmd[$k], 2) . " = " . number_format($monthly, 2) . " (kwh)\n";
            $msg .= "$currency => " . number_format($monthly * $price, 0, "", "") . "\n";
            if (!empty($rt['rates'][$currency])) {
                $msg .= "USD => " . number_format(($monthly * $price) / $rt['rates'][$currency], 0);
            }
            $msg .= "\n\n";
        }
    }
}
echo "OUTPUT: $msg \n";
if (!empty($msg))
    send_message($msg);

function get_meter($url, $workdir) {

    $rs = file_get_contents("http://" . $url . "/status");
    $rj = json_decode($rs, true);

    if (!isset($rj['meters'][0]['total'])) {
        echo "Meter query failed.\n";
        return 0;
    }
    //watt min to kwh
    $counter_wm = $rj['meters'][0]['total'];
    $counter_kwh = $counter_wm * 0.0000166666667;

    echo "Meter value: $counter_kwh\n";

    $sv = get_stored_value($workdir . $url);

    echo "Read stored total value: $sv\n";

    $sl = get_stored_last($workdir . $url);

    echo "Read last value: $sl\n";

    if ($counter_kwh < $sl) {  //aktualis ertek kisebb mint az elozo -> hozzadjuk a totalhoz
        
        set_stored_value($workdir . $url, $sl + $sv, $counter_kwh);
        return $counter_kwh + $sv;
    } else {
        
        set_stored_value($workdir . $url, $sv, $counter_kwh);
        return $counter_kwh + $sv;
    }
}

function set_stored_value($azon, $val, $last) {
    echo "store: " . json_encode(array("total" => $val, "last" => $last)) . "\n";
    file_put_contents("$azon.json", json_encode(array("total" => $val, "last" => $last)));
}

function get_stored_last($azon) {
    $js = file_get_contents("$azon.json");
    $data = json_decode($js, true);
    if (!empty($data['last'])) {
        return $data['last'];
    }
    return 0;
}

function get_stored_value($azon) {

    $js = file_get_contents("$azon.json");
    $data = json_decode($js, true);
    if (!empty($data['total'])) {
        return $data['total'];
    }
    return 0;
}

function send_message($msg) {
    global $tg_bot_token, $tg_conversation_id;
    
    file_get_contents("https://api.telegram.org/bot" . urlencode($tg_bot_token) . "/sendMessage?chat_id=" . urlencode($tg_conversation_id) . "&text=" . urlencode($msg));
}
