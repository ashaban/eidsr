<?php
class eidsr {
  function __construct( $reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,$rapidpro_url,$csd_host,$csd_user,
                        $csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$reported_disease
                       ) {
    $this->reporter_phone = $reporter_phone;
    $this->reporter_name = $reporter_name;
    $this->report = $report;
    $this->reported_disease = $reported_disease;
    $this->reporter_rp_id = $reporter_rp_id;
    $this->reporter_globalid = $reporter_globalid;
    $this->rapidpro_token = $rapidpro_token;
    $this->rapidpro_host = $rapidpro_url;
    $this->csd_host = $csd_host;
    $this->csd_user = $csd_user;
    $this->csd_passwd = $csd_passwd;
    $this->csd_doc = $csd_doc;
    $this->rp_csd_doc = $rp_csd_doc;
    $this->eidsr_host = $eidsr_host;
    $this->eidsr_user = $eidsr_user;
    $this->eidsr_passwd = $eidsr_passwd;
    $this->reporter_facility = $this->get_provider_facility($this->reporter_globalid);
    $this->county_uuid = $this->get_county_uuid($this->reporter_facility["parent"]);
  }

  public function get_contacts_in_grp ($group_name) {
    $group_uuid = $this->get_group_uuid ($group_name);
    $url = $this->rapidpro_host."api/v2/contacts.json?group=$group_uuid";
    $header = Array(
                         "Content-Type: application/json",
                         "Authorization: Token $this->rapidpro_token"
                   );
    $res=  $this->exec_request($url,"","","GET","",$header);
    $res = json_decode($res,true);
    if(count($res["results"]) > 0){
      foreach($res["results"] as $conts) {
        $contact_uuids[] = $conts["uuid"];
      }
    }
    return $contact_uuids;
  }

  public function get_group_uuid ($group_name) {
    $url = $this->rapidpro_host."api/v2/groups.json?name=$group_name";
    $header = Array(
                         "Content-Type: application/json",
                         "Authorization: Token $this->rapidpro_token"
                   );
    $res=  $this->exec_request($url,"","","GET","",$header);
    $res = json_decode($res,true);
    if(count($res["results"]) > 0){
      return $res["results"][0]["uuid"];
    }
  }

  public function get_provider_facility($provider_uuid) {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$provider_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
    $prov_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $fac_uuid = $this->extract($prov_entity,"/csd:provider/csd:facilities/csd:facility/@entityID",'providerDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$fac_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:facility-search";
    $fac_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $fac_name = $this->extract($fac_entity,"/csd:facility/csd:primaryName",'facilityDirectory',true);
    $fac_parent = $this->extract($fac_entity,"/csd:facility/csd:organizations/csd:organization/@entityID",'facilityDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$fac_uuid.'"/>
          <csd:otherID position="3"/>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:facility_read_otherid";
    $fac_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $fac_code = $this->extract($fac_entity,"/csd:facility/csd:otherID",'facilityDirectory',true);

    $fac = array("uuid"=>$fac_uuid,"code"=>$fac_code,"name"=>$fac_name,"parent"=>$fac_parent);
    return $fac;
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

  public function get_county_uuid($district_uuid) {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$district_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_uuid = $this->extract($org_entity,"/csd:organization/csd:parent/@entityID",'organizationDirectory',true);
    return $county_uuid;
  }

  public function get_county_code($county_uuid) {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$county_uuid.'"/>
          <csd:otherID position="1"/>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:organization_read_otherid";
    $org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $org_uuid = $this->extract($org_entity,"/csd:organization/csd:otherID",'organizationDirectory',true);
    return $org_uuid;
  }

  public function get_dso() {
    $district_uuid = $this->reporter_facility["parent"];
    if(!$district_uuid) {
      error_log("District UUID Missing,DSO wont be alerted");
      return array();
    }
    //get facilities under $district_uuid
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:organizations>
            <csd:organization  entityID="'.$district_uuid.'"/>
           </csd:organizations>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:facility-search";
    $fac_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $fac_uuids1 = $this->extract($fac_entity,"/csd:facility/@entityID",'facilityDirectory',true);
    $fac_uuids = explode(";", $fac_uuids1);
    //foreach facility,fetch providers and check if is DSO
    $dso = array();
    foreach ($fac_uuids as $fac_uuid) {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:facilities>
            <csd:facility  entityID="'.$fac_uuid.'"/>
           </csd:facilities>
          </csd:requestParams>';
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
      $prov_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $prov_entity = new SimpleXMLElement($prov_entity);
      foreach ($prov_entity->providerDirectory->children("urn:ihe:iti:csd:2013") as $prov) {
        global $dso;
        if($prov->extension->position == "District Surveillance Officer" or $prov->extension->position == "Surveillance Officer" or $prov->extension->position == "DSO"){
          $curr_dso = $dso;
          if(count($curr_dso)==0)
            $curr_dso = array();
          $dso1 = array((string)$prov->attributes()->entityID);
          $dso = array_merge($curr_dso,$dso1);
        }
      }
    }
    return $dso;
  }

  public function get_cso() {
    //get districts under $this->county_uuid
    if(!$this->county_uuid) {
      error_log("County UUID Missing,CSO wont be alerted");
      return array();
    }
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:parent entityID="'.$this->county_uuid.'"/>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $orgs_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $orgs_uuids = $this->extract($orgs_entity,"/csd:organization/@entityID",'organizationDirectory',true);
    $distr_uuids = explode(";", $orgs_uuids);
    $fac_uuids = array();
    foreach ($distr_uuids as $distr_uuid) {
      global $fac_uuids;
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
              <csd:organizations>
                <csd:organization  entityID="'.$distr_uuid.'"/>
              </csd:organizations>
            </csd:requestParams>';
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:facility-search";
      $fac_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $fac = $this->extract($fac_entity,"/csd:facility/@entityID",'facilityDirectory',true);
      $fac = explode(";", $fac);
      $curr_fac = $fac_uuids;
      if(count($curr_fac)==0)
        $curr_fac = array();
      $fac_uuids = array_merge($curr_fac,$fac);
    }

    //foreach facility,fetch providers and check if is DSO
    $cso = array();
    foreach ($fac_uuids as $fac_uuid) {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:facilities>
            <csd:facility  entityID="'.$fac_uuid.'"/>
           </csd:facilities>
          </csd:requestParams>';
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
      $prov_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $prov_entity = new SimpleXMLElement($prov_entity);
      foreach ($prov_entity->providerDirectory->children("urn:ihe:iti:csd:2013") as $prov) {
        global $cso;
        if($prov->extension->position == "Council Surveillance Officer" or $prov->extension->position == "Surveillance Officer" or $prov->extension->position == "CSO"){
          $curr_cso = $cso;
          if(count($curr_cso)==0)
            $curr_cso = array();
          $cso1 = array((string)$prov->attributes()->entityID);
          $cso = array_merge($curr_cso,$cso1);
        }
      }
    }
    return $cso;
  }

  public function get_rapidpro_id ($provs_uuid = array()) {
    $ids = "";
    foreach ($provs_uuid as $prov) {
        $ids .= "<csd:id entityID='" . $prov . "'/>\n" ;
      }
    $csr = "<csd:requestParams xmlns:csd='urn:ihe:iti:csd:2013'>"
           . $ids
           ."<csd:code>rapidpro_contact_id</csd:code>"
           ."  </csd:requestParams>" ;
    $url = $this->csd_host."csr/{$this->rp_csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:bulk_health_worker_read_otherids_json";
    $prov_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    if (! is_array($all_resp = json_decode($prov_entity,true))
            || ! array_key_exists('results',$all_resp)
            || ! is_array($resp = $all_resp['results'])
            ) {
              return;
      } else {
            $rp_ids = array();
            foreach ($resp as $res) {
                if (!is_array($res)
                    || ! array_key_exists('entityID',$res)
                    || ! array_key_exists('otherID',$res)
                    || ! is_array( $res['otherID'])
                    || count($res['otherID']) == 0
                    || ! in_array($res['entityID'],$provs_uuid)
                    ) {
                    continue;
                }
                    foreach ($res['otherID'] as $other_id) {
                        if ( !array_key_exists('authority',$other_id)
                            ) {
                            continue;
                        }
                        $rp_ids[] = $other_id["value"];
                    }
            }
      }
    return $rp_ids;
  }

  public function validate_report() {
    $report = explode(".",$this->report);
    $possible_alive_outcomes = array("alive","alve","aliv","ali","alv");
    $possible_dead_outcomes = array("dead","dea","de","ded","dd","da");
    $possible_specimen = array("yes","ye","y");
    $this->specimen = "";
    if(count($report)>1 and is_numeric($report[1])) {
      $this->caseid = $report[1];
    }
    else if (!$this->caseid and count($report)>1 and in_array(strtolower($report[1]),$possible_specimen)) {
      $this->specimen = true;
    }
    if(!$this->caseid and count($report)>2 and is_numeric($report[2])) {
      $this->caseid = $report[2];
    }
    else if(!$this->specimen and count($report)>2 and in_array(strtolower($report[2]),$possible_specimen)) {
      $this->specimen = true;
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
    $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->reporter_facility["name"]." By ".$this->reporter_name.".";
    if($this->specimen)
    $msg .= "A sample was also taken for Riders to pick";
    $this->broadcast($cont_alert,$msg);

    //alert CSO
    $cso = $this->get_cso();
    $cont_alert = $this->get_rapidpro_id($cso);
    $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->reporter_facility["name"]." By ".$this->reporter_name.". Please verify with DSO";
    if($this->specimen)
    $msg .= ".A sample was also taken for Riders to pick";
    $this->broadcast($cont_alert,$msg);

    //alert DSO
    $dso = $this->get_dso();
    $cont_alert = $this->get_rapidpro_id($dso);
    $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->reporter_facility["name"]." By ".$this->reporter_name."(".$this->reporter_phone."). Please call or visit health facility to verify";
    if($this->specimen)
    $msg .= ".A sample was also taken for Riders to pick";
    $this->broadcast($cont_alert,$msg);

    //if sample collected then alert Riders dispatch
    if($this->specimen) {
      $riders_contacts = $this->get_contacts_in_grp(urlencode("Riders Dispatch"));
      $msg = "A suspected case of ".$this->reported_disease." Has been Reported From ".$this->reporter_facility["name"]." Facility. Sample is available at this facility for you to pick";
      $this->broadcast($riders_contacts,$msg);
    }

    //send data to offline tracker
    $this->send_to_syncserver();
  }

  public function start_flow($flow_uuid,$group_uuid,$contacts_uuid = array(),$extra) {
    $url = $this->rapidpro_host."api/v2/flow_starts.json";
    $header = Array(
                       "Content-Type: application/json",
                       "Authorization: Token $this->rapidpro_token",
                    );

    if(count($contacts_uuid)>0) {
      foreach ($contacts_uuid as $cont_uuid) {
        $post_data = '{ "flow":"'.$flow_uuid.'",
                        "contacts":["'.$cont_uuid.'"],
                        "extra": {'.$extra.'}
                      }';
        error_log($post_data);
        $this->exec_request($url,"","","POST",$post_data,$header);
      }
    }
  }

  public function broadcast($contacts_uuid = array(),$msg) {
    $url = $this->rapidpro_host."api/v2/broadcasts.json";
      $header = Array(
                       "Content-Type: application/json",
                       "Authorization: Token $this->rapidpro_token",
                     );
      foreach($contacts_uuid as $uuid) {
        $post_data = '{ "contacts": ["'.$uuid.'"], "text": "'.$msg.'" }';
        error_log($post_data);
      $this->exec_request($url,"","","POST",$post_data,$header);
      }
  }

  public function send_to_syncserver() {
    $dhis2_facility_uid = $this->get_dhis2_facility_uid($this->reporter_facility["uuid"]);
    $header = Array(
                    "Content-Type: application/json"
                   );
    if($this->caseid and $this->reporter_facility["code"]){
      $county_code = $this->get_county_code($this->county_uuid);
      if($county_code)
      $idsrid = $county_code."-".$this->reporter_facility["code"]."-".$this->caseid;
    }
    $post_data = '{
                    "reportingPersonName":"'.$this->reporter_name.'",
                    "reportingPersonPhoneNumber":"'.$this->reporter_phone.'",
                    "facilityCode":"'.$this->reporter_facility["code"].'",
                    "diseaseOrCondition":"'.$this->reported_disease.'",
                    "caseId":"'.$this->caseid.'",
                    "sampleCollected"
                  }';
    error_log($post_data);
    $response = $this->exec_request($this->eidsr_host,$this->eidsr_user,$this->eidsr_passwd,"POST",$post_data,$header);
    $response = explode("\r\n",$response);
    foreach($response as $resp) {
      if(substr($resp,0,8) == "Location") {
        $trackerid = str_ireplace ("Location: /casealert/","",$resp);
        echo '{"trackerid":"'.$trackerid.'"}';
      }
    }
  }

  public function update_syncserver() {
    if($this->community_detection)
    $comm_det = '"communityLevelDetection":"'.$this->community_detection.'",';
    if($this->international_travel)
    $inter_trav = '"crossedBorder":"'.$this->international_travel.'",';
    if($this->reason_no_specimen)
    $reason_no_specimen = '"comments":"'.$this->reason_no_specimen.'",';
    if($this->specimen_collected)
    $specimen_collected = '"specimenCollected":"'.$this->specimen_collected.'",';

    $post_data = '{'.$comm_det.$inter_trav.$reason_no_specimen.$specimen_collected.'}';
    error_log($post_data);
    $url = $this->eidsr_host."/".$trackerid;
    $header = Array(
                    "Content-Type: application/json"
                   );
    $response = $this->exec_request($url,$this->eidsr_user,$this->eidsr_passwd,"PUT",$post_data,$header);
  }

  public function exec_request($url,$user,$password,$req_type,$post_data,$header = Array("Content-Type: text/xml"),$get_header=false) {
    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    if($get_header)
    curl_setopt($curl, CURLOPT_HEADER, true);
    if($req_type=="POST" or $req_type=="PUT") {
      $req_type = '".$req_type."';
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $req_type);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    if($user or $password)
      curl_setopt($curl, CURLOPT_USERPWD, $user.":".$password);
      $curl_out = curl_exec($curl);
      if ($err = curl_errno($curl) ) {
        return false;
      }
      curl_close($curl);
      return $curl_out;
    }

  public function extract($entity,$xpath,$entity_type,$implode = true) {
    $entity_xml = new SimpleXMLElement($entity);
    $entity_xml->registerXPathNamespace ( "csd" , "urn:ihe:iti:csd:2013");
    $xpath_pre = "(/csd:CSD/csd:{$entity_type})[1]";
    $results =  $entity_xml->xpath($xpath_pre . $xpath);
    if ($implode && is_array($results)) {
      $results = implode(";",$results);
    }
    return $results;
  }

}


$category = $_REQUEST["category"];
$reporter_phone = $_REQUEST["reporter_phone"];
$report = $_REQUEST["report"];
$reported_disease = $_REQUEST["reported_disease"];
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
$reporter_name = $_REQUEST["reporter_name"];
$reporter_globalid = $_REQUEST["reporter_globalid"];
$rapidpro_token = "";
$rapidpro_url = "https://app.rapidpro.io/";
$csd_host = "http://localhost:8984/CSD/";
$csd_user = "csd";
$csd_passwd = "csd";
$csd_doc = "liberia";
$rp_csd_doc = "mhero_liberia_rapidpro";
$eidsr_host = "https://lib-eidsr-dev.ehealthafrica.org/casealert";
$eidsr_user = "";
$eidsr_passwd = "";

$report = str_ireplace("alert.","",$report);
$eidsr = new eidsr( $reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,$rapidpro_url,$csd_host,$csd_user,
                    $csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$reported_disease
                  );

if($category == "alert_all") {
  $eidsr->notify_group = array("DPC Group");
  $eidsr->validate_report();
  $eidsr->alert_all();
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
