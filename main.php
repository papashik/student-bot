<?php
function json_to_str($json_file) {
	return json_encode($json_file, JSON_UNESCAPED_UNICODE);
}
function str_to_json($string) {
	return json_decode($string, true);
}
function formatHtml($string) {
	$string = str_replace('&', '&amp;', $string);
	$string = str_replace('<', '&lt;', $string);
	$string = str_replace('>', '&gt;', $string);
	return $string;
}
function searchForLine($filename, $line) {
	$file = fopen($filename, 'r');
	if ($file) {
		while (($buffer = fgets($file, 100)) !== false) {
			if (trim($buffer) == $line) {
				fclose($file);
				return true;
			}
		}
		fclose($file);
	}
	return false;
}

function is_there_a_word($string, $array) {
	$string = mb_strtolower($string, 'UTF-8');
	foreach($array as $word) {
		if (strpos($string, $word) !== false) {
			return true;
		}
	}
	return false;
}
// returns all text after first word
function get_request_text($message_text) {
	return explode(' ', $message_text, 2)[1];
}

include_once('log.class.php');
include_once('tg.class.php');
include_once('mysql.class.php');
include_once('student_functions.php');
include_once('teacher_functions.php');

$log = new LOG();
$tg = new TG('', $log);
$conn = mysqli_connect('', '', '', '');
if (!$conn) { $tg->sendMe('Ошибка соединения: '.mysqli_connect_error()); }
$mysql = new MYSQL($conn);
$ADMIN_CHAT_IDS = array();


$event_body = file_get_contents('php://input');
$event_arr = str_to_json($event_body);
$event_type = ($event_arr == null ? null : array_keys($event_arr)[1]);
$event_content = $event_arr[$event_type];
$log->addLogText('New event "'.$event_type.'"  =>  \n'.json_to_str($event_arr));


if ($event_type == 'callback_query' && $event_content['data'] != '') {
	$callback_data = $event_content['data'];
	$callback_id = $event_content['id'];
	$chat_id = $event_content['message']['chat']['id'];
	$message_id = $event_content['message']['message_id'];
	$message_text = $event_content['message']['text'];

	$user = $mysql->getUser($chat_id);
	$id = $user['id'];

	if (in_array($chat_id, $ADMIN_CHAT_IDS, true)) {
		////////////////////////////// ADMIN PANEL //////////////////////////////
		$tg->sendMe($callback_data);

		/////////////////////////////////////////////////////////////////////////
	} else {
		$callback_array = explode(' ', $callback_data);
		$callback_command = $callback_array[0];
		$params = explode('_', $callback_array[1]);
		// $tg->editInlineKeyboard($chat_id, $message_id);
		switch ($callback_command) {
			case 'set_mark':
				// $tg->sendMessage($chat_id, $callback_data);
				// $tg->sendMessage($chat_id, 'ok '.$params[2], $message_id);
				$mark_user_id = $params[0];
				$mark_work_id = $params[1];
				$mark = $params[2];


				foreach (explode('_', $callback_array[2]) as $_deleting_message_id) {
					$tg->deleteMessage($chat_id, $_deleting_message_id);
				}

				if ($mark == 0) {
					$tg->editMessageText($chat_id, '<b>'.explode('\n\n', $message_text)[0].'</b>Решение отклонено', $message_id);
					$tg->callbackAnswer($callback_id, 'Решение отклонено');
				} else {
					$tg->editMessageText($chat_id, '<b>'.explode('\n\n', $message_text)[0].'</b>Поставлена оценка - $mark', $message_id);
					$tg->callbackAnswer($callback_id, 'Оценка $mark поставлена');
				}

				$new_mark_id = $mysql->changeMark($mark_user_id, $mark_work_id, $mark);
				$mysql->setStatus($id, 'teacher_mark_description_setting', $new_mark_id);
				$tg->sendMessage($chat_id, 'Введите комментарий к оценке', '', $tg->makeKeyboard(array($tg->button('Поставить без комментария'))));

				break;
			default:
				$tg->sendMessage($chat_id, 'Неизвестная команда', $message_id);
				break;
		}


	//$tg->callbackAnswer($callback_id, 'hello');

	}

} else if ($event_type == 'message' && ($event_content['text'] != '' || $event_content['caption'] != '' || $event_content['document']['file_id'] != '')) {
	$message_text = ($event_content['text'] != '' ? $event_content['text'] : $event_content['caption']);
	$message_file = ($event_content['document']['file_id'] != '' ? $event_content['document']['file_id'] : '');
	$message_id = $event_content['message_id'];
	$chat_id = $event_content['chat']['id'];
	$chat_type = $event_content['chat']['type'];
	$user_first_name = $event_content['from']['first_name'];
	$telegram_id = $event_content['from']['id'];
	$telegram_username = $event_content['from']['username'];
	if (in_array($chat_id, $ADMIN_CHAT_IDS, true)) {
		////////////////////////////// ADMIN PANEL //////////////////////////////

		$tg->sendMessage($chat_id, 'Привет!', $message_id);
		///////////////////////////////////////////////////////








		///////////////////////////////////////////////////////
	} else if ($chat_type == 'private') {
		$user = $mysql->getUser($telegram_id);
		if (!$user) {
			///// НЕИЗВЕСТНЫЙ ПОЛЬЗОВАТЕЛЬ /////
			if ($message_text == '/start') {
				$tg->sendMessage($chat_id, 'Привет, это бот для учеников 57 школы. \nПришли свои данные в формате {Класс} {Имя} {Фамилия}, например вот так: 10Я Павел Якубов');
			} else {
				// Ищем в файле с учениками
				if (searchForLine('students.txt', $message_text)) {
					$userdata_array = explode(' ', $message_text, 2);
					$user_class_name_ru = $userdata_array[0];
					$user_full_name = $userdata_array[1];
					$is_registered = $mysql->getUserByFullName($user_full_name);
					if ($is_registered && $is_registered['class_id'] == $mysql->getClassId($user_class_name_ru)) {
						// Попытка повторной регистрации
						$tg->sendMessage($chat_id, 'К сожалению, регистрация не удалась, обратитесь к преподавателю! \nКод ошибки DR', '', $tg->makeKeyboard());
					} else {
						// Успешная регистрация
						$mysql->addUser($telegram_id, $telegram_username, $user_full_name, $user_class_name_ru);
						$tg->sendMessage($chat_id, 'Добро пожаловать, $user_full_name, вы успешно зарегистрированы в боте! Ориентируйтесь по кнопкам ниже. Связь с преподавателем в разделе Инфо');
						$id = $mysql->getUser($telegram_id)['id'];
						sendMain($mysql, $id, $tg, $chat_id);
					}

				} else {
					// Ученик не найден в файле учеников
					$tg->sendMessage($chat_id, 'К сожалению, регистрация не удалась, обратитесь к преподавателю! \nКод ошибки NF', '', $tg->makeKeyboard());
				}
			}
		} else {
			///// ЗАРЕГИСТРИРОВАННЫЙ ПОЛЬЗОВАТЕЛЬ /////
			$id = $user['id'];
			$full_name = $user['full_name'];
			$role = $user['role'];
			$class_id = $user['class_id'];
			$status = $user['status'];
			$status_info = $user['status_info'];

			if ($role == 'teacher') {
				////////////////////////
				///// МЕНЮ УЧИТЕЛЯ /////
				switch ($status) {
					default:
					case 'teacher_main':
						switch($message_text) {
							case 'Работы':
								sendUncheckedWorks($mysql, $tg, $chat_id);
								sendTeacherMain($mysql, $id, $tg, $chat_id);
								break;
							case 'Посылки':
								sendUncheckedDeliveries($mysql, $tg, $chat_id);
								sendTeacherMain($mysql, $id, $tg, $chat_id);
								break;
							case 'Оценки':
								sendMarksQuestion_class($mysql, $id, $tg, $chat_id);
								break;
							case 'Новая работа':
								sendNewWorkQuestion_class($mysql, $id, $tg, $chat_id);
								break;
							default:
								$tg->sendMessage($chat_id, 'Неизвестная команда', '', mainTeacherKeyboard($tg));

						}
						break;

					case 'teacher_marks_class_setting':
						if ($message_text != 'В главное меню') {
							sendMarks($mysql, $id, $tg, $chat_id, $message_text);
						} else {
							sendTeacherMain($mysql, $id, $tg, $chat_id);
						}
						break;

					case 'teacher_new_work_class':
						if ($message_text != 'В главное меню') {
							sendNewWorkQuestion_name($mysql, $id, $tg, $chat_id, $message_text);
						} else {
							sendTeacherMain($mysql, $id, $tg, $chat_id);
						}
						break;

					case 'teacher_new_work_name':
						if ($message_text != 'В главное меню') {
							sendNewWorkQuestion_description($mysql, $id, $tg, $chat_id, $status_info, $message_text);
						} else {
							sendTeacherMain($mysql, $id, $tg, $chat_id);
						}
						break;

					case 'teacher_new_work_description':
						if ($message_text != 'В главное меню') {
							sendNewWorkQuestion_file($mysql, $id, $tg, $chat_id, $status_info, $message_text);
						} else {
							sendTeacherMain($mysql, $id, $tg, $chat_id);
						}
						break;

					case 'teacher_mark_description_setting':
						if ($message_text != 'Поставить без комментария') {
							$mark_user_id = $mysql->changeMarkDescriptionById($status_info, $message_text);
							$tg->sendMessage($mysql->getTgId($mark_user_id), '🔔 Вам поставлена оценка с комментарием');
						} else {
							$mark_user_id = $mysql->getMarkById($status_info)['user_id'];
							$tg->sendMessage($mysql->getTgId($mark_user_id), '🔔 Вам поставлена оценка');
						}
						sendTeacherMain($mysql, $id, $tg, $chat_id);
						break;
				}

			} else if ($role == 'student') {
				switch ($status) {
					default:
					case 'student_main':
						switch($message_text) {
							case 'Мои работы':
								sendWorkList($mysql, $id, $class_id, $tg, $chat_id);
								break;
							case 'Инфо':
								sendInfo($mysql, $id, $tg, $chat_id, $class_id);
								break;
							default:
								sendUnknownAndMain($mysql, $id, $tg, $chat_id, $message_id);
						}
						break;
					case 'student_work_list':
						switch($message_text) {
							case 'Обновить список':
								sendWorkList($mysql, $id, $class_id, $tg, $chat_id);
								break;
							case 'Главное меню':
								sendMain($mysql, $id, $tg, $chat_id);
								break;
							default:
								$work_number = explode('. ', $message_text, 2)[0];
								if (is_numeric($work_number)) {
									$work = $mysql->getWork($class_id, $work_number);
									if (!$work['id']) {
										sendUnknownAndMain($mysql, $id, $tg, $chat_id, $message_id);
									} else {
										sendWork($mysql, $id, $work, $tg, $chat_id);
									}
								} else {
									sendUnknownAndMain($mysql, $id, $tg, $chat_id, $message_id);
								}
						}
						break;
					case 'student_work':
						switch($message_text) {
							case 'Загрузить решение':
								$mark_array = $mysql->getMarkArray($id, $status_info);
								if (!$mark_array || $mark_array['mark'] == 1) {
									$mysql->setStatus($id, 'student_work_uploading', $status_info);
									$tg->sendMessage($chat_id, 'Ну так присылайте, чего же вы ждете?', '', $tg->makeKeyboard($tg->button('Отменить')));
								} else {
									sendUnknownAndMain($mysql, $id, $tg, $chat_id, $message_id);
								}
								break;
							case 'Возврат к списку':
								sendWorkList($mysql, $id, $class_id, $tg, $chat_id);
								break;
							default:
								sendUnknownAndMain($mysql, $id, $tg, $chat_id, $message_id);
						}
						break;
					case 'student_work_uploading':
						switch($message_text) {
							case 'Отменить':
								sendWork($mysql, $id, $mysql->getWorkById($status_info), $tg, $chat_id);
								break;
							default:
								/////////////////////////////////////////////////////////////////////////////////
								////////////////////////////// SQL INJECTION ////////////////////////////////////
								/////////////////////////////////////////////////////////////////////////////////
								$mysql->setStatus($id, 'student_work_uploaded', $status_info);
								$mysql->addDelivery($id, $status_info, $message_file, $message_text);
								$tg->sendMessage($chat_id, 'Записано! Хотите добавить еще?', $message_id, $tg->makeKeyboard($tg->button('Закончить отправку')));
								$mark_array = $mysql->getMarkArray($id, $status_info);
								if (!$mark_array) {
									$mysql->addMark($id, $status_info, 1);
								}
								break;
						}
						break;
					case 'student_work_uploaded':
						switch($message_text) {
							case 'Закончить отправку':
								$mysql->setStatus($id, 'student_work', $status_info);
								$tg->sendMessage($chat_id, 'Решение успешно записано!');

								sendWork($mysql, $id, $mysql->getWorkById($status_info), $tg, $chat_id);
								break;
							default:
								/////////////////////////////////////////////////////////////////////////////////
								////////////////////////////// SQL INJECTION ////////////////////////////////////
								/////////////////////////////////////////////////////////////////////////////////
								$mysql->addDelivery($id, $status_info, $message_file, $message_text);
								$tg->sendMessage($chat_id, 'Записано! Хотите добавить еще?', $message_id);
								break;
						}
						break;
				}
			}
		}
	}
}

function exiting($conn) {
	mysqli_close($conn);
	exit('ok');
}
exiting($conn);
