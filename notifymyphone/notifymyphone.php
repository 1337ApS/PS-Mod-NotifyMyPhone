<?php
if (!defined('_CAN_LOAD_FILES_'))
	exit;

class NotifyMyPhone extends Module {

	public function __construct() {
		$this->name = 'notifymyphone';
		$this->tab = 'others';
		$this->need_instance = 1;
		
		parent::__construct();
		
		$this->displayName = $this->l('Notify My Phone');
		$this->description = $this->l("Get notified using Pushover (iOS/Android), Prowl (iOS) or NotifyMyAndroid (Android) when a new order has been created.");
		$this->confirmUninstall = $this->l("Are you sure you want to delete this module?");
		
		$this->version = '1.0';
		$this->author = "1337 ApS";
		$this->error = false;
		$this->valid = false;		
	}
	
	public function getContent(){
		$this->_html = '<h2>'.$this->displayName.'</h2>';
		$this->_postProcess();
		$this->_displayForm();
		return $this->_html;
	}
	
	private function _postProcess(){
		if(Tools::isSubmit('submitNMPKeys')){
			$prowl = Tools::getValue('prowlkeys', '');
			$notifymyandroid = Tools::getValue('notifymyandroidkeys', '');
			$pushover = Tools::getValue('pushover', '');
			$pushover_app = Tools::getValue('pushover_app_key', '');
			
			Configuration::updateValue($this->name.'_prowl_api_keys', $prowl);
			Configuration::updateValue($this->name.'_nma_api_keys', $notifymyandroid);
			Configuration::updateValue($this->name.'_pushover_api_keys', $pushover);
			Configuration::updateValue($this->name.'_pushover_app_key', $pushover_app);
		}
	}
	
	private function _displayForm(){
		//global $currentIndex;
		//$token = Tools::getValue('token');
		$url = $_SERVER['REQUEST_URI'];
		$prowl_keys = Configuration::get($this->name.'_prowl_api_keys');
		$nma_keys = Configuration::get($this->name.'_nma_api_keys');
		$pushover_keys = Configuration::get($this->name.'_pushover_api_keys');
		$pushover_app_key = Configuration::get($this->name.'_pushover_app_key');

		$this->_html .= '
			<form action="'.$url.'" method="post">
				<fieldset><legend><img src="'.$this->_path.'logo.gif" /> '.$this->l('API Key Administration').'</legend>
					<label for="prowlkeys">'.$this->l('Prowl Keys').' </label>
					<div class="margin-form">
						<textarea id="prowlkeys" class="width3" name="prowlkeys">'.$prowl_keys.'</textarea>
						&nbsp;<label for="prowlkeys" class="t">'.$this->l('Add one key per line.').'</label>
					</div>
					<label for="notifymyandroidkeys">'.$this->l('NotifyMyAndroid Keys').' </label>
					<div class="margin-form">
						<textarea id="notifymyandroidkeys" class="width3" name="notifymyandroidkeys">'.$nma_keys.'</textarea>
						&nbsp;<label for="notifymyandroidkeys" class="t">'.$this->l('Add one key per line.').'</label>
					</div>

					<label for="pushover">'.$this->l('Pushover User Keys').' </label>
					<div class="margin-form">
						<textarea id="pushover" class="width3" name="pushover">'.$pushover_keys.'</textarea>
						&nbsp;<label for="pushover" class="t">'.$this->l('Add one key per line.').'</label>
					</div>
					<label for="pushover_app_key">'.$this->l('Pushover App API Keys').' </label>
					<div class="margin-form">
						<input type="text" value="'.$pushover_app_key.'" id="pushover_app_key" class="width3" name="pushover_app_key">
					</div>

					<center>
						<input type="submit" value="'.$this->l('   Save   ').'" name="submitNMPKeys" class="button" />
					</center>
				</fieldset>
			</form>';
	}
	
	public function hookActionValidateOrder($params){
		// Get order data
		$currency = $params['currency'];
		$order = $params['order'];
		
		$this->sendProwlNotifications($order, $currency);
		$this->sendNotifyMyAndroidNotifications($order, $currency);
		$this->sendPushoverNotification( $order, $currency );
	}

	private function sendPushoverNotification($order, $currency){
		if(!class_exists('Pushover'))
			require_once 'pushover.class.php';

		$app_key = Configuration::get($this->name.'_pushover_app_key');
		$api_keys = Configuration::get($this->name.'_pushover_api_keys');
		
		// Make sure the string isnt empty
		if(empty($api_keys) || empty($app_key))
			return;
		
		// Replace carriage-returns with normal linefeeds
		$api_keys = str_replace("\r\n", "\n", $api_keys);
		
		// Explode the string to create the API Key Array
		$api_keys = explode("\n", $api_keys);
		
		// Test if there's any API Keys entered
		if(empty($api_keys))
			return;
		
		// Get message content
		$application = Configuration::get('PS_SHOP_NAME');
		$price = Tools::displayPrice($order->total_paid, $currency);
		
		$event = $this->l('New Order!');
		$description = sprintf($this->l('Total paid: %s'), $price);
		$priority = 0;
		
		foreach( $api_keys as $key ){
			$p = new Pushover( $app_key, $key );
			$p->notify("[" . $application . "] " . $event, $description, $priority);
		}
	}
	
	private function sendNotifyMyAndroidNotifications($order, $currency){
		if(!class_exists('nmaApi'))
			require_once 'nmaApi.class.php';
		
		$api_keys = Configuration::get($this->name.'_nma_api_keys');
		
		// Make sure the string isnt empty
		if(empty($api_keys))
			return;
		
		// Replace carriage-returns with normal linefeeds
		$api_keys = str_replace("\r\n", "\n", $api_keys);
		
		// Explode the string to create the API Key Array
		$api_keys = explode("\n", $api_keys);
		
		// Test if there's any API Keys entered
		if(empty($api_keys))
			return;
		
		// Get message content
		$application = Configuration::get('PS_SHOP_NAME');
		$price = Tools::displayPrice($order->total_paid, $currency);
		
		$event = sprintf($this->l('New Order! (%s)'), $price);
		$description = sprintf($this->l('Total paid: %s'), $price);
		$priority = 0;
		
		$nma = new nmaApi();
		$nma->notify($application, $event, $description, $priority, implode(',', $api_keys));
	}
	
	private function sendProwlNotifications($order, $currency){
		if(!class_exists('Prowl'))
			require_once 'class.php-prowl.php';
		
		// Setup Prowl
		$prowl = new Prowl();
		$prowl->setDebug(false);
		
		// Get all Prowl Keys from the Options
		$prowl_api_keys = Configuration::get($this->name.'_prowl_api_keys');
		
		// Make sure the string isnt empty
		if(empty($prowl_api_keys))
			return;
		
		// Replace carriage-returns with normal linefeeds
		$prowl_api_keys = str_replace("\r\n", "\n", $prowl_api_keys);
		
		// Explode the string to create the API Key Array
		$prowl_api_keys = explode("\n", $prowl_api_keys);
		
		// Test if there's any API Keys entered
		if(empty($prowl_api_keys))
			return;
		
		// Set the Prowl message content
		$application = Configuration::get('PS_SHOP_NAME');
		$price = Tools::displayPrice($order->total_paid, $currency);
		
		$event = sprintf($this->l('New Order! (%s)'), $price);
		$description = sprintf($this->l('Total paid: %s'), $price);
		$priority = 0;
		
		// Send to all registered API keys
		foreach($prowl_api_keys as $api_key){
			$api_key = trim($api_key);
			
			if(empty($api_key))
				continue;
			
			$prowl->setApiKey($api_key);
			$prowl->add($application,$event,$priority,$description,'');
		}
	}

	public function install(){
		if(!parent::install() || !$this->registerHook('actionValidateOrder'))
			return false;
		
		return true;
	}
}