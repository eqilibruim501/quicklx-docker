<?php

class base {
  
    /**
     * Get all users info regardless of course
     *
     * Parameters - $departmentid = int;
     *              $page = int;
     *              $perpade = int;
     *
     * Return array();
     **/
    public static function get_all_user($departmentid, $courseid=0,$params){
         global $DB,$USER;
//print_r($params);       
         if(isset($params['page']))
			$page =$params['page'];
		else
			$page =0;
		if(isset($params['perpage']))
          $perpage = $params['perpage'];
          
         if(isset($params['daterange']) && $params['daterange'] =='no' ){
				unset($params['datefrom']); 
				unset($params['dateto']); 	 
		 }
        
         if(isset($params['daterange']) && $params['daterange'] =='datereg' ){
			$beginOfDay = strtotime("midnight", $params['datefrom']);
			$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
			$courseregisterd=" AND ue.timecreated > $beginOfDay AND  ue.timecreated < $endOfDay";
		}
		else
			$courseregisterd = '';
	if(isset($params['departmentid'])){
			 $departmentid = $params['departmentid'];
			$alldepartments = company::get_all_subdepartments($departmentid);
	}
		      
	if(isset($params['organization']) && $params['organization'] != "0"){
		//print_r($params['organization']);
		$organizations =explode(",",$params['organization']);
		$alldepartments =array();
		foreach($organizations as $key=>$value){
			$companyid = $value;
			$department = $DB->get_record('department', array('parent' => 0,'company'=>$companyid));
			$departmentid = $department->id;
			$orgdepartments = company::get_all_subdepartments($departmentid);
			foreach($orgdepartments as $key=>$value){
				$alldepartments[$key] = $value;
			}

		}
			
	}		
        
        // Create a temporary table to hold the userids.
        $temptablename = 'tmp_'.uniqid();
        $dbman = $DB->get_manager();

        // Define table user to be created.
        $table = new xmldb_table($temptablename);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_temp_table($table);
        
        if (count($alldepartments) > 0 ) {
    		$departmentids = implode(',', array_keys($alldepartments));
            // Deal with suspended or not.  
		$searchusername='';
		$searchfirstname='';
		$searchlastname='';
		$searchemail='';
		$searchcountry='';   
		$searchcreatedon='';
		$searchuserregon='';

		if(isset($params['subgroup']) && $params['subgroup'] != "0"  ){
			$departmentids =  $params['subgroup'];
		}
			if(isset($params['daterange']) && $params['daterange'] =='createdon' ){
				$beginOfDay = strtotime("midnight", $params['datefrom']);
				$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
				$searchcreatedon=" AND timecreated > $beginOfDay AND  timecreated < $endOfDay";
			}
            
			if(isset($params['daterange']) && $params['daterange'] =='userdatereg' ){
				$beginOfDay = strtotime("midnight", $params['datefrom']);
				$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
				$searchuserregon=" AND firstaccess > $beginOfDay AND  firstaccess < $endOfDay";
			}
            if(isset($params['username']) )
					 $searchusername = " AND userid IN (".$params['username'].") ";
					 //$searchusername = " AND username LIKE '%".$params['username']."%' ";
       
            if(isset($params['firstname']) )
					 $searchfirstname = " AND firstname LIKE '%".$params['firstname']."%' ";
       
            if(isset($params['lastname']) )
					 $searchlastname = " AND lastname LIKE '%".$params['lastname']."%' ";
					 
            if(isset($params['email']) )
					 $searchemail = " AND email LIKE '%".$params['email']."%' ";
       
            if(isset($params['country']) )
					 $searchcountry = " AND country = '".$params['country']."' ";
       
			$usernamefilter =$searchusername.$searchfirstname.$searchlastname.$searchemail.$searchcountry.$searchcreatedon.$searchuserregon;
			
             $enrolledsql = " ";
              if(isset($params['enrolledstatus']) && $params['enrolledstatus'] == 1)
					 $enrolledsql = " AND userid IN (select distinct(userid) FROM {user_enrolments} ) ";

              if(isset($params['enrolledstatus']) && $params['enrolledstatus'] == 2)
					 $enrolledsql = " AND userid NOT IN (select distinct(userid) FROM {user_enrolments} ) ";


$completionsql = " ";
			if(isset($params['completionstatus'])){
				$sql = 'select distinct(userid) FROM {course_completions} where timecompleted IS NOT NULL';
				 if(isset($params['course']) && $params['course'] != "0" ){
					 $coursesearch = $params['course'];
					$sql .=  " AND course in ($coursesearch)" ;
				 }
				$userids = $DB->get_records_sql($sql);
				if($userids){
					foreach($userids as $userid)
						$ids[] = $userid->userid;
					 $id = implode(',',$ids);
					if($params['completionstatus'] == 2)
						$completionsql = " AND userid NOT IN ($id )";
					else if($params['completionstatus'] == 3)
						$completionsql = " AND userid  IN ($id )";
				}
				
			}

             $suspendedsql = " AND userid IN (select id FROM {user} WHERE 1 $usernamefilter) ";
              if(isset($params['activestatus']) && $params['activestatus'] == '1')
					 $suspendedsql = " AND userid IN (select id FROM {user} WHERE suspended = 0 AND deleted=0 $usernamefilter) ";

              if(isset($params['activestatus']) && $params['activestatus'] == '2')
					 $suspendedsql = " AND userid IN (select id FROM {user} WHERE suspended = 1 OR deleted=1 $usernamefilter) ";

               $tempcreatesql = "INSERT INTO {".$temptablename."} (userid) SELECT userid from {company_users}
                              WHERE departmentid IN ($departmentids) $suspendedsql $enrolledsql $completionsql";
        } else {
            $tempcreatesql = "";
        }
        $DB->execute($tempcreatesql);
        $returnarr = array();

		if(isset($params['enrolledstatus']) && $params['enrolledstatus'] == 2){
			if($tempcreatesql != ""){
				$userids = $DB->get_records($temptablename);
				if($userids){
					foreach($userids as $userid)
						$returnarr[] = $userid->userid;
				}
			}
		}
		else{
			// All or one course?
			$courses = array();
			if (!empty($courseid)) {
				$courses[$courseid] = new stdclass();
				$courses[$courseid]->id = $courseid;
			} else {
				$courses2 = array();
				$courses = company::get_recursive_department_courses($departmentid);
				$all_courses = base::get_license_course_list();
				foreach ($all_courses as $key => $value) {
					$courses2[$key]=new stdclass();
					$courses2[$key]->courseid=$value;
				}
				$courses = array_merge($courses,$courses2);
			}

			// We only want the student role.
			$studentrole = $DB->get_record('role', array('shortname' => 'student'));
	   
			// Process them!
			
			 if(isset($params['user']))
			 //$userfilter = " AND ue.userid=".$params['user']." AND ra.userid=".$params['user'];
			 $userfilter = " AND ue.userid=".$params['user'];
			else
				$userfilter = '';
	 
				$iscourse=true;
			foreach ($courses as $course) {
				 if(isset($params['course']) && $params['course'] != "0" ){
						 $coursesearch =explode(",",$params['course']);
						if (in_array($course->courseid, $coursesearch))
							$iscourse=true;
						else
							$iscourse=false;

				 }
				if($iscourse && $DB->get_record('course',array('id'=>$course->courseid))){
					$contextcourse = context_course::instance($course->courseid);    
/*					 $users=$DB->get_records_sql("SELECT tt.id as tid,ue.id,tt.userid FROM {user_enrolments} ue
					   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
					   JOIN {role_assignments} ra ON (ue.userid = ra.userid)
					   JOIN {".$temptablename."} tt ON (ue.userid = tt.userid)
					   WHERE e.courseid = :course
					   AND ra.roleid = :student
					   ".$userfilter." 
					   AND ra.contextid = :coursecontext ". $courseregisterd,
					   array('course' => $course->courseid,
						 'student' => $studentrole->id,
						 'coursecontext' => $contextcourse->id));
*/
					$users=$DB->get_records_sql("SELECT tt.id as tid,ue.id,tt.userid FROM {user_enrolments} ue												   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)													      JOIN {".$temptablename."} tt ON (ue.userid = tt.userid)	
					       			WHERE e.courseid = :course ".$userfilter." ". $courseregisterd,													   array('course' => $course->courseid));													 
					  foreach($users as $user){
								$returnarr[] = $user->userid;
					  }
				  }
			   }
			   
						  $returnarr = array_unique($returnarr);
				if(count($returnarr)>0){
					
				 if(isset($params['daterange']) && $params['daterange'] =='datecomp' ){
					$beginOfDay = strtotime("midnight", $params['datefrom']);
					$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
					$useridarr = implode(', ',$returnarr);
					 $sql = "select * from {course_completions} where userid in ($useridarr) and 
					timecompleted > $beginOfDay AND  timecompleted < $endOfDay";
					
					$course_completions =$DB->get_records_sql($sql);
					$returnarr = array();
					if($course_completions){
						foreach($course_completions as $course_completion)
							$returnarr[] = $course_completion->userid;
					}

				}
			}
	
		}
			if(count($returnarr)>0){
			//for pagination
				 $useridarr = implode(', ',$returnarr);
				 $sql = "select * from {user} where id in ($useridarr) ORDER BY firstname ASC ";
				 if(isset($params['download']))
					$users = $DB->get_records_sql($sql);
				else
					$users = $DB->get_records_sql($sql,null, $page * $perpage, $perpage);

				$countusers = $DB->get_records_sql($sql);
				$numusers = count($countusers);

				$returnobj = new stdclass();
				$returnobj->users = $users;
				$returnobj->totalcount = $numusers;

				return $returnobj;
				
			}
			else{
				$returnobj = new stdclass();
				$returnobj->users = array();
				$returnobj->totalcount = 0;

				return $returnobj;
			}
        
    }
    /**
     * Get all users info regardless of course
     *
     * Parameters - $departmentid = int;
     *              $page = int;
     *              $perpade = int;
     *
     * Return array();
     **/
    public static function get_all_courses($departmentid, $courseid=0, $showsuspended,$deletecourse){
         global $DB;

        // Get the company details.
        $departmentrec = $DB->get_record('department', array('id' => $departmentid));
        $company = new company($departmentrec->company);

        // Get the full company tree as we may need it.
        $topcompanyid = $company->get_topcompanyid();
        $topcompany = new company($topcompanyid);
        $companytree = $topcompany->get_child_companies_recursive();
        $parentcompanies = $company->get_parent_companies_recursive();

        // Create a temporary table to hold the userids.
        $temptablename = 'tmp_'.uniqid();
        $dbman = $DB->get_manager();

        // Define table user to be created.
        $table = new xmldb_table($temptablename);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        $dbman->create_temp_table($table);

        // Deal with parent company managers
        if (!empty($parentcompanies)) {
            $userfilter = " AND userid NOT IN (
                             SELECT userid FROM {company_users}
                             WHERE companyid IN (" . implode(',', array_keys($parentcompanies)) . "))";
        } else {
            $userfilter = "";
        }

        // Populate it.
        $alldepartments = company::get_all_subdepartments($departmentid);
        if (count($alldepartments) > 0 ) {
           
                $suspendedsql = "";
            
            $tempcreatesql = "INSERT INTO {".$temptablename."} (userid) SELECT userid from {company_users}
                              WHERE departmentid IN (".implode(',', array_keys($alldepartments)).") $userfilter $suspendedsql";
        } else {
            $tempcreatesql = "";
        }
        $DB->execute($tempcreatesql);

        // All or one course?
        $courses = array();
        if (!empty($courseid)) {
            $courses[$courseid] = new stdclass();
            $courses[$courseid]->id = $courseid;
        } else {
            $courses = company::get_recursive_department_courses($departmentid);
        }
      
		return $courses;
} 
    
public static function get_all_user_courses($params){
		global $DB,$USER;
         //print_r($params);
         if(isset($params['departmentid'])){
			 $departmentid = $params['departmentid'];
		 }
	if(isset($params['organization'])){
	 	$companyid = $params['organization'];
	 	$department = $DB->get_record('department', array('parent' => 0,'company'=>$companyid));
		$departmentid = $department->id;
	}
         if(isset($params['page']))
			$page =$params['page'];
		else
			$page =0;
			
		if(isset($params['perpage']))	
          $perpage = $params['perpage'];
          
         if(isset($params['daterange']) && $params['daterange'] =='no' ){
				unset($params['datefrom']); 
				unset($params['dateto']); 	 
		 }
        if(!empty($params['selectuser']))
				$userid =$params['selectuser'];
		
		if(!empty($params['course']) && $params['course'] != "0"){
			$coursestr = $params['course'] ;
			$course = explode(',',$coursestr);
		}
	 				 		 //print_r($course);

		if(isset($params['completionstatus'])){
			$completionstatus=array();						
			$completionsql = "select * from {course_completions} where userid=".$userid;
			$completionsqlwhere = " AND  timecompleted IS NOT NULL ";
			$completecourses = $DB->get_records_sql($completionsql.$completionsqlwhere);
			if($completecourses ){
				if(count($completecourses)>0 ){
					foreach($completecourses as $completecourse)
						$completionstatus[] =$completecourse->course;
					}
				}
				//print_r($completionstatus);
				if($params['completionstatus'] == 2){
					if(count($completionstatus)>0 && isset($course) ){
							$course=array_diff($course,$completionstatus);
						
					}
					else{
					
						$coursesobj = base::get_all_courses($departmentid, 0, '',0);
						if($coursesobj){
							foreach($coursesobj as $course){
								$allcourses[] = $course->courseid;
							}
							if(count($completionstatus)>0){
								$course=array_diff($allcourses,$completionstatus);
							}
							else
								$course=$allcourses;
						}
						else
							$course=array();

				}	
			}
			else if($params['completionstatus'] == 3){
				if(count($completionstatus)>0 && isset($course) ){
						$course=array_intersect($course,$completionstatus);
				}
				else{
					$course =$completionstatus;
				}
					
			 }
			 else if($params['completionstatus'] == 1){
				$coursesobj = base::get_all_courses($departmentid, 0, '',0);
					if($coursesobj){
						foreach($coursesobj as $courseobj){
							$allcourses[] = $courseobj->courseid;
						}
						if(count($allcourses)>0 && isset($course) ){
							$course=array_intersect($course,$allcourses);
						}
						else{
							$course=$allcourses;
						}
					}
					else
						$course=array();
			 }
				//echo $completionsql.$completionsqlwhere;
		}
	 				// 	 print_r($course);

		if(isset($params['daterange']) && $params['daterange'] =='datecomp' ){
			$datecompletionstatus =array();

			$beginOfDay = strtotime("midnight", $params['datefrom']);
			$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;

			 $sql= "SELECT * FROM {course_completions} 
					WHERE userid=$userid 
					AND timecompleted > $beginOfDay AND  timecompleted < $endOfDay " ; 
			$datecompletecourses = $DB->get_records_sql($sql);
			if($datecompletecourses){
				foreach($datecompletecourses as $datecompletecourse)
					$datecompletionstatus[] =$datecompletecourse->course;
			}
			if(isset($course) ){
				if(count($course)>0)
					$course=array_intersect($course,$datecompletionstatus);
				}
			else
				$course = $datecompletionstatus;
		}

		if(isset($params['daterange']) && $params['daterange'] =='datereg' ){
			$dateregisteredstatus =array();
			$beginOfDay = strtotime("midnight", $params['datefrom']);
			$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;

			$sql= "SELECT c.* FROM {user_enrolments} ue
						JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
						JOIN {course} c ON (e.courseid = c.id)
					WHERE ue.userid=$userid 
					AND ue.timecreated > $beginOfDay AND  ue.timecreated < $endOfDay " ; 
			$dateregisteredcourses = $DB->get_records_sql($sql);
			if($dateregisteredcourses){
				foreach($dateregisteredcourses as $dateregisteredcourse)
					$dateregisteredstatus[] =$dateregisteredcourse->id;
			}
			if(isset($course)){
				if(count($course)>0)
					$course=array_intersect($course,$dateregisteredstatus);
			}
			else
				$course = $dateregisteredstatus;
		}
 
		// print_r($course);
		$courseearch ="";
		if(isset($course)){
			if(count($course)>0){
				$courses=implode(", ", $course);
				$courseearch =" AND c.id IN ($courses)" ;
			}
			else{
				$returnobj = new stdclass();
				$returnobj->courses = array();
				$returnobj->totalcount = 0;

				return $returnobj;
			}
		}
		
		$sqlselect = "SELECT ue.id as ueid,ue.timecreated as registered,ue.timestart as timestart,
							ue.timeend as due,c.* ";
		$sqlwhere =" FROM {user_enrolments} ue
						   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
						   JOIN {course} c ON (e.courseid = c.id)
						   WHERE ue.userid = $userid ".$courseearch;

		 
		 if(isset($params['download'])){
			 $sql = "select *,ue.timecreated as registered,ue.timestart as timestart ";
			$courses = $DB->get_records_sql($sql.$sqlwhere);
		}
		else{
			$courses = $DB->get_records_sql($sqlselect.$sqlwhere,null, $page * $perpage, $perpage);
			
		}

		$countcourses = $DB->get_records_sql($sqlselect.$sqlwhere);
		$numcourses = count($countcourses);
		if($courses){
			$returnobj = new stdclass();
			$returnobj->courses = $courses;
			$returnobj->totalcount = $numcourses;

			return $returnobj;
			
		}
		else{
			$returnobj = new stdclass();
			$returnobj->courses = array();
			$returnobj->totalcount = 0;

			return $returnobj;
		}
		 
	 }
	 
	public static function report_button()
	{	
		return '&nbsp;<button class="btn btn-secondary" name="emailreport" id="id_emailreport" type="button" data-toggle="modal" data-target="#modalForm">
	'.get_string("emailreport", 'local_base').'</button>
	<button class="btn btn-secondary" name="schedulereport" id="id_schedulereport" type="button" data-toggle="modal" data-target="#schedulereportForm">
	'.get_string("schedulereport", 'local_base').'</button>';
		
	}
	
	 public static function get_nextrun($schedule,$nextrunstartdate,$nextruntime,$opt1,$opt2,$opt3,$opt4){ 
		 $record =new stdclass();
		 if($schedule == 'Once'){
			 $nextrun = $nextrunstartdate.' '.$nextruntime;
			 $nextrun = strtotime($nextrun);
			 if( $nextrun < time()) {
				 $nextrun = 0;
			 }
			 $record->nextrun = $nextrun; 

		}
		else if($schedule == 'Daily'){
			 $nextrun = $nextrunstartdate.' '.$nextruntime;
			 $nextrun = strtotime($nextrun);
			 if( $nextrun < time()) {
				 $nextrun = $nextrun+ 24*60*60;
			 }
			 $record->nextrun = $nextrun; 
		 }
		else if($schedule == 'Weekly'){
			$daynamearray=explode(',',$opt2);
			 $nextrunstartdate = $nextrunstartdate.' '.$nextruntime;
			$nextrunstartdate = strtotime($nextrunstartdate);
			for($i=0; $i < 7; $i++){
				$daytime = $nextrunstartdate+($i*24*60*60);				
				 $dayname=  date('l',$daytime);
				 $daydate = date("Y-m-d",$daytime);
				if(in_array($dayname,$daynamearray)){
					 $nextrun = $daydate.' '.$nextruntime;
					 $nextrun = strtotime($nextrun);
					 if( $nextrunstartdate <= $daytime) {
						$record->nextrun = $nextrun;
						break;
					}
				 }
			 } 
			 
		 }
		 else if($schedule == 'Monthly'){
			$monthnamearray=explode(',',$opt4);
			$nextrunstartdate = $nextrunstartdate.' '.$nextruntime;
			$nextrunstartdate = strtotime($nextrunstartdate);
			for($i=0; $i < 12; $i++){
				$daytime = strtotime ("+".$i." month",$nextrunstartdate);
				 $monthname=  date('F',$daytime);		
				if(in_array($monthname,$monthnamearray)){
					if($opt1 > 0){
						$daydate = date("Y-m-d",$daytime);
						$month = new DateTime($daydate);
						$opt= $opt1-1 ;//since we add with first day
						$opt1thday  = mktime(0, 0, 0, $month->format('m'), 1, $month->format('Y'))+($opt*24*60*60);
						 $nextrun = $opt1thday;
					 }
					 else{
						 $date = date('Y-m',$daytime);
						$dayofweek = strtotime($opt2." ".$opt3." ".$date);
						 $nextrun = $dayofweek;

					 }
					 $nextrun = date("Y-m-d",$nextrun);
					 $nextrun = $nextrun.' '.$nextruntime;
					 $nextrun = strtotime($nextrun);
					 if( $nextrunstartdate < $daytime) {
							 $record->nextrun = $nextrun;
							 break;
					 }
				 }
			 } 
			 
			 
			 
		 }
		 return $record->nextrun;
		 
	 }
		 
	public static function nextrun($schedule){
		 global $DB;
			$record = new \stdClass();
			$record->id = $schedule->id; 
			$record->lastrun = time();
			$record->timemodified = time(); 
			$starttime = date("H:i", $schedule->starttime);

			if($schedule->enddate > 0){
				$enddate = date("Y-m-d",$schedule->enddate);
				$enddaytime = $enddate.' '.$starttime;
				$endtime = strtotime($enddaytime);
			}
			else
				$endtime =0;
			$nextrun = 0;	

			if($schedule->schedule == 'Once'){
				$nextrun = 0; 

			}
			else if($schedule->schedule == 'Daily'){
				$days = $schedule->opt1;
				$nextrun = $schedule->nextrun+ ($days*24*60*60);			 
			 }
			 else if($schedule->schedule == 'Weekly'){
				$daynamearray=explode(',',$schedule->opt2);
				 $nextrunstartdate = $schedule->nextrun;
				for($i=1; $i < 7; $i++){
					$daytime = $nextrunstartdate+($i*24*60*60);				
					 $dayname=  date('l',$daytime);
					if($dayname == 'Sunday' && $schedule->opt1 > 1){
							$weeks = $schedule->opt1 ;
							 $weeks = ($weeks * 7)-1-$i;
							$daytime = $nextrunstartdate+($weeks*24*60*60);				
							$dayname=  date('l',$daytime);
					}
					  $daydate = date("Y-m-d",$daytime);
					if(in_array($dayname,$daynamearray)){
						 $nextrun = $daydate.' '.$starttime;
						 $nextrun = strtotime($nextrun);
						if( $nextrunstartdate < $daytime){
							 $nextrun =$nextrun;
							break;
						}
					 }
				 } 
			 }
			 else if($schedule->schedule == 'Monthly'){
				$monthnamearray=explode(',',$schedule->opt4);
				$nextrunstartdate = $schedule->nextrun;
				for($i=0; $i < 12; $i++){
					$daytime = strtotime ("+".$i." month",$nextrunstartdate);
					 $monthname=  date('F',$daytime);		
					if(in_array($monthname,$monthnamearray)){
						if($schedule->opt1 > 0){
							$daydate = date("Y-m-d",$daytime);
							$month = new \DateTime($daydate);
							$opt= $schedule->opt1-1 ;//since we add with first day
							$opt1thday  = mktime(0, 0, 0, $month->format('m'), 1, $month->format('Y'))+($opt*24*60*60);
							 $nextrun = $opt1thday;
						 }
						 else{
							 $date = date('Y-m',$daytime);
							$dayofweek = strtotime($schedule->opt2." ".$schedule->opt3." ".$date);
							 $nextrun = $dayofweek;

						 }
						 $nextrun = date("Y-m-d",$nextrun);
						 $nextrun = $nextrun.' '.$starttime;
						 $nextrun = strtotime($nextrun);
						 if( $nextrunstartdate < $daytime) {
								 $nextrun = $nextrun;
								 break;
						 }
					 }
				 } 
				 
				 
				 
			 }
			 
			 if($endtime > 0 ){					
					if($endtime > $nextrun)
						 $record->nextrun = $nextrun; 				 
					 else{
						$record->nextrun = 0;
						$record->pause = 1;				
					} 
			 }
			 else if($endtime == 0){
				$record->nextrun = $nextrun;
				$record->pause = 1;				
			}
				
			// echo $schedule->description;
			 //echo date("Y-m-d H:i:s",$record->nextrun);

			//print_r($record);
			$DB->update_record('schedule_report_config',$record); 
	 }
	 
	 public static function schedule_email($schedule,$filename){
		global $DB,$CFG;
		$format = $schedule->format;
		$fileLocation = $CFG->tempdir; 
		$fileLocation = rtrim($fileLocation, '/') . '/';
		
		$emailarray=explode(";",$schedule->emailusers);
	  // Send email
		$eventdata = new stdClass();
		$eventdata->subject           = $schedule->emailsubject;
		$eventdata->fullmessage   = $schedule->emailbody;
		if($format=='html'){
			$fileLocation = '';
			$filename = '';
			$eventdata->fullmessagehtml   = $schedule->html;
		}
		else
			$eventdata->fullmessagehtml   = '';

		$sendfrom=get_admin();
		foreach($emailarray as $key=>$value){
			//echo $value;
			if(!empty($value)){
				$sendto=$DB->get_record('user',array('email'=>$value));
				if(!$sendto){
					$sendto = \core_user::get_user(1);
					$sendto->email = $value;
					$sendto->firstname = '';
					$sendto->lastname='';
				}			 
				if($emailed = email_to_user($sendto, $sendfrom,$eventdata->subject,$eventdata->fullmessage ,$eventdata->fullmessagehtml, $fileLocation.$filename, $filename)){
						$status = 'ok';
				}else{
					$status = 'err';
				}
			} 
		 }
	}
	public static function createpdfobject(){
		global $CFG; 
		require_once($CFG->libdir.'/tcpdf/tcpdf.php');
		$pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		$pdf->SetCreator(PDF_CREATOR);

		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
		if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
			require_once(dirname(__FILE__).'/lang/eng.php');
			$pdf->setLanguageArray($l);
		}

		$pdf->setFontSubsetting(true);
		

		$pdf->AddPage();
		return $pdf;

	}
	
	public static function selectcountry(){
		$countryselect = array('0'=>'Select Country');
		$countries = get_string_manager()->get_list_of_countries();
		$countries = array_merge($countryselect,$countries);
		return $countries;
	}
	
	public static function selectcourses($departmentid){
		global $DB;
		   $coursesobj = base::get_all_courses($departmentid, 0, '',0);
          $courses=array();
        if($coursesobj){
			foreach($coursesobj as $course){
				if($course = $DB->get_record('course',array('id'=>$course->courseid))){
					$courses[$course->id] = $course->fullname; 
				}
		  }
	  }
		natcasesort($courses);
	    $selcourses=array('0'=>'Select Course(s)');
		$courses = $selcourses + $courses;
		return $courses;
	}
	
/*	public static function selectusers($departmentid){
		global $DB;
		 $params['download']='download';
        $returnobj = base::get_all_user($departmentid, 0,$params);
        $userdataobj= $returnobj->users ;
        $userarray=array();
		if($userdataobj){
			foreach($userdataobj as $user){
				$userid=$user->id;
				if($user=$DB->get_record('user',array('id'=>$userid))){
					$fullname =$user->username.' ('.$user->firstname.' '.$user->lastname.') ';
					$userarray[$user->id] = $fullname;
				}
			}
		}
		natcasesort($userarray);
		$selusernames=array('0'=>'Select User');
		$userarray = $selusernames + $userarray;

		return $userarray;
	}
	
	public static function selectorganization($departmentid){
		global $DB;
			$organization=array();
		if (is_siteadmin()){
			if($companyrecords = $DB->get_records('company', array('suspended' => 0))){
				foreach($companyrecords as $companyrecord)
					$organization[$companyrecord->id] = $companyrecord->name;
			}
		}
		else{
			 // Get the company details.
			$departmentrec = $DB->get_record('department', array('id' => $departmentid));
			$organization[$departmentrec->company] = $departmentrec->name;
			$company = new company($departmentrec->company);
			$companytrees = $company->get_child_companies_recursive();
			if($companytrees){
				foreach($companytrees as $key => $companytree)
					$organization[$companytree->id] = $companytree->name;
				}
			
		}
			 natcasesort($organization);
			$selorganization=array('0'=>'Select Group');
		$organization = $selorganization + $organization;

		return $organization;
	}*/
	public static function selectsubgroup($companyid){
		global $DB;
		$organization=array();
		$sql ="select * from {department} where parent <> 0 and company=".$companyid;
		$alldepartments =  $DB->get_records_sql($sql);
		foreach($alldepartments as 	$department){
				$organization[$department->id] = $department->name;
		}
		natcasesort($organization);
		$selorganization=array('0'=>'Select Subgroup');
		$organization = $selorganization + $organization;

		return $organization;
	}
	public static function get_subgroupname($departmentid){
		global $DB;
		$organization=array();
		$sql ="select * from {department} where parent <> 0 and id=".$departmentid;
		$department =  $DB->get_record_sql($sql);
		if($department)
			$departmentname = $department->name;
		else
			$departmentname = '';

		return $departmentname;
	}
/*	public static function selectusernames($departmentid,$student){
		global $DB;
		 $params['download']='download';
        $returnobj = base::get_all_user($departmentid, 0,$params);
        $userdataobj= $returnobj->users ;
        $userarray=array();
		if($userdataobj){
			foreach($userdataobj as $user){
				$userid=$user->id;
				if(!in_array($userid,$student)){
					if($user=$DB->get_record('user',array('id'=>$userid))){
						$value =$user->username;
						$key =$user->id;
						$userarray[$key] = $value;
					}
				}
			}
		}
		natcasesort($userarray);
//		$selusernames=array('0'=>'Select User Name');
//		$userarray = $selusernames + $userarray;

		return $userarray;
	}*/

public static function get_all_organization($departmentid){
		global $DB;
			$organization=array();
				if (is_siteadmin()){
			if($companyrecords = $DB->get_records('company', array('suspended' => 0))){
				foreach($companyrecords as $companyrecord)
					$organization[$companyrecord->id] = $companyrecord->name;
			}
		}
		else{
			 // Get the company details.
			$departmentrec = $DB->get_record('department', array('id' => $departmentid));
			$organization[$departmentrec->company] = $departmentrec->name;
			$company = new company($departmentrec->company);
			$companytrees = $company->get_child_companies_recursive();
			if($companytrees){
			foreach($companytrees as $key => $companytree)
					$organization[$companytree->id] = $companytree->name;
				}
			
		}
		return $organization;
	}
public static function get_all_subgroup($departmentid){
		global $DB;
		$organization=base::get_all_organization($departmentid);
		$subgroups=array('0'=>'Select Subgroup');

		foreach($organization as $key=>$value){	
				$comsubgroups =base::selectsubgroup($key);
				foreach($comsubgroups as $key1=>$value1){
					if($key1 != '0'){
						$subgroups[$key1] = $value1;
					}
				}
		}
		return $subgroups;
	}
	public static function selectorganization($departmentid){
		global $DB;
			$organization=base::get_all_organization($departmentid);
	
			 natcasesort($organization);
			$selorganization=array('0'=>'Select Group');
		$organization = $selorganization + $organization;

		return $organization;
	}
	public static function selectusers($departmentid){
		global $DB;
		$organization=base::get_all_organization($departmentid);
        $userarray=array();
		if($organization){
			foreach($organization as $key=>$value){
				if($companyusers=$DB->get_records('company_users',array('companyid'=>$key))){
					foreach($companyusers as $companyuser){
						$userid=$companyuser->userid;
						if($user=$DB->get_record('user',array('id'=>$userid))){
							$fullname =$user->username.' ('.$user->firstname.' '.$user->lastname.') ';
							$userarray[$user->id] = $fullname;
						}	
					}
					
				}

				
			}
		}
		natcasesort($userarray);
		$selusernames=array('0'=>'Select User');
		$userarray = $selusernames + $userarray;

		return $userarray;
	}
	public static function selectusernames($departmentid,$student){
		global $DB;
		 $params['download']='download';
		$organization=base::get_all_organization($departmentid);
        $userarray=array();
		if($organization){
			foreach($organization as $key=>$value){
				if($companyusers=$DB->get_records('company_users',array('companyid'=>$key))){
					foreach($companyusers as $companyuser){
						$userid=$companyuser->userid;
						if(!in_array($userid,$student)){
							if($user=$DB->get_record('user',array('id'=>$userid))){
								$value =$user->username;
								$key =$user->id;
								$userarray[$key] = $value;
							}
						}	
					}	
				}	
			}
		}
		natcasesort($userarray);
		//$selusernames=array('0'=>'Select User Name');
		//$userarray = $selusernames + $userarray;

		return $userarray;
	}
	public static function selectlicenses($departmentid){
		global $DB;
		$licensearray=array('0'=>'Select License');

		 $sql ="select * from {companylicense}
					where companyid = $departmentid ORDER BY name ASC";
		$userlicenses = $DB->get_records_sql($sql);
		if($userlicenses){
			foreach($userlicenses as $userlicense){
					$licensearray[$userlicense->id] = $userlicense->name;
			}
		}

		return $licensearray;
	}
	public static function dateRange(){
		$daterange = array(	'1-Today'=>'Today',
							'2-Yesterday'=>'Yesterday',
							'3-365DaysAgo'=>'365 Days Ago'	
							);
		return $daterange;
	}
	
	public static function get_date($str){
		if($str == 'Today')
			$daterange = time();
		else if($str == 'Yesterday'){
			$date = date('d.m.Y',strtotime("-1 days"));
			$daterange = strtotime($date);

		}
		else if($str == '365DaysAgo'){
			$date = date('d.m.Y',strtotime("-365 days"));
			$daterange = strtotime($date);

		}
		return $daterange;
	}
	  
	  public static function schedule($id){
		 global $CFG,$DB;
		 	$schedule = $DB->get_record('schedule_report_config',array('id'=>$id)); 	
			base::runschedule($schedule);
		 
		 
	 }
	  public static function schedulefilters($id){
		 global $CFG,$DB;
		 	$filters=$DB->get_record('schedule_report_filter',array('configid'=>$id));
			$params =array();
			foreach($filters as $key=>$value){
				if(!empty($value) ){				
					$params[$key] = $value;
				if($key == 'course'){
				    $params['urlcourse'] = $value;
				    $params['selectcourse'] = $value;
				}
				else if($key == 'datefrom')
				    $params['urldatefrom'] = $value;
				else if($key == 'dateto')
				    $params['urldateto'] = $value;
				else if($key == 'user')
				    $params['selectuser'] = $value;

				}
						
			}
			return $params;
		 
	 }
	  public static function runschedule($schedule){
		
		 global $CFG,$DB;
		 require_once($CFG->libdir.'/completionlib.php');
		require_once $CFG->libdir.'/gradelib.php';
		require_once($CFG->dirroot.'/grade/querylib.php');
		require_once($CFG->libdir.'/excellib.class.php');
		require_once($CFG->libdir.'/tcpdf/tcpdf.php');
		require_once($CFG->dirroot.'/blocks/iomad_company_admin/lib.php');
		$fileLocation = $CFG->tempdir; 
		$fileLocation = rtrim($fileLocation, '/') . '/';
			$format = $schedule->format;
			$params =base::schedulefilters($schedule->id);
			 $params['download'] = 'download';
			// print_r($params);
			//echo '<br>';
			 $departmentid= $params['departmentid'];
			 if($format=='pdf'){
				$pdf = base::createpdfobject();

			}
			//on time calculation based on date range
			if($schedule->startrange == 'Sincerecent'){
				$sql = "select * from {schedule_report_config} 
						where reportname='$schedule->reportname' AND screen=$schedule->screen ORDER BY timemodified DESC Limit 0,1";
				$lasttrack = $DB->get_record_sql($sql);
				if($lasttrack){
					$startrange = $lasttrack->timemodified;//lastrun;
			}
			else
				$startrange = time();
			}
			else
				$params['datefrom'] = base::get_date($schedule->startrange);
			$params['dateto'] = base::get_date($schedule->endrange);
			
		if($params['daterange'] !='no'){
			$reportdaterange = 'Date Range: '. get_string($params['daterange'], 'local_base');
			$reportdate = date('m/d/Y',$params['datefrom']).' to '.date('m/d/Y',$params['dateto']);
		}
		else{
			$reportdaterange =  get_string($params['daterange'], 'local_base');
			$reportdate =  '';
		}
			if(isset($params['daterange']) && $params['daterange'] =='no' ){
				unset($params['datefrom']); 
				unset($params['dateto']); 	 
			}
			 if(isset($params['daterange']) && $params['daterange'] =='datereg' ){
							$beginOfDay = strtotime("midnight", $params['datefrom']);
							$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
							$courseregisterd=" AND ue.timecreated > $beginOfDay AND  ue.timecreated < $endOfDay";
			}
			else
				$courseregisterd = '';
			if($schedule->reportname == "reportcard"){
				require_once($CFG->dirroot.'/local/report_reportcard/locallib.php');

				if($schedule->screen == 1){
					$returnobj = base::get_all_user($departmentid, 0, $params);
					$userdataobj= $returnobj->users ;
					if($userdataobj){
						$reporthead = 'Report Card ';
						
						if($format=='pdf'  || $format=='html'){

							$tablepdf ='<table>
											<thead >
												<tr style="background-color: rgb(203, 205, 208);">
													<th  style="text-align:left;">'.get_string('login', 'local_base').'</th>
													<th style="text-align:center;">'.get_string('firstname', 'local_base').'</th>
													<th style="text-align:left;" >'.get_string('lastname', 'local_base').'</th>
													<th colspan="2" style="text-align:left;">'.get_string('email', 'local_base').'</th>
													<th style="text-align:left;">'.get_string('organization', 'local_base').'</th>
													<th style="text-align:left;">'.get_string('subgroup', 'local_base').'</th>

												</tr>
											</thead>
										<tbody> ';
						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata   ='"'.$reporthead."\"\n"	;
							$csvdata  .='"'.$reportdaterange."\"\n"	;

							$csvdata  .='"'.$reportdate."\"\n\n"	;
								$csvdata  .= '"'.get_string('login', 'local_base').'","'
										.get_string('firstname', 'local_base').'","'
										.get_string('lastname', 'local_base').'","'
										.get_string('email', 'local_base').'","'
										.get_string('organization', 'local_base').'","'
										.get_string('subgroup', 'local_base')."\"\n"	;
						}
												$i=1;

						foreach($userdataobj as $user){
							$userid=$user->id;
							$user=$DB->get_record('user',array('id'=>$userid));
/*							$sql = "select name,cu.departmentid from {company} c 
										join  {company_users} cu on c.id=cu.companyid 
										where cu.userid=".$userid;
		if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
				$sql .=" AND cu.departmentid in (".$params['subgroup'].")";
		}
							$userorganization = $DB->get_record_sql($sql);
							$orgname = $userorganization->name;
							$subgroupname = base::get_subgroupname($userorganization->departmentid);
*/
$sql = "select distinct(c.id),name from {company} c 
						join  {company_users} cu on c.id=cu.companyid 
						where cu.userid=".$userid;
				if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
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
}
							if($format=='pdf'  || $format=='html'){
																if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';								  			
								$tablepdf  .= '<tr style="'.$style.'">
												<td style="text-align:left;">'.$user->username.' </td>
												<td style="text-align:center;" >'.$user->firstname.'</td>
												<td style="text-align:left;">'.$user->lastname.' </td>
												<td colspan="2" style="text-align:left;">'.$user->email.' </td>
												<td style="text-align:left;" >'.$orgname.'</td>
												<td style="text-align:left;" >'.$subgroupname.'</td>

												</tr>';
	   		$i++;

							}
							if($format =='csv' || $format =='xlsx'){
								$csvdata .= '"'.$user->username.
													'","'.$user->firstname.
													'","'.$user->lastname.
													'","'.$user->email.
													'","'.$orgname.
													'","'.$subgroupname. "\"\n";
							}
							 $coursesearch ='';
							if(!empty($params['course']) && $params['course'] != "0"){
								$course = $params['course'];
								$coursesearch = " AND c.id in ($course) ";
							}
								
							 if(isset($params['daterange']) && $params['daterange'] =='datecomp' ){
								$beginOfDay = strtotime("midnight", $params['datefrom']);
								$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
								
								$coursedataobj = $DB->get_records_sql("SELECT distinct(ue.id) as ueid,ue.timecreated as registered,
												ue.timeend as due,c.* 
												FROM {user_enrolments} ue
											   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
											   JOIN {course} c ON (e.courseid = c.id)
											   JOIN {course_completions} cc ON (cc.course = c.id)
											   WHERE ue.userid = $userid  AND  cc.userid=$userid
												$coursesearch
											   AND cc.timecompleted > $beginOfDay AND  cc.timecompleted < $endOfDay "
											   .$courseregisterd); 
						  }
						  else{
							 
							  $coursedataobj = $DB->get_records_sql("SELECT ue.id as ueid,ue.timecreated as registered,
													ue.timeend as due,c.* 
													FROM {user_enrolments} ue
												   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
												   JOIN {course} c ON (e.courseid = c.id)
												   WHERE ue.userid = :user ".$courseregisterd.$coursesearch,
												   array('user' => $userid));
						  }
						  
							
						}
						if($format=='pdf'  || $format=='html'){
							$tablepdf .= '	</tbody>
										</table>
							';
							$html = '<h1>'.$reporthead.'</h1><h5>'.$reportdate.'</h5><br>';
						}
						if($format=='pdf' ){
							
							$filename = 'reportcard.pdf';															base::create_tempdf($reporthead,$reportdaterange.'<br>'.$reportdate,$tablepdf,$filename);
						}
						if($format =='csv' || $format =='xlsx'){						
							$filename = 'reportcard.'.$format;
							file_put_contents($fileLocation.$filename, $csvdata);
						}
						if($format=='html'){
							$schedule->html =$reportdaterange.'<br>'.$reportdate.$tablepdf;	
							$filename = '';					
						}
					}
				}
				else if($schedule->screen == 2){
					$selectuser  = $params['user'];
					$params['selectuser'] =  $selectuser;
					$returnobj = base::get_all_user_courses($params);
					$coursedataobj= $returnobj->courses ;
					$userid=$selectuser;
					$user=$DB->get_record('user',array('id'=>$userid));
					$userdetails =$user->username.' ( '.$user->firstname.' '.$user->lastname.') ';
					
					if($coursedataobj){
						
						$reporthead = 'Learner Report Card ';

						if($format=='pdf'  || $format=='html'){

							$tablepdf ='<table>
											<thead >
												<tr style="background-color: rgb(203, 205, 208);">
													<th  >'.get_string('coursename', 'local_base').'</th>
													<th >'.get_string('completed', 'local_base').'</th>
													<th  >'.get_string('dateregistered', 'local_base').'</th>
													<th >'.get_string('datecompleted', 'local_base').'</th>
												</tr>
											</thead>
										<tbody> ';
						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata   ='"'.$reporthead."\"\n"	;
							$csvdata  .='"'.$reportdaterange."\"\n"	;
							$csvdata  .='"'.$reportdate."\"\n\n"	;
							$csvdata  .='"User:'.$userdetails."\"\n\n"	;
								$csvdata  .= '"'.get_string('coursename', 'local_base').'","'
										.get_string('completed', 'local_base').'","'
										.get_string('dateregistered', 'local_base').'","'
										.get_string('datecompleted', 'local_base')."\"\n"	;
						}
												$i=1;

						foreach($coursedataobj as $coursedata){
							$completedate = '';
							$iscomplete = 'No';
							$course_completions =$DB->get_record('course_completions',array('userid'=>$userid,'course'=>$coursedata->id));
							if($course_completions){
								if(isset($course_completions->timecompleted)){
									$completedate	= date('m/d/Y',$course_completions->timecompleted);
									$iscomplete = 'Yes';
								}
							}
							if($format=='pdf'  || $format=='html'){
																if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';								  			
								$tablepdf  .= '<tr style="'.$style.'">
												<td >'.$coursedata->fullname.' </td>
												<td  >'.$iscomplete.'</td>
												<td >'.date('m/d/Y',$coursedata->registered).' </td>
												<td >'.$completedate.' </td>
												</tr>';
	   		$i++;

							}
							if($format =='csv' || $format =='xlsx'){
								$csvdata .= '"'.$coursedata->fullname.
													'","'.$iscomplete.
													'","'.date('m/d/Y',$coursedata->registered).
													'","'.$completedate. "\"\n";
							}
							
							
						}
						if($format=='pdf'  || $format=='html'){
							$tablepdf .= '	</tbody>
										</table>
							';	
						$html =$reportdaterange.'<br>'.$reportdate.'<br> User: '.$userdetails;

						}
						if($format=='pdf' ){
							
							$filename = 'learnerreportcard.pdf';						
							base::create_tempdf($reporthead,$html,$tablepdf,$filename);

						}
						if($format =='csv' || $format =='xlsx'){
							$filename = 'learnerreportcard.'.$format;
							file_put_contents($fileLocation.$filename, $csvdata);
						}
						if($format=='html'){
							$schedule->html =$html.$tablepdf;	
							$filename = '';					
						}
					}

				}
				else if($schedule->screen == 3){
					$selectuser  = $params['user'];
					$params['selectuser'] =  $selectuser;
					$selectcourse  = $params['course'];
					$params['selectcourse'] =  $selectcourse;
					$modules = report_reportcard::get_all_course_modules( $params);

					if($modules){
		
						$reporthead = 'Report Card ';
						$userid=$selectuser;
						$user=$DB->get_record('user',array('id'=>$userid));
						$userdetails =$user->username.' ( '.$user->firstname.' '.$user->lastname.') ';
						$courseid=$selectcourse;
						$course=$DB->get_record('course',array('id'=>$courseid));
						$coursedetails =$course->fullname;
						
						if($format=='pdf'){
							$html = $reportdaterange.'<br>'.$reportdate.'<br> User: '.$userdetails.'<br>Course: '.$coursedetails;

							// output the HTML content
							$pdf->SetY( 15 ); 
							$pdf->SetX( 15 ); 
							$pdf->SetFont('Helvetica','B',16);
							$pdf->writeHTML($reporthead, true, false, true, false, '');
							$pdf->SetFont('Helvetica','',10);
							$pdf->writeHTML('<br><br>'.$html.'<br>', true, false, true, false, '');

						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata   ='"'.$reporthead."\"\n"	;
							$csvdata  .='"'.$reportdaterange."\"\n"	;
							$csvdata  .='"'.$reportdate."\"\n\n"	;
							$csvdata  .='"User:'.$userdetails."\"\n";
							$csvdata  .='"Course:'.$coursedetails."\"\n\n";
						}
						
						if($format=='pdf'  ||  $format =='html')
							$shtml =$reportdaterange.'<br>'.$reportdate.'<br> User: '.$userdetails.'<br>Course: '.$coursedetails;
						
						foreach($modules as $module){
							if($format =='csv' || $format =='xlsx'){
								$csvdata   .="\n".'"'.ucfirst($module->name)."\"\n"	;
							}
							if($format=='pdf'  || $format=='html' ){
								$shtml .= '<br><h3>'.ucfirst($module->name).'</h3>'; 
							}
							if($format=='pdf'  || $format=='html'){

							$tablepdf ='<table>
											<thead >
												<tr style="background-color: rgb(203, 205, 208);">
													<th  >'.get_string('name', 'local_base').'</th>
													<th >'.get_string('grade', 'local_base').'</th>
													<th  >'.get_string('completionstatus', 'local_base').'</th>
													<th >'.get_string('datecompleted', 'local_base').'</th>
												</tr>
											</thead>
										<tbody> ';
						}
						if($format =='csv' || $format =='xlsx'){
							
							$csvdata  .= '"'.get_string('name', 'local_base').'","'
									.get_string('grade', 'local_base').'","'
									.get_string('completionstatus', 'local_base').'","'
									.get_string('datecompleted', 'local_base')."\"\n"	;
						}
						
						$params['module']=$module->id;	
						$params['modulename']=$module->name;	
						$coursemodules = report_reportcard::get_all_module_details( $params);
						if($coursemodules){
													$i=1;

							foreach($coursemodules as $coursemodule){
								$name=$coursemodule->name;
								$sql="SELECT * FROM {course_modules_completion} 
										where coursemoduleid=".$coursemodule->id." AND userid=".$userid;
								$cmcompletion =$DB->get_record_sql($sql);
								if($cmcompletion){
									if($cmcompletion->completionstate >0){
										$completionstate ='Yes';
										$completiondate = date('m/d/Y',$cmcompletion->timemodified);
									}
									else{
										$completionstate ='No';
										$completiondate = '';
									}
								}
								else{
									$completionstate ='No';
									$completiondate = '';
								}
								$gradeitem = grade_get_grades($courseid, 'mod', $module->name, $coursemodule->instance, $userid);
								$grade ='';
								if($gradeitem->items){
									$grade = $gradeitem->items[0]->grades[$userid]->grade;
									$grade =number_format($grade,2);
								}
								
								if($format=='pdf'  || $format=='html'){
																	if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';								  			
								$tablepdf  .= '<tr style="'.$style.'">
													<td >'.$name.' </td>
													<td  >'.$grade.'</td>
													<td >'.$completionstate.' </td>
													<td >'.$completiondate.' </td>
													</tr>';
	   		$i++;

								}
								if($format =='csv' || $format =='xlsx'){
									$csvdata .= '"'.$name.
														'","'.$grade.
														'","'.$completionstate.
														'","'.$completiondate. "\"\n";
								}
								
								
							}
						}
						if($format=='pdf'  || $format=='html'){
							$tablepdf .= '	</tbody>
										</table>
							';
							$shtml .=$tablepdf;
							
						}
						if($format=='pdf' ){
							$pdf->SetFont('Helvetica', '', 7);
							$html = '<br><h3>'.ucfirst($module->name).'</h3>'; 
							$pdf->writeHTML($html, true, false, true, false, '');
							$pdf->writeHTMLCell(0, 0, '', '', $tablepdf, 0, 1, 0, true, '', true);
						}
						
						
					}

					if($format=='pdf' ){
						$filename = 'modulereportcard.pdf';				
						$pdf->Output($fileLocation.$filename, 'F');
					}
					if($format =='csv' || $format =='xlsx'){						
						$filename = 'modulereportcard.'.$format;
						file_put_contents($fileLocation.$filename, $csvdata);
					}
					if($format=='html'){
							$schedule->html =$shtml;	
							$filename = '';					
						}
				}
			}
		}
		else if($schedule->reportname == "learnertranscript"){
			if($schedule->screen == 1){
				$returnobj = base::get_all_user($departmentid, 0, $params);
				$userdataobj= $returnobj->users ;
				//print_r($userdataobj);
				if($userdataobj){					
					$reporthead = 'Learner Transcript Report';
				
						if($format=='pdf'  || $format=='html'){

						$tablepdf ='<table>
										<thead >
											<tr style="background-color: rgb(203, 205, 208);">
												<th  style="text-align:left;">'.get_string('login', 'local_base').'</th>
												<th style="text-align:center;">'.get_string('firstname', 'local_base').'</th>
												<th  style="text-align:left;">'.get_string('lastname', 'local_base').'</th>
												<th colspan="2" style="text-align:left;">'.get_string('email', 'local_base').'</th>
												<th style="text-align:left;" >'.get_string('organization', 'local_base').'</th>
											</tr>
										</thead>
									<tbody> ';
					}
					if($format =='csv' || $format =='xlsx'){
						$csvdata   ='"'.$reporthead."\"\n"	;
						$csvdata  .='"'.$reportdaterange."\"\n"	;
						$csvdata  .='"'.$reportdate."\"\n"	;
						$csvdata  .= '"'.get_string('login', 'local_base').'","'
									.get_string('firstname', 'local_base').'","'
									.get_string('lastname', 'local_base').'","'
									.get_string('email', 'local_base').'","'
									.get_string('organization', 'local_base')."\"\n"	;
					}
											$i=1;

					foreach($userdataobj as $user){
						$userid=$user->id;
						$user=$DB->get_record('user',array('id'=>$userid));
						$sql = "select name from {company} c 
									join  {company_users} cu on c.id=cu.companyid 
									where cu.userid=".$userid;
						$userorganization = $DB->get_record_sql($sql);
						$orgname = $userorganization->name;
					
						if($format=='pdf'  || $format=='html'){
															if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';								  			
								$tablepdf  .= '<tr style="'.$style.'">
											<td style="text-align:left;" >'.$user->username.' </td>
											<td  style="text-align:center;">'.$user->firstname.'</td>
											<td style="text-align:left;" >'.$user->lastname.' </td>
											<td colspan="2" style="text-align:left;">'.$user->email.' </td>
											<td style="text-align:left;">'.$orgname.'</td>
											</tr>';
												   		$i++;


						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata .= '"'.$user->username.
												'","'.$user->firstname.
												'","'.$user->lastname.
												'","'.$user->email.
												'","'.$orgname. "\"\n";
						}
						 $coursesearch ='';
						if(!empty($params['course'] && $params['course'] != "0")){
							$course = $params['course'];
							$coursesearch = " AND c.id in ($course) ";
						}
			 
				
					}
					if($format=='pdf'  || $format=='html'){
						$tablepdf .= '	</tbody>
									</table>
						';
						$html = $reportdaterange.'<br>'.$reportdate;
					}
					if($format=='pdf' ){
						
						$filename = 'learnerreport.pdf';						
						base::create_tempdf($reporthead,$html,$tablepdf,$filename);	
					}
					if($format =='csv' || $format =='xlsx'){
					
						$filename = 'learnerreport.'.$format;
						file_put_contents($fileLocation.$filename, $csvdata);
					}
					if($format=='html'){
							$schedule->html =$html.$tablepdf;	
							$filename = '';					
					}
				}
				
			}
			else if($schedule->screen == 2){
				 $selectuser  = $params['user'];
				 $params['selectuser'] =  $selectuser;

				$reporthead = 'Learner\'s Transcript Report';
				$userid=$selectuser;
				$user=$DB->get_record('user',array('id'=>$userid));
				$userdetails =$user->username.' - '.$user->firstname.' '.$user->lastname;
				if($format =='pdf'){
					$pdf->SetY( 15 ); 
					$pdf->SetX( 15 ); 
					$pdf->SetFont('Helvetica','B',16);
					$pdf->writeHTML($reporthead, true, false, true, false, '');
					$pdf->SetFont('Helvetica','',10);
					$pdf->writeHTML('<br><br>'.$reportdaterange.'<br>'.$reportdate.'<br>', true, false, true, false, '');
					$pdf->SetFont('Helvetica','',10);
					$pdf->writeHTML($userdetails, true, false, true, false, '');

				}
				if($format =='csv' || $format =='xlsx'){
					$csvdata   ='"'.$reporthead."\"\n"	;
					$csvdata  .='"'.$reportdaterange."\"\n"	;
					$csvdata  .='"'.$reportdate."\"\n\n";
					$csvdata  .='"User:'.$userdetails."\"\n\n";
				}
		 
				  
				$coursehead = 'My Courses';

				$returnobj = base::get_all_user_courses($params);
				$coursedataobj= $returnobj->courses ;
				if($format =='csv' || $format =='xlsx'){
							$csvdata   .="\n".'"'.$coursehead."\"\n"	;
					}
					if($format=='html' || $format=='pdf' ){
						$html = '<br><h3>'.$coursehead.'</h3>'; 
					}	
					
				//print_r($coursedataobj);
				if($coursedataobj){
					
					if($format=='pdf'  || $format=='html'){

						$tablepdf ='<table>
										<thead >
											<tr style="background-color: rgb(203, 205, 208);">
												<th  >'.get_string('coursename', 'local_base').'</th>
												<th >'.get_string('score', 'local_base').'</th>
												<th  >'.get_string('datestarted', 'local_base').'</th>
												<th >'.get_string('datecompleted', 'local_base').'</th>
												<th >'.get_string('licensed', 'local_base').'</th>
											</tr>
										</thead>
									<tbody> ';
					}
					if($format =='csv' || $format =='xlsx'){
						
						$csvdata  .= '"'.get_string('coursename', 'local_base').'","'
								.get_string('score', 'local_base').'","'
								.get_string('datestarted', 'local_base').'","'
								.get_string('datecompleted', 'local_base').'","'
								.get_string('licensed', 'local_base')."\"\n"	;
					}
											$i=1;
	
					foreach($coursedataobj as $coursedata)	{
						//print_r($coursedata);
						$completedate = '';
						$course_completions =$DB->get_record('course_completions',array('userid'=>$userid,'course'=>$coursedata->id));
						if($course_completions){
							if(isset($course_completions->timecompleted)){
								$completedate	= date('m/d/Y',$course_completions->timecompleted);
							}
						}
						
						$grade = '';
						$grades =\grade_get_course_grade($userid, $coursedata->id);
						//print_r($grades);
						if($grades){
								$grade = $grades->str_grade;			
						}
						
						$islicensed = 'No';
						$license =$DB->get_record('companylicense_users',array('userid'=>$userid,'licensecourseid'=>$coursedata->id));
						if($license){
								$islicensed = 'Yes';			
						}
						
						$timestart = empty($coursedata->timestart) ? "" : date('m/d/Y',$coursedata->timestart);		
						if($format=='pdf'  || $format=='html'){
															if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';								  			
								$tablepdf  .= '<tr style="'.$style.'">
											<td >'.$coursedata->fullname.' </td>
											<td  >'.$grade.'</td>
											<td >'.$timestart.' </td>
											<td >'.$completedate.' </td>
											<td >'.$islicensed.' </td>
											</tr>';
												   		$i++;


						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata .= '"'.$coursedata->fullname.
										'","'.$grade.
										'","'.$timestart.
										'","'.$completedate.
										'","'.$islicensed. "\"\n";
						}
								
								
					}
						if($format=='pdf'  || $format=='html'){
							$tablepdf .= '	</tbody>
										</table>
							';
							
						}
						if($format=='pdf' ){
							$pdf->SetFont('Helvetica','',7);
							$pdf->writeHTML($html, true, false, true, false, '');
							$pdf->writeHTMLCell(0, 0, '', '', $tablepdf, 0, 1, 0, true, '', true);
						}

				}
				else{
					if($format =='csv' || $format =='xlsx'){
							$csvdata   .='"There are no courses","'	;
					}
					if($format=='html' || $format=='pdf'){
						$html .= '<br>There are no courses';
						$tablepdf = ''; 
					}
					if($format=='pdf' ){
							$pdf->SetFont('Helvetica','',7);
							$pdf->writeHTML($html, true, false, true, false, '');
					}
					
				}		
					

				$licenseshead = 'My Licenses';
				$licensesobj = array();
				$explicensesobj = array();
				$sql ="select cu.id,cu.*,cl.*,cl.name,cl.validlength,cu.issuedate from {companylicense} cl 
						join {companylicense_users} cu on cu.licenseid=cl.id
						where cu.userid=".$userid." ORDER BY cl.name ASC";
					$userlicenses = $DB->get_records_sql($sql);
					if($userlicenses){
						foreach($userlicenses as $userlicense){
							$expiredon = strtotime("+".$userlicense->validlength." day", $userlicense->issuedate );
							if($expiredon > time()){
								$licenses = new \stdclass();
								$licenses->name =$userlicense-> name;
								$licenses->issuedate =date('m/d/Y', $userlicense->issuedate);
								$licenses->expdate =date('m/d/Y', $expiredon);
								$licensesobj[$userlicense->id] =$licenses ;
							}
							else{
								$explicenses = new \stdclass();
								$explicenses->name =$userlicense-> name;
								$explicenses->issuedate =date('m/d/Y', $userlicense->issuedate);
								$explicenses->expdate =date('m/d/Y', $expiredon);
								$explicensesobj[$userlicense->id] =$explicenses ;
							}
						}
						
					}	
					
				if($format =='csv' || $format =='xlsx'){
							$licensecsvdata   ="\n".'"'.$licenseshead."\"\n"	;
					}
					if($format=='html' || $format=='pdf' ){
						$licensehtml = '<br><h3>'.$licenseshead.'</h3>'; 
					}	
					
				if($licensesobj){
					
					if($format=='pdf'  || $format=='html'){

						$licensetablepdf ='<table>
										<thead >
											<tr style="background-color: rgb(203, 205, 208);">
												<th  >'.get_string('license', 'local_base').'</th>
												<th >'.get_string('dateregistered', 'local_base').'</th>
												<th >'.get_string('dateexp', 'local_base').'</th>
											</tr>
										</thead>
									<tbody> ';
					}
					if($format =='csv' || $format =='xlsx'){
						
						$licensecsvdata  .= '"'.get_string('license', 'local_base').'","'
								.get_string('dateregistered', 'local_base').'","'
								.get_string('dateexp', 'local_base')."\"\n"	;
					}
						
					foreach($licensesobj as $license)	{
						
						if($format=='pdf'  || $format=='html'){
							$licensetablepdf  .= '<tr>
											<td >'.$license->name.' </td>
											<td  >'.$license->issuedate.'</td>
											<td >'.$license->expdate.' </td>
											</tr>';

						}
						if($format =='csv' || $format =='xlsx'){
							$licensecsvdata .= '"'.$license->name.
										'","'.$license->issuedate.
										'","'.$license->expdate. "\"\n";
						}
								
								
					}
						if($format=='pdf'  || $format=='html'){
							$licensetablepdf .= '	</tbody>
										</table>
							';
							
						}
						if($format=='pdf' ){
							$pdf->SetFont('Helvetica','',7);
							$pdf->writeHTML($licensehtml, true, false, true, false, '');
							$pdf->writeHTMLCell(0, 0, '', '', $licensetablepdf, 0, 1, 0, true, '', true);
						}

				}
				else{
					if($format =='csv' || $format =='xlsx'){
							$licensecsvdata   .='"There are no licenses","'	;
					}
					if($format=='html' || $format=='pdf'){
						$licensehtml .= '<br>There are no licenses';
						$licensetablepdf =''; 
					}
					if($format=='pdf' ){
						$pdf->SetFont('Helvetica','',7);
						$pdf->writeHTML($licensehtml, true, false, true, false, '');
					}
					
				}		
				$explicenseshead = 'My Expired Licenses';		
					
					
					
				if($format =='csv' || $format =='xlsx'){
							$explicensecsvdata   ="\n".'"'.$explicenseshead."\"\n"	;
					}
					if($format=='html' || $format=='pdf' ){
						$explicensehtml = '<br><h3>'.$explicenseshead.'</h3>'; 
					}	
					
				if($explicensesobj){
					
					if($format=='pdf'  || $format=='html'){

						$explicensetablepdf ='<table>
										<thead >
											<tr style="background-color: rgb(203, 205, 208);">
												<th  >'.get_string('license', 'local_base').'</th>
												<th >'.get_string('dateregistered', 'local_base').'</th>
												<th >'.get_string('dateexp', 'local_base').'</th>
											</tr>
										</thead>
									<tbody> ';
					}
					if($format =='csv' || $format =='xlsx'){
						
						$explicensecsvdata  .= '"'.get_string('license', 'local_base').'","'
								.get_string('dateregistered', 'local_base').'","'
								.get_string('dateexp', 'local_base')."\"\n"	;
					}
						
					foreach($explicensesobj as $explicense)	{
						
						if($format=='pdf'  || $format=='html'){
							$explicensetablepdf  .= '<tr>
											<td >'.$explicense->name.' </td>
											<td  >'.$explicense->issuedate.'</td>
											<td >'.$explicense->expdate.' </td>
											</tr>';

						}
						if($format =='csv' || $format =='xlsx'){
							$explicensecsvdata .= '"'.$explicense->name.
										'","'.$explicense->issuedate.
										'","'.$explicense->expdate. "\"\n";
						}
								
								
					}
						if($format=='pdf'  || $format=='html'){
							$explicensetablepdf .= '	</tbody>
										</table>
							';
							
						}
						if($format=='pdf' ){
							$pdf->SetFont('Helvetica','',7);
							$pdf->writeHTML($explicensehtml, true, false, true, false, '');
							$pdf->writeHTMLCell(0, 0, '', '', $explicensetablepdf, 0, 1, 0, true, '', true);
						}

				}
				else{
					if($format =='csv' || $format =='xlsx'){
							$explicensecsvdata   .='"There are no expired licenses","'	;
					}
					if($format=='html' || $format=='pdf'){
						$explicensehtml .= '<br>There are no expired licenses'; 
						$explicensetablepdf = '';
					}
					if($format=='pdf' ){
							$pdf->SetFont('Helvetica','',7);
							$pdf->writeHTML($explicensehtml, true, false, true, false, '');
					}
					
				}		
				if($format=='html'){
					$schedule->html =$reportdaterange.'<br>'.$reportdate.$html.$tablepdf.$licensehtml.$licensetablepdf.$explicensehtml.$explicensetablepdf;	
					$filename = '';					
				}
				
				if($format=='pdf' ){
					$filename = 'learnertranscriptreport.pdf';				
					$pdf->Output($fileLocation.$filename, 'F');
				}
				if($format =='csv' || $format =='xlsx'){

					$filename = 'learnertranscriptreport.'.$format;
					file_put_contents($fileLocation.$filename, $csvdata.$licensecsvdata.$explicensecsvdata);
				}
								 
			}
		}
		else if($schedule->reportname == "courseprogress"){
			$returnobj = base::get_all_user($departmentid, 0, $params);
			$userdataobj= $returnobj->users ;
			//print_r($userdataobj);
			if($userdataobj){

				$reporthead = 'Course Progress Report';
					
					if($format=='pdf' || $format=='html'){

					 $tablepdf ='<table>
									<thead >
											<tr  style="background-color: rgb(144, 140, 141);">
											<th  colspan="2">'.get_string('coursename', 'local_base').'</th>
											<th colspan="2">'.get_string('courseprogress', 'local_base').'</th>
											<th  >'.get_string('dateregistered', 'local_base').'</th>
											<th >'.get_string('datecompleted', 'local_base').'</th>
											<th >'.get_string('duedate', 'local_base').'</th>
<th >'.get_string('subgroup', 'local_base').'</th>
										</tr>
									</thead>
								<tbody> ';
				}

				if($format =='csv' || $format =='xlsx'){
					$csvdata   ='"'.$reporthead."\"\n"	;
					$csvdata  .='"'.$reportdaterange."\"\n"	;
					$csvdata  .='"'.$reportdate."\"\n"	;
					$csvdata  .= '"'.get_string('coursename', 'local_base').'","'
								.get_string('courseprogress', 'local_base').'","'
								.get_string('dateregistered', 'local_base').'","'
								.get_string('datecompleted', 'local_base').'","'
								.get_string('duedate', 'local_base').'","'
								.get_string('subgroup', 'local_base')."\"\n"	;
				}
				
				foreach($userdataobj as $user){
					$userid=$user->id;
					$user=$DB->get_record('user',array('id'=>$userid));
					$fullname = $user->lastname.', '.$user->firstname.' ('.$user->username.') ';
/*					$sql = "select name,cu.departmentid from {company} c 
							join  {company_users} cu on c.id=cu.companyid 
							where cu.userid=".$userid;
		if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
				$sql .=" AND cu.departmentid in (".$params['subgroup'].")";
		}
							$userorganization = $DB->get_record_sql($sql);
							$orgname = $userorganization->name;
					$subgroupname = base::get_subgroupname($userorganization->departmentid);
*/
$sql = "select distinct(c.id),name from {company} c 
						join  {company_users} cu on c.id=cu.companyid 
						where cu.userid=".$userid;
				if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
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
}
					if($format=='pdf'  || $format=='html'){
						$tablepdf  .= '<tr class="" style="background-color: rgb(203, 205, 208);">
									<td colspan="2"><b>'.$fullname.' </b></td>
									<td colspan="2">'.$user->email.'</td>
									<td>'.$orgname.'</td>
									<td ></td>
									<td ></td>
									<td >'.$subgroupname.'</td>
									</tr>';

					}
					if($format =='csv' || $format =='xlsx'){
						$csvdata .= '"'.$fullname.
											'","'.$user->email.
											'","'.$orgname.
											'","'.' '.
											'","'.' '.
											'","'.$subgroupname. "\"\n";
					}
					 $coursesearch ='';
					if(!empty($params['course']) && $params['course'] != "0"){
						$course = $params['course'];
						$coursesearch = " AND c.id in ($course) ";
					}
						
					 if(isset($params['daterange']) && $params['daterange'] =='datecomp' ){
						$beginOfDay = strtotime("midnight", $params['datefrom']);
						$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
						
						$coursedataobj = $DB->get_records_sql("SELECT distinct(ue.id) as ueid,ue.timecreated as registered,
										ue.timeend as due,c.* 
										FROM {user_enrolments} ue
									   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
									   JOIN {course} c ON (e.courseid = c.id)
									   JOIN {course_completions} cc ON (cc.course = c.id)
									   WHERE ue.userid = $userid  AND  cc.userid=$userid
										$coursesearch
									   AND cc.timecompleted > $beginOfDay AND  cc.timecompleted < $endOfDay "
									   .$courseregisterd); 
				  }
				  else{
					 
					  $coursedataobj = $DB->get_records_sql("SELECT ue.id as ueid,ue.timecreated as registered,
											ue.timeend as due,c.* 
											FROM {user_enrolments} ue
										   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
										   JOIN {course} c ON (e.courseid = c.id)
										   WHERE ue.userid = :user ".$courseregisterd.$coursesearch,
										   array('user' => $userid));
				  }
				  
				foreach($coursedataobj as $coursedata)	{
					
					$duedate ='';
					if($coursedata->due > 0)
						$duedate =date('m/d/Y',$coursedata->due);
						
					$completedate = '';
					$course_completions =$DB->get_record('course_completions',array('userid'=>$userid,'course'=>$coursedata->id));
					if($course_completions){
						if(isset($course_completions->timecompleted))
							$completedate	= date('m/d/Y',$course_completions->timecompleted);
					}
					
					// Get criteria for course
					$completion = new \completion_info($coursedata);
					$progress ='';
					if (!$completion->has_criteria()) {
						$progress = 'Not Started';
					}
					else{
						$modinfo = get_fast_modinfo($coursedata);
						$result = array();
						foreach ($modinfo->get_cms() as $cm) {
							if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
								$result[$cm->id] = $cm->id;
							}
						}
						 $coursemodules = count($result);
						
						
						if($coursemodules ==0)
							$progress = 'Nil';
						else{
							 $completedcoursemodules = $DB->count_records_sql("select count(cm.id) 
													from {course_modules_completion} cmc 
													join {course_modules} cm on cm.id=cmc.coursemoduleid
													where cmc.completionstate=1 and
													 cm.course =:course and cmc.userid=$userid",
													 array('course'=>$coursedata->id));
							$progress = round((($completedcoursemodules /$coursemodules)*100),2).'%'; 
							
						}
					}
					if($format=='pdf' || $format=='html'){
						$tablepdf .= '<tr >
										<td colspan="2">'.$coursedata->fullname.' </td>
										<td colspan="2">'.$progress.'</td>
										<td>'.date('m/d/Y',$coursedata->registered).'</td>
										<td >'.$completedate.'</td>
										<td >'.$duedate.'</td>
										</tr>';
													  
				   }
					if($format =='csv' || $format =='xlsx'){
						$csvdata .= '"'.$coursedata->fullname.
										'","'.$progress.
										'","'.date('m/d/Y',$coursedata->registered).
										'","'.$completedate.
										'","'.$duedate. "\"\n";
					} 
					 
				 }
					
				}
				if($format=='pdf'  || $format=='html'){
					$tablepdf .= '	</tbody>
								</table>
					';	
					$html = $reportdaterange.'<br>'.$reportdate;				
				}
				if($format=='pdf' ){
					
					$filename = 'courseprogressreport.pdf';
					base::create_tempdf($reporthead,$html,$tablepdf,$filename);	
				}
				if($format =='csv' || $format =='xlsx'){
				
					$filename = 'courseprogressreport.'.$format;
					file_put_contents($fileLocation.$filename, $csvdata);
				}
				if($format=='html'){
					$schedule->html =$html.$tablepdf;	
					$filename = '';					
				}
			}
			
		}
		else if($schedule->reportname == "user_registration"){
			require_once($CFG->dirroot.'/local/report_user_registration/locallib.php');
			$reporthead = 'User Registration Report';
			$returnobj = base::get_all_user($departmentid, 0, $params);
			$userdataobj= $returnobj->users ;
			if($format=='pdf'  || $format=='html'){

				$tablepdf ='<table>
						<thead >
						<tr style="background-color: rgb(203, 205, 208);">
							<th style="text-align:left;" >'.get_string('username', 'local_base').'</th>
							<th style="text-align:center;">'.get_string('firstname', 'local_base').'</th>
							<th  style="text-align:left;">'.get_string('lastname', 'local_base').'</th>
							<th style="text-align:left;">'.get_string('organization', 'local_base').'</th>
							<th style="text-align:left;">'.get_string('subgroup', 'local_base').'</th>
							<th style="text-align:left;">'.get_string('createdon', 'local_base').'</th>
							<th style="text-align:left;">'.get_string('lastloggedin', 'local_base').'</th>
							<th style="text-align:left;">'.get_string('nooflogins', 'local_base').'</th>
						</tr>
						</thead>
					<tbody> ';
			}
			if($format =='csv' || $format =='xlsx'){
				$csvdata   ='"'.$reporthead."\"\n"	;
				$csvdata  .='"'.$reportdaterange."\"\n"	;
				$csvdata  .='"'.$reportdate."\"\n"	;
				$csvdata  .= '"'.get_string('username', 'local_base').'","'
							.get_string('firstname', 'local_base').'","'
							.get_string('lastname', 'local_base').'","'
							.get_string('organization', 'local_base').'","'
							.get_string('subgroup', 'local_base').'","'
							.get_string('createdon', 'local_base').'","'
							.get_string('lastloggedin', 'local_base').'","'
							.get_string('nooflogins', 'local_base')."\"\n"	;
			}
			$i=1;
			if($userdataobj){
				foreach($userdataobj as $user){
				
					$userid=$user->id;
					$user=$DB->get_record('user',array('id'=>$userid));
					
/*					$sql = "select name,cu.departmentid from {company} c 
					join  {company_users} cu on c.id=cu.companyid 
					where cu.userid=".$userid;
		if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
				$sql .=" AND cu.departmentid in (".$params['subgroup'].")";
		}
					$userorganization = $DB->get_record_sql($sql);
					$orgname = $userorganization->name;*/
					$countloggedin = report_user_registration::countUserLoggedin($userid);
//						$subgroupname = base::get_subgroupname($userorganization->departmentid);
$sql = "select distinct(c.id),name from {company} c 
						join  {company_users} cu on c.id=cu.companyid 
						where cu.userid=".$userid;
				if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
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
}
			$lastaccess = empty($user->lastaccess) ? "" : date('m/d/Y',$user->lastaccess);								  			
					if($format=='pdf'  || $format=='html'){
						if($i%2==0)
							$style = 'background-color: #ece9e9;';
						else
							$style ='';								  					$lastaccess = empty($user->lastaccess) ? "" : date('m/d/Y',$user->lastaccess);
						$tablepdf  .= '<tr style="'.$style.'">
									<td style="text-align:left;">'.$user->username.' </td>
									<td style="text-align:center;" >'.$user->firstname.'</td>
									<td style="text-align:left;">'.$user->lastname.' </td>
									<td style="text-align:left;">'.$orgname.'</td>
									<td style="text-align:left;">'.$subgroupname.'</td>
									<td style="text-align:left;">'.date('m/d/Y',$user->timecreated).'</td>
									<td style="text-align:left;">'.$lastaccess.'</td>
									<td style="text-align:left;">'.$countloggedin.'</td>
								</tr>';
				$i++;

					}
					if($format =='csv' || $format =='xlsx'){
						$csvdata .= '"'.$user->username.
											'","'.$user->firstname.
											'","'.$user->lastname.
											'","'.$orgname.
											'","'.$subgroupname.
											'","'.date('m/d/Y',$user->timecreated).
											'","'.$lastaccess.
											'","'.$countloggedin. "\"\n";
					}
						
				}
			}
			if($format=='pdf'  || $format=='html'){
				$tablepdf .= '	</tbody>
							</table>
				';
				$html = '<h1>'.$reporthead.'</h1><h5>'.$reportdaterange.'<br>'.$reportdate.'</h5><br>';
			}
			if($format=='pdf' ){
			
				$filename = 'userregistration.pdf';	
				// output the HTML content
				base::create_tempdf($reporthead,$reportdaterange.'<br>'.$reportdate,$tablepdf,$filename);	
			}
			if($format =='csv' || $format =='xlsx'){
			
				$filename = 'userregistration.'.$format;
				file_put_contents($fileLocation.$filename, $csvdata);
			}
			if($format=='html'){
				$schedule->html =$html.$tablepdf;	
				$filename = '';					
			}
		}	
		else if($schedule->reportname == "login_activity"){
			$reporthead = 'Login Activity Report';
			$searchsessionfrom=" ";
			if($params['daterange'] !='no'){
				$beginOfDay = strtotime("midnight", $params['datefrom']);
				$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
				$searchsessionfrom=" AND timecreated > $beginOfDay AND  timecreated < $endOfDay";
					}
require_once($CFG->dirroot.'/local/report_login_activity/locallib.php');
			$returnobj = report_login_activity::get_users_login($departmentid, $params);
			$userdataobj= $returnobj->users ;
			if($format=='pdf'  || $format=='html'){

				$tablepdf ='<table>
								<thead >
									<tr style="background-color: rgb(203, 205, 208);">
										<th style="text-align:left;" >'.get_string('username', 'local_base').'</th>
										<th style="text-align:center;">'.get_string('firstname', 'local_base').'</th>
										<th  style="text-align:left;">'.get_string('lastname', 'local_base').'</th>
										<th style="text-align:left;">'.get_string('sessionstart', 'local_base').'</th>
										<th style="text-align:left;">'.get_string('sessionend', 'local_base').'</th>
										<th style="text-align:left;">'.get_string('timeconnect', 'local_base').'</th>
									</tr>
								</thead>
							<tbody> ';
			}
			if($format =='csv' || $format =='xlsx'){
				$csvdata   ='"'.$reporthead."\"\n"	;
				$csvdata  .='"'.$reportdaterange."\"\n"	;
				$csvdata  .='"'.$reportdate."\"\n"	;
				$csvdata  .= '"'.get_string('username', 'local_base').'","'
							.get_string('firstname', 'local_base').'","'
							.get_string('lastname', 'local_base').'","'
							.get_string('sessionstart', 'local_base').'","'
							.get_string('sessionend', 'local_base').'","'
							.get_string('timeconnect', 'local_base')."\"\n"	;
			}
														$i=1;

			foreach($userdataobj as $usersession){
			
				$userid=$usersession->userid;
				$user=$DB->get_record('user',array('id'=>$userid));
				
				
						$sessionend =0;	
						if($usersession->action == 'loggedin')
						$sessionstart = $usersession->timecreated;
						else if($usersession->action == 'loggedout')
							$sessionend = $usersession->timecreated;
						if($sessionend > 0){
							$time= $sessionend - $sessionstart;
							$str_time = date("H:i:s",$time);

							if($format=='pdf'  || $format=='html'){
								if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';								  			
								$tablepdf  .= '<tr style="'.$style.'">
												<td style="text-align:left;">'.$user->username.' </td>
												<td  style="text-align:center;">'.$user->firstname.'</td>
												<td style="text-align:left;">'.$user->lastname.' </td>
												<td style="text-align:left;">'.date('m/d/Y h:i:s A',base::usertimezonenextrun($sessionstart)).'</td>
												<td style="text-align:left;" >'.date('m/d/Y h:i:s A' , base::usertimezonenextrun($sessionend)).'</td>
												<td style="text-align:left;">'.$str_time.'</td>
												</tr>';
	   		$i++;

							}
							if($format =='csv' || $format =='xlsx'){
								$csvdata .= '"'.$user->username.
													'","'.$user->firstname.
													'","'.$user->lastname.
													'","'.date('m/d/Y h:i:s A',base::usertimezonenextrun($sessionstart)).
													'","'.date('m/d/Y h:i:s A' ,base::usertimezonenextrun($sessionend)).
													'","'.$str_time. "\"\n";
							}
								
						
					
				}
			}
						
			if($format=='pdf'  || $format=='html'){
				$tablepdf .= '	</tbody>
							</table>
				';
				$html = '<h1>'.$reporthead.'</h1><h5>'.$reportdaterange.'<br>'.$reportdate.'</h5><br>';
			}
			if($format=='pdf' ){
			
				$filename = 'loginactivity.pdf';	
				base::create_tempdf($reporthead,$reportdaterange.'<br>'.$reportdate,$tablepdf,$filename);	
			}
			if($format =='csv' || $format =='xlsx'){
			
				$filename = 'loginactivity.'.$format;
				file_put_contents($fileLocation.$filename, $csvdata);
			}
			if($format=='html'){
				$schedule->html =$html.$tablepdf;	
				$filename = '';					
			}
		}	
		else if($schedule->reportname == "schedule_reports"){
			 require_once($CFG->dirroot.'/local/report_schedule_reports/locallib.php');

			$reporthead = 'Scheduled Reports';
			$activehead = 'Active Scheduled Reports';
			$inactivehead = 'Inactive Scheduled Reports';
			$returnobj = report_schedule_reports::scheduled_reports($departmentid);

			if($returnobj){
				$active= $returnobj->active ;
				$inactive= $returnobj->inactive ;
				
				if($format=='pdf'  || $format=='html'){

				 $activepdf = $inactivepdf ='<table >
								<thead  >
								<tr style="background-color: rgb(203, 205, 208);">
								<th class="header c1" style="text-align:center;" >'.get_string('description', 'local_report_schedule_reports').'</th>
								<th class="header c3" style="text-align:center;" >'.get_string('nextrun', 'local_report_schedule_reports').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('lastrun', 'local_report_schedule_reports').'</th>
								<th colspan="2" class="header c4" style="text-align:center;" >'.get_string('recipients', 'local_report_schedule_reports').'</th>
								<th colspan="2" class="header c4" style="text-align:center;" >'.get_string('scheduledescription', 'local_report_schedule_reports').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('format', 'local_report_schedule_reports').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('pause', 'local_report_schedule_reports').'</th>
								</tr>
								</thead>
								<tbody> ';
				}
				if($format =='csv' || $format =='xlsx'){
					$csvdata   ='"'.$reporthead."\"\n"	;
					$csvdata   .="\n".'"'.$activehead."\"\n"	;
					$inactivedata = $activedata  = '"'.get_string('description', 'local_report_schedule_reports').'","'
								.get_string('nextrun', 'local_report_schedule_reports').'","'
								.get_string('lastrun', 'local_report_schedule_reports').'","'
								.get_string('recipients', 'local_report_schedule_reports').'","'
								.get_string('scheduledescription', 'local_report_schedule_reports').'","'
								.get_string('format', 'local_report_schedule_reports').'","'
								.get_string('pause', 'local_report_schedule_reports')."\"\n"	;
				}
				if($format=='html' || $format=='pdf' ){
						$html = '<br><h3>'.$activehead.'</h3>'; 
				}
				if($active){			
					if($format =='csv' || $format =='xlsx')
						$csvdata   .=$activedata;			
			$i=1;	

					foreach($active as $key=>$report){
						$report->emailusers = str_replace(';', '; ', $report->emailusers);
						$nextrun ='';
						if($report->nextrun > 0)
							$nextrun =date("m/d/Y h:i:s A",base::usertimezonenextrun($report->nextrun));
						$lastrun ='';
						if($report->lastrun > 0)
							$lastrun =date("m/d/Y h:i:s A",base::usertimezonenextrun($report->lastrun));
							
						$discription = report_schedule_reports::sceduleddescription($report);
						$reportformat = strtoupper($report->format);
						
						if($report->pause == 0){
							$exportpause = 'play';
						}else{
							$exportpause = 'paused';
						}
						if($format=='pdf'  || $format=='html'){
								if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';	
							$activepdf  .= '<tr style="'.$style.'">
												<td class="cell c1" style="text-align:center;">'.$report->description.'</td>
												<td class="cell c1" style="text-align:center;">'.$nextrun.'</td>
												<td class="cell c1" style="text-align:center;">'.$lastrun.'</td>
												<td colspan="2" class="cell c1" style="text-align:center;">'.$report->emailusers.'</td>
												<td colspan="2" class="cell c1" style="text-align:center;">'.$discription.'</td>
												<td class="cell c1" style="text-align:center;">'.$reportformat.'</td>
												<td class="cell c1" style="text-align:center;">'.$exportpause.'</td>
												</tr>';
									   		$i++;

						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata .= '"'.$report->description.
												'","'.$nextrun.
												'","'.$lastrun.
												'","'.$report->emailusers.
												'","'.$discription.
												'","'.$reportformat.
												'","'.$exportpause. "\"\n";
						}		
						
					}
					if($format=='pdf'  || $format=='html'){
						$activepdf .= '	</tbody>
									</table>
						';
						
					}
					if($format=='pdf' ){
						$pdf->writeHTML($html, true, false, true, false, '');
						$pdf->writeHTMLCell(0, 0, '', '', $activepdf, 0, 1, 0, true, '', true);
					}
					
				}
				else{
					if($format =='csv' || $format =='xlsx'){
							$csvdata   .='"There are no active report","'."\"\n"	;
					}
					if($format=='html' || $format=='pdf'){
						$html .= '<br>There are no active report'; 
						$activepdf ='';
					}
					if($format=='pdf' ){
							$pdf->writeHTML($html, true, false, true, false, '');
					}
					
				}
				if($format =='csv' || $format =='xlsx'){
					$csvdata   .="\n\n".'"'.$inactivehead."\"\n"	;
				}
				if($format=='html' || $format=='pdf' ){
					$html1 = '<br><h3>'.$inactivehead.'</h3>'; 
				}		
				if($inactive){	
								$i=1;	
	
					foreach($inactive as $key=>$report){
						$report->emailusers = str_replace(';', '; ', $report->emailusers);
						$nextrun ='';
						/*if($report->nextrun > 0)
							$nextrun =date("m/d/Y h:i:s A",base::usertimezonenextrun($report->nextrun));*/
						$lastrun ='';
						if($report->lastrun > 0)
							$lastrun =date("m/d/Y h:i:s A",base::usertimezonenextrun($report->lastrun));
							
						$discription = report_schedule_reports::sceduleddescription($report);
						$reportformat = strtoupper($report->format);
						
						if($report->pause == 0){
							$exportpause = 'play';
						}else{
							$exportpause = 'paused';
						}
						if($format=='pdf'  || $format=='html'){
								if($i%2==0)
									$style = 'background-color: #ece9e9;';
								else
									$style ='';	
							$inactivepdf  .= '<tr style="'.$style.'">
												<td class="cell c1" style="text-align:center;">'.$report->description.'</td>
												<td class="cell c1" style="text-align:center;">'.$nextrun.'</td>
												<td class="cell c1" style="text-align:center;">'.$lastrun.'</td>
												<td colspan="2" class="cell c1" style="text-align:center;">'.$report->emailusers.'</td>
												<td colspan="2" class="cell c1" style="text-align:center;">'.$discription.'</td>
												<td class="cell c1" style="text-align:center;">'.$reportformat.'</td>
												<td class="cell c1" style="text-align:center;">'.$exportpause.'</td>
												</tr>';
									   		$i++;

						}
						if($format =='csv' || $format =='xlsx'){
							$inactivedata .= '"'.$report->description.
												'","'.$nextrun.
												'","'.$lastrun.
												'","'.$report->emailusers.
												'","'.$discription.
												'","'.$reportformat.
												'","'.$exportpause. "\"\n";
						}		
						
					}
					if($format=='pdf'  || $format=='html'){
						$inactivepdf .= '	</tbody>
									</table>
						';
						
					}
					if($format=='pdf' ){
						$pdf->writeHTML($html1, true, false, true, false, '');
						$pdf->writeHTMLCell(0, 0, '', '', $inactivepdf, 0, 1, 0, true, '', true);
					}
					
				}
				else{
					if($format =='csv' || $format =='xlsx'){
							$inactivedata   .='"There are no inactive report","'."\"\n"	;
					}
					if($format=='html' || $format=='pdf'){
						$html1 .= '<br>There are no inactive report';
						$inactivepdf ='';
					}
					if($format=='pdf' ){
							$pdf->writeHTML($html1, true, false, true, false, '');
					}
					
				}	
				
			}	
			
			
				if($format=='html'){
					$schedule->html =$html.$activepdf.$html1.$inactivepdf;	
					$filename = '';					
				}
				if($format=='pdf' ){					
					$filename = 'scheduledreport.pdf';
					$pdf->Output($fileLocation.$filename, 'F');
				}
				if($format =='csv' || $format =='xlsx'){
					$filename = 'scheduledreport.'.$format;
					file_put_contents($fileLocation.$filename, $csvdata.$inactivedata);
				}
				
		}	
		else if($schedule->reportname == "course_completion"){
			require_once($CFG->dirroot.'/local/report_course_completion/locallib.php');
			$returnobj = report_course_completion::get_all_users_courses($params);
			$exportuserdataobj= $returnobj->courses ;
			unset($params['download']); 
			$countries = get_string_manager()->get_list_of_countries();
				
			if($exportuserdataobj){				
				$reporthead = 'Course Completion Report';
			
				if($format=='pdf'  || $format=='html'){

					 $tablepdf ='<table class="generaltable" id="ReportTable">
								<thead >
								<tr style="background-color: rgb(203, 205, 208);">
								<th  class="header c0" style="text-align:center;" >'.get_string('firstname', 'local_base').'</th>
								<th  class="header c1" style="text-align:center;" >'.get_string('lastname', 'local_base').'</th>
								<th class="header c3" style="text-align:center;" >'.get_string('username', 'local_base').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('address', 'local_report_course_completion').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('city', 'local_report_course_completion').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('country', 'local_base').'</th>
								<th colspan="2" class="header c4" style="text-align:center;" >'.get_string('email', 'local_base').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('phone', 'local_report_course_completion').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('coursename', 'local_base').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('coursecode', 'local_report_course_completion').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('organization', 'local_base').'</th>
								<th class="header c4" style="text-align:center;" >'.get_string('datelastupdated', 'local_report_course_completion').'</th>
								</tr>
								</thead>
								<tbody> ';
				}
				if($format =='csv' || $format =='xlsx'){
					$csvdata   ='"'.$reporthead."\"\n"	;
					$csvdata  .='"'.$reportdaterange."\"\n"	;
					$csvdata  .='"'.$reportdate."\"\n"	;
					$csvdata  .= '"'.get_string('firstname', 'local_base').'","'
									.get_string('lastname', 'local_base').'","'
									.get_string('username', 'local_base').'","'
									.get_string('address', 'local_report_course_completion').'","'
									.get_string('city', 'local_report_course_completion').'","'
									.get_string('country', 'local_base').'","'
									.get_string('email', 'local_base').'","'
									.get_string('phone', 'local_report_course_completion').'","'
									.get_string('coursename', 'local_base').'","'
									.get_string('datecompleted', 'local_base').'","'
									.get_string('coursecode', 'local_report_course_completion').'","'
									.get_string('organization', 'local_base').'","'
									.get_string('datelastupdated', 'local_report_course_completion')."\"\n"	;
				}
				$i=1;

				foreach($exportuserdataobj as $user){
				
					$userid=$user->id;
					$courseid=$user->cid;
					$showcountry="";
					foreach($countries as $key=>$value){
						if($user->country ==$key ){
							$showcountry = $value;
							break;
						}
						
					}
					 $sql = "select * from {logstore_standard_log} 
					where eventname='\\\core\\\\event\\\course_viewed' and userid=".$userid." and courseid=".$courseid.
					" ORDER BY timecreated DESC LIMIT 0,1";
					$lastupdated = $DB->get_record_sql($sql);
					
					$sql = "select name from {company} c 
					join  {company_users} cu on c.id=cu.companyid 
					where cu.userid=".$userid;
					$userorganization = $DB->get_record_sql($sql);
					$orgname = $userorganization->name;
					
					if($format=='pdf'  || $format=='html'){
						if($i%2==0)
							$style = 'background-color: #ece9e9;';
						else
							$style ='';								  			
						$tablepdf  .= '<tr style="'.$style.'">
										<td >'.$user->firstname.' </td>
										<td >'.$user->lastname.'</td>
										<td >'.$user->username.' </td>
										<td >'.$user->address.' </td>
										<td >'.$user->city.' </td>
										<td >'.$showcountry.' </td>
										<td colspan="2" >'.$user->email.' </td>
										<td >'.$user->phone2.' </td>
										<td >'.$user->fullname.' </td>
										<td >'.date('m/d/Y',$user->timecompleted).'</td>
										<td >'.$user->cidnumber.' </td>
										<td >'.$orgname.' </td>
										<td >'.date('m/d/Y',$lastupdated->timecreated).'</td>
						</tr>';
					$i++;

					}
					if($format =='csv' || $format =='xlsx'){
						$csvdata .= '"'.$user->firstname.
									'","'.$user->lastname.
									'","'.$user->username.
									'","'.$user->address.
									'","'.$user->city.
									'","'.$showcountry.
									'","'.$user->email.
									'","'.$user->phone2.
									'","'.$user->fullname.
									'","'.date('m/d/Y',$user->timecompleted).
									'","'.$user->cidnumber.
									'","'.$orgname.
									'","'.date('m/d/Y',$lastupdated->timecreated). "\"\n";
					}
						
				}
				if($format=='pdf'  || $format=='html'){
					$tablepdf .= '	</tbody>
								</table>
					';
					$html = '<h1>'.$reporthead.'</h1><h5>'.$reportdaterange.'<br>'.$reportdate.'</h5><br>';

				}
				if($format=='pdf' ){
					
					$filename = 'coursecompletion.pdf';
					base::create_tempdf($reporthead,$reportdaterange.'<br>'.$reportdate,$tablepdf,$filename);
				}
				if($format =='csv' || $format =='xlsx'){
				
					$filename = 'coursecompletion.'.$format;
					file_put_contents($fileLocation.$filename, $csvdata);
				}
				if($format=='html'){
				$schedule->html =$html.$tablepdf;	
				$filename = '';					
			}
			}
		}
		else if($schedule->reportname == "completionstatus"){	
			require_once($CFG->dirroot.'/local/report_completionstatus/locallib.php');
			$params['download'] = 'download';
			//print_r($params);
			$returnobj = base::get_all_user($departmentid, 0, $params);
			$userdataobj= $returnobj->users ;
			$reporthead = 'Completion Status Report';
	
			if($userdataobj ){
				$userid='';
				foreach($userdataobj as $userobj){
					
					$userid .=$userobj->id.',';
				}
				$userid = trim($userid,',');
				
				$returnobj = report_completionstatus::get_all_users_courses($userid,$params);
				$exportcoursedataobj= $returnobj->users ;
				unset($params['download']); 
				
				if($exportcoursedataobj){
					
					if($format=='pdf'  || $format=='html'){

						$tablepdf ='<table >
							<thead >
							<tr style="background-color: rgb(203, 205, 208);">
							<th  style="text-align:left;"  >'.get_string('firstname', 'local_base').'</th>
							<th   style="text-align:left;" >'.get_string('lastname', 'local_base').'</th>
							<th  style="text-align:left;" >'.get_string('username', 'local_base').'</th>
							<th  style="text-align:left;"  >'.get_string('organization', 'local_base').'</th>
							<th  style="text-align:left;"  >'.get_string('course', 'local_base').'</th>
							<th  style="text-align:left;" >'.get_string('status', 'local_report_completionstatus').'</th>
							<th  style="text-align:left;" >'.get_string('score', 'local_report_completionstatus').'</th>
							<th  style="text-align:left;"  >'.get_string('dateregistered', 'local_base').'</th>
							<th  style="text-align:left;"  >'.get_string('datecompleted', 'local_base').'</th>
							<th  style="text-align:left;"  >'.get_string('validuntil', 'local_report_completionstatus').'</th>
							</tr>
							</thead>
							<tbody> ';
					}
					if($format =='csv' || $format =='xlsx'){
						$csvdata   ='"'.$reporthead."\"\n"	;
						$csvdata  .='"'.$reportdaterange."\"\n"	;
						$csvdata  .='"'.$reportdate."\"\n"	;
						$csvdata  .= '"'.get_string('firstname', 'local_base').'","'
										.get_string('lastname', 'local_base').'","'
										.get_string('username', 'local_base').'","'
										.get_string('organization', 'local_base').'","'
										.get_string('course', 'local_base').'","'
										.get_string('status', 'local_report_completionstatus').'","'
										.get_string('score', 'local_report_completionstatus').'","'
										.get_string('dateregistered', 'local_base').'","'
										.get_string('datecompleted', 'local_base').'","'
										.get_string('validuntil', 'local_report_completionstatus')."\"\n"	;
					}
					$i=1;
					foreach($exportcoursedataobj as $coursedata){
					
						$userid=$coursedata->userid;
						$courseid=$coursedata->id;
						$user = $DB->get_record('user',array('id'=>$userid));

						$sql = "select name from {company} c 
								join  {company_users} cu on c.id=cu.companyid 
								where cu.userid=".$userid;
						$userorganization = $DB->get_record_sql($sql);
						$orgname = $userorganization->name;
						$sql = "SELECT * 
									FROM {grade_grades} gg
									JOIN {grade_items} gi ON (gi.id = gg.itemid  AND gi.itemtype = 'course')
								   WHERE gg.userid =$userid AND gi.courseid = $courseid"; 
						
						$score = '-';
						$course_score =$DB->get_record_sql($sql);
						if($course_score){
							if(isset($course_score->finalgrade))
								$score = round($course_score->finalgrade, 0)."%";
						}
						$sql = "SELECT ue.id as ueid,ue.timecreated as registered,ue.userid as userid,
											ue.timeend as due,c.* 
											FROM {user_enrolments} ue
											JOIN {user} u ON (u.id = ue.userid)
										   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
										   JOIN {course} c ON (e.courseid = c.id)
										   WHERE ue.userid =$userid AND c.id = $courseid"; 
						
						$registered = '-';
						$course_registered =$DB->get_record_sql($sql);
						if($course_registered){
							if(isset($course_registered->registered))
								$registered	= date('m/d/Y',$course_registered->registered);
						}
			
						$completedate = '-';
						$course_completions =$DB->get_record('course_completions',array('userid'=>$userid,'course'=>$coursedata->id));
						if($course_completions){
							if(isset($course_completions->timecompleted))
								$completedate	= date('m/d/Y',$course_completions->timecompleted);
						}
						if (!$iomadcourseinfo = $DB->get_record('iomad_courses', array('courseid' => $coursedata->id))) {
							$iomadcourseinfo = new stdclass();
						  }
						if (!empty($course_completions->timecompleted) && !empty($iomadcourseinfo->validlength)) {
							$validuntil = date('m/d/Y', $course_completions->timecompleted + ($iomadcourseinfo->validlength * 24 * 60 * 60) );
						} else {
							$validuntil = "-";
						}
			
						$completion = new completion_info($coursedata);
						$status ='';
						if (!$completion->has_criteria()) {
							$status = 'Not Started';
						}
						else{
							$modinfo = get_fast_modinfo($coursedata);
							$result = array();
							foreach ($modinfo->get_cms() as $cm) {
								if ($cm->completion != COMPLETION_TRACKING_NONE && !$cm->deletioninprogress) {
									$result[$cm->id] = $cm->id;
								}
							}
							 $coursemodules = count($result);
							
							
							if($coursemodules ==0)
								$status = 'Nil';
							else{
								 $completedcoursemodules = $DB->count_records_sql("select count(cm.id) 
														from {course_modules_completion} cmc 
														join {course_modules} cm on cm.id=cmc.coursemoduleid
														where cmc.completionstate=1 and
														 cm.course =:course and cmc.userid=$userid",
														 array('course'=>$coursedata->id));
								$status = round((($completedcoursemodules /$coursemodules)*100),2).'%'; 
								
							}
						}
						
						if($format=='pdf'  || $format=='html'){
							if($i%2==0)
								$style = 'background-color: #ece9e9;';
							else
								$style ='';								  			
							$tablepdf  .= '<tr style="'.$style.'">
										<td style="text-align:left;">'.$user->firstname.' </td>
										<td  style="text-align:left;">'.$user->lastname.'</td>
										<td  style="text-align:left;">'.$user->username.' </td>
										<td  style="text-align:left;">'.$orgname.' </td>
										<td  style="text-align:left;">'.$coursedata->fullname.' </td>
										<td  style="text-align:left;">'.$status.' </td>
										<td  style="text-align:left;">'.$score.' </td>
										<td  style="text-align:left;">'.$registered.'</td>
										<td  style="text-align:left;">'.$completedate.'</td>
										<td  style="text-align:left;">'.$validuntil.'</td>
										</tr>';
						$i++;

						}
						if($format =='csv' || $format =='xlsx'){
							$csvdata .= '"'.$user->firstname.
										'","'.$user->lastname.
										'","'.$user->username.
										'","'.$orgname.
										'","'.$coursedata->fullname.
										'","'.$status.
										'","'.$score.
										'","'.$registered.
										'","'.$completedate.
										'","'.$validuntil. "\"\n";
						}
							
					}
					if($format=='pdf'  || $format=='html'){
						$tablepdf .= '	</tbody>
									</table>
						';
						$html = $reportdaterange.'<br>'.$reportdate;
					}
					if($format=='pdf' ){
						
						$filename = 'completionstatus.pdf';
						base::create_tempdf($reporthead,$html,$tablepdf,$filename);
		
					}
					if($format =='csv' || $format =='xlsx'){
					
						$filename = 'completionstatus.'.$format;
						file_put_contents($fileLocation.$filename, $csvdata);
					}
					if($format=='html'){
						$schedule->html =$html.$tablepdf;	
						$filename = '';					
					}
							
				}
			}
			
		}
		else if($schedule->reportname == "registration"){	
			require_once($CFG->dirroot.'/local/report_registration/locallib.php');
			$params['download'] = 'download';
			//print_r($params);
			$returnobj = report_registration::get_all_license_events($params);
			$exportuserdataobj= $returnobj->events ;
			$reporthead = 'Registration Report';
			$countries = get_string_manager()->get_list_of_countries();
	
			if($exportuserdataobj){
				
				if($format=='pdf'  || $format=='html'){

					$tablepdf ='<table >
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
				if($format =='csv' || $format =='xlsx'){
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
				foreach($exportuserdataobj as  $logevent){
					   unset($licenseid);
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
		else{		
			$licenseid=$logevent->objectid;
		}
					$userid=$logevent->userid;
					$user=$DB->get_record('user',array('id'=>$userid));
					$courseid=$logevent->courseid;
					$course=$DB->get_record('course',array('id'=>$courseid));
					if($course)
						$coursefullname = $course->fullname;
					else
						$coursefullname = "";
					$showcountry="";
					foreach($countries as $key=>$value){
						if($user->country ==$key ){
							$showcountry = $value;
							break;
						}
						
					}		
					$sql = "select distinct(c.id),name from {company} c 
							join  {company_users} cu on c.id=cu.companyid 
							where cu.userid=".$userid;
					if(isset($params['subgroup']) && $params['subgroup'] != "0" ){
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
					}
			
					if($format=='pdf'  || $format=='html'){
						if($i%2==0)
							$style = 'background-color: #ece9e9;';
						else
							$style ='';								  			
						$tablepdf  .= '<tr style="'.$style.'">
									<td style="text-align:left;">'.date('m/d/Y',$logevent->timecreated).' </td>
									<td style="text-align:left;">'.$licensename.'</td>
									<td style="text-align:left;">'.$coursefullname.' </td>
									<td style="text-align:left;">'.$action.' </td>
									<td style="text-align:left;">'.$user->username.' </td>
									<td style="text-align:left;">'.$user->firstname.' </td>
									<td style="text-align:left;" colspan="2" >'.$user->lastname.' </td>
									<td style="text-align:left;">'.$showcountry.' </td>
									<td style="text-align:left;">'.$orgname.' </td>
									<td style="text-align:left;">'.$subgroupname.'</td>
									<td style="text-align:left;">'.$user->email.' </td>
									<td style="text-align:left;">'.$user->phone2.' </td>
									<td style="text-align:left;">'.''.'</td>
									<td style="text-align:left;">'.$user->id.'</td>
									</tr>';
					$i++;

					}
					if($format =='csv' || $format =='xlsx'){
							$csvdata .= '"'.date('m/d/Y',$logevent->timecreated).
										'","'.$licensename.
										'","'.$coursefullname.
										'","'.$action.
										'","'.$user->username.
										'","'.$user->firstname.
										'","'.$user->lastname.
										'","'.$showcountry.
										'","'.$orgname.
										'","'.$subgroupname.
										'","'.$user->email.
										'","'.$user->phone2.
										'","'.''.
										'","'.$user->id. "\"\n";
					}
						
				}
					if($format=='pdf'  || $format=='html'){
						$tablepdf .= '	</tbody>
									</table>
						';
						$html = $reportdaterange.'<br>'.$reportdate;
					}
					if($format=='pdf' ){
						
						$filename = 'registration.pdf';
						base::create_tempdf($reporthead,$html,$tablepdf,$filename);
		
					}
					if($format =='csv' || $format =='xlsx'){
					
						$filename = 'registration.'.$format;
						file_put_contents($fileLocation.$filename, $csvdata);
					}
					if($format=='html'){
						$schedule->html =$html.$tablepdf;	
						$filename = '';					
					}
							
				}			
		}		
		 //set nextrun
		base::schedule_email($schedule,$filename);
		base::nextrun($schedule);

	}

 public static function usertimezone($userid){
		 global $CFG,$DB;
	
		$usertimezone = 'EST';	
		$date = new DateTime($usertimezone);
		$timeZone = $date->getTimezone();
		$timezonename= $timeZone->getName();
		date_default_timezone_set($timezonename);
		date_default_timezone_get();
		$timezone = date('h:i A');
     
		return $timezone;
		 
	 }
	 public static function usertimezonenextrun($nextrun){
		
		global $CFG,$DB;
	
		$tousertimezone = 'EST';		
		$nextrun = date('m/d/Y h:i A',$nextrun);
		$fromtimezone = date_default_timezone_get();
		$date = new DateTime($nextrun, new DateTimeZone($fromtimezone));
        $date->setTimezone(new DateTimeZone($tousertimezone));
        $time= $date->format('Y-m-d h:i:s A');
        $nextrun =  strtotime($time);
		return $nextrun;
		 
	 }
	 public static function servertimezonenextrun($nextrun){
			
		 $date = new DateTime($nextrun, new DateTimeZone('EST'));
		 $fromtimezone = date_default_timezone_get();

		 $date->setTimezone(new DateTimeZone($fromtimezone));
	         $time= $date->format('Y-m-d h:i:s A'); 
	    	 $nextrun =  strtotime($time);
		return $nextrun;
		 
	 }

	 public static function dateRange_info($range){
		 if($range == 'Sincerecent')
		 			return 'Since Recent';
		 else{
			$daterange = base::dateRange();
			$infotoprint ='';
			foreach($daterange as $key => $value){
				$keyarray = explode('-',$key);
				if($keyarray[1]==$range){
					$infotoprint = $value;
					break;
				}
			}
			return $infotoprint;
		}
	}
	 public static function get_username($id){
		 global $DB;
		 $user = $DB->get_record('user',array('id'=>$id));
		 $username = $user->username;
		 return $username;
	 }
	 public static function create_tempdf($reporthead,$reportdate,$tablepdf,$filename){
		 global $CFG,$DB;
		 require_once($CFG->libdir.'/tcpdf/tcpdf.php');
		$fileLocation = $CFG->tempdir; 
		$fileLocation = rtrim($fileLocation, '/') . '/';
		$pdf = base::createpdfobject();
		$pdf->SetY( 15 ); 
		$pdf->SetX( 15 ); 
		$pdf->SetFont('Helvetica','B',16);
		$pdf->writeHTML($reporthead, true, false, true, false, '');
		$pdf->SetFont('Helvetica','',10);
		$pdf->writeHTML('<br><br>'.$reportdate.'<br>', true, false, true, false, '');
		$pdf->SetFont('Helvetica', '', 7);
		$pdf->writeHTMLCell(0, 0, '', '', $tablepdf, 0, 1, 0, true, '', true);
		$pdf->Output($fileLocation.$filename, 'F');
	 }
	public static function get_user_course($userid,$params)
	{
		global $DB;
		if(isset($params['daterange']) && $params['daterange'] =='datereg' ){
			$beginOfDay = strtotime("midnight", $params['datefrom']);
			$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
			$courseregisterd=" AND ue.timecreated > $beginOfDay AND  ue.timecreated < $endOfDay";
		}
		else
			$courseregisterd = '';
		$coursesearch ='';
		if(!empty($params['course']) && $params['course'] != "0"){
			$course = $params['course'];
			$coursesearch = " AND c.id in ($course) ";
		}
		if(isset($params['daterange']) && $params['daterange'] =='datecomp' ){
				$beginOfDay = strtotime("midnight", $params['datefrom']);
				$endOfDay   = strtotime("tomorrow", $params['dateto']) - 1;
				
				$coursedataobj = $DB->get_records_sql("SELECT distinct(ue.id) as ueid,ue.timecreated as registered,
								ue.timeend as due,c.* 
								FROM {user_enrolments} ue
							   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
							   JOIN {course} c ON (e.courseid = c.id)
							   JOIN {course_completions} cc ON (cc.course = c.id)
							   WHERE ue.userid = $userid  AND  cc.userid=$userid 
							   $coursesearch
							   AND cc.timecompleted > $beginOfDay AND  cc.timecompleted < $endOfDay "
							   .$courseregisterd); 
		  }
		  else{
			 
			  $coursedataobj = $DB->get_records_sql("SELECT ue.id as ueid,ue.timecreated as registered,
									ue.timeend as due,c.* 
									FROM {user_enrolments} ue
								   JOIN {enrol} e ON (e.id = ue.enrolid AND e.status = 0)
								   JOIN {course} c ON (e.courseid = c.id)
								   WHERE ue.userid = :user ".$courseregisterd.$coursesearch,
								   array('user' => $userid));
		  }	
			return  $coursedataobj;

	}
public static function format_time($t,$f=':') // t = seconds, f = separator 
	{
	  return sprintf("%02d%s%02d%s%02d", floor($t/3600), $f, ($t/60)%60, $f, $t%60);
	}	

//Custom code changes start

	/**
     * function to flatten a multi-dimension array to a single dimension array.
     *
     * Parameters -
     *              $array = array();
     *              &$result = array();
     *
     * Returns array();
     *
     **/
    public static function array_flatten($array, &$result=null) {

        $r = null === $result;
        $i = 0;
        foreach ($array as $key => $value) {
            $i++;
            if (is_array($value)) {
                self::array_flatten($value, $result);
            } else {
                $result[$key] = $value;
            }
        }
        if ($r) {
            return $result;
        }
    }	

	public static function get_license_course_list(){
		global $DB;
		$context = context_system::instance();
		$companyid = iomad::get_my_companyid($context);
		$sql ="select * from {companylicense}
					where companyid = $companyid ORDER BY name ASC";
		$userlicenses = $DB->get_records_sql($sql);
		$licensecourse_array = array();
		$licensetagcourse_array = array();
		foreach ($userlicenses as $licenscourses) {

			$licensecourses_list = $DB->get_records_sql("select * from {companylicense_courses}
					where licenseid = $licenscourses->id");

			$licensetagcourses_list = $DB->get_records_sql("select * from {companylicense_tags}
					where licenseid = $licenscourses->id");

			if(!empty($licensetagcourses_list) && !empty($licensecourses_list)){
				
				$licensetagcourse_array[] = $licensetagcourses_list;

				$licensecourse_array[] = $licensecourses_list;
			}

		}
		$all_courses_list = self::array_flatten(array_merge($licensecourse_array,$licensetagcourse_array));
		$all_courses_list_id = array();
		if(isset($all_courses_list)){
			foreach ($all_courses_list as $value) {
				$all_courses_list_id[] = $value->courseid;
			}
		}
		$all_courses_list = array_unique($all_courses_list_id);

		return $all_courses_list;
	 }

//Custom code changes end

	
}

require_once($CFG->libdir.'/tcpdf/tcpdf.php');
class MYPDF extends TCPDF {

    // Page footer
    public function Footer() {
		$this->SetY( -15 ); 
		$this->SetX( 0 ); 

        // Set font
        $this->SetFont('Helvetica', '', 8);
        // Page number
        $this->Cell(0, 0, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'C');
        $this->SetY( -15 ); 
	$this->SetX( 170 ); 
        $currenttime = date("d-M-Y H:i A",time());
        $this->Cell(0, 0,$currenttime, 0, false, 'C', 0, '', 0, false, 'T', 'M');

    }
}
