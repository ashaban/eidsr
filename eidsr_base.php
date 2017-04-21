<?php
class eidsr_base {
  function __construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd) {
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
  }

  public function get_facility_details ($facility_identifier,$type) {
    if($type == "code") {
      $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
             <csd:otherID code="code" assigningAuthorityName="http://localhost/ihris-manage-site">'.
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
    $fac_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $facility_uuid = $this->extract($fac_entity,"/csd:facility/@entityID",'facilityDirectory',true);
    $facility_name = $this->extract($fac_entity,"/csd:facility/csd:primaryName",'facilityDirectory',true);
    $facility_code = $this->extract($fac_entity,"/csd:facility/csd:otherID[@code='code']",'facilityDirectory',true);
    $district_uuid = $this->extract($fac_entity,"/csd:facility/csd:organizations/csd:organization/@entityID",'facilityDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$district_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_uuid = $this->extract($org_entity,"/csd:organization/csd:parent/@entityID",'organizationDirectory',true);
    $district_name = $this->extract($org_entity,"/csd:organization/csd:primaryName",'organizationDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
          <csd:id entityID="'.$county_uuid.'">
          </csd:id>
        </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:ihe:iti:csd:2014:stored-function:organization-search";
    $org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
    $county_name = $this->extract($org_entity,"/csd:organization/csd:primaryName",'organizationDirectory',true);

    $csr='<csd:requestParams xmlns:csd="urn:ihe:iti:csd:2013">
            <csd:id entityID="'.$county_uuid.'"/>
            <csd:otherID position="1"/>
          </csd:requestParams>';
    $url = $this->csd_host."csr/{$this->csd_doc}/careServicesRequest/urn:openhie.org:openinfoman-hwr:stored-function:organization_read_otherid";
    $org_entity = $this->exec_request($url,$this->csd_user,$this->csd_passwd,"POST",$csr);
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
    return $fac_det;
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

  public function get_dso($district_uuid) {
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

  public function get_cso($county_uuid) {
    //get districts under $this->county_uuid
    if(!$county_uuid) {
      error_log("County UUID Missing,CSO wont be alerted");
      return array();
    }
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

  public function broadcast($contacts_uuid = array(),$msg) {
    $url = $this->rapidpro_host."api/v2/broadcasts.json";
      $header = Array(
                       "Content-Type: application/json",
                       "Authorization: Token $this->rapidpro_token",
                     );
      foreach($contacts_uuid as $uuid) {
        $post_data = '{ "contacts": ["'.$uuid.'"], "text": "'.$msg.'" }';
        error_log($post_data);
      //$this->exec_request($url,"","","POST",$post_data,$header);
      }
  }

  public function exec_request($url,$user,$password,$req_type,$post_data,$header = Array("Content-Type: text/xml"),$get_header=false) {
    $curl =  curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, false);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
    if($get_header)
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

?>
