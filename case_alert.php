<?php
class mhero {
  function __construct($reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$broadcast_flow_uuid,$channel) {
    $this->reporter_phone = $reporter_phone;
    $this->reporter_name = $reporter_name;
    $this->report = $report;
    $this->reporter_rp_id = $reporter_rp_id;
    $this->reporter_globalid = $reporter_globalid;
    $this->rapidpro_token = $rapidpro_token;
    $this->broadcast_flow_uuid = $broadcast_flow_uuid;
    $this->rapidpro_host = $rapidpro_url;
    $this->channel = $channel;
    $this->csd_host = $csd_host;
    $this->csd_user = $csd_user;
    $this->csd_passwd = $csd_passwd;
    $this->csd_doc = $csd_doc;
    $this->rp_csd_doc = $rp_csd_doc;
    $this->reporter_facility = $this->get_provider_facility($this->reporter_globalid);
  }

  public function get_contacts_in_grp ($group_name) {
    $group_uuid = $this->get_group_uuid ($group_name);
    $url = $this->rapidpro_host."api/v1/contacts.json?group_uuids=$group_uuid";
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
    $fac = array("uuid"=>$fac_uuid,"name"=>$fac_name,"parent"=>$fac_parent);
    return $fac;
  }

  public function get_dso() {
    $district_uuid = $this->reporter_facility["parent"];
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
    $district_uuid = $this->reporter_facility["parent"];
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$district_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_uuid = $this->extract($org_entity,"/csd:organization/csd:parent/@entityID",'organizationDirectory',true);
    //get districts under $county_uuid
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:parent entityID="'.$county_uuid.'"/>
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
    $rep = explode(".",$this->report);
    $this->disease = $rep[0];
    $this->age = $rep[1];
    $this->outcome = $rep[2];
    $this->lab = $rep[3];
  }
  public function send_confirmation () {
    $flow_uuid = "95ed064f-104d-4290-8723-611074b94cd5";
    $dso = $this->get_dso();
    $cont_alert = $this->get_rapidpro_id($dso);
    $extra = '"reporter_name":"'. $this->reporter_name .
             '","reporter_rp_id":"'.$this->reporter_rp_id.
             '","reporter_globalid":"'.$this->reporter_globalid.
             '","reporter_phone":"'.$this->reporter_phone.
             '","facility":"'.$this->reporter_facility["name"].
             '","report":"'.$this->report.
             '","disease":"'.$this->disease.
             '","age":"'.$this->age.
             '","outcome":"'.$this->outcome.
             '","lab":"'.$this->lab.'"';
    $this->start_flow($flow_uuid,'',$cont_alert,$extra);
  }

  public function alert_all (){
    //get county
    $cso = $this->get_cso();
    $cont_alert = $this->get_rapidpro_id($cso);
    foreach($this->notify_group as $group_name) {
      $other_contacts = $this->get_contacts_in_grp (urlencode($group_name));
      if(count($other_contacts)>0)
      $cont_alert = array_merge($cont_alert,$other_contacts);
    }
    //alert all partners
    $msg = "DSO has validated a suspected case of ".$this->disease." Reported By ".$this->reporter_name." From ".$this->reporter_facility["name"]." Patient Details (Age=".$this->age.",Outcome=".$this->outcome.")";
    $this->broadcast($cont_alert,$msg);

    //notify reporter
    $msg = "Thank you for reporting a suspected case of ". $this->disease.",This report was validated by your DSO and has been forwarded for further action.";
    $cont_alert = array($this->reporter_rp_id);
    $this->broadcast($cont_alert,$msg);
    $this->send_to_eidsr();
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

  public function send_to_eidsr() {
    $header = Array(
                    "Content-Type: application/json"
                   );
    $post_data = '{
                    "reportingPerson":"'.$this->reporter_name.'",
                    "reportingPhoneNumber":"'.$this->reporter_phone.'",
                    "facilityName":"'.$this->reporter_facility["name"].'",
                    "diseaseName":"'.$this->disease.'",
                    "patientOutcome":"'.$this->outcome.'",
                    "patientAge":"'.$this->age.'"
                  }';
    error_log($post_data);
    //$this->exec_request($this->eidsr_host,$this->eidsr_user,$this->eidsr_passwd,"POST",$post_data,$header);
  }

  public function exec_request($url,$user,$password,$req_type,$post_data,$header = Array("Content-Type: text/xml")) {
    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    if($req_type=="POST") {
      curl_setopt($curl, CURLOPT_POST, true);
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
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
$reporter_name = $_REQUEST["reporter_name"];
$reporter_globalid = $_REQUEST["reporter_globalid"];
$rapidpro_token = "";
$broadcast_flow_uuid = "d491bc11-35ff-4f65-a2d8-ae51d0ed9288";
$rapidpro_url = "https://app.rapidpro.io/";
$channel = "80ab406b-1a6b-44d5-ad0c-26866bfa844b";//optional
$csd_host = "http://localhost:8984/CSD/";
$csd_user = "csd";
$csd_passwd = "csd";
$csd_doc = "liberia";
$rp_csd_doc = "mhero_liberia_rapidpro";
$eidsr_host = "https://lib-eidsr-dev.ehealthafrica.org/casealert";
$eidsr_user = "";
$eidsr_passwd = "";

$report = str_replace("alert.","",$report);
$mhero = new mhero($reporter_phone,$reporter_name,$report,$reporter_rp_id,$reporter_globalid,$rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$broadcast_flow_uuid,$channel);
if($category == "confirm") {
  $mhero->validate_report();
  $mhero->send_confirmation();
}

else if($category == "alert_all") {
  $mhero->notify_group = array("DPC Group","Assistant Ministers");
  $mhero->disease = $_REQUEST["disease"];
  $mhero->age = $_REQUEST["age"];
  $mhero->outcome = $_REQUEST["outcome"];
  $mhero->lab = $_REQUEST["lab"];
  $mhero->eidsr_host = $eidsr_host;
  $mhero->eidsr_user = $eidsr_user;
  $mhero->eidsr_passwd = $eidsr_passwd;
  $mhero->alert_all();
}
?>