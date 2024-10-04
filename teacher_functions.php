<?php
function sendUncheckedWorks($mysql, $tg, $chat_id) {
	$works = $mysql->getUncheckedWorks();
	$message = "<b>Учащийся -- ID работы</b>\n";
	while ($row = mysqli_fetch_assoc($works)) {
		$full_name = $row['full_name'];
		$work_id = $row['work_id'];
		$message = $message."$full_name -- $work_id\n";
	}
	$tg->sendMessage($chat_id, $message);
}

function generateInlineKeyboard($id, $work_id, $tg, $message_ids='') {
	$callback = "set_mark ".$id."_".$work_id."_";
	$inline_buttons_array = array();
	$inline_button = $tg->inlineButton("Отклонить", "", $callback."0 ".$message_ids);
	$inline_buttons_array[] = $inline_button;
	// $inline_button = $tg->inlineButton("Отклонить с описанием", "", $callback."d ".$message_ids);
	// $inline_buttons_array[] = $inline_button;
	for ($i = 2; $i < 6; $i++) {
		$inline_button = $tg->inlineButton($i, "", $callback.$i." ".$message_ids);
		$inline_buttons_array[] = $inline_button;
	}
	return $tg->makeInlineKeyboard($inline_buttons_array, array(1, 4));
}

function sendDeliveryWithFiles($chat_id, $tg, $message, $files, $id, $work_id, $date) {
	$inline_keyboard = generateInlineKeyboard($id, $work_id, $tg);
	$sent_message_id = $tg->sendMessage($chat_id, $message, '', $inline_keyboard)['result']['message_id'];
	$media_array = array();

	foreach ($files as $_counter => $_file) {
		$media_array[] = $tg->mediaDocument($_file, "Посылка $_counter");
	}
	if (count($media_array) > 10) {
		$messages_array = $tg->sendMediaGroup($chat_id, array_slice($media_array, 0, 10))['result'];
		$tg->sendMediaGroup($chat_id, array_slice($media_array, 10))['result'];
	} else {
		$messages_array = $tg->sendMediaGroup($chat_id, $media_array)['result'];
	}
	$message_ids = "";
	foreach ($messages_array as $_message) {
		$message_ids = $message_ids.$_message['message_id']."_";
	}

	$inline_keyboard = generateInlineKeyboard($id, $work_id, $tg, $message_ids);
	$tg->editInlineKeyboard($chat_id, $sent_message_id, $inline_keyboard);
}

function sendUncheckedDeliveries($mysql, $tg, $chat_id) {
	$deliveries = $mysql->getUncheckedDeliveries();
	$id_now = 0;
	$work_id_now = 0;
	$counter = 0;
	$files = array();
	$message = '';
	while ($row = mysqli_fetch_assoc($deliveries)) {
		$id = $row['user_id'];
		$full_name = $row['full_name'];
		$work_id = $row['work_id'];
		$file = $row['files'];
		$description = formatHtml($row['description']);
		$date = $row['date'];

		if ($id_now != $id || $work_id_now != $work_id) {
			sendDeliveryWithFiles($chat_id, $tg, $message, $files, $id_now, $work_id_now, $date);
			$id_now = $id;
			$work_id_now = $work_id;
			$work = $mysql->getWorkById($work_id);
			$work_number = $work['number'];
			$work_name = $work['name'];
			$work_class_id = $work['class_id'];
			$work_class_name_ru = $mysql->getClassNameRu($work_class_id);
			$message = "<b>Учащийся:</b> $full_name\n<b>Класс:</b> $work_class_name_ru\n<b>Работа:</b> $work_number. $work_name\n\n";
			$counter = 1;
			$files = array();
		}
		if ($file) {
			$message = $message."<b>Посылка $counter ($date):</b> Файл⬇️ $description\n";
			$files[$counter] = $file;
		} else {
			$message = $message."<b>Посылка $counter ($date):</b> \n$description\n";
		}
		$counter++;

	}
	sendDeliveryWithFiles($chat_id, $tg, $message, $files, $id, $work_id, $date);

	if ($counter == 0) {
		$tg->sendMessage($chat_id, "Непроверенных работ на данный момент нет");
	} else {
		$tg->sendMessage($chat_id, "Это были все непроверенные работы");
	}
}

function classQuestion($mysql, $id, $tg, $chat_id) {
	$classes = $mysql->getTeacherClasses($chat_id);
	$buttons_array = array($tg->button("В главное меню"));
	while ($row = mysqli_fetch_assoc($classes)) {
		$class_name_ru = $row['class_name_ru'];
		$buttons_array[] = $tg->button($class_name_ru);
	}
	$tg->sendMessage($chat_id, "Выберите класс⬇️", '', $tg->makeKeyboard($buttons_array));
}

function sendMarksQuestion_class($mysql, $id, $tg, $chat_id) {
	classQuestion($mysql, $id, $tg, $chat_id);
	$mysql->setStatus($id, 'teacher_marks_class_setting', 0);
}

function sendMarks($mysql, $id, $tg, $chat_id, $message_text) {
	$class_id = $mysql->getClassId($message_text);
	if ($class_id === false) {
		sendTeacherMain($mysql, $id, $tg, $chat_id);
		return;
	}
	$works = $mysql->getClassWorks($class_id);
	while ($work = mysqli_fetch_assoc($works)) {
		$work_id = $work['id'];
		$work_number = $work['number'];
		$work_name = $work['name'];
		$message = "<b>$work_number. $work_name</b> \n";
		$marks = $mysql->getWorkMarks($work_id);
		
		$i = 0;
		while ($mark = mysqli_fetch_assoc($marks)) {
			$i = $i + 1;
			$full_name = "$i. ".$mark['full_name'];
			while (mb_strlen($full_name, "UTF-8") < 22) {
				$full_name = $full_name." ";
			}
			$message = $message."<pre><code class='python'>".$full_name."</code></pre> --- <b>".$mark['mark']."</b> \n";
		}
		$tg->sendMessage($chat_id, $message);
	}
	
	sendTeacherMain($mysql, $id, $tg, $chat_id);
}

function mainTeacherKeyboard($tg) {
	return $tg->makeKeyboard(array($tg->button('Работы'), $tg->button('Посылки'), $tg->button('Новая работа'), $tg->button('Оценки')));
}

function sendTeacherMain($mysql, $id, $tg, $chat_id) {
	$mysql->setStatus($id, 'teacher_main');
	$tg->sendMessage($chat_id, 'Главное меню⬇️', '', mainTeacherKeyboard($tg));
}

function sendNewWorkQuestion_class($mysql, $id, $tg, $chat_id) {
	classQuestion($mysql, $id, $tg, $chat_id);
	$mysql->setStatus($id, 'teacher_new_work_class', 0);
}

function sendNewWorkQuestion_name($mysql, $id, $tg, $chat_id, $message_text='') {
	$class_id = $mysql->getClassId($message_text);
	if ($class_id === false) {
		sendTeacherMain($mysql, $id, $tg, $chat_id);
		return;
	}
	$work_id = $mysql->addWork($class_id);
	$mysql->setStatus($id, 'teacher_new_work_name', $work_id);
	$tg->sendMessage($chat_id, "Напишите название работы⬇️", '', $tg->makeKeyboard(array($tg->button("В главное меню"))));
}

function sendNewWorkQuestion_description($mysql, $id, $tg, $chat_id, $work_id, $work_name='') {
	$mysql->changeWorkName($work_id, $work_name);
	$mysql->setStatus($id, 'teacher_new_work_description', $work_id);
	$tg->sendMessage($chat_id, "Напишите условие работы⬇️", '', $tg->makeKeyboard(array($tg->button("В главное меню"))));
}

function sendNewWorkQuestion_file($mysql, $id, $tg, $chat_id, $work_id, $work_description='') {
	$mysql->changeWorkDescription($work_id, $work_description);
	$mysql->setStatus($id, 'teacher_new_work_file', $work_id);
	$tg->sendMessage($chat_id, "Пришлите файл для работы⬇️", '', $tg->makeKeyboard(array($tg->button("Файл не нужен"), $tg->button("В главное меню"))));
	sendTeacherMain($mysql, $id, $tg, $chat_id);
}

