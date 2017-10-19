<?php
require("eidsr_base.php");
class weekly_reminders extends eidsr_base{
  function __construct(
                        $weekly_report_submission,$report_first_day_name,$rapidpro_token,$rapidpro_url,
                        $csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,
                        $openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                      ) {
    parent::__construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                       );
     $this->orchestrations = array();
     $this->response_body = array();
     /*
     $this->reporter_phone = $reporter_phone;
     $this->reporter_name = $reporter_name;
     $this->reporter_rp_id = $reporter_rp_id;
     $this->reporter_globalid = $reporter_globalid;
     */
     $this->weekly_report_submission = $weekly_report_submission;
     $this->report_first_day_name = $report_first_day_name;
     $this->database = $database;
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
        foreach($facilities as $facility_globalid) {
          $day_number = date("N");
          if($day_number < $this->weekly_report_submission) {
            $week_period = date("j-n-Y",strtotime("$this->report_first_day_name last week"));
          }
          else {
            //php treats Sunday as the first day of the week
            if(date('N') == 7) {
              $week_period = date('j-n-Y',strtotime("$this->report_first_day_name last week"));
            }
            else {
              $week_period = date('j-n-Y',strtotime("$this->report_first_day_name this week"));
            }
          }
          $report = $this->find_weekly_report_by_period($week_period,$facility_globalid);
          if($report["_id"] != "" or $report["_id"] != null or $report["_id"] != false) {
            continue;
          }
          else {
            $formatted_week_period = date("l jS \of F Y",strtotime($week_period));
            $start_date = date("Y-m-d\T00:00:00",strtotime("this month -2 months"));
            $end_date = date("Y-m-d\T23:59:59");
            $wrs = $this->find_case_reporters_by_facility_date($start_date,$end_date,$facility_globalid);
            $reminded = array();
            foreach($wrs as $wr) {
              //lets check if your are still working on this facility,otherwise we dont send you an alert
              $fac_det = $this->get_provider_facility($wr["reporter_globalid"]);
              if($fac_det["facility_uuid"]!=$wr["facility_globalid"])
              continue;
              if(in_array($wr["reporter_rapidpro_id"],$reminded))
              continue;
              $msg = "Hi, this is a reminder to submit the weekly EPI Case Report for the week of $formatted_week_period in your facility ".$wr["facility_name"].".Send an SMS in the format wr.total_cases to the short code 4636";
              $this->broadcast("Remind Facility About Weekly Report",array($wr["reporter_rapidpro_id"]),$msg);
              $reminded[] = $wr["reporter_rapidpro_id"];
            }
          }
        }
      }
    }
  }

  public function alert_all ($msg){
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

//$headers = getallheaders();
//$openHimTransactionID = $headers["X-OpenHIM-TransactionID"];
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
$report_first_day_name = date('l', strtotime("Sunday +{$weekly_report_first_day} days"));
$weeklyReminders = new weekly_reminders($weekly_report_submission,$report_first_day_name,$rapidpro_token,$rapidpro_url,
                                        $csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,$eidsr_passwd,
                                        $openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                                       );
$weeklyReminders->scan_missing_reports();
?>
