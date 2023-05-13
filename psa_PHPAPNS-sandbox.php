<?php
require("psa_phpMQTT.php");

$NODE_ENV = 'development-phase2'; // Set this the same as node env

$MQTT_HOST = '13.215.76.4';
$MQTT_PORT = '1883';
$MQTT_CLIENT_ID = 'triet_apn'; // Make sure this is unique

$APN_TOPIC = "/mydpa/server/$NODE_ENV/apn_notification";

$APN_CERTIFICATES = json_decode('{
	"mdpa": "Production_Certificates.pem",
	"pass": "AuditSandboxAdhoc.pem",
	"pals": "palsSandboxAdhoc.pem"
}');

$APN_CERTIFICATE = $APN_CERTIFICATES->mdpa;
echo $APN_CERTIFICATE;
// $APN_SSL='ssl://gateway.push.apple.com:2195';
// $APN_FEEDBACK = 'ssl://feedback.push.apple.com:2196';
// $APN_SSL='api.push.apple.com:443';
$APN_SSL = 'api.push.apple.com:443';
$APN_FEEDBACK = 'ssl://feedback.sandbox.push.apple.com:2196';
$APNS_URL = 'https://api.push.apple.com/3/device/';
$APNS_TOPIC = 'com.2359media.mydigitalpa.staging';

$mqtt = new phpMQTT($MQTT_HOST, $MQTT_PORT, $MQTT_CLIENT_ID);

retryConnect:

try {
        // If unable to connect
        if(!$mqtt->connect()){
                echo "\nRetry after 10 seconds\n";
        sleep(10);
        goto retryConnect;
        }

	error_log("----------");
        echo "\nConnected!\n";
        $topics[$APN_TOPIC] = array("qos"=>0, "function"=>"processMessage");
        $mqtt->subscribe($topics,2);

        echo "\nSubscribed to $APN_TOPIC \n";
        $lostConnectionCounter=1;

    retryPing:

    while($mqtt->proc()) {
        $lostConnectionCounter=1;
    }

    $lostConnectionCounter = $lostConnectionCounter + 1;

    if ($lostConnectionCounter < 100) {
        goto retryPing;
    }
} catch (Exception $e){
        echo 'Caught exception: ',  $e->getMessage(), "\n\n";
        echo "\nRetry after 10 seconds\n";
        sleep(10);
        goto retryConnect;
}

$mqtt->close();
goto retryConnect;

function processMessage($topic, $rawMessage){
  global $APN_CERTIFICATES, $APN_CERTIFICATE;
    // Message coming in
        date_default_timezone_set('Asia/Singapore');
        echo "\n\nMsg Received: ".date("r")."\nTopic: {$topic}\n$Raw message: $rawMessage\n";
		error_log("Msg Received: ".date("r")."\nTopic: {$topic}\n$Raw message: $rawMessage\n");

        $message = json_decode($rawMessage, true);

        $token = $message['token'];
        $title = $message['title'];
        $payload = $message['payload'];
        $expiry = $message['expiry'];
        $app = $message['app'];

        if( $app == "pass") {
          $APN_CERTIFICATE = $APN_CERTIFICATES->pass;
        }else if($app == "pals") {
          $APN_CERTIFICATE = $APN_CERTIFICATES->pals;
        }else{
           $APN_CERTIFICATE = $APN_CERTIFICATES->mdpa;
        }
        echo "Certificate: $APN_CERTIFICATE\n";
        $notification = array(
            'aps' => array(
                'alert' => $title,
                'payload' => $payload ? $payload : array()
            ),
        );
        $encodedNotification = json_encode($notification, JSON_FORCE_OBJECT);
        echo $encodedNotification;

        // pushMessage($encodedNotification, $token);
		if(defined('CURL_HTTP_VERSION_2_0')){
			pushMessageCurl($encodedNotification, $token);
		}
		else{
			pushMessage($encodedNotification, $token);	
		}
        echo "\n\n".memory_get_usage().''.' bytes'."\n\n";
}

function pushMessageCurl($message, $device_token){
	echo "\n -------pushMessageCurl-------- \n";
	global $APN_CERTIFICATE, $APNS_URL, $APNS_TOPIC;
	echo "APN_CERTIFICATE: $APN_CERTIFICATE, APNS_URL: $APNS_URL, APNS_TOPIC: $APNS_TOPIC \n";
	echo "device_token: $device_token \n";
	echo "message: $message \n";
	$ch = curl_init($APNS_URL . $device_token);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $message);
	curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_2_0);
	curl_setopt($ch, CURLOPT_HTTPHEADER, array("apns-topic: $APNS_TOPIC"));
	curl_setopt($ch, CURLOPT_SSLCERT, $APN_CERTIFICATE);
	$response = curl_exec($ch);
	$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

	echo "httpcode: $httpcode \n";
	var_dump($response);
	//On successful response you should get true in the response and a status code of 200
	//A list of responses and status codes is available at 
	//https://developer.apple.com/library/ios/documentation/NetworkingInternet/Conceptual/RemoteNotificationsPG/Chapters/TheNotificationPayload.html#//apple_ref/doc/uid/TP40008194-CH107-SW1
}

function pushMessage($message, $token){
        // Connect to apple push server

        $ctx = stream_context_create();

	error_log("-------pushmessage--------\n");
	

        global $APN_CERTIFICATE, $APN_SSL;
        error_log($APN_SSL."\n");
        error_log($APN_CERTIFICATE."\n");

        // Set settings
        stream_context_set_option($ctx, 'ssl', 'local_cert', $APN_CERTIFICATE);

        $fp = stream_socket_client($APN_SSL, $error, $errorString, 60, STREAM_CLIENT_CONNECT, $ctx);

        if (!$fp) {
                echo "Failed to connect to APNS server: {$error} {$errorString}";
        } else {

                $msg = chr(0).pack("n",32).pack('H*', $token).pack("n", strlen($message)).$message;
                echo "11111";
                var_dump($message);
                var_dump($msg);

                echo "2222";
                $fwrite = fwrite($fp, $msg, strlen($msg));
                echo $msg;
                echo "~~~~~!!";
                if (!$fwrite) {
                        echo "ERROR: Failed writing to stream.";
                } else {
                        echo "\n\nPush Successful";
                }
        }

        fclose($fp);
        checkFeedback($message);
}

function checkFeedback($message){
        $ctx = stream_context_create();

        global $APN_CERTIFICATE, $APN_FEEDBACK, $mqtt;

        stream_context_set_option($ctx, 'ssl', 'local_cert', $APN_CERTIFICATE);

        stream_context_set_option($ctx, 'ssl', 'verify_peer', false);
        $fp = stream_socket_client($APN_FEEDBACK, $error, $errorString, 60, STREAM_CLIENT_CONNECT, $ctx);

        if (!$fp) {
                echo "NOTICE: Failed to connect to device : {$error} {$errorString}.";
        }
        echo "checkFeedback===";
        echo "_".$fp."_";
        var_dump(fread($fp, 38));
        while ($devcon = fread($fp, 38)) {
                echo "@@@@@@@!!!!"; 
         $arr = unpack("H*", $devcon);
         $rawhex = trim(implode("", $arr));
         $token = substr($rawhex, 12, 64);
         var_dump($token);

         if (!empty($token)) {
              echo "NOTICE: Unregistering Device Token:". $token;
         }
    }

    fclose($fp);
}
?>