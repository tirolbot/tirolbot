<?php
require_once __DIR__.'/vendor/autoload.php';
use Symfony\Component\HttpFoundation\Request;
date_default_timezone_set('Asia/Tokyo');
$app = new Silex\Application();
$app->post('/callback', function (Request $request) use ($app) {
    $body = json_decode($request->getContent(), true);
    foreach ($body['result'] as $msg) {
        //fromとメッセージを取得a
        $from = $msg['content']['from'];
        $message = $msg['content']['text'];
        //Redisからcontextを取得
        $redis = new Predis\Client(getenv('REDIS_URL'));
        $context = $redis->get($from);
        //雑談対話APIを叩く
        $response = dialogue($message, $context);
        //contextをRedisに保存する
        $redis->set($from, $response->context);
        //LINEに返信
        $post_data = [
            "to" => [
                $from
            ],
            "toChannel" => "1383378250",
            "eventType" => "138311608800106203",
            "content" => [
                "contentType" => 1,
                "toType" => 1,
                "text" => $response->utt
            ]
        ];
        $ch = curl_init("https://trialbot-api.line.me/v1/events");
        curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
        curl_setopt($ch, CURLOPT_PROXY, getenv('http://fixie:MXV77a8kMFHYAPf@velodrome.usefixie.com:80'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json; charser=UTF-8",
            "X-Line-ChannelID: ". getenv('1474893192'),
            "X-Line-ChannelSecret: ". getenv('054b381e4134feb2421543f0fd31d67d'),
            "X-Line-Trusted-User-With-ACL: ". getenv('u5fd9e9941087e0b663a55ac64e255ccf')
        ]);
        curl_exec($ch);
        curl_close($ch);
    }

    return 0;
});

function dialogue($message, $context) {
    $post_data = array('utt' => $message);
    $post_data['context'] = $context;
    // DOCOMOに送信
    $ch = curl_init("https://api.apigw.smt.docomo.ne.jp/dialogue/v1/dialogue?APIKEY=". getenv('7a7949696d525668572f55355251503774314e2f71664e4b474458632f6447554f6c785264385055324641'));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json; charser=UTF-8"
    ]);
    $result = curl_exec($ch);
    curl_close($ch);
    return json_decode($result);
}

$app->run();
