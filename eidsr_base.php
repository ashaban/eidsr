<?php
require ("openHimConfig.php");
require ("openHimUtilities.php");
class eidsr_base extends openHimUtilities {
  function __construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database) {
    parent::__construct($ohimApiHost,$ohimApiUser,$ohimApiPassword);
    $this->rapidpro_token = $rapidpro_token;
    $this->rapidpro_host = $rapidpro_url;
    $this->csd_host = $csd_host;
    $this->csd_user = $csd_user;
    $this->csd_passwd = $csd_passwd;
    $this->csd_doc = $csd_doc;
    $this->database = $database;
    $this->rp_csd_doc = $rp_csd_doc;
    $this->eidsr_host = $eidsr_host;
    $this->eidsr_user = $eidsr_user;
    $this->eidsr_passwd = $eidsr_passwd;
  }

  public function get_provider_facility($provider_uuid) {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$provider_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
    $prov_entity = $this->exec_request("Getting Reporter Details",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    if($prov_entity == "") {
      error_log("An error has ocured,Openinfoman has returned empty results");
      return false;
    }
    $fac_uuid = $this->extract($prov_entity,"/csd:provider/csd:facilities/csd:facility/@entityID",'providerDirectory',true);
    $fac_det = $this->get_facility_details ($fac_uuid,"uuid");
    return $fac_det;
  }

  public function get_facility_details ($facility_identifier,$type) {
    if($type == "code") {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
             <csd:otherID code="code" assigningAuthorityName="ihris.moh.gov.lr">'.
              $facility_identifier.
             '</csd:otherID>
            </csd:requestParams>';
    }
    else if ($type == "uuid") {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
             <csd:id entityID="'.$facility_identifier.'">
             </csd:id>
            </csd:requestParams>';
    }

    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:facility-search";
    $fac_entity = $this->exec_request("Searching Facility",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $facility_uuid = $this->extract($fac_entity,"/csd:facility/@entityID",'facilityDirectory',true);
    $facility_name = $this->extract($fac_entity,"/csd:facility/csd:primaryName",'facilityDirectory',true);
    $facility_code = $this->extract($fac_entity,"/csd:facility/csd:otherID[@code='code']",'facilityDirectory',true);
    $district_uuid = $this->extract($fac_entity,"/csd:facility/csd:organizations/csd:organization/@entityID",'facilityDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$district_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $org_entity = $this->exec_request("Searching District",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_uuid = $this->extract($org_entity,"/csd:organization/csd:parent/@entityID",'organizationDirectory',true);
    $district_name = $this->extract($org_entity,"/csd:organization/csd:primaryName",'organizationDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$county_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $org_entity = $this->exec_request("Searching County",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_name = $this->extract($org_entity,"/csd:organization/csd:primaryName",'organizationDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
            <csd:id entityID="'.$county_uuid.'"/>
            <csd:otherID position="1"/>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:organization_read_otherid";
    $org_entity = $this->exec_request("Getting Other Id",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_code = $this->extract($org_entity,"/csd:organization/csd:otherID",'organizationDirectory',true);

    $fac_det = array("facility_uuid"=>$facility_uuid,
                  "facility_name"=>$facility_name,
                  "facility_code"=>$facility_code,
                  "district_uuid"=>$district_uuid,
                  "district_name"=>$district_name,
                  "county_uuid"=>$county_uuid,
                  "county_name"=>$county_name,
                  "county_code"=>$county_code
                 );
                 error_log(print_r($fac_det,true));
    return $fac_det;
  }

  public function get_rapidpro_id ($provs_uuid = array()) {
    $ids = "";
    if(count($provs_uuid) == 0)
    return array();

    foreach ($provs_uuid as $prov) {
        $ids .= "<csd:id entityID='" . $prov . "'/>\n" ;
      }
    $csr = "<csd:requestParams xmlns:csd='urn:ihe:iti:csd:2013'>"
           . $ids
           ."<csd:code>rapidpro_contact_id</csd:code>"
           ."  </csd:requestParams>" ;
    $url = $this->csd_host."csr/{$this->rp_csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:bulk_health_worker_read_otherids_json";
    $prov_entity = $this->exec_request("Getting Rapidpro Contact UUID",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
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

  public function get_counties () {
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:codedType code="REGION" codingScheme="urn:ihris.org:ihris-manage-liberia:organizations:types"/>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $county_entity = $this->exec_request("Getting Counties",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_uuids = $this->extract($county_entity,"/csd:organization/@entityID",'organizationDirectory',true);
    $county_uuids = explode(";", $county_uuids);
    if(count($county_uuids) == 0) {
      array_push($this->response_body,array("Counties"=>"No Counties Found"));
    }
    return $county_uuids;
  }

  public function get_county_districts($county_uuid) {
    //get districts under $this->county_uuid
    if(!$county_uuid) {
      error_log("County UUID Missing,CSO wont be alerted");
      return array();
    }
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:parent entityID="'.$county_uuid.'"/>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $orgs_entity = $this->exec_request("Getting County Districts",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $orgs_uuids = $this->extract($orgs_entity,"/csd:organization/@entityID",'organizationDirectory',true);
    $distr_uuids = explode(";", $orgs_uuids);
    if(count($distr_uuids) == 0) {
      array_push($this->response_body,array("County Districts"=>"No Districts Found In A County"));
    }
    return $distr_uuids;
  }

  public function get_district_facilities($district_uuid) {
    if(!$district_uuid) {
      error_log("District UUID Missing,DSO wont be alerted");
      return array();
    }
    //get facilities under this county
    $fac_uuids = array();
    global $fac_uuids;
    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
            <csd:organizations>
              <csd:organization  entityID="'.$district_uuid.'"/>
            </csd:organizations>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:facility-search";
    $fac_entity = $this->exec_request("Getting District Facilities",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $fac = $this->extract($fac_entity,"/csd:facility/@entityID",'facilityDirectory',true);
    $fac_uuids = explode(";", $fac);
    if(count($fac_uuids) == 0)
    array_push($this->response_body,array("Districts Facilities"=>"No Facilities Found In A District"));
    return $fac_uuids;
  }

  public function get_dso($district_uuid) {
    if(!$district_uuid) {
      error_log("District UUID Missing,DSO wont be alerted");
      return array();
    }
    //get facilities under $district_uuid
    $fac_uuids = $this->get_district_facilities($district_uuid);
    //foreach facility,fetch providers and check if is DSO
    global $dso;
    $dso = array();
    foreach ($fac_uuids as $fac_uuid) {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:facilities>
            <csd:facility  entityID="'.$fac_uuid.'"/>
           </csd:facilities>
          </csd:requestParams>';
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
      $prov_entity = $this->exec_request("Getting Providers In A Facility",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $prov_entity = new SimpleXMLElement($prov_entity);
      foreach ($prov_entity->providerDirectory->children("urn:ihe:iti:csd:2013") as $prov) {
        global $dso;
        if($prov->extension->position && $prov->extension->position->attributes()->title ) {
          if( $prov->extension->position->attributes()->title == "District Surveillance Officer" or
              strpos($prov->extension->position->attributes()->title,"District Surveillance") !== false or
              $prov->extension->position->attributes()->title == "DSO"){
            $curr_dso = $dso;
            if(count($curr_dso)==0)
              $curr_dso = array();
            $dso1 = array((string)$prov->attributes()->entityID);
            $dso = array_merge($curr_dso,$dso1);
          }
        }
      }
    }
    error_log("DSO===>" . print_r($dso,true));
    if(count($dso) == 0) {
      array_push($this->response_body,array("iHRIS DSO"=>"No DSO Found On The System For Reported Facility"));
    }
    else {
      array_push($this->response_body,array("iHRIS DSO"=>$dso));
    }
    return $dso;
  }

  public function get_cso($county_uuid) {
    $distr_uuids = $this->get_county_districts($county_uuid);
    $fac_uuids = array();
    foreach ($distr_uuids as $distr_uuid) {
      $fac = $this->get_district_facilities($distr_uuid);
      $curr_fac = $fac_uuids;
      if(count($curr_fac)==0)
        $curr_fac = array();
      $fac_uuids = array_merge($curr_fac,$fac);
    }

    //foreach facility,fetch providers and check if is DSO
    global $cso;
    $cso = array();
    foreach ($fac_uuids as $fac_uuid) {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:facilities>
            <csd:facility  entityID="'.$fac_uuid.'"/>
           </csd:facilities>
          </csd:requestParams>';
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
      $prov_entity = $this->exec_request("Getting Providers In A Facility",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $prov_entity = new SimpleXMLElement($prov_entity);
      foreach ($prov_entity->providerDirectory->children("urn:ihe:iti:csd:2013") as $prov) {
        global $cso;
        if($prov->extension->position && $prov->extension->position->attributes()->title ) {
          if( $prov->extension->position->attributes()->title == "County Surveillance Officer" or
              strpos($prov->extension->position->attributes()->title,"County Surveillance") !== false or
              $prov->extension->position->attributes()->title == "CSO"){
            $curr_cso = $cso;
            if(count($curr_cso)==0)
              $curr_cso = array();
            $cso1 = array((string)$prov->attributes()->entityID);
            $cso = array_merge($curr_cso,$cso1);
          }
        }
      }
    }
    error_log("CSO===>" . print_r($cso,true));
    if(count($cso) == 0) {
      array_push($this->response_body,array("iHRIS CSO"=>"No CSO Found On The System For Reported Facility"));
    }
    else {
      array_push($this->response_body,array("iHRIS CSO"=>$cso));
    }
    return $cso;
  }

  public function get_cdo($county_uuid) {
    $distr_uuids = $this->get_county_districts($county_uuid);
    $fac_uuids = array();
    foreach ($distr_uuids as $distr_uuid) {
      $fac = $this->get_district_facilities($distr_uuid);
      $curr_fac = $fac_uuids;
      if(count($curr_fac)==0)
        $curr_fac = array();
      $fac_uuids = array_merge($curr_fac,$fac);
    }

    //foreach facility,fetch providers and check if is DSO
    $cdo = array();
    foreach ($fac_uuids as $fac_uuid) {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
           <csd:facilities>
            <csd:facility  entityID="'.$fac_uuid.'"/>
           </csd:facilities>
          </csd:requestParams>';
      $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:provider-search";
      $prov_entity = $this->exec_request("Getting Providers In A Facility",$url,$this->csd_user,$this->csd_passwd,"POST",$csr);
      $prov_entity = new SimpleXMLElement($prov_entity);
      foreach ($prov_entity->providerDirectory->children("urn:ihe:iti:csd:2013") as $prov) {
        global $cdo;
        if($prov->extension->position && $prov->extension->position->attributes()->title ) {
          if( $prov->extension->position->attributes()->title == "County Diagnostic Officer" or
              strpos($prov->extension->position->attributes()->title,"County Diagnostic") !== false or
              $prov->extension->position->attributes()->title == "CDO"){
            $curr_cdo = $cdo;
            if(count($curr_cdo)==0)
              $curr_cdo = array();
            $cdo1 = array((string)$prov->attributes()->entityID);
            $cdo = array_merge($curr_cdo,$cdo1);
          }
        }
      }
    }
    error_log("CDO===>" . print_r($cdo,true));
    if(count($cdo) == 0) {
      array_push($this->response_body,array("iHRIS CDO"=>"No CDO Found On The System For Reported Facility"));
    }
    else {
      array_push($this->response_body,array("iHRIS CDO"=>$cdo));
    }
    return $cdo;
  }

  public function get_group_uuid ($group_name) {
    $url = $this->rapidpro_host."api/v2/groups.json?name=$group_name";
    $header = Array(
                         "Content-Type: application/json",
                         "Authorization: Token $this->rapidpro_token"
                   );
    $res=  $this->exec_request("Getting Raidpro Group UUID",$url,"","","GET","",$header);
    $res = json_decode($res,true);
    if(count($res["results"]) > 0){
      return $res["results"][0]["uuid"];
    }
  }

  public function get_contacts_in_grp ($group_name) {
    $group_uuid = $this->get_group_uuid ($group_name);
    if($group_uuid == "")
    return array();
    $url = $this->rapidpro_host."api/v2/contacts.json?group=$group_uuid";
    $header = Array(
                         "Content-Type: application/json",
                         "Authorization: Token $this->rapidpro_token"
                   );
    $res=  $this->exec_request("Getting Rapidpro Contacts In A Group",$url,"","","GET","",$header);
    $res = json_decode($res,true);
    if(count($res["results"]) > 0){
      foreach($res["results"] as $conts) {
        $contact_uuids[] = $conts["uuid"];
      }
    }
    return $contact_uuids;
  }

  public function broadcast($subject="",$contacts_uuid = array(),$msg) {
    $url = $this->rapidpro_host."api/v2/broadcasts.json";
      $header = Array(
                       "Content-Type: application/json",
                       "Authorization: Token $this->rapidpro_token",
                     );
      $broadcast_data = array();
      foreach($contacts_uuid as $uuid) {
        $post_data = '{ "contacts": ["'.$uuid.'"], "text": "'.$msg.'" }';
        error_log($post_data);
        array_push($broadcast_data,json_decode($post_data));
        $this->exec_request("Sending Broadcast Message To Rapidpro Contacts",$url,"","","POST",$post_data,$header);
      }
      //push data for reporting to openHIM
      array_push($this->response_body,array($subject=>$broadcast_data));
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
        $this->exec_request("Starting A Workflow",$url,"","","POST",$post_data,$header);
      }
    }
  }

  public function find_weekly_report_by_period($week_period,$facility_globalid) {
    $weekly_reports = (new MongoDB\Client)->{$this->database}->weekly_report;
    $report = $weekly_reports->findOne(array('$and'=>array(
                                                  array('week_period' => $week_period),
                                                  array("facility_globalid"=> $facility_globalid)
                                                )));
    return $report;
  }

  public function find_case_reporters_by_facility_date($start_date,$end_date,$facility_globalid) {
    $weekly_reports = (new MongoDB\Client)->{$this->database}->case_details;
    $filter = array('$and'=>array(
                                  array('date'=>array('$gt' => $start_date,'$lt' => $end_date)),
                                  array("facility_globalid" => $facility_globalid)
                                ));
    if($weekly_reports->count($filter) == 0) {
      $case_reports = (new MongoDB\Client)->{$this->database}->weekly_report;
      $report = $case_reports->find($filter);
    }
    else
    $report = $weekly_reports->find($filter);
    return $report;
  }

  public function exec_request($request_name,$url,$user,$password,$req_type,$post_data,$header = Array("Content-Type: text/xml"),$get_header=false) {
    if($request_name == "") {
      error_log("Name of the request is missing");
      return false;
    }

    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    curl_setopt($curl, CURLOPT_HEADER, true);
    if($req_type == "POST") {
      curl_setopt($curl, CURLOPT_POST, true);
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    else if($req_type == "PUT") {
      curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
      curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
    }
    if($user or $password)
      curl_setopt($curl, CURLOPT_USERPWD, $user.":".$password);
    $curl_out = curl_exec($curl);
    if ($err = curl_errno($curl) ) {
      error_log("An error occured while accessing url ".$url .". CURL error number ".$err);
      return false;
    }

    //Orchestrations
    //prepare data for orchestration
    $status_code = curl_getinfo($curl,CURLINFO_HTTP_CODE);
    if($status_code >= 400 && $status_code <= 600)
    $this->transaction_status = "Completed with error(s)";
    $beforeTimestamp = date("Y-m-d G:i:s");
    $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $header = substr($curl_out, 0, $header_size);
    $body = substr($curl_out, $header_size);
    //implement in case header is missing
    //send orchestration
    $newOrchestration = $this->buildOrchestration($request_name,$beforeTimestamp,$req_type,$url,$post_data,$status_code,$header,$body);
    array_push($this->orchestrations, $newOrchestration);
    //End of orchestration

    curl_close($curl);
    if($get_header === false) {
      return $body;
    }
    else
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

?>
