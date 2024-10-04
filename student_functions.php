<?php
function sendUnknown($tg, $chat_id, $message_id) {
	$tg->sendMessage($chat_id, "Неизвестная команда😐", $message_id);
}

function sendUnknownAndMain($mysql, $id, $tg, $chat_id, $message_id) {
	sendUnknown($tg, $chat_id, $message_id);
	sendMain($mysql, $id, $tg, $chat_id);
}

function mainKeyboard($tg) {
	return $tg->makeKeyboard(array($tg->button('Мои работы'), $tg->button('Инфо')), [2]);
}

function sendMain($mysql, $id, $tg, $chat_id) {
	$mysql->setStatus($id, 'student_main');
	$tg->sendMessage($chat_id, 'Главное меню⬇️', '', mainKeyboard($tg));
}

function sendWorkList($mysql, $id, $class_id, $tg, $chat_id) {
	$mysql->setStatus($id, 'student_work_list');
	$student_works = $mysql->getStudentStats($id, $class_id);
	$res = '';
	$keyboard = array('Обновить список', 'Главное меню');
	while ($row = mysqli_fetch_assoc($student_works)) {
		$mark = $row['mark'];
		if ($mark === null) {
			// Нет оценки и нет ни одной посылки
			$symbol = '❌';
		} else if ($mark == 0) {
			// Преподаватель отклонил работу
			$symbol = '⚠️';
		} else if ($mark == 1) {
			// Хотя бы одна посылка есть => Работа на проверке
			$symbol = '⌛️';
		} else {
			// Работа проверена
			$symbol_array = array('0️⃣', '1️⃣', '2️⃣', '3️⃣', '4️⃣', '5️⃣');
			$symbol = $symbol_array[$mark];
		}
		$res = $res.$row['work_number'].". ".$row['work_name']." $symbol\n";
		$keyboard[] = $row['work_number'].". ".$row['work_name'];
	}
	if ($res == '') {
		$message = "Преподаватель пока не задавал вам работ";
	} else {
		$message = "Список работ: \n$res";
	}
	$tg->sendMessage($chat_id, $message, '', $tg->makeKeyboard($keyboard));
}

function sendWork($mysql, $id, $work, $tg, $chat_id) {
	$work_id = $work['id'];
	$mysql->setStatus($id, 'student_work', $work_id);
	$work_number = $work['number'];
	$work_name = $work['name'];
	$work_description = $work['description'];
	$work_files = $work['files'];
	$work_deadline = $work['deadline'];

	$mark_array = $mysql->getMarkArray($id, $work_id);
	$mark = $mark_array['mark'];
	$mark_date = $mark_array['date'];
	$mark_description = $mark_array['description'];
	$header = '';
	$keyboard = array('Загрузить решение', 'Возврат к списку');
	if ($mark === null) {
		$header = "Вы еще не отправляли эту работу";
	} else if ($mark == 0) {
		$mysql->deleteMark($id, $work_id);
		$header = "Преподаватель отклонил вашу работу $mark_date";
		if ($mark_description != '') {
			$header = $header." с комментарием: <i>$mark_description</i>";
		}
	} else if ($mark == 1) {
		$header = "Работа на проверке преподавателем";
	} else {
		$keyboard = array('Возврат к списку'); // Убираем кнопку загрузки, так как оценка уже стоит
		$header = "За эту работу $mark_date вам поставлена оценка <b>$mark</b>";
		if ($mark_description != '') {
			$header = $header." с комментарием: <i>$mark_description</i>";
		}
	}
	$text = "$header \n\n<b>Информация о работе №$work_number</b> \n<b>Название:</b> $work_name \n<b>Дедлайн:</b> $work_deadline \n<b>Текст задания:</b> $work_description";
	if ($work_files != '') {
		$tg->sendDocument($chat_id, $work_files, $text, '', $tg->makeKeyboard($keyboard));
	} else {
		$tg->sendMessage($chat_id, $text, '', $tg->makeKeyboard($keyboard));
	}
}

function sendInfo($mysql, $user_id, $tg, $chat_id, $class_id) {
	$class_name_ru = $mysql->getClassNameRu($class_id);
	$teachers = $mysql->getTeachers($user_id);
	$res = "<b>Контакты преподавателей:</b>";
	while ($row = mysqli_fetch_assoc($teachers)) {
		$tg_username = $row['tg_username'];
		$full_name = $row['full_name'];
		$res = $res."\n<a href=\"t.me/$tg_username\">$full_name</a>";
	}
	$tg -> sendMessage($chat_id, "<b>Ваш класс:</b> ".$class_name_ru."\n".$res);
}
