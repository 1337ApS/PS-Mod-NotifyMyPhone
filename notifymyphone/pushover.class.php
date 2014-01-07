<?php

class Pushover{
	private $app_token;
	private $user_key;

	public function __construct( $app_token, $user_key ){
		$this->app_token = $app_token;
		$this->user_key = $user_key;
	}

	public function notify( $title, $description, $url = ""){
		$data = array(
			  "token" => $this->app_token,
			  "user" => $this->user_key,
			  "message" => $description,
			  "title" => $title
		);

		curl_setopt_array($ch = curl_init(), array(
			  CURLOPT_URL => "https://api.pushover.net/1/messages.json",
			  CURLOPT_POSTFIELDS => $data,
			  CURLOPT_SSL_VERIFYPEER => false,
			  CURLOPT_SSL_VERIFYHOST => false
		));
		curl_exec($ch);
		curl_close($ch);
	}
}