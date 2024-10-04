<?php
class LOG {
	private $filename = '';

	public function __construct($filename='log', $date_stamp=1) {
		date_default_timezone_set('Russia/Moscow');
		$this->filename =
			'logs/'
			.$filename
			.($date_stamp == 1 ? date('d-m-Y', time()) : '')
			.'.txt';
	}

	public function println($log_text, $jump=1) {
		$file = fopen($this->filename, 'a');
		fwrite($file, $log_text.($jump == 1 ? "\n" : ''));
		fclose($file);
	}

	public function addLogText($log_text, $mode='a') {
		$file = fopen($this->filename, $mode);
		$date = date('d/m/Y H:i:s:ms', time());
		$tmp = '-------------';

		fwrite($file, $tmp.' '.$date.' '.$tmp."\n");
		fwrite($file, $log_text."\n");
		fwrite($file, $tmp.$tmp.$tmp.$tmp."\n\n");
		fclose($file);
	}

}
