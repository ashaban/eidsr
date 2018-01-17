<?php
class lab_results extends eidsr_base {
  function __construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,
                        $rp_csd_doc,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                      ){
    parent::__construct(
                          $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,"","","",$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                      );
    $this->orchestrations = array();
    $this->response_body = array();
    $this->rapidpro_token = $rapidpro_token;
    $this->rapidpro_host = $rapidpro_url;
    $this->csd_host = $csd_host;
    $this->csd_user = $csd_user;
    $this->csd_passwd = $csd_passwd;
    $this->csd_doc = $csd_doc;
    $this->rp_csd_doc = $rp_csd_doc;
    $this->database = $database;
    $this->transaction_status = "Successful";
    $this->openHimTransactionID = $openHimTransactionID;
  }

  public function get_lab_results($lab_res,$idsr_id) {
    $last_lab_res = $lab_res["lastLabResultNotification"];
    if($last_lab_res == null) {
    	$res_keys = array_keys($lab_res["labResults"]);
			sort($res_keys);
			$chunks = array_chunk($res_keys,1,true);
    }
    else {
			$res_keys = array_keys($lab_res["labResults"]);
			sort($res_keys);
			$last_res_pos = array_search($last_lab_res,$res_keys);
			$last_res_pos++;
			$chunks = array_chunk($res_keys,$last_res_pos,true);
			unset($chunks[0]);
    }
    foreach ($chunks as $chunk_array) {
		  foreach ($chunk_array as $tests) {
		  	$sms .= "IDSRID:" . $idsr_id."\n";
		    $sms .= "specimenType:" . $lab_res["labResults"][$tests]["specimenType"]."\n";
		    $sms .= "Condition:" . $lab_res["labResults"][$tests]["condition"]."\n";
		    $sms .= "Condition Reason:" . $lab_res["labResults"][$tests]["conditionReason"]."\n";
		    $sms .= "Disease Or Condition:" . $lab_res["labResults"][$tests]["diseaseOrCondition"]."\n";
		    $sms .= "finalLabResult:" . $lab_res["labResults"][$tests]["finalLabResult"]."\n";
		    $sms .= "OtherConditionReason:" . $lab_res["labResults"][$tests]["otherConditionReason"]."\n";
		    $sms .= "otherDisease:" . $lab_res["labResults"][$tests]["otherDisease"]."\n";
		  }
		}
		return $sms;
  }

  public function process_results($lab_res) {
    $trackerid = $lab_res["caseId"];
    if(count($case_details) > 0) {
    	$case_details = find_case_by_id($trackerid);
    	$results = get_lab_results($lab_res,$case_details["idsr_id"]);
    	if($results == "") {
    		error_log("No Lab results was found on the request");
    		return;
    	}
      $case_reporter = array($case_details["reporter_rapidpro_id"]);
      $this->broadcast("Send Lab Results To Case Reporter",$case_reporter,$results);
      $facility_details = $this->get_provider_facility($case_details["reporter_globalid"]);

      //msg Lab Results DSO
      $dso = $this->get_dso($this->facility_details["district_uuid"]);
      $cont_alert = $this->get_rapidpro_id($dso);
      if(count($cont_alert) > 0) {
        $this->broadcast("Send Lab Results To DSO",$cont_alert,$results);
      }

      //msg Lab Results CSO
      $cso = $this->get_cso($this->facility_details["county_uuid"]);
      $cont_alert = $this->get_rapidpro_id($cso);
      if(count($cont_alert) > 0) {
        $this->broadcast("Send Lab Results To CSO",$cont_alert,$results);
      }

      //msg Lab Results CDO
      $cdo = $this->get_cdo($this->facility_details["county_uuid"]);
      $cont_alert = $this->get_rapidpro_id($cdo);
      if(count($cont_alert) > 0) {
        $this->broadcast("Send Lab Results To CDO",$cont_alert,$results);
      }

      $cont_alert = array();
      foreach($this->notify_group as $group_name) {
        $other_contacts = $this->get_contacts_in_grp(urlencode($group_name));
        if(count($other_contacts)>0)
        $cont_alert = array_merge($cont_alert,$other_contacts);
      }
      if(count($cont_alert) > 0) {
        $this->broadcast("Send Lab Results To DPC+Others",$cont_alert,$results);
      }
    }
  }

}

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

$labObj = new lab_results($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,
                          $rp_csd_doc,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                          );
$labObj->notify_group = $notify_group;
$lab_res = @json_decode(($stream = fopen('php://input', 'r')) !== false ? stream_get_contents($stream) : "{}",true);
$labObj->process_results($lab_res);
?>
