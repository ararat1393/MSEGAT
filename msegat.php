<?php
/**
* @author hobrt.me
*/

namespace backend\api;

use Yii;

class Msegat
{
	private $send_sms_url;
	private $balance_url;

	private $content;
	private $header;

	private $request = [];
	private $response_codes = [
		"1" => "Success",
		"M0000" => "Success",
		"M0001" => "Variables missing",
		"M0002" => "Invalid login info",
		"M0022" => "Exceed number of senders allowed",
		"M0023" => "Sender Name is active or under activation or refused",
		"M0024" => "Sender Name should be in English or number",
		"M0025" => "Invalid Sender Name Length",
		"M0026" => "Sender Name is already activated or not found",
		"M0027" => "Activation Code is not Correct",
		"M0028" => "You reach maximum number of attempts. Sender name is locked",
		"1010" => "Variables missing",
		"1020" => "Invalid login info",
		"1050" => "MSG body is empty",
		"1060" => "Balance is not enough",
		"1061" => "MSG duplicated",
		"1110" => "Sender name is missing or incorrect",
		"1120" => "Mobile numbers is not correct",
		"1140" => "MSG length is too long"
	];

	public function __construct() {

		$this->send_sms_url = env('MSEGAT_SSMS_HOST');
		$this->balance_url  = env('MSEGAT_BALANCE_HOST');

		$this->request = [
			"userName"     => env('MSEGAT_USERNAME'),
			"userPassword" => env('MSEGAT_PASSWORD'),
			"userSender"   => env('MSEGAT_USERSENDER'),
			"msgEncoding"  => env('MSEGAT_ENCODING')
		];
	}
	
	/**
	* @access public
	* @param array, String, String
	* @return true
	**/
	public function send( $number , $message, $time = false, $by = "Link")
	{	 
		$this->request['numbers'] = $number;
		$this->request['msg']     = $message;
		$this->request['By']      = $by;

		if($time !== false)
		{
			$this->request['exactTime']  = $time;
			$this->request['timeToSend'] = "later";
		}

		$query = http_build_query( $this->request );
		$url   = $this->send_sms_url."?".$query;
		$content = $this->sendRequest( $url );
		return $this->getResponseMessage( $content );
	}

	/**
	* @access public
	* @param Void
	* @return Int
	**/

	public function getCredet()
	{
		$query = http_build_query($this->request);
		$url = $this->balance_url.$query;
		return $this->sendRequest($url);
	}

	public function sendRequest($url)
	{
		$options = array(
			CURLOPT_RETURNTRANSFER => true,     // return web page
			CURLOPT_HEADER         => false,    // don't return headers
			CURLOPT_FOLLOWLOCATION => true,     // follow redirects
			CURLOPT_ENCODING       => "",       // handle all encodings
			CURLOPT_AUTOREFERER    => true,     // set referer on redirect
			CURLOPT_CONNECTTIMEOUT => 120,      // timeout on connect
			CURLOPT_TIMEOUT        => 120,      // timeout on response
			CURLOPT_MAXREDIRS      => 10,       // stop after 10 redirects
			CURLOPT_SSL_VERIFYPEER => false     // Disabled SSL Cert checks
		);

		$ch      = curl_init( $url );
		curl_setopt_array( $ch, $options );
		$content = curl_exec( $ch );
		$err     = curl_errno( $ch );
		$errmsg  = curl_error( $ch );
		$this->header  = curl_getinfo( $ch );
		curl_close( $ch );

		$this->header['errno']   = $err;
		$this->header['errmsg']  = $errmsg;
		$this->header['content'] = $content;
		$this->header['url'] = $url;
		return $content;
	}

	public function getResponseMessage( $code ){

		$message = (object)[
			'status'=> 200,
			'msg' => $this->response_codes[$code] 
		];

		if( $code != '1' && $code != 'M0000' ){
			$message->status = 404;
			$message->msg = (object)[
				'error'=>(object)[
					'code' => $code,
					'message' => $this->response_codes[$code]
				]
			];
		}

		return $message;
	}

	public function gerResposeHeader(){
		return $this->header;
	}

	public function msegatRequestData(){
		return $this->request;
	}

	public function sendEmail( $message = '', $subject = 'MSEGAT ERROR' )
    {	
    	$mailer = false;

    	try {

	        $mailer = Yii::$app->mailer->compose()
		            ->setFrom(env('MSEGAT_FROM_EMAIL'))
		            ->setTo(env('MSEGAT_TO_EMAIL'))
		            ->setSubject( $subject )
		            ->setTextBody( $message )
		            ->send();
    		
        }catch( \Swift_TransportException $exception ){

        	Yii::$app->errorHandler->logException( $exception );
        }

        return $mailer;
    }
}
