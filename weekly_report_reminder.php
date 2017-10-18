<?php
require("eidsr_base.php");
class weekly_reminders extends eidsr_base{
  function __construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,
                        $eidsr_host,$eidsr_user,$eidsr_passwd,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                      ) {
    parent::__construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                       );
     $this->orchestrations = array();
     $this->response_body = array();
     /*
     $this->reporter_phone = $reporter_phone;
     $this->reporter_name = $reporter_name;
     $this->reporter_rp_id = $reporter_rp_id;
     $this->reporter_globalid = $reporter_globalid;
     */
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
  }

  public function scan_missing_reports() {
    $counties = $this->get_counties();
    foreach($counties as $county) {
      $districts = $this->get_county_districts($county);
      foreach($districts as $district) {
        $facilities = $this->get_district_facilities($district);
        foreach($facilities as $facility) {
          $week_period = 
          $weekly_report = (new MongoDB\Client)->eidsr->weekly_report;
          $case = $weekly_report->findOne(['trackerid' => $eidsr->trackerid]);
        }
      }
    }
  }

  public function alert_all (){
    $msg = $this->reporter_name ." Has submitted a weekly report of $cases aggregate cases from ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].")";
    $cont_alert = array();
    foreach($this->notify_group as $group_name) {
      $other_contacts = $this->get_contacts_in_grp(urlencode($group_name));
      if(count($other_contacts)>0)
      $cont_alert = array_merge($cont_alert,$other_contacts);
    }
    //alert all partners
    if(count($cont_alert) > 0) {
      $this->broadcast("Alert DPC And Others",$cont_alert,$msg);
    }

    //alert CSO
    $cso = $this->get_cso($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cso);
    if(count($cont_alert) > 0) {
      $this->broadcast("Alert CSO",$cont_alert,$msg);
    }

    //alert DSO
    $dso = $this->get_dso($this->facility_details["district_uuid"]);
    $cont_alert = $this->get_rapidpro_id($dso);
    if(count($cont_alert) > 0) {
      $this->broadcast("Alert DSO",$cont_alert,$msg);
    }

    //alert CDO
    $cdo = $this->get_cdo($this->facility_details["county_uuid"]);
    $cont_alert = $this->get_rapidpro_id($cdo);
    if(count($cont_alert) > 0) {
      $this->broadcast("Alert CDO",$cont_alert,$msg);
    }
    return;
  }
}

class missing_report {
  public function scan_missing_reports () {

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
$reporter_phone = $_REQUEST["reporter_phone"];
$report = $_REQUEST["report"];
$reporter_rp_id = $_REQUEST["reporter_rp_id"];
$reporter_name = $_REQUEST["reporter_name"];
$reporter_globalid = $_REQUEST["reporter_globalid"];
$cases = str_ireplace("testwr.","",$report);
$cases = str_ireplace("wr.","",$report);

$weeklyReminders = new weekly_reminders($rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,
                                  $eidsr_host,$eidsr_user,$eidsr_passwd,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword
                                 );
?>