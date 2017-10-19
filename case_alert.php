<?php
require("eidsr_base.php");
class eidsr extends eidsr_base{
  function __construct(
                        $reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,$rapidpro_url,
                        $case_alert_flow_uuid,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,
                        $eidsr_passwd,$reported_disease,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                      ) {
    parent::__construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                       );

    $this->orchestrations = array();
    $this->response_body = array();
    $this->reporter_phone = $reporter_phone;
    $this->reporter_name = $reporter_name;
    $this->report = $report;
    $this->reported_disease = $reported_disease;
    $this->reporter_rp_id = $reporter_rp_id;
    $this->reporter_globalid = $reporter_globalid;
    $this->rapidpro_token = $rapidpro_token;
    $this->rapidpro_host = $rapidpro_url;
    $this->case_alert_flow_uuid = $case_alert_flow_uuid;
    $this->csd_host = $csd_host;
    $this->csd_user = $csd_user;
    $this->csd_passwd = $csd_passwd;
    $this->csd_doc = $csd_doc;
    $this->rp_csd_doc = $rp_csd_doc;
    $this->eidsr_host = $eidsr_host;
    $this->eidsr_user = $eidsr_user;
    $this->eidsr_passwd = $eidsr_passwd;
    $this->transaction_status = "Successful";
    $this->openHimTransactionID = $openHimTransactionID;

    //update opnHIM status to Processing
    $timestamp = date("Y-m-d G:i:s");
    $body = array("server-status"=>"Still Processing");
    $body = json_encode($body);
    $update = array("status"=>"Processing",
                    "response"=>array("status"=>200,
                                      "headers"=>array("content-type"=>"application/json+openhim"),
                                      "timestamp"=> $timestamp,
                                      "body"=>$body
                                     )
                   );
    $update = json_encode($update);
    $this->updateTransaction($openHimTransactionID,"Processing",$body,200,$this->orchestrations);
    $this->facility_details = $this->get_provider_facility($this->reporter_globalid);
    //if facility code is missing then stop execution or alert HMIS director
    error_log(print_r($this->facility_details,true));
    array_push($this->response_body,array("Reporter Location Details"=>$this->facility_details));
    $this->county_uuid = $this->facility_details["county_uuid"];
  }

  public static function trim_values(&$value) {
    $value = trim($value);
  }

  public function validate_report() {
    $report = explode(".",$this->report);
    array_walk($report,'eidsr::trim_values');
    $possible_specimen = array("yes"=>"Yes","ye"=>"Yes","y"=>"Yes","no"=>"No","n"=>"No");
    $this->specimen = "";
    $this->caseid = false;
    if(count($report)>1 and is_numeric($report[1])) {
      $this->caseid = $report[1];
    }
    else if (!$this->caseid and count($report)>1 and array_key_exists(strtolower($report[1]),$possible_specimen)) {
      $specimen = strtolower($report[1]);
      $this->specimen = ucfirst($possible_specimen[$specimen]);
    }
    if(!$this->caseid and count($report)>2 and is_numeric($report[2])) {
      $this->caseid = $report[2];
    }
    else if(!$this->specimen and count($report)>2 and array_key_exists(strtolower($report[2]),$possible_specimen)) {
      $specimen = strtolower($report[2]);
      $this->specimen = ucfirst($possible_specimen[$specimen]);
    }
    if($this->specimen == "") {
      array_push($this->response_body,array("Case Details"=>"Specimen Collection Was Not Specified"));
    }

    if($this->caseid === false) {
      array_push($this->response_body,array("Case Details"=>"Case ID is Missing"));
      return false;
    }
    else
    return true;
  }

  public function alert_all ($idsrid){
    $cont_alert = array();
    foreach($this->notify_group as $group_name) {
      $other_contacts = $this->get_contacts_in_grp(urlencode($group_name));
      if(count($other_contacts)>0)
      $cont_alert = array_merge($cont_alert,$other_contacts);
    }
    //alert all partners
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") By ".$this->reporter_name.".";
      if($this->specimen == "Yes")
      $msg .= "A sample was also taken for Riders to pick";
      else if($this->specimen == "No")
      $msg .= "Sample was not collected";
      else
      $msg .= "Sample collection was not specified";
      $this->broadcast("Alert DPC And Others",$cont_alert,$msg);
    }

    //alert CSO
    $cso = $this->get_cso($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cso);
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") By ".$this->reporter_name.". Please verify with DSO";
      if($this->specimen == "Yes")
      $msg .= ".A sample was also taken for Riders to pick";
      else if($this->specimen == "No")
      $msg .= ".Sample was not collected";
      else
      $msg .= ".Sample collection was not specified";
      $this->broadcast("Alert CSO",$cont_alert,$msg);
    }

    //alert DSO
    $dso = $this->get_dso($this->facility_details["district_uuid"]);
    $cont_alert = $this->get_rapidpro_id($dso);
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") With IDSRID ".$idsrid." By ".$this->reporter_name."(".$this->reporter_phone."). Please call or visit health facility to verify";
      if($this->specimen == "Yes")
      $msg .= ".A sample was also taken for Riders to pick";
      else if($this->specimen == "No")
      $msg .= ".Sample was not collected";
      else
      $msg .= ".Sample collection was not specified";
      $this->broadcast("Alert DSO",$cont_alert,$msg);
    }

    //alert CDO
    $cdo = $this->get_cdo($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cdo);
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].")";
      if($this->specimen == "Yes")
      $msg .= ".A sample was also taken for Riders to pick";
      else if($this->specimen == "No")
      $msg .= ".Sample was not collected";
      else
      $msg .= ".Sample collection was not specified";
      $this->broadcast("Alert CDO",$cont_alert,$msg);
    }

    //if sample collected then alert Riders dispatch
    if($this->specimen == "Yes") {
      $riders_contacts = $this->get_contacts_in_grp(urlencode($this->riders_group));
      if(count($riders_contacts) > 0) {
        $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") With IDSRID ".$idsrid.". Sample is available at this facility for you to pick";
        $this->broadcast("Alert Riders",$riders_contacts,$msg);
      }
    }
    return;
  }

  public function send_to_syncserver() {
    $header = Array(
                    "Content-Type: application/json"
                   );
    $post_data = '{
                    "reportingPersonName":"'.$this->reporter_name.'",
                    "reportingPersonPhoneNumber":"'.$this->reporter_phone.'",
                    "facilityCode":"'.$this->facility_details["facility_code"].'",
                    "diseaseOrCondition":"'.$this->reported_disease.'",
                    "caseId":"'.$this->caseid.'",
                    "specimenCollected":"'.$this->specimen.'"
                  }';
    error_log($post_data);
    $response = $this->exec_request("Submitting Case Alert To Offline Tracker",$this->eidsr_host,$this->eidsr_user,$this->eidsr_passwd,"POST",$post_data,$header,true);
    list($header, $body) = explode("\r\n\r\n", $response, 2);
    error_log($response);
    if(count($header) == 0 or $header == "") {
      error_log("Something went wrong,sync server returned empty header");
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"An error occured while processing your request,please retry after sometime");
      array_push($this->response_body,array("Case Details"=>"Something went wrong,sync server returned empty header"));
    }
    if(count($body) == 0 or $body == "") {
      error_log("Something went wrong,sync server returned empty body");
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"An error occured while processing your request,please retry after sometime");
      array_push($this->response_body,array("Case Details"=>"Something went wrong,sync server returned empty body"));
      return false;
    }

    $body = json_decode($body,true);
    if(array_key_exists("message",$body) and strpos($body["message"],"The caseI id is already") !== false) {
      error_log("The caseID you submitted is already used,pleae resubmit this case with a different caseID");
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"The caseID you submitted is already used,please resubmit this case with a different caseID");
      array_push($this->response_body,array("Case Details"=>$body));
      return false;
    }
    //above if statement will be replaced with this else statement after offline tracker codes that are in dev get deployed to production
    else if(array_key_exists("error",$body) and $body["error"] == "Duplicate caseid") {
      error_log("The caseID you submitted is already used,pleae resubmit this case with a different caseID");
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"The caseID you submitted is already used,please resubmit this case with a different caseID");
      array_push($this->response_body,array("Case Details"=>$body));
      return false;
    }
    if(array_key_exists("message",$body) and strpos($body["message"],"Missing organisation unit (Facility) with code") !== false) {
      error_log($body["message"]);
      array_push($this->response_body,array("Case Details"=>$body));
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"You are located in a facility that is not allowed to send case alerts");
      return false;
    }
    //above if statement will be replaced with this else statement after offline tracker codes that are in dev get deployed to production
    else if(array_key_exists("error",$body) and strpos($body["error"],"Missing organisation unit") !== false) {
      error_log($body["error"]);
      array_push($this->response_body,array("Case Details"=>$body));
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"You are located in a facility that is not allowed to send case alerts");
      return false;
    }

    if(stripos($header,400) !== false) {
      //report this to openHIM
      error_log($response);
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"An error occured while processing your request,please retry after sometime");
      array_push($this->response_body,array("Case Details"=>"IDSRID was not returned by the sync server"));
      array_push($this->response_body,array("Case Details"=>$body));
      return false;
    }
    $idsrid = strtoupper($body["caseInfo"]["idsrId"]);
    if(!$idsrid) {
      $this->broadcast("Alert Case Reporter",array($this->reporter_rp_id),"An error occured while processing your request,please retry after sometime");
      array_push($this->response_body,array("Case Details"=>"IDSRID was not returned by the sync server"));
      error_log("IDSRID was not returned by the sync server");
      return false;
    }

    $header = explode("\r\n",$header);
    foreach($header as $resp) {
      if(substr($resp,0,8) == "Location") {
        $trackerid = str_ireplace ("Location: /casealert/","",$resp);
        break;
      }
    }
    $sync_server_results = array("trackerid"=>$trackerid,"idsrid"=>$idsrid);
    error_log(print_r($sync_server_results,true));

    $collection = (new MongoDB\Client)->eidsr->case_details;
    $date = date("Y-m-d\TH:m:s");
    $insertOneResult = $collection->insertOne([
                                                "disease_name"=>$this->reported_disease,
                                                "idsr_id"=>$idsrid,
                                                "trackerid"=>$trackerid,
                                                "reporter_globalid"=>$this->reporter_globalid,
                                                "reporter_rapidpro_id"=>$this->reporter_rp_id,
                                                "facility_code"=>$this->facility_details["facility_code"],
                                                "facility_name"=>$this->facility_details["facility_name"],
                                                "district_name"=>$this->facility_details["district_name"],
                                                "county_name"=>$this->facility_details["county_name"],
                                                "openHimTransactionID"=>$this->openHimTransactionID,
                                                "date"=>$date
                                              ]);
    error_log("case details saved to database with id ".$insertOneResult->getInsertedId());

    return $sync_server_results;
  }

  public function update_syncserver() {
    if($this->community_detection)
    $comm_det = '"communityLevelDetection":"'.$this->community_detection.'"';
    if($this->international_travel)
    $inter_trav = '"crossedBorder":"'.$this->international_travel.'"';
    if($this->reason_no_specimen)
    $reason_no_specimen = '"comments":"'.$this->reason_no_specimen.'"';
    if($this->specimen_collected)
    $specimen_collected = '"specimenCollected":"'.$this->specimen_collected.'"';

    $post_data = '{'.$comm_det.$inter_trav.$reason_no_specimen.$specimen_collected.'}';
    error_log($post_data);
    $url = $this->eidsr_host."/".$this->trackerid;
    $header = Array(
                    "Content-Type: application/json"
                   );
    $response = $this->exec_request("Updating Case Alert In Offline Tracker",$url,$this->eidsr_user,$this->eidsr_passwd,"PUT",$post_data,$header);
    error_log($response);
  }

}


/*This code sends a response to rapidpro and continue execution of the rest
This is important because rapidpro webhook calling has a wait time limit,if exceeded then it will show the webhook calling has failed
*/
$headers = getallheaders();
$openHimTransactionID = $headers["X-OpenHIM-TransactionID"];
ob_start();
$size = ob_get_length();
http_response_code(200);
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
//loading mongodb for php
require_once __DIR__ . "/vendor/autoload.php";

//$_REQUEST = array('category'=>'alert_all','report'=>'Alert.lf.7798599','reporter_phone'=>'077 615 9231','reporter_name'=>'Ally Shaban','reported_disease'=>'Lassa Fever','reporter_rp_id'=>'43f66ce0-ecd7-4ac1-b615-7259bd4e9b55','reporter_globalid'=>'urn:uuid:c8125cb3-3bb6-3676-835d-7bc3290add6f');
$category = $_REQUEST["category"];
$reporter_phone = $_REQUEST["reporter_phone"];
$report = $_REQUEST["report"];
$reported_disease = $_REQUEST["reported_disease"];
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
$reporter_name = $_REQUEST["reporter_name"];
$reporter_globalid = $_REQUEST["reporter_globalid"];
//require("test_config.php");
//in case this is comming from test workflow
$report = str_ireplace("testalert.","",$report);
$report = str_ireplace("alert.","",$report);
$eidsr = new eidsr( $reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,
                    $rapidpro_url,$case_alert_flow_uuid,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,
                    $eidsr_host,$eidsr_user,$eidsr_passwd,$reported_disease,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                  );

//if no facility for the reporter then
if($eidsr->facility_details["facility_uuid"] == "") {
  $eidsr->broadcast("Alert Case Reporter",array($reporter_rp_id),"You are not allowed to access EIDSR system");
  $eidsr->updateTransaction($openHimTransactionID,"Failed",$eidsr->response_body,200,$eidsr->orchestrations);
  return;
}

if($category == "alert_all") {
  $eidsr->notify_group = $notify_group;
  $eidsr->riders_group = $riders_group;
  $valid = $eidsr->validate_report();
  if($valid) {
    $sync_server_results = $eidsr->send_to_syncserver();
    if($sync_server_results != false) {
      $idsrid = $sync_server_results["idsrid"];
      $trackerid = $sync_server_results["trackerid"];
      $eidsr->alert_all($idsrid);
      $extra = '"trackerid":"'.$trackerid.'","idsrid":"'.$idsrid.'","disease_name":"'.$reported_disease.'","specimenCollected":"'.$eidsr->specimen.'"';
      $eidsr->start_flow($eidsr->case_alert_flow_uuid,"",array($reporter_rp_id),$extra);
      $eidsr->updateTransaction($openHimTransactionID,$eidsr->transaction_status,$eidsr->response_body,200,$eidsr->orchestrations);
    }
    else {
      $eidsr->updateTransaction($openHimTransactionID,"Failed",$eidsr->response_body,400,$eidsr->orchestrations);
    }
  }
  else {
    $eidsr->broadcast("Alert Case Reporter",array($reporter_rp_id),"Case Id for this case report is missing,please resubmit the case with case ID");
    $eidsr->updateTransaction($openHimTransactionID,"Failed",$eidsr->response_body,400,$eidsr->orchestrations);
    return;
  }
}
if($category == "update") {
  $eidsr->community_detection = $_REQUEST["community_detection"];
  $eidsr->international_travel = $_REQUEST["international_travel"];
  $eidsr->specimen_collected = $_REQUEST["specimen_collected"];
  $eidsr->reason_no_specimen = $_REQUEST["reason_no_specimen"];
  $eidsr->trackerid = $_REQUEST["trackerid"];
  //just in case the reporter forgot to specify whether specimen was collected or not,during the initial alert
  if($eidsr->specimen_collected == "Yes") {
    $riders_contacts = $eidsr->get_contacts_in_grp(urlencode($riders_group));
    if(count($riders_contacts) > 0) {
      $reported_cases = (new MongoDB\Client)->eidsr->case_details;
      $case = $reported_cases->findOne(['trackerid' => $eidsr->trackerid]);
      $msg = "A suspected case of ".$case["disease_name"]." Has been Reported From ".$case["facility_name"]."(".$case["district_name"].",".$case["county_name"].") With IDSRID ".$case["idsr_id"].". Sample is available at this facility for you to pick";
      $eidsr->broadcast("Alert Riders",$riders_contacts,$msg);
    }
  }
  $eidsr->update_syncserver();
  $eidsr->updateTransaction($openHimTransactionID,$eidsr->transaction_status,$eidsr->response_body,200,$eidsr->orchestrations);
}
if($category == "query") {
  if($_REQUEST["query_type"] == "provider_facility" and $_REQUEST["reporter_globalid"]) {
    $reporter_facility = $eidsr->get_provider_facility($_REQUEST["reporter_globalid"]);
    $extra = '"facility":"'.$reporter_facility["name"].'"';
    $eidsr->start_flow($request_pickup_flow_uuid,"",array($reporter_rp_id),$extra);
    $eidsr->updateTransaction($openHimTransactionID,$eidsr->transaction_status,$eidsr->response_body,200,$eidsr->orchestrations);
  }
else {
  $extra = '"facility":"Unknown"';
  $eidsr->start_flow($request_pickup_flow_uuid,"",array($reporter_rp_id),$extra);
  $eidsr->updateTransaction($openHimTransactionID,$eidsr->transaction_status,$eidsr->response_body,200,$eidsr->orchestrations);
}
return;
}
?>
