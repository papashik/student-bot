<?php
class MYSQL {
	private $conn;
	private $log;

	public function __construct($conn) {
		mysqli_set_charset($conn, 'utf8mb4');
		mysqli_query($conn, "SET NAMES 'utf8mb4'");
		$this->conn = $conn;
		$this->log = new LOG('MysqlLog');
		$this->log->addLogText("Запросник создан");
	}

	private function getOneRow($sql) {
		// Especially for requests with LIMIT 1
		// Returns FALSE or ARRAY()
		$sql_result = $this->getSqlResult($sql);
		if (!$sql_result) { return false; }
		$row = mysqli_fetch_assoc($sql_result);
		$this->log->addLogText("Результат запроса: ".implode(', ', $row));
		return $row;
	}

	private function getSqlResult($sql) {
		$sql_result = mysqli_query($this->conn, $sql);
		$this->log->addLogText("Попытка запроса: ".$sql);
		if (!$sql_result){
    			$this->log->addLogText("Ошибка запроса: ".mysqli_error($this->conn));
			return false;
		} else { $this->log->addLogText("Успешный запрос"); }

		if (mysqli_num_rows($sql_result) == 0) {
			$this->log->addLogText("Запрос не содержит результата");
			return false;
		} else {
			return $sql_result;
		}
	}

	public function getUser($telegram_id) {
		// returns FALSE or ARRAY(id, full_name, role, class_id, status, status_info)
		$sql = "SELECT
					user_id AS id, user_full_name AS full_name,
					user_role AS role, user_class_id AS class_id,
					user_status AS status, user_status_info AS status_info
				FROM user_table
				WHERE user_tg_id = $telegram_id
				LIMIT 1";
		return $this->getOneRow($sql);
	}

	public function getTgId($user_id) {
		$sql = "SELECT user_tg_id AS tg_id
				FROM user_table
				WHERE user_id = $user_id
				LIMIT 1";
		$arr = $this->getOneRow($sql);
		return ($arr ? $arr['tg_id'] : $arr);
	}

	public function getUserByFullName($full_name) {
		// returns FALSE or ARRAY(id, full_name, role, class_id, status, status_info)
		$sql = "SELECT
					user_id AS id, user_full_name AS full_name,
					user_role AS role, user_class_id AS class_id,
					user_status AS status, user_status_info AS status_info
				FROM user_table
				WHERE user_full_name = '$full_name'
				LIMIT 1";
		return $this->getOneRow($sql);
	}

	public function getAllMarks($class_id) {
		$sql = "SELECT user_full_name, delivery_files, delivery_description, delivery_date
				FROM delivery_table
				LEFT JOIN work_table
				ON (delivery_work_id = work_id)
				LEFT JOIN user_table
				ON (user_id = delivery_user_id)
				WHERE user_class_id = $class_id
				ORDER BY delivery_date";
		return $this->getSqlResult($sql);
	}

	public function getClassId($class_name_ru) {
		/// Returns FALSE or class_id
		$sql = "SELECT class_id
				FROM class_table
				WHERE class_name_ru = '$class_name_ru'
				LIMIT 1";
		$arr = $this->getOneRow($sql);
		return ($arr ? $arr['class_id'] : $arr);
	}
	
	public function getClassNameRu($class_id) {
		/// Returns FALSE or class_name_ru
		$sql = "SELECT class_name_ru
				FROM class_table
				WHERE class_id = $class_id
				LIMIT 1";
		$arr = $this->getOneRow($sql);
		return ($arr ? $arr['class_name_ru'] : $arr);
	}


	public function addUser($user_tg_id, $user_tg_username, $user_full_name, $user_class_name_ru, $user_role='student', $user_status='student_main', $user_status_info=0) {
		$user_class_id = $this->getClassId($user_class_name_ru);
		$user_class_id = ($user_class_id ? $user_class_id : 0);
		$sql = "INSERT INTO user_table (user_id, user_tg_id, user_tg_username, user_full_name, user_class_id, user_role, user_status, user_status_info)
		 		VALUES (NULL, $user_tg_id, '$user_tg_username', '$user_full_name', $user_class_id, '$user_role', '$user_status', $user_status_info)";
		return $this->getSqlResult($sql);
	}

	public function setStatus($user_id, $user_status, $user_status_info=0) {
		$sql = "UPDATE user_table SET user_status = '$user_status', user_status_info = $user_status_info WHERE user_id = $user_id";
		return $this->getSqlResult($sql);
	}

	public function getStatistics($user_id) {
		return 'Норм статистика';
	}

	public function getClassWorksAmount($class_id) {
		$sql = "SELECT class_works_amount FROM class_table WHERE class_id = $class_id LIMIT 1";
		$arr = $this->getOneRow($sql);
		return ($arr ? $arr['class_works_amount'] : $arr);
	}

	public function getStudentStats($user_id, $class_id) {
		$sql = "SELECT DISTINCT work_id, work_number, work_name, delivery_user_id, mark
				FROM work_table
				LEFT JOIN delivery_table
				ON (delivery_user_id = $user_id AND delivery_work_id = work_id)
				LEFT JOIN mark_table
				ON (mark_user_id = $user_id AND mark_work_id = work_id)
				WHERE work_class_id = $class_id AND work_activity = 1
				ORDER BY work_number";
		return $this->getSqlResult($sql);
	}

	public function getWork($work_class_id, $work_number) {
		// Returns work by class_id and work number in class
		$sql = "SELECT work_id AS id, work_number AS number, work_name AS name, work_description AS description, work_files AS files, work_deadline AS deadline
				FROM work_table WHERE work_class_id = $work_class_id AND work_number = $work_number LIMIT 1";
		return $this->getOneRow($sql);
	}

	public function getWorkById($work_id) {
		// Returns work by it's id
		$sql = "SELECT work_id AS id, work_class_id AS class_id, work_number AS number, work_name AS name, work_description AS description, work_files AS files, work_deadline AS deadline
				FROM work_table WHERE work_id = $work_id LIMIT 1";
		return $this->getOneRow($sql);
	}

	public function getMarkArray($user_id, $work_id) {
		$sql = "SELECT mark_date AS date, mark, mark_description AS description
				FROM mark_table
				WHERE mark_user_id = $user_id AND mark_work_id = $work_id
				LIMIT 1";
		return $this->getOneRow($sql);
	}

	public function deleteMark($user_id, $work_id) {
		$sql = "DELETE FROM mark_table WHERE mark_user_id = $user_id AND mark_work_id = $work_id";
		return $this->getSqlResult($sql);
	}

	public function changeMark($user_id, $work_id, $mark, $mark_description='') {
		$sql = "UPDATE mark_table SET mark = $mark, mark_description = '$mark_description', mark_date = now() 
				WHERE mark_user_id = $user_id AND mark_work_id = $work_id";
		$this->getSqlResult($sql);
		$sql = "SELECT mark_id
				FROM mark_table
				WHERE mark_user_id = $user_id AND mark_work_id = $work_id
				LIMIT 1";
		return $this->getOneRow($sql)['mark_id'];
	}
	
	public function changeMarkById($mark_id, $mark, $mark_description='') {
		// returns user_id of user, which mark was set
		$sql = "UPDATE mark_table SET mark = $mark, mark_description = '$mark_description', mark_date = now() 
				WHERE mark_id = $mark_id";
		$this->getSqlResult($sql);
		$sql = "SELECT mark_user_id
				FROM mark_table
				WHERE mark_id = $mark_id
				LIMIT 1";
		return $this->getOneRow($sql)['mark_user_id'];
	}
	
	public function changeMarkDescriptionById($mark_id, $mark_description='') {
		// returns user_id of user, which mark was set
		$sql = "UPDATE mark_table SET mark_description = '$mark_description', mark_date = now() 
				WHERE mark_id = $mark_id";
		$this->getSqlResult($sql);
		$sql = "SELECT mark_user_id
				FROM mark_table
				WHERE mark_id = $mark_id
				LIMIT 1";
		return $this->getOneRow($sql)['mark_user_id'];
	}
	
	public function getMarkById($mark_id) {
		// Returns mark by it's id
		$sql = "SELECT mark_id AS id, mark_user_id AS user_id, mark_work_id AS work_id, mark_date AS date, mark_setter_user_id AS setter_user_id, mark, mark_description AS description
				FROM mark_table WHERE mark_id = $mark_id LIMIT 1";
		return $this->getOneRow($sql);
	}

	public function addMark($user_id, $work_id, $mark, $mark_description='') {
		$sql = "INSERT INTO mark_table (mark_user_id, mark_work_id, mark, mark_description)
				VALUES ($user_id, $work_id, $mark, '$mark_description')";
		return $this->getSqlResult($sql);
	}

	public function addDelivery($delivery_user_id, $delivery_work_id, $delivery_files, $delivery_description='') {
		$sql = "INSERT INTO delivery_table (delivery_user_id, delivery_work_id, delivery_files, delivery_description)
		 		VALUES ($delivery_user_id, $delivery_work_id, '$delivery_files', '$delivery_description')";
		return $this->getSqlResult($sql);
	}

	public function getStudentDeliveries($user_id, $work_id) {
		// Returns all deliveries, that student has on this work
		$sql = "SELECT delivery_work_id, delivery_files, delivery_description, delivery_date
				FROM delivery_table
				WHERE delivery_user_id = $user_id AND delivery_work_id = $work_id
				ORDER BY delivery_date";
		return $this->getSqlResult($sql);
	}

	public function getTeachers($user_id) {
		// get list of student's teachers
		$sql = "SELECT user_tg_username AS tg_username, user_full_name AS full_name 
				FROM user_table
				WHERE user_role = 'teacher' AND user_class_id = (SELECT user_class_id FROM user_table WHERE user_id = $user_id LIMIT 1)";
		return $this->getSqlResult($sql);
	}


////////////////// ONLY FOR TEACHER ////////////////////
////////////////////////////////////////////////////////

	public function getUncheckedWorks() {
		$sql = "SELECT user_full_name AS full_name, mark_user_id AS user_id, mark_work_id AS work_id
				FROM mark_table
				LEFT JOIN user_table
				ON user_table.user_id = mark_user_id
				WHERE mark = 1
				ORDER BY full_name, work_id";
		return $this->getSqlResult($sql);
	}

	public function getUncheckedDeliveries() {
		$sql = "SELECT user_full_name AS full_name, delivery_user_id AS user_id, delivery_work_id AS work_id,
					   delivery_files AS files, delivery_description AS description,
					   delivery_date AS date
				FROM delivery_table
				JOIN (SELECT user_full_name, mark_user_id, mark_work_id
                      FROM mark_table
                      LEFT JOIN user_table
                      ON (user_table.user_id = mark_user_id)
                      WHERE mark = 1) AS people
				ON (delivery_user_id = mark_user_id AND delivery_work_id = mark_work_id)
				ORDER BY work_id, full_name, date";
		return $this->getSqlResult($sql);
	}

	public function getTeacherClasses($user_tg_id) {
		$sql = "SELECT user_class_id AS class_id, class_name_ru
				FROM user_table
				LEFT JOIN class_table
				ON user_class_id = class_id
				WHERE user_tg_id = $user_tg_id";
		return $this->getSqlResult($sql);
	}
	
	public function addWork($class_id) {
		// returns work_id of new work
		$sql = "INSERT INTO work_table (work_class_id)
		 		VALUES ($class_id)";
		$this->getSqlResult($sql);
		$sql = "SELECT work_id
				FROM work_table
				ORDER BY work_id DESC
				LIMIT 1";
		return $this->getOneRow($sql)['work_id'];
	}
	
	public function getWorkMarks($work_id) {
		$sql = "SELECT mark, user_full_name AS full_name FROM mark_table
				LEFT JOIN user_table ON mark_user_id = user_id
				WHERE mark_work_id = $work_id
				ORDER BY user_full_name";
		return $this->getSqlResult($sql);
	}
	
	public function getClassWorks($class_id) {
		$sql = "SELECT work_id AS id, work_number AS number, work_name AS name FROM work_table
				WHERE work_class_id = $class_id";
		return $this->getSqlResult($sql);
	}
	
	public function changeWorkName($work_id, $work_name) {
		$sql = "UPDATE work_table
				SET work_name = '$work_name'
				WHERE work_id = $work_id";
		return $this->getSqlResult($sql);	
	}
	
	public function changeWorkDescription($work_id, $work_description) {
		$sql = "UPDATE work_table
				SET work_description = '$work_description'
				WHERE work_id = $work_id";
		return $this->getSqlResult($sql);	
	}
	
	public function changeWorkFile($work_id, $work_file) {
		$sql = "UPDATE work_table
				SET work_files = '$work_file'
				WHERE work_id = $work_id";
		return $this->getSqlResult($sql);	
	}
	
	public function changeWorkActivity($work_id) {
		$sql = "SET @activity = (SELECT work_activity FROM work_table WHERE work_id = $work_id);
				UPDATE work_table SET work_activity = NOT(@activity)
				WHERE work_id = $work_id;";
		return $this->getSqlResult($sql);
	}

}
