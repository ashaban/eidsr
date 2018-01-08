<?php
require("eidsr_base.php");
class weekly_report extends eidsr_base{
  function __construct(
                        $reporter_phone,$reporter_name,$reporter_rp_id,$reporter_globalid,$rapidpro_token,
                        $rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,
                        $eidsr_passwd,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                      ) {
    parent::__construct(
                        $rapidpro_token,$rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,
                        $eidsr_user,$eidsr_passwd,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                       );
     $this->orchestrations = array();
     $this->response_body = array();
     $this->reporter_phone = $reporter_phone;
     $this->reporter_name = $reporter_name;
     $this->reporter_rp_id = $reporter_rp_id;
     $this->reporter_globalid = $reporter_globalid;
     $this->rapidpro_token = $rapidpro_token;
     $this->rapidpro_host = $rapidpro_url;
     $this->csd_host = $csd_host;
     $this->csd_user = $csd_user;
     $this->csd_passwd = $csd_passwd;
     $this->csd_doc = $csd_doc;
     $this->rp_csd_doc = $rp_csd_doc;
     $this->database = $database;
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
     $this->facility_details = $this->get_provider_facility($this->reporter_globalid);
     //if facility code is missing then stop execution or alert HMIS director
     error_log(print_r($this->facility_details,true));
     array_push($this->response_body,array("Reporter Location Details"=>$this->facility_details));
     $this->county_uuid = $this->facility_details["county_uuid"];
  }

  public function alert_all ($cases,$week_period){
    $msg = $this->reporter_name ." Has submitted a weekly report of $cases aggregate cases from ".$this->facility_details["facility_name"]."(".$this->facility_details["district_name"].",".$this->facility_details["county_name"].") for the week period $week_period";
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
    error_log(print_r($cont_alert,true));
    if(count($cont_alert) > 0) {
      $this->broadcast("Alert CDO",$cont_alert,$msg);
    }
    return;
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
$report_first_day_name = date('l', strtotime("Sunday +{$weekly_report_first_day} days"));
$report = preg_replace('/\s+/', '', $report);
$cases = str_ireplace("testwr.","",$report);
$cases = str_ireplace("wr.","",$cases);
$cases = str_ireplace("wr:","",$cases);
$cases = str_ireplace("testwr:","",$report);
$cases = str_ireplace("wr,","",$cases);
$cases = str_ireplace("testwr,","",$cases);
$cases = str_ireplace("wr","",$cases);
$cases = str_ireplace("testwr","",$report);

error_log("received weekly report with details ".print_r($_REQUEST,true));
$weeklyReport = new weekly_report($reporter_phone,$reporter_name,$reporter_rp_id,$reporter_globalid,$rapidpro_token,
                                  $rapidpro_url,$csd_host,$csd_user,$csd_passwd,$csd_doc,$rp_csd_doc,$eidsr_host,$eidsr_user,
                                  $eidsr_passwd,$openHimTransactionID,$ohimApiHost,$ohimApiUser,$ohimApiPassword,$database
                                 );

$weeklyReport->notify_group = $notify_group;
//if no facility for the reporter then
if($weeklyReport->facility_details["facility_uuid"] == "") {
  $weeklyReport->broadcast("Alert Reporter of Weekly Report",array($reporter_rp_id),"You are not allowed to access EIDSR system");
  $weeklyReport->updateTransaction($openHimTransactionID,"Failed",$weeklyReport->response_body,200,$weeklyReport->orchestrations);
  return;
}

$casesArr = explode(".",$cases);

if(count($casesArr) == 1) {
  $cases = $casesArr[0];
  $day_number = date("N");
  if($day_number < $weekly_report_submission) {
    $week_period = date("j-n-Y",strtotime("$report_first_day_name last week"));
  }
  else {
    //php treats Sunday as the first day of the week
    if(date('N') == 7) {
      $week_period = date('j-n-Y',strtotime("$report_first_day_name last week"));
    }
    else {
      $week_period = date('j-n-Y',strtotime("$report_first_day_name this week"));
    }
  }
  $report = $weeklyReport->find_weekly_report_by_period($week_period,$weeklyReport->facility_details["facility_uuid"]);
  if($report["_id"] != "" or $report["_id"] != null or $report["_id"] != false) {
    $formatted_week_period = date("l jS \of F Y",strtotime($week_period));
    $weeklyReport->broadcast("Alert Reporter of Weekly Report",array($reporter_rp_id),"The weekly report for week of $formatted_week_period already exists,your weekly report was not accepted");
    $weeklyReport->updateTransaction($openHimTransactionID,"Failed",$weeklyReport->response_body,400,$weeklyReport->orchestrations);
    return false;
  }
}
else {
  $weeklyReport->broadcast("Alert Reporter of Weekly Report",array($reporter_rp_id),"The weekly report you submitted is in wrong format,send it in the format wr.total_cases e.g wr.4");
  $weeklyReport->updateTransaction($openHimTransactionID,"Failed",$weeklyReport->response_body,400,$weeklyReport->orchestrations);
  return false;
}
//check if this is a number
if(ctype_digit(strval($cases))) {
  /**to do
  //submit it to the sync server
  */
  $collection = (new MongoDB\Client)->{$database}->weekly_report;
  $date = date("Y-m-d\TH:m:s");
  $insertOneResult = $collection->insertOne([
                                              "trackerid"=>$trackerid,
                                              "cases"=>$cases,
                                              "week_period"=>$week_period,
                                              "reporter_globalid"=>$weeklyReport->reporter_globalid,
                                              "reporter_rapidpro_id"=>$weeklyReport->reporter_rp_id,
                                              "facility_globalid"=>$weeklyReport->facility_details["facility_uuid"],
                                              "facility_code"=>$weeklyReport->facility_details["facility_code"],
                                              "facility_name"=>$weeklyReport->facility_details["facility_name"],
                                              "district_name"=>$weeklyReport->facility_details["district_name"],
                                              "county_name"=>$weeklyReport->facility_details["county_name"],
                                              "openHimTransactionID"=>$weeklyReport->openHimTransactionID,
                                              "date"=>$date
                                            ]);
  //alert stakeholders about this
  $formatted_week_period = date("l jS \of F Y",strtotime($week_period));
  $weeklyReport->alert_all($cases,$formatted_week_period);
  $weeklyReport->updateTransaction($openHimTransactionID,$weeklyReport->transaction_status,$weeklyReport->response_body,200,$weeklyReport->orchestrations);
  error_log("Weekly Report Saved To DB");
}
else {
  $weeklyReport->broadcast("Alert Reporter of Weekly Report",array($reporter_rp_id),"The weekly report you submitted does not include total cases,please resend in the format wr.total_cases e.g wr.4");
  $weeklyReport->updateTransaction($openHimTransactionID,"Failed",$weeklyReport->response_body,400,$weeklyReport->orchestrations);
  return false;
}
?>
