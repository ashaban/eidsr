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
                        $eidsr_user,$eidsr_passwd,$mhero_eidsr_flow_uuid
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
  }

  public function alert_all (){
    $cont_alert = array();
    foreach($this->notify_group as $group_name) {
      $other_contacts = $this->get_contacts_in_grp(urlencode($group_name));
      if(count($other_contacts)>0)
      $cont_alert = array_merge($cont_alert,$other_contacts);
    }
    //alert all partners
    $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]." By ".$this->reporter_name.".";
    if($this->specimen)
    $msg .= "A sample was also taken for Riders to pick";
    $this->broadcast($cont_alert,$msg);

    //alert CSO
    $cso = $this->get_cso($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cso);
    $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]." By ".$this->reporter_name.". Please verify with DSO";
    if($this->specimen)
    $msg .= ".A sample was also taken for Riders to pick";
    $this->broadcast($cont_alert,$msg);

    //alert DSO
    $dso = $this->get_dso($this->facility_details["district_uuid"]);
    $cont_alert = $this->get_rapidpro_id($dso);
    $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]." By ".$this->reporter_name."(".$this->reporter_phone."). Please call or visit health facility to verify";
    if($this->specimen)
    $msg .= ".A sample was also taken for Riders to pick";
    $this->broadcast($cont_alert,$msg);

    //if sample collected then alert Riders dispatch
    if($this->specimen) {
      $riders_contacts = $this->get_contacts_in_grp(urlencode("Riders Dispatch"));
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->facility_details["facility_name"]." Facility. Sample is available at this facility for you to pick";
      $this->broadcast($riders_contacts,$msg);
    }
    return;
  }

  public function send_to_syncserver() {
    $dhis2_facility_uid = $this->get_dhis2_facility_uid($this->facility_details["facility_uuid"]);
    $header = Array(
                    "Content-Type: application/json"
                   );
    $idsrid = $this->facility_details["county_code"]."-".$this->facility_details["facility_code"]."-".$this->caseid;
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
    $response = explode("\r\n",$response);
    foreach($response as $resp) {
      if(substr($resp,0,8) == "Location") {
        $trackerid = str_ireplace ("Location: /casealert/","",$resp);
        error_log('{"trackerid":"'.$trackerid.'"}');
        return $trackerid;
      }
    }
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


require("config.php");
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

if($category == "alert_all") {
  $eidsr->notify_group = array("DPC Group");
  $eidsr->validate_report();
  $eidsr->alert_all();
  $trackerid = $eidsr->send_to_syncserver();
  $extra = '"trackerid":"'.$trackerid.'","disease":"'.$reported_disease.'","specimenCollected":"'.$eidsr->specimen.'"';
  $eidsr->start_flow($eidsr->mhero_eidsr_flow_uuid,"",array($reporter_rp_id),$extra);
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
