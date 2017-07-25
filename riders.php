<?php
require("eidsr_base.php");

class riders extends eidsr_base {
  function __construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$samples,$reporter_rp_id) {
    parent::__construct($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd);
    $this->samples = $samples;
    $this->reporter_rp_id = $reporter_rp_id;
    $this->notify_group = array("DPC Group","National Reference Lab");
  }

  public function sample_action($action) {
    $reported_cases = file_get_contents("reported_cases.json");
    $reported_cases = json_decode($reported_cases,true);
    $samples = str_ireplace("picked","",$this->samples);
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

    if(count($samples) == 0){
      $extra = '"status":"incomplete"';
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
      return false;
    }

    if($action == "sample_delivered") {
      $status = "delivered";
    }
    else if($action == "sample_picked") {
      $status = "picked";
    }

    foreach($samples as $sample) {
      if($sample == "")
      continue;
      $sample_found = false;
      $sample = strtoupper(trim($sample));
      foreach ($reported_cases as $case) {
        if(in_array($sample,$case)) {
          $sample_found = true;
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
            $msg .= " Which was Reported From your facility (".$facility_details["facility_name"].",".$facility_details["district_name"].")";
            $this->broadcast($cont_alert,$msg);
          }
          break;
        }
      }
      if(!$sample_found) {
        if(!$missing)
        $missing = $sample;
        else
        $missing .= ",".$sample;
      }
    }
    if($missing) {
      $extra = '"status":"not_found","samples":"'.$missing.'"';
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
      return false;
    }
    else {
      $extra = '"status":"success"';
      $this->start_flow($this->flow_uuid,"",array($this->reporter_rp_id),$extra);
      return true;
    }
  }

}

/*This code sends a response to rapidpro and continue execution of the rest
This is important because rapidpro webhook calling has a wait time limit,if exceeded then it will show the webhook calling has failed
*/
//$_REQUEST = array("category" => "sample_picked","samples"=>"Picked.grc-21m4-006","reporter_phone" => "088 684 7915","reporter_name" => "Stephen Mambu Gbanyan","reporter_rp_id" => "3124c792-c322-4aed-8206-b7bcedddd46f","reporter_globalid" =>"urn:uuid:a5547568-a24c-39b7-b895-734ed8a777f2");
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
$category = $_REQUEST["category"];
$samples = $_REQUEST["samples"];
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
require("test_config.php");
$obj = new riders($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,
                  $csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,$samples,
                  $reporter_rp_id,$category
                 );

if($category == "sample_picked") {
  $obj->flow_uuid = "90b9c70e-1b2f-4815-a704-14e01c307655";
  $obj->sample_action("sample_picked");
  }
else if($category == "sample_delivered") {
  $obj->flow_uuid = "81a2f0ed-2451-4db0-8a92-7716ead14e16";
  $obj->sample_action("sample_delivered");
  }
?>
