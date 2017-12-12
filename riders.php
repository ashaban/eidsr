<?php
require("eidsr_base.php");

class riders extends eidsr_base {
  function __construct( $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd,$samples,$reporter_rp_id,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                      ) {
    parent::__construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                       );

    $this->samples = $samples;
    $this->reporter_rp_id = $reporter_rp_id;
    $this->orchestrations = array();
    $this->response_body = array();
    $this->transaction_status = "Successful";
    //this variable holds transactionId that submitted this message to openHIM
    $this->openHimTransactionID = $openHimTransactionID;
    //this variable will holds all the transactionIds for every picked/delivered samples,in case an sms come in format picked.idsrid1.idsrid2
    $this->openHimTransactionIDs = array();
  }

  public function sample_action($action) {
    $samples = str_ireplace("testpicked","",$this->samples);
    $samples = str_ireplace("picked","",$samples);
    $samples = str_ireplace("testdelivered","",$samples);
    $samples = str_ireplace("delivered","",$samples);
    $samples = explode(".",$samples);
    $lab = "";
    if($action == "sample_delivered") {
      $lab = end($samples);
      $test_lab = explode("-",$lab);
      $lab = "";
      reset($samples);
      if(count($test_lab) == 1) {
        $lab = end($samples);
        unset($samples[count($samples)-1]);
      }
      reset($samples);
    }
    error_log(print_r($samples,true));

    if(count($samples) == 0){
      $extra = '"status":"incomplete"';
      array_push($this->response_body,array("Riders ".$action=>"Case ID Not Included On The Message,Rider To Resend"));
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
      return false;
    }

    if($action == "sample_delivered") {
      $status = "delivered";
    }
    else if($action == "sample_picked") {
      $status = "picked";
    }

    $reported_cases = (new MongoDB\Client)->eidsr->case_details;
    foreach($samples as $sample) {
      if($sample == "")
      continue;
      $sample = strtoupper(trim($sample));
      $case = $reported_cases->findOne(['idsr_id' => $sample]);
      if($case) {
        if($action == "sample_picked")
        $collection = (new MongoDB\Client)->eidsr->picked_samples;
        else if($action == "sample_delivered")
        $collection = (new MongoDB\Client)->eidsr->delivered_samples;
        $insertOneResult = $collection->insertOne([
                                                    "case_id"=>$case["_id"],
                                                    "idsr_id"=>$case["idsr_id"],
                                                    "reporter_rapidpro_id"=>$this->reporter_rp_id,
                                                  ]);
        $cont_alert = array();
        $facility_code = $case["facility_code"];
        $disease_name = $case ["disease_name"];

        //store transactionId of this case
        $this->openHimTransactionIDs[$sample] = $case["openHimTransactionID"];
        $facility_details = $this->get_facility_details ($facility_code,"code");
        $msg = "Rider has ".$status." a sample of a Suspected case of ".$disease_name;
        if($lab)
        $msg .= " To ".$lab." Lab,";
        $msg .= " Which was Reported From ".$facility_details["facility_name"].",".$facility_details["district_name"];

        //alert DSO
        $dso = $this->get_dso($facility_details["district_uuid"]);
        $cont_alert = $this->get_rapidpro_id($dso);
        $this->broadcast("Alert DSO",$cont_alert,$msg);

        //alert CSO
        $cso = $this->get_cso($facility_details["county_uuid"]);
        $cont_alert = $this->get_rapidpro_id($cso);
        $this->broadcast("Alert CSO",$cont_alert,$msg);

        //alert others
        $cont_alert = array();
        foreach($this->notify_group as $group_name) {
          $other_contacts = $this->get_contacts_in_grp(urlencode($group_name));
          if(count($other_contacts)>0)
          $cont_alert = array_merge($cont_alert,$other_contacts);
        }
        if(count($cont_alert) > 0)
        $this->broadcast("Alert DPC And Others",$cont_alert,$msg);
        //if delivered,alert HW
        if($action == "sample_delivered") {
          $cont_alert = $this->get_rapidpro_id(array($case["reporter_globalid"]));
          $msg = "Rider has ".$status." a sample of a Suspected case of ".$disease_name;
          if($lab)
          $msg .= " To ".$lab." Lab,";
          $msg .= " Which was Reported From your facility (".$facility_details["facility_name"].",".$facility_details["district_name"].")";
          $this->broadcast("Alert Person Reported Case",$cont_alert,$msg);
        }
        if(!$found_samples)
        $found_samples = $sample;
        else
        $found_samples .= ",".$sample;
        break;
      }
      else {
        if(!$missing_samples)
        $missing_samples = $sample;
        else
        $missing_samples .= ",".$sample;
      }
    }
    if($missing_samples) {
      $extra = '"status":"not_found","samples":"'.$missing_samples.'"';
      array_push($this->response_body,array("Riders ".$action=>"IDSRID ".$missing_samples." Not Found On The System"));
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
    }
    if($found_samples) {
      $extra = '"status":"success","samples":"'.$found_samples.'"';
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
    }

    if(!$found_samples and !$missing_samples) {
      $samples = str_ireplace("testpicked","",$this->samples);
      $samples = str_ireplace("picked","",$samples);
      $samples = str_ireplace("testdelivered","",$samples);
      $samples = str_ireplace("delivered","",$samples);
      $samples = str_ireplace(".","",$samples);
      $extra = '"status":"not_found","samples":"'.$samples.'"';
      array_push($this->response_body,array("Riders ".$action=>"IDSRID ".$samples." Not Found On The System"));
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
      return false;
    }

    if($missing_samples)
    return false;
    else
    return true;
  }

}

/*This code sends a response to rapidpro and continue execution of the rest
This is important because rapidpro webhook calling has a wait time limit,if exceeded then it will show the webhook calling has failed
*/
//$_REQUEST = array("category" => "sample_picked","samples"=>"picked.MAR-2RH4-77985","reporter_phone" => "088 684 7915","reporter_name" => "Stephen Mambu Gbanyan","reporter_rp_id" => "3124c792-c322-4aed-8206-b7bcedddd46f","reporter_globalid" =>"urn:uuid:a5547568-a24c-39b7-b895-734ed8a777f2");
$headers = getallheaders();
$openHimTransactionID = $headers["X-OpenHIM-TransactionID"];
ob_start();
$size = ob_get_length();
header("Content-Encoding: none");
header("Content-Length: {$size}");
header("Connection: close");
ob_end_flush();
ob_flush();
flush();
if(session_id())
session_write_close();
//end of closing the connection,now start processing the request and start a separate flow
require("config.php");
require("openHimConfig.php");
require_once __DIR__ . "/vendor/autoload.php";
$category = $_REQUEST["category"];
$samples = $_REQUEST["samples"];
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
//require("test_config.php");
$samples = preg_replace('/\s+/', '', $samples);
$riderObj = new riders($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,
                  $csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$samples,
                  $reporter_rp_id,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                 );
$riderObj->notify_group = $notify_group;
if($category == "sample_picked") {
  $riderObj->flow_uuid = $riders_picked_flow_uuid;
  $success = $riderObj->sample_action("sample_picked");
  }
else if($category == "sample_delivered") {
  $riderObj->flow_uuid = $riders_delivered_flow_uuid;
  $success = $riderObj->sample_action("sample_delivered");
  }

/*
  The code below reports to openHIM
**/
  if($success) {
    //report to openHIM transactionId that received this request
    $timestamp = date("Y-m-d G:i:s");
    $response_body = $riderObj->response_body;
    array_push($response_body,array("Parent Transaction"=>$riderObj->openHimTransactionIDs));
    $riderObj->updateTransaction($openHimTransactionID,$riderObj->transaction_status,$response_body,200,$riderObj->orchestrations);
  }
  //incase some caseid were found on the system and some were missing
  else if(!$success and count($riderObj->openHimTransactionIDs) > 0){
    //report to openHIM transactionId that received this request
    $timestamp = date("Y-m-d G:i:s");
    $response_body = $riderObj->response_body;
    array_push($response_body,array("Parent Transaction"=>$riderObj->openHimTransactionIDs));
    $riderObj->updateTransaction($openHimTransactionID,"Completed with error(s)",$response_body,200,$riderObj->orchestrations);
  }

  //incase it is a total failure
  else if(!$success and count($riderObj->openHimTransactionIDs) ==0 ) {
    $riderObj->updateTransaction($openHimTransactionID,"Failed",$riderObj->response_body,400);
  }

  //update the parent transaction (Transaction for which a case was reported) to complete flow i.e from case report to case picked and delivered
  foreach ($riderObj->openHimTransactionIDs as $caseid => $openHimTransactionID) {
    $transactionData = $riderObj->getTransactionData($openHimTransactionID);
    $transactionData = json_decode($transactionData,true);
    $body = $transactionData["response"]["body"];
    $body = json_decode($body,true);
    $response_body = $riderObj->response_body;
    if($category == "sample_delivered")
    array_push($response_body,array("Rider Sample Delivered Transaction Id"=>$riderObj->openHimTransactionID));
    else if($category == "sample_picked")
    array_push($response_body,array("Rider Sample Picked Transaction Id"=>$riderObj->openHimTransactionID));
    if (json_last_error() === JSON_ERROR_NONE) {
      if(in_array("Still Processing",$body)) {
        $body = $response_body;
      }
      else
      array_push($body,$response_body);
      }
    else {
      $body = $response_body;
    }

    if($transactionData["status"] != "Completed with error(s)" or $transactionData["status"] != "Failed")
    $status = $riderObj->transaction_status;
    else
    $status = $transactionData["status"];
    //TODO retrieve orchestrations from transaction and append with this one
    $riderObj->updateTransaction($openHimTransactionID,$status,$body,200,$riderObj->orchestrations);
  }
?>
