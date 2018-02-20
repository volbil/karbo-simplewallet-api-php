<?php

class Karbo {

	/**
	 * Host of Simplewallet JSON RPC API
	 */
	const RPC_HOST = "127.0.0.1";

	/**
	 * Port
	 */
	const RPC_PORT = "32348";

	/**
	 * Username for authentication
	 * Keep this field empty, if don't care about security of your KRB :)
	 */
	const RPC_USER = "USERNAME";

	/**
	 * Password for authentication
	 */
	const RPC_PASSWORD = "PASSWORD";

	/**
	 * Version of JSON RPC
	 */
	const RPC_V = "2.0";

	/**
	 * Delay for usleep function
	 */
	const RPC_TIMER = 50000;

	/**
	 * Decimal point, needed for KRB amount calculations
	 * (don't change this value, because it may cause loss of your KRB)
	 */
	const DECIMAL_POINT = 12;

	/**
	 * Number of decimal places
	 * (you may change this value, if price of KRB goes to high one day)
	 */
	const PREC_POINT = 4;

	/**
	 * ID of request, you can put here whatever you want
	 * (actually I don't now purpose of this field, maybe you find out it :D)
	 */
	const ID_CONN = "Y7h2LgDrBVh7wbSj";

	/**
	 * Fee of transaction
	 * (100000000 == 0.0001 KRB)
	 */
	const KRB_FEE = 100000000;

	/**
	 * Transaction mixin
	 */
	const KRB_MIXIN = 0;

	private $service_host = null;
	private $service_port = null;
	private $service_user = null;
	private $service_password = null;
	private $service_type = null;
	private $service_port_ssl = false;
	private $service_auth = false;
	private $service_mixin = null;
	private $service_fee = null;

	/**
	 * Constructor
	 * The constructor sets all important stuffs
	 *
	 * @param string [$rpc_host] Host of RPC API
	 * @param string [$rpc_port] Port of RPC API
	 * @param bool [$rpc_ssl] Type of connection
	 */
	public function __construct($rpc_host = "", $rpc_port = "", $rpc_ssl = false) {
		$this->id_connection = self::ID_CONN;
		$this->service_host = self::RPC_HOST;
		$this->service_port = self::RPC_PORT;
		$this->service_user = self::RPC_USER;
		$this->service_password = self::RPC_PASSWORD;
		$this->service_mixin = self::KRB_MIXIN;
		$this->service_fee = self::KRB_FEE;

		if (!empty($rpc_host) && !empty($rpc_port)) {
			$this->service_host = $rpc_host;
			$this->service_port = $rpc_port;
		}

		if (!empty(self::RPC_USER) && !empty(self::RPC_PASSWORD)) {
			$this->service_auth = true;
		}

		if ($rpc_ssl) $this->service_port_ssl = true;

		return true;
	}

	/**
	 * apiCall()
	 * Request to Simplewallet API 
	 *
	 * @param array [$req] Request data
	 */
	private function apiCall($req) {
		static $ch = null;

		$url = ($this->service_port_ssl) ? "https://" : "http://";
		$url .= $this->service_host . ":" . $this->service_port . "/json_rpc";
		$auth = $this->service_user . ":" . $this->service_password;

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json; charset=utf-8"]);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($req));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, 15);
		if ($this->service_auth) {
			curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
			curl_setopt($ch, CURLOPT_USERPWD, $auth);
		}
		usleep(self::RPC_TIMER);
		$res = curl_exec($ch);

		if(curl_errno($ch) > 0){
			curl_close($ch);
			return false;
		} else {
			curl_close($ch);
			$result = json_decode($res, true);
			if ($result != NULL) {
				if(!isset($result["error"])) {
					return $result;
				}
			}

			return false;
		}
	}

	/**
	 * checkDestinations()
	 * Check destinations of transaction, used for checking list of transactions
	 *
	 * @param array [$destinations] array which contain amount and address of reciver
	 */
	private function checkDestinations($destinations) {
		$result = [];
		foreach ($destinations as $key => $destination) {
			if (self::checkAddress($destination["address"])) {
				$item["address"] = $destination["address"];
				$item["amount"] = self::balanceFormat($destination["amount"], true);
				array_push($result, $item);
			}
		}

		return $result;
	}

	/**
	 * balanceFormat()
	 * Converting balance format
	 * Example: 100000000 -> 0.0001 ($mode = false) | 0.0001 -> 100000000 ($mode = true)
	 *
	 * @param string [$balance_src] Balance which needed to be converted
	 * @param bool [$mode] Type of convertion
	 */
	public static function balanceFormat($balance_src, $mode = false){
		$balance_res = 0;
		if ($balance_src > 0) {
			if ($mode) {
				$balance_res = round($balance_src * pow(10, self::DECIMAL_POINT), 0);
			} else {
				$balance_res = round($balance_src / pow(10, self::DECIMAL_POINT), self::PREC_POINT);
			}
		}

		return $balance_res;
	}

	/**
	 * getBalance()
	 * Returns the wallet balance
	 */
	public function getBalance() {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "getbalance";
		$args["id"] = $this->id_connection;
		$args["params"] = [];

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
					if (isset($data["result"]["available_balance"])) {
						$result["status"] = true;
						$result["available_balance"] = self::balanceFormat($data["result"]["available_balance"], false);
						$result['locked_amount'] = self::balanceFormat($data["result"]["locked_amount"], false);
					}
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * getAddress()
	 * Returns the wallet address
	 */
	public function getAddress() {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "get_address";
		$args["id"] = $this->id_connection;
		$args["params"] = [];

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
					if (isset($data["result"]["address"])) {
						$result["status"] = true;
						$result["address"] = $data["result"]["address"];
					}
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * getHeight()
	 * Returns current blockchain height
	 */
	public function getHeight() {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "get_height";
		$args["id"] = $this->id_connection;
		$args["params"] = [];

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
					if (isset($data["result"]["height"])) {
						$result["status"] = true;
						$result["height"] = $data["result"]["height"];
					}
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * getTransfers()
	 * Returns incoming/outcoming transfert
	 */
	public function getTransfers() {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "get_transfers";
		$args["id"] = $this->id_connection;
		$args["params"] = [];

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
					if (isset($data["result"]["transfers"])) {
						$result["status"] = true;
						foreach ($data["result"]["transfers"] as $key => $transfer) {
							$transferItem = [];
							$transferItem["address"] = $transfer["address"];
							$transferItem["amount"] = self::balanceFormat($transfer["amount"]);
							$transferItem["fee"] = self::balanceFormat($transfer["fee"]);
							$transferItem["block_index"] = $transfer["blockIndex"];
							$transferItem["output"] = $transfer["output"];
							$transferItem["payment_id"] = $transfer["paymentId"];
							$transferItem["time"] = $transfer["time"];
							$transferItem["transaction_hash"] = $transfer["transactionHash"];
							$transferItem["unlock_time"] = $transfer["unlockTime"];
							
							$result["transfers"][$key] = $transferItem;
						}
					}
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * getPayments()
	 * Returns incoming payments by payment id
	 *
	 * @param string [$payment_id] payment id of transaction
	 */
	public function getPayments($paymentId) {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "get_payments";
		$args["id"] = $this->id_connection;

		$args["params"] = [];
		$args["params"]["payment_id"] = $paymentId;

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
	  		if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
		  			$result["status"] = true;
	  				if (isset($data["result"]["payments"])) {
						$result["status"] = true;
						foreach ($data["result"]["payments"] as $key => $payment) {
							$paymentItem = [];
							$paymentItem["amount"] = self::balanceFormat($payment["amount"]);
							$paymentItem["block_height"] = $payment["block_height"];
							$paymentItem["tx_hash"] = $payment["tx_hash"];
							$paymentItem["unlock_time"] = $payment["unlock_time"];
							
							$result["payments"][$key] = $paymentItem;
						}
					}
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * store()
	 * Store wallet data
	 */
	public function store() {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "store";
		$args["id"] = $this->id_connection;
		$args["params"] = [];

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * transfer()
	 * Make transfer form wallet
	 * 
	 * @param array [$destinations] array which contain amount and address of reciver
	 * @param string [$payment_id] payment id of transaction
	 * @param int [$fee] fee of transation
	 * @param int [$unlock_time] unlock time of transation
	 */
	public function transfer($destinations, $payment_id, $fee = 0, $unlock_time = 0) {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "transfer";
		$args["id"] = $this->id_connection;

		$args["params"] = [];
		$args["params"]["destinations"] = self::checkDestinations($destinations);
		$args["params"]["payment_id"] = $payment_id;
		$args["params"]["unlock_time"] = $unlock_time;
		$args["params"]["mixin"] = $this->service_mixin;
		$args["params"]["fee"] = ($fee > 0) ? $fee : $this->service_fee;

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
					$result["tx_hash"] = $data["result"]["tx_hash"];
					$result["payment_id"] = $payment_id;
				}
			}

			return $result;
		}

		return false;
	}

	/**
	 * reset()
	 * Re-synchronize the wallet from scratch
	 */
	public function reset() {
		$args = [];
		$args["jsonrpc"] = self::RPC_V;
		$args["method"] = "reset";
		$args["id"] = $this->id_connection;
		$args["params"] = [];

		$result = [];
		$data = $this->apiCall($args);
		$result["status"] = false;

		if (!$data === false) {
			if (isset($data["id"])) {
				if ($data["id"] == $this->id_connection) {
					$result["status"] = true;
				}
			}

			return $result;
		}

		return false;
	}
	
	/**
	 * checkAddress()
	 * Regex check for wallet address
	 *
	 * @param string [$address] Addres of Karbo wallet
	 */
	public static function checkAddress($address) {
		$result = preg_match("/^K[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{94}$/", $address);

		return $result;
	}

	/**
	 * checkPaymentId()
	 * Regex check for payment id
	 *
	 * @param string [$payment_id] Payment id
	 */
	public static function checkPaymentId($payment_id) {
		$result = preg_match("\"[0-9A-Fa-f]{64}$\"", $payment_id);

		return $result;
	}

	/**
	 * genPaymentId()
	 * Generate payment id
	 */
	public static function genPaymentId() {
		$buff = "";
		$buff = bin2hex(openssl_random_pseudo_bytes(32));

		return $buff;
	}

}