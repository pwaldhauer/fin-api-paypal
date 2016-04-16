<?php

date_default_timezone_set('UTC');

$config = [];
$params = ['username', 'password', 'signature', 'url', 'token'];
foreach ($params as $param) {
    if (empty($_ENV[strtoupper($param)])) {
        die('You need to specify ' . $param . PHP_EOL);
    }

    $config[$param] = $_ENV[strtoupper($param)];
}

$start = date('Y-m-d\TH:i:s\Z', time() - 86400);
$end = date('Y-m-d\TH:i:s\Z');

print_r($config);

$info = 'USER=' . $config['username']
    . '&PWD=' . $config['password']
    . '&SIGNATURE=' . $config['signature']
    . '&METHOD=TransactionSearch'
    . '&STARTDATE=' . $start
    . '&ENDDATE=' . $end
    . '&VERSION=94';

$mapping = [
    'L_TIMESTAMP'     => 'timestamp',
    'L_TYPE'          => 'type',
    'L_EMAIL'         => 'email',
    'L_NAME'          => 'name',
    'L_TRANSACTIONID' => 'transaction_id',
    'L_STATUS'        => 'status',
    'L_CURRENCYCODE'  => 'currency',
    'L_FEEAMT'        => 'fee_amount',
    'L_NETAMT'        => 'net_amount'
];

$returned_array = [];
$curl = curl_init('https://api-3t.paypal.com/nvp');
curl_setopt($curl, CURLOPT_FAILONERROR, true);
curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

curl_setopt($curl, CURLOPT_POSTFIELDS, $info);
curl_setopt($curl, CURLOPT_HEADER, 0);
curl_setopt($curl, CURLOPT_POST, 1);

$result = curl_exec($curl);

$result = explode("&", $result);

$temp = [];
foreach ($result as $value) {
    $value = explode("=", $value);
    $temp[$value[0]] = $value[1];
}

for ($i = 0; $i < count($temp) / 11; $i++) {
    $arr = [];

    foreach ($mapping as $key => $val) {
        if (!isset($temp[$key . $i])) {
            continue;
        }

        $arr[$val] = urldecode($temp[$key . $i]);
    }

    $returned_array[$i] = $arr;
}


foreach ($returned_array as $item) {

    if (empty($item)) {
        continue;
    }

    if ($item['type'] === 'Currency Conversion (debit)' || $item['type'] === 'Currency Conversion (credit)') {
        continue;
    }

    $date = strtotime($item['timestamp']);
    $value = floatval($item['net_amount']);
    $text = sprintf('%s: %s (%s) #%s', $item['type'], $item['name'], $item['email'], $item['transaction_id']);

    if ($item['currency'] !== 'EUR') {
        $value = convert_to_eur($value);
    }

    $params = [
        'account_id'    => 5,
        'booked_at'     => date('Y-m-d H:i:s', $date),
        'value'         => $value * 100,
        'original_text' => $text,
        'data'          => $item
    ];

    print_r($params);

    $curl = curl_init($config['url']);
    curl_setopt($curl, CURLOPT_FAILONERROR, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-Auth-Token: ' . $config['token']
    ]);

    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($curl, CURLOPT_HEADER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);

    $result = curl_exec($curl);
    var_dump($result);
}


function convert_to_eur($value)
{
    $file = 'currency.json';
    $data = '';
    if (!file_exists($file) || filemtime($file) < (time() - 86400)) {
        $data = file_get_contents('http://api.fixer.io/latest?base=USD');
        file_put_contents($file, $data);
    } else {
        $data = file_get_contents($file);
    }

    $data = json_decode($data, true);

    return $value * floatval($data['rates']['EUR']);
}
