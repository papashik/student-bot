<?php
class TG {

    private $token = '';
 	private $log;
	private $parse_mode = 'HTML';
	private $my_chat_id = 485715108;

    public function __construct($token, $log) {
        $this->token = $token;
        $this->log = $log;
    }

	public function inlineButton($text, $url='', $callback_data='') {
		return array(
			'text'			=>	$text,
			'url'			=>	$url,
			'callback_data'	=>	$callback_data,
		);
	}

	public function button($text) {
		return array(
			'text'	=>	$text,
		);
	}

	public function makeKeyboard($buttons_array=array(), $rows_amount_array=array(), $one_time_keyboard=false) {
		if (count($buttons_array) == 0) {
			return json_to_str(array('remove_keyboard' => true));
		}
		$keyboard = array();
		if (count($rows_amount_array) == 0) {
			foreach($buttons_array as $button) {
				$keyboard[] = array($button);
			}
		} else {
			foreach($rows_amount_array as $row_amount) {
				$keyboard[] = array_slice($buttons_array, 0, $row_amount);
				$buttons_array = array_slice($buttons_array, $row_amount);
			}
		}
		return json_to_str(array('keyboard' => $keyboard, 'one_time_keyboard' => $one_time_keyboard));
	}

	public function makeInlineKeyboard($buttons_array, $rows_amount_array) {
		$keyboard = array();
		if (count($rows_amount_array) == 0) {
			foreach($buttons_array as $button) {
				$keyboard[] = array($button);
			}
		} else {
			foreach($rows_amount_array as $row_amount) {
				$keyboard[] = array_slice($buttons_array, 0, $row_amount);
				$buttons_array = array_slice($buttons_array, $row_amount);
			}
		}
		return json_to_str(array('inline_keyboard' => $keyboard));
	}

	public function callbackAnswer($callback_query_id, $text='') {
		$data = array(
			'callback_query_id'		=>	$callback_query_id,
			'text'					=>	$text,
		);
        return $this->request('answerCallbackQuery', $data);
	}

	public function editInlineKeyboard($chat_id, $message_id, $inline_keyboard="") {
		$inline_keyboard = ($inline_keyboard == "" ? json_to_str(array('inline_keyboard' => array())) : $inline_keyboard);
		$data = array(
			'chat_id'		=>	$chat_id,
			'message_id'	=>	$message_id,
			'reply_markup'	=>	$inline_keyboard,
		);
        return $this->request('editMessageReplyMarkup', $data);
	}

    public function sendMessage($chat_id, $message, $reply_to_message_id='', $reply_markup='') {
		$data = array(
			'chat_id'				=>	$chat_id,
			'text'					=>	$message,
			'reply_to_message_id'	=>	$reply_to_message_id,
			'reply_markup'			=>	$reply_markup,
			'parse_mode'			=>	$this->parse_mode,
		);
        return $this->request('sendMessage', $data);
    }

	public function sendMe($message, $reply_to_message_id='', $reply_markup='') {
		return $this->sendMessage($this->my_chat_id, $message, $reply_to_message_id, $reply_markup);
	}

	public function editMessageText($chat_id, $new_message_text, $editing_message_id, $inline_keyboard="") {
		$inline_keyboard = ($inline_keyboard == "" ? json_to_str(array('inline_keyboard' => array())) : $inline_keyboard);
		$data = array(
			'chat_id'		=>	$chat_id,
			'text'			=>	$new_message_text,
			'message_id'	=>	$editing_message_id,
			'parse_mode'	=>	$this->parse_mode,
			'reply_markup'	=>	$inline_keyboard,
		);
       	return $this->request('editMessageText', $data);
	}

	public function deleteMessage($chat_id, $deleting_message_id) {
		$data = array(
			'chat_id'		=>	$chat_id,
			'message_id'	=>	$deleting_message_id,
		);
        return $this->request('deleteMessage', $data);
	}

	public function sendPhoto($chat_id, $photo, $caption='', $reply_to_message_id='') {
		$data = array(
			'chat_id'				=>	$chat_id,
			'photo'					=>	$photo,
			'caption'				=>	$caption,
			'reply_to_message_id'	=>	$reply_to_message_id,
			'parse_mode'			=>	$this->parse_mode,
		);
		return $this->request('sendPhoto', $data);
	}

	public function sendDocument($chat_id, $document, $caption='', $reply_to_message_id='', $reply_markup='') {
		$data = array(
			'chat_id'				=>	$chat_id,
			'document'				=>	$document,
			'caption'				=>	$caption,
			'reply_to_message_id'	=>	$reply_to_message_id,
			'parse_mode'			=>	$this->parse_mode,
			'reply_markup'			=>	$reply_markup,
		);
		return $this->request('sendDocument', $data);
	}

	public function mediaDocument($file_id, $caption='') {
		return array(
			'type'		=>	'document',
			'media'		=>	$file_id,
			'caption' 	=>	$caption,
		);
	}

	public function sendMediaGroup($chat_id, $media_array) {
		$data = array(
			'chat_id'	=>	$chat_id,
			'media'		=>	json_to_str($media_array),
		);
		return $this->request('sendMediaGroup', $data);
	}

	public function getFile($file_id) {
		$data = array(
			'file_id'	=>	$file_id,
		);
		return $this->request('getFile', $data);
	}

	public function getUserProfilePhotos($user_id, $offset='', $limit='') {
		$data = array(
			'user_id'	=>	$user_id,
			'offset'	=>	$offset,
			'limit'		=>	$limit,
		);
		return $this->request('getUserProfilePhotos', $data);
	}

    private function request($method, $data = array()) {

    	$log_text = 'Result of '.$method.'('.json_to_str($data).')  =>  ';

        $curl = curl_init();

		curl_setopt($curl, CURLOPT_URL, 'https://api.telegram.org/bot'.$this->token.'/'.$method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);

        $result = str_to_json(curl_exec($curl));
		$this->log->addLogText(stripslashes($log_text.json_to_str($result)));

        curl_close($curl);

        return $result;
    }
}
