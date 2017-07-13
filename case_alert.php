<?php
require("eidsr_base.php");

class eidsr extends eidsr_base{
  function __construct(
                        $reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,$rapidpro_url,
                        $mhero_eidsr_flow_uuid,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,
                        $eidsr_passwd,$reported_disease
                      ) {
    parent::__construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd
                       );
    $this->reporter_phone = $reporter_phone;
    $this->reporter_name = $reporter_name;
    $this->report = $report;
    $this->reported_disease = $reported_disease;
    $this->reporter_rp_id = $reporter_rp_id;
    $this->reporter_globalid = $reporter_globalid;
    $this->rapidpro_token = $rapidpro_token;
    $this->rapidpro_host = $rapidpro_url;
    $this->mhero_eidsr_flow_uuid = $mhero_eidsr_flow_uuid;
    $this->csd_host = $csd_host;
    $this->csd_user = $csd_user;
    $this->csd_passwd = $csd_passwd;
    $this->csd_doc = $csd_doc;
    $this->rp_csd_doc = $rp_csd_doc;
    $this->eidsr_host = $eidsr_host;
    $this->eidsr_user = $eidsr_user;
    $this->eidsr_passwd = $eidsr_passwd;
    $this->facility_details = $this->get_provider_facility($this->reporter_globalid);
    $this->county_uuid = $this->facility_details["county_uuid"];
  }

  public function get_provider_facility($provider_uuid) {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$provider_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
    $prov_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $fac_uuid = $this->extract($prov_entity,"/csd:provider/csd:facilities/csd:facility/@entityID",'providerDirectory',true);
    $fac_det = $this->get_facility_details ($fac_uuid,"uuid");
    return $fac_det;
  }

  public function get_dhis2_facility_uid($facility_uuid) {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$facility_uuid.'"/>
          <csd:otherID position="2"/>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:facility_read_otherid";
    $fac_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $dhis2_fac_uid = $this->extract($fac_entity,"/csd:facility/csd:otherID",'facilityDirectory',true);
    return $dhis2_fac_uid;
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
    if($this->caseid === false)
    return false;
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
      if($this->specimen)
      $msg .= "A sample was also taken for Riders to pick";
      $this->broadcast($cont_alert,$msg);
    }

    //alert CSO
    $cso = $this->get_cso($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cso);
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") By ".$this->reporter_name.". Please verify with DSO";
      if($this->specimen)
      $msg .= ".A sample was also taken for Riders to pick";
      $this->broadcast($cont_alert,$msg);
    }

    //alert DSO
    $dso = $this->get_dso($this->facility_details["district_uuid"]);
    $cont_alert = $this->get_rapidpro_id($dso);
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") With IDSRID ".$idsrid." By ".$this->reporter_name."(".$this->reporter_phone."). Please call or visit health facility to verify";
      if($this->specimen)
      $msg .= ".A sample was also taken for Riders to pick";
      $this->broadcast($cont_alert,$msg);
    }

    //alert CDO
    $cdo = $this->get_cdo($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cdo);
    if(count($cont_alert) > 0) {
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].")";
      if($this->specimen)
      $msg .= ".A sample was also taken for Riders to pick";
      $this->broadcast($cont_alert,$msg);
    }

    //if sample collected then alert Riders dispatch
    if($this->specimen) {
      $riders_contacts = $this->get_contacts_in_grp(urlencode("Riders Dispatch"));
      if(count($riders_contacts) > 0) {
        $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") With IDSRID ".$idsrid.". Sample is available at this facility for you to pick";
        $this->broadcast($riders_contacts,$msg);
      }
    }
    return;
  }

  public function send_to_syncserver() {
    $dhis2_facility_uid = $this->get_dhis2_facility_uid($this->facility_details["facility_uuid"]);
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
    $response = $this->exec_request($this->eidsr_host,$this->eidsr_user,$this->eidsr_passwd,"POST",$post_data,$header,true);
    list($header, $body) = explode("\r\n\r\n", $response, 2);
    if(count($header) == 0)
    error_log("Something went wrong,sync server returned no header");
    if(count($body) == 0)
    error_log("Something went wrong,sync server returned no body");
    $body = json_decode($body,true);
    $idsrid = $body["caseInfo"]["idsrId"];


    $reported_cases = file_get_contents("reported_cases.json");
    $reported_cases = json_decode($reported_cases,true);
    $total_cases = count($reported_cases);
    $reported_cases[$total_cases] = array("disease_name"=>$this->reported_disease,
                                          "idsr_id"=>$idsrid,
                                          "reporter_globalid"=>$this->reporter_globalid,
                                          "facility_code"=>$this->facility_details["facility_code"]
                                         );
    $reported_cases = json_encode($reported_cases,true);
    file_put_contents("reported_cases.json",$reported_cases);
    $header = explode("\r\n",$header);
    foreach($header as $resp) {
      if(substr($resp,0,8) == "Location") {
        $trackerid = str_ireplace ("Location: /casealert/","",$resp);
        break;
      }
    }
    $sync_server_results = array("trackerid"=>$trackerid,"idsrid"=>$idsrid);
    error_log(print_r($sync_server_results,true));
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
    $response = $this->exec_request($url,$this->eidsr_user,$this->eidsr_passwd,"PUT",$post_data,$header);
    error_log($response);
  }

}


/*This code sends a response to rapidpro and continue execution of the rest
This is important because rapidpro webhook calling has a wait time limit,if exceeded then it will show the webhook calling has failed
*/
ob_start();
echo '{"status":"processing"}';
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
//$_REQUEST = array('category'=>'alert_all','report'=>'Alert.lf.77878.yes','reporter_phone'=>'077 615 9231','reporter_name'=>'Ally Shaban','reported_disease'=>'Lassa Fever','reporter_rp_id'=>'43f66ce0-ecd7-4ac1-b615-7259bd4e9b55','reporter_globalid'=>'urn:uuid:2d2259d9-c52f-3430-bbef-d08992444058');
$category = $_REQUEST["category"];
$reporter_phone = $_REQUEST["reporter_phone"];
$report = $_REQUEST["report"];
$reported_disease = $_REQUEST["reported_disease"];
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
$reporter_name = $_REQUEST["reporter_name"];
$reporter_globalid = $_REQUEST["reporter_globalid"];

require("test_config.php");
$report = str_ireplace("alert.","",$report);
$eidsr = new eidsr( $reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,
                    $rapidpro_url,$mhero_eidsr_flow_uuid,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,
                    $eidsr_host,$eidsr_user,$eidsr_passwd,$reported_disease
                  );

//if no facility for the reporter then
if($eidsr->facility_details["facility_uuid"] == "") {
  $eidsr->broadcast(array($reporter_rp_id),"You are not allowed to access EIDSR system");
  return;
}

if($category == "alert_all") {
  $eidsr->notify_group = array("DPC Group","National Reference Lab");
  $valid = $eidsr->validate_report();
  if($valid) {
    $sync_server_results = $eidsr->send_to_syncserver();
    $idsrid = $sync_server_results["idsrid"];
    $trackerid = $sync_server_results["trackerid"];
    $eidsr->alert_all($idsrid);
    $extra = '"trackerid":"'.$trackerid.'","idsrid":"'.$idsrid.'","disease":"'.$reported_disease.'","specimenCollected":"'.$eidsr->specimen.'"';
    $eidsr->start_flow($eidsr->mhero_eidsr_flow_uuid,"",array($reporter_rp_id),$extra);
  }
  else {
    $eidsr->broadcast(array($reporter_rp_id),"Case Id for this case report is missing,please resubmit the case with case ID");
    return;
  }
}
if($category == "update") {
  $eidsr->community_detection = $_REQUEST["community_detection"];
  $eidsr->international_travel = $_REQUEST["international_travel"];
  $eidsr->specimen_collected = $_REQUEST["specimen_collected"];
  $eidsr->reason_no_specimen = $_REQUEST["reason_no_specimen"];
  $eidsr->trackerid = $_REQUEST["trackerid"];
  $eidsr->update_syncserver();
}
if($category == "query") {
  if($_REQUEST["query_type"] == "provider_facility" and $_REQUEST["reporter_globalid"]) {
    $reporter_facility = $eidsr->get_provider_facility($_REQUEST["reporter_globalid"]);
    echo '{"facility":"'.$reporter_facility["name"].'"}';
  }
else
echo '{"facility":"Unknown"}';
return;
}
?>
