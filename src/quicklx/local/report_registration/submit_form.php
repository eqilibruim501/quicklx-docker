<?php
require_once(dirname(__FILE__).'/../../config.php');
require_once($CFG->libdir.'/tcpdf/tcpdf.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->dirroot.'/local/base/locallib.php');
require_once('locallib.php');

require_login($SITE);

if(isset($_POST['contactFrmSubmit']) 
			&& !empty($_POST['emailsubject'])
			&& !empty($_POST['format'])
			&& !empty($_POST['attach'])
			&& !empty($_POST['toemail']) 
			&& !empty($_POST['emailbody'])){
    
    // Submitted form data
    $params=array();
 
	if (isset($_POST['user']) && $_POST['user']) {
		$params['user'] = $_POST['user'];
	}
	if (isset($_POST['firstname']) && $_POST['firstname']) {
		$params['firstname'] = $_POST['firstname'];
	}
	if (isset($_POST['lastname']) && $_POST['lastname']) {
		$params['lastname'] = $_POST['lastname'];
	}
	if (isset($_POST['username']) && $_POST['username']) {
		$params['username'] = $_POST['username'];
	}
	if (isset($_POST['email']) && $_POST['email']) {
		$params['email'] = $_POST['email'];
	}
	if (isset($_POST['organization']) && $_POST['organization']) {
		$params['organization'] =$_POST['organization'];
	}
	if (isset($_POST['activestatus']) && $_POST['activestatus']) {
		$params['activestatus'] = $_POST['activestatus'];
	}
	if (isset($_POST['country']) && $_POST['country']) {
		$params['country'] = $_POST['country'];
	}

	if (isset($_POST['daterange']) && $_POST['daterange']) {
		$params['daterange'] = $_POST['daterange'];
	}
	if(isset($_POST['datefrom']) && $_POST['datefrom']!=0){
		$params['datefrom'] = $_POST['datefrom'];
	}
	if(isset($_POST['dateto']) && $_POST['dateto']!=0){
		$params['dateto'] = $_POST['dateto'];
	}
	if (isset($_POST['course']) && $_POST['course']) {
		$params['course'] = $_POST['course'];
	}
	if (isset($_POST['departmentid']) && $_POST['departmentid']) {
		$params['departmentid'] =$_POST['departmentid'];
	}		
	if (isset($_POST['subgroup']) && $_POST['subgroup']) {
		$params['subgroup'] =$_POST['subgroup'];
	}		
	if (isset($_POST['eventtype']) && $_POST['eventtype']) {
		$params['eventtype'] =$_POST['eventtype'];
	}		
	if (isset($_POST['license']) && $_POST['license']) {
		$params['license'] =$_POST['license'];
	}
	if (isset($_POST['showcoursedata']) && $_POST['showcoursedata']) {
		$params['showcoursedata'] =$_POST['showcoursedata'];
		$showcoursedata =$_POST['showcoursedata'];
	}
    $departmentid = $_POST['departmentid'];   
    $email  = $_POST['toemail'];
    $emailsubject  = $_POST['emailsubject'];
    $emailbody  = $_POST['emailbody'];
    $format  = $_POST['format'];
    $attach  = $_POST['attach'];
    	//print_r($params);
    $params['download'] = 'download';
	$returnobj = report_registration::get_all_license_events($params);
	$exportuserdataobj= $returnobj->events ;
	unset($params['download']); 
	
	$countries = get_string_manager()->get_list_of_countries();
		
	if($exportuserdataobj){
		$fileLocation = $CFG->tempdir; 
		$fileLocation = rtrim($fileLocation, '/') . '/';
		if(isset($params['daterange']) && $params['daterange'] =='no' ){
			unset($params['datefrom']); 
			unset($params['dateto']); 	 
		}
		$reporthead = 'Registration Report';
		if($params['daterange'] !='no'){
			$reportdaterange = 'Date Range: '. get_string($params['daterange'], 'local_report_registration').'<br>';
			$reportdate = date('m/d/Y',$params['datefrom']).' to '.date('m/d/Y',$params['dateto']);
		}
		else{
			$reportdaterange =  get_string($params['daterange'], 'local_base'); 
			$reportdate =  '';
		}
			
		if($format=='pdf'){
			$pdf = base::createpdfobject();

		}
		if($format=='pdf'  || $format=='html'){

			 $tablepdf ='<table class="generaltable" id="ReportTable">
						<thead >
						<tr style="background-color: rgb(203, 205, 208);">
						<th class="header c4" style="text-align:center;" >'.get_string('eventdate', 'local_report_registration').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('licensename', 'local_report_registration').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('course', 'local_base').'</th>
						<th  class="header c0" style="text-align:center;" >'.get_string('eventtype', 'local_report_registration').'</th>
						<th  class="header c1" style="text-align:center;" >'.get_string('username', 'local_base').'</th>
						<th class="header c3" style="text-align:center;" >'.get_string('firstname', 'local_base').'</th>
						<th class="header c3" style="text-align:center;" >'.get_string('lastname', 'local_base').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('country', 'local_base').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('organization', 'local_base').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('subgroup', 'local_base').'</th>
						<th colspan="2" class="header c4" style="text-align:center;" >'.get_string('email', 'local_base').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('phone', 'local_base').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('modifiedby', 'local_report_registration').'</th>
						<th class="header c4" style="text-align:center;" >'.get_string('userid', 'local_report_registration').'</th>
						</tr>
						</thead>
						<tbody> ';
		}
		if($format =='csv' || $format =='xlsx' ){
			$csvdata   ='"'.$reporthead."\"\n"	;
			$csvdata  .='"'.$reportdaterange."\"\n"	;
			$csvdata  .='"'.$reportdate."\"\n"	;
			$csvdata  .= '"'.get_string('eventdate', 'local_report_registration').'","'
							.get_string('licensename', 'local_report_registration').'","'
							.get_string('course', 'local_base').'","'
							.get_string('eventtype', 'local_report_registration').'","'
							.get_string('username', 'local_base').'","'
							.get_string('firstname', 'local_base').'","'
							.get_string('lastname', 'local_base').'","'
							.get_string('country', 'local_base').'","'
							.get_string('organization', 'local_base').'","'
							.get_string('subgroup', 'local_base').'","'
							.get_string('email', 'local_base').'","'
							.get_string('phone', 'local_base').'","'
							.get_string('modifiedby', 'local_report_registration').'","'
							.get_string('userid', 'local_report_registration')."\"\n"	;
		}
		$i=1;

		foreach($exportuserdataobj as $logevent){

	       	if( $logevent->action == "assigned"){

				if(!empty($logevent->other)){
					$other = explode('"',$logevent->other);
					if(is_numeric($other[3]))
						$licenseid =$other[3];
				}
				else{		
					$licenseid=$logevent->objectid;
				}	
				if(!isset($licenseid)){

					$companylicense_users =$DB->get_record('companylicense_users',array('id'=>$logevent->objectid,
																						'userid'=>$logevent->userid,
																						'licensecourseid'=>$logevent->courseid));

					if($companylicense_users){
						 $licenseid = $companylicense_users->licenseid;
					}
					else{		
						$licenseid=$logevent->objectid;
					}
				}


			}
			else {
				$licenseid=$logevent->objectid;
			}

			//  Custom Code - Syllametrics - Updated (12/03/2019)
			$toprint =1;
		
			if(!isset($showcoursedata)){
				if(isset($prvlogevent) && $prvlogevent->userid==$logevent->userid && 
					$prvlicenseid==$licenseid && 
					$prvlogevent->eventname==$logevent->eventname) {
						$toprint =0;
				}
			}
			
			if(isset($params['license'])){
				if($licenseid ==$params['license'])
					$toprint =1;
				else
					$toprint =0;
			}

			$prvlogevent= $logevent;
			$prvlicenseid = $licenseid;
			
			
					
			if($toprint ==1){
				if(isset($showcoursedata)){
					$courseid=$logevent->courseid;
					$course=$DB->get_record('course',array('id'=>$courseid));
					if($course)
						$coursefullname = $course->fullname;
					else
						$coursefullname = "";
				}
				else
						$coursefullname = "";
						
				// end updated (12/03/2019)




				/*$other = explode('"',$logevent->other);
				if(is_numeric($other[3]))
					$licenseid =$other[3];
				else
					$licenseid = $logevent->objectid;
				*/
				$userid=$logevent->userid;
				//$user=$DB->get_record('user',array('id'=>$userid));
				$courseid=$logevent->courseid;
				/*$course=$DB->get_record('course',array('id'=>$courseid));
				if($course)
					$coursefullname = $course->fullname;
				else
					$coursefullname = "";*/
				$showcountry="";
				foreach($countries as $key=>$value){
				if($logevent->country ==$key) {
					//if($user->country ==$key ){
						$showcountry = $value;
						break;
					}
					
				}		
				/* $sql = "select distinct(c.id),name from {company} c 
				join  {company_users} cu on c.id=cu.companyid 
				where cu.userid=".$userid;
				*/

		        $orgname = $logevent->compname;
		        $subgroupname = $logevent->deptname;

		        $comapnyid = $logevent->compid;
		        $licensename = $logevent->licname;
		        if (!$licensename) {
					$license = report_registration::get_company_license($licenseid);
					if($license) {
						$licensename = $license->name;
						$logevent->expirydate = $license->expirydate;
					} else {
						$licensename = "N/A";
					}
		        }
		        if ($comapnyid) {
		                $action = report_registration::get_action($comapnyid,$logevent);
		        } else {
		                $action = "";
		        }

				/*if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
							$sql .=" AND cu.departmentid in (".$params['subgroup'].")";
				}
				$userorganizations = $DB->get_records_sql($sql);
				if($userorganizations){
					$orgname = "";
					foreach($userorganizations as $userorganization){
						$orgname .= $userorganization->name.',';
					}
					$orgname = trim($orgname,',');
					 $sql = "select distinct(d.id),name from {department} d 
								join  {company_users} cu on d.id=cu.departmentid 
								where cu.userid=".$userid." and d.parent <> 0";
					if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
								$sql .=" AND cu.departmentid in (".$params['subgroup'].")";
					}
					$usersubgroups = $DB->get_records_sql($sql);
					$subgroupname = "";
					if($usersubgroups){
						foreach($usersubgroups as $usersubgroup){
							$subgroupname .= $usersubgroup->name.',';
						}
						$subgroupname = trim($subgroupname,',');
					}
						$license = report_registration::get_company_license($licenseid);
						if($license)
							$licensename = $license->name;
						else
							$licensename = "";
						$action = report_registration::get_action($licenseid,$logevent);
				}
				else{
					$orgname = "No";
					$subgroupname = "No";
					$licensename = "NO";
					$action = "";
				}*/
						
				if($format=='pdf'  || $format=='html'){
					if($i%2==0)
						$style = 'background-color: #ece9e9;';
					else
						$style ='';								  			
					$tablepdf  .= '<tr style="'.$style.'">
									<td style="text-align:left;">'.date('m/d/Y',$logevent->timecreated).' </td>
									<td style="text-align:left;">'.$licensename.'</td>
									<td style="text-align:left;">'.$logevent->fullname.' </td>
									<td style="text-align:left;">'.$action.' </td>
									<td style="text-align:left;">'.$logevent->username.' </td>
									<td style="text-align:left;">'.$logevent->firstname.' </td>
									<td style="text-align:left;" colspan="2" >'.$logevent->lastname.' </td>
									<td style="text-align:left;">'.$showcountry.' </td>
									<td style="text-align:left;">'.$orgname.' </td>
									<td style="text-align:left;">'.$subgroupname.'</td>
									<td style="text-align:left;">'.$logevent->email.' </td>
									<td style="text-align:left;">'.$logevent->phone2.' </td>
									<td style="text-align:left;">'.''.'</td>
									<td style="text-align:left;">'.$user->id.'</td>
								</tr>';
		   			$i++;
				}
				if($format =='csv' || $format =='xlsx'){
					$csvdata .= '"'.date('m/d/Y',$logevent->timecreated).
								'","'.$licensename.
								//'","'.$coursefullname.
								'","'.$logevent->fullname.
								'","'.$action.
								/*'","'.$user->username.
								'","'.$user->firstname.
								'","'.$user->lastname.*/
								'","'.$logevent->username.
								'","'.$logevent->firstname.
								'","'.$logevent->lastname.
								'","'.$showcountry.
								'","'.$orgname.
								'","'.$subgroupname.
								/*'","'.$user->email.
								'","'.$user->phone2.
								'","'.''.
								'","'.$user->id. "\"\n";*/
								'","'.$logevent->email.
								'","'.$logevent->phone2.
								'","'.''.
								'","'.$logevent->uid. "\"\n";
				}
			}	
		}
		if($format=='pdf'  || $format=='html'){
			$tablepdf .= '	</tbody>
						</table>
			';
		
		}
		if($format=='html'){
		
			$fileLocation = '';
			$filename = '';
		}	
                if($format=='pdf' ){
                        $fileLocation = $CFG->tempdir;
                        while (true) {
                                $filename = uniqid('registration', true) . '.pdf';
                                if (!file_exists($fileLocation .'/'. $filename)) break;
                        }
                        base::create_tempdf($reporthead,$reportdaterange.'<br>'.$reportdate,$tablepdf,$filename);
                }
                if($format =='csv' || $format =='xlsx'){
                        $fileLocation = $CFG->tempdir;
                        while (true) {
                                $filename = uniqid('registration', true) . '.csv';
                                if (!file_exists($fileLocation .'/'. $filename)) break;
                        }
                        file_put_contents($fileLocation.'/'.$filename, $csvdata);
                }
                if($format =='xlsx')
                {
                    include "$CFG->libdir/phpexcel/PHPExcel/IOFactory.php";
                    $objReader = PHPExcel_IOFactory::createReader('CSV');
                    $objPHPExcel = $objReader->load($fileLocation.'/'.$filename);
                    $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel2007');
                    $filename = "registration_". date('Y-m-d-H-i-s') . '.xlsx';
                    $objWriter->save($fileLocation.'/'.$filename);
                }

	}
		
    $emailarray=explode(";",$email);
    
     // Send email
	$eventdata = new stdClass();
	$eventdata->subject           = $emailsubject;
	$eventdata->fullmessage   = $emailbody;
	if($format=='html')
		$eventdata->fullmessagehtml   = $tablepdf;
	else
		$eventdata->fullmessagehtml   = '';

	$sendfrom=get_admin();
	foreach($emailarray as $key=>$value){
		//echo $value;
		if(!empty($value)){
			$sendto=$DB->get_record('user',array('email'=>$value));
			if(!$sendto){
				$sendto = core_user::get_user(1);
				$sendto->email = $value;
				$sendto->firstname = '';
				$sendto->lastname='';
			}			 
			if(email_to_user($sendto, $sendfrom,$eventdata->subject,$eventdata->fullmessage ,$eventdata->fullmessagehtml, $fileLocation.'/'.$filename, $filename)){
					$status = 'ok';
			}else{
				$status = 'err';
			}
		} 
	 }
	if(!empty($users) ){
		 foreach($users as $user){	
			if(email_to_user($user, $sendfrom,$eventdata->subject,$eventdata->fullmessage ,$eventdata->fullmessagehtml, $fileLocation.'/'.$filename, $filename)){
				$status = 'ok';
			}else{
				$status = 'err';
			}
		 
		 }
	 }
    
    // Output status
    echo $status;die;
}
