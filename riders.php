<?php
require("eidsr_base.php");

class riders extends eidsr_base {
  function __construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$samples) {
    parent::__construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd);
    $this->samples = $samples;
    $this->notify_group = array("DPC Group");
  }

  public function sample_action($action) {
    $reported_cases = file_get_contents("reported_cases.json");
    $reported_cases = json_decode($reported_cases,true);
    $samples = str_ireplace("picked","",$this->samples);
    $samples = explode(".",$samples);
    $lab = "";

    if($action == "sample_delivered") {
      $lab = end($samples);
      $test_lab = explode("-",$lab);
      $lab = "";
      reset($samples);
      if(count($test_lab) == 1)
      $lab = end($samples);
      reset($samples);
    }

    if($action == "sample_delivered") {
      $status = "delivered";
    }
    else if($action == "sample_picked") {
      $status = "picked";
    }

    foreach($samples as $sample) {
      foreach ($reported_cases as $case) {
        if(in_array($sample,$case)) {
          $cont_alert = array();
          $facility_code = $case["facility_code"];
          $disease_name = $case ["disease_name"];
          $facility_details = $this->get_facility_details ($facility_code,"code");
          $msg = "Rider has ".$status." a sample of a Suspected case of ".$disease_name;
          if($lab)
          $msg .= " To ".$lab." Lab,";
          $msg .= " Which was Reported From ".$facility_details["facility_name"].",".$facility_details["district_name"];

          //alert DSO
          $dso = $this->get_dso($facility_details["district_uuid"]);
          $cont_alert = $this->get_rapidpro_id($dso);
          $this->broadcast($cont_alert,$msg);

          //alert CSO
          $cso = $this->get_cso($facility_details["county_uuid"]);
          $cont_alert = $this->get_rapidpro_id($cso);
          $this->broadcast($cont_alert,$msg);

          //alert others
          $cont_alert = array();
          foreach($this->notify_group as $group_name) {
            $other_contacts = $this->get_contacts_in_grp(urlencode($group_name));
            if(count($other_contacts)>0)
            $cont_alert = array_merge($cont_alert,$other_contacts);
          }
          if(count($cont_alert) > 0)
          $this->broadcast($cont_alert,$msg);

          //if delivered,alert HW
          if($action == "sample_delivered") {
            $cont_alert = $this->get_rapidpro_id(array($case["reporter_globalid"]));
            $msg = "Rider has ".$status." a sample of a Suspected case of ".$disease_name;
            if($lab)
            $msg .= " To ".$lab." Lab,";
            $msg .= " Which was Reported From your facility (".$facility_details["facility_name"].",".$facility_details["district_name"].")
            ";
            $this->broadcast($cont_alert,$msg);
          }
          break;
        }
      }
    }
  }

}

require("config.php");
require("test_config.php");
$obj = new riders($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,
                  $csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$samples
                 );
$category = "sample_delivered";
if($category == "sample_picked")
$obj->sample_action("sample_picked");
else if($category == "sample_delivered")
$obj->sample_action("sample_delivered");
?>
