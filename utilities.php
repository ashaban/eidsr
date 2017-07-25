<?php
class utilities{
  function __construct($openhim_core_host,$openhim_core_user,$openhim_core_password) {
    $this->openhim_core_host = $openhim_core_host;
    $this->openhim_core_user = $openhim_core_user;
    $this->openhim_core_password = $openhim_core_password;
    $this->authUserMap = array();
  }

  public function authenticate() {
    $url = $this->openhim_core_host."/authenticate/".$this->openhim_core_user;
    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    $curl_out = curl_exec($curl);

    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($curl_out, 0, $header_size);
    $body = substr($curl_out, $header_size);

    if ($err = curl_errno($curl) ) {
      echo $err;
      return false;
    }
    if(strpos($header,"200") ===false) {
      error_log("User ".$this->openhim_core_user." Not Authorized");
      return false;
    }
    $body = json_decode($body,true);
    if(is_array($body) && array_key_exists("salt",$body)) {
      $this->authUserMap[$this->openhim_core_user] = $body["salt"];
    }
    else {
      error_log("Something went wrong during authentication");
      return false;
    }

    $output = curl_close($curl);
  }

  public function genAuthHeaders() {
    $salt = $this->authUserMap[$this->openhim_core_user];
    if($salt == "") {
      error_log($this->openhim_core_user." Is not authenticated");
      return false;
    }

    //creating token
    $now = date("D M j Y G:i:s T");
    $passhash = hash("sha512",$salt.$this->openhim_core_password);
    $token = hash("sha512",$passhash.$salt.$now);
    return array( "auth-username: $this->openhim_core_user",
                  "auth-ts: $now",
                  "auth-salt: $salt",
                  "auth-token: $token"
                );
  }

  public function updateTransaction($transactionId,$update=array()) {
    if(count($update) == 0) {
      error_log("Empty Update Passed for transaction ".$transactionId);
      //return false;
    }
    if(!$transactionId) {
      error_log("Empty transactionId passed");
      return false;
    }

    $timestamp = date("Y-m-d G:i:s");
    $update = array("status"=>"Failed","response"=>array("status"=>200,"headers"=>array("content-type"=>"application/json+openhim"),"timestamp"=> $timestamp,"body"=>"Natania"));
    $update = json_encode($update);
    $this->authenticate();
    $headers = $this->genAuthHeaders();
    array_push($headers,"content-type:application/json");
    $url = $this->openhim_core_host . '/transactions/' . $transactionId;
    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($curl, CURLOPT_HEADER, true);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($curl, CURLOPT_POSTFIELDS, $update);
    $curl_out = curl_exec($curl);
    print_r($curl_out);
    curl_close($curl);
  }

  public function buildOrchestration ($name, $beforeTimestamp, $method, $url, $requestBody, $statusCode,$responseHeaders, $responseBody) {
    $parsed_url = parse_url($url);
    $timestamp = date("Y-m-d G:i:s");
    $orchestration = array( "name"=>$name,
                            "request"=>array( "method"=>$method,
                                              "body"=>$requestBody,
                                              "timestamp"=>$beforeTimestamp,
                                              "path"=>$parsed_url["path"],
                                              "querystring"=>$parsed_url["query"]
                                            ),
                            "response"=>array("status"=>$statusCode,
                                              "headers"=>$responseHeaders,
                                              "body"=>$responseBody,
                                              "timestamp"=>$timestamp
                                             )
                         );
      return$orchestration;
  }
}

$util = new utilities("https://localhost:8080","root@openhim.org","testpassword");
$util->updateTransaction("59778b0f59ba5a08098d956f");
?>
