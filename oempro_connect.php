<?php

/**
 * OEMPRO CONNECT
 *
 * Class to connect to Oempro v4.* API
 *
 * @version 1.0
 * @copyright AjaxMasters.com
 * @author Chocksy <chocksy@ajaxmasters.com>
 *
 */
class OemproConnect {
    const ResponseFormatJSON = "JSON";
    const ResponseFormatXML = "XML";

    private $ApiURL = "";
    private $ResponseFormat = self::ResponseFormatJSON; //default response format
    private $SessionID = "";
    private $bolLogged = false;
    private $UserAgent = "Mozilla/5.0 (Windows; U; Windows NT 5.1; pt-BR; rv:1.9.0.7) Gecko/2009021910 Firefox/3.0.10Cookie";

    function __construct($apiurl) {
        $this->setApiURL($apiurl);
    }

    /**
     * Sets the Oempro API URL used to make all requests
     *
     * @return
     * @param string $URL
     * @example $oempro->setApiURL("http://www.yourdomain.com/api.php");
     */
    public function setApiURL($URL) {
        $this->ApiURL = $URL;
    }

    /**
     * Sets the User Agent used on HTTP Request to the API
     *
     * @return
     * @param string $UserAgent
     */
    public function setUserAgent($UserAgent) {
        $this->UserAgent = $UserAgent;
    }

    /**
     * Sets preferred response format, must be XML or JSON
     *
     * @return
     * @param string $newFormat XML or JSON
     */
    public function setResponseFormat($newFormat) {
        if ($newFormat == self::ResponseFormatJSON || $newFormat == self::ResponseFormatXML)
            $this->ResponseFormat = $newFormat;
        else
            throw new Exception("Response format must be JSON or XML.");
    }

    /**
     * Parse all params to the API applying stripslashes
     *
     * @return array
     * @param array $arrParams
     */
    private function parseParams(array $arrParams) {
        foreach ($arrParams as $name => $value)
            $arrParams[$name] = stripslashes($value);
        return $arrParams;
    }

    /**
     * TO-DO
     *
     * @return
     * @param object $XMLResponse
     */
    private function xmlParser($XMLResponse) {
        // TODO: xmlParser method
    }

    /**
     * Verify if the user is logged. The method userLogin must be the first call.
     *
     * @return
     * @see userLogin
     */
    private function checkLogin() {
        if (!$this->bolLogged)
            throw new Exception('In order to execute any API Command you must be logged in, use the method ->userLogin().');
    }

    /**
     * Throw one or more erros using specific error codes provided by each method call
     *
     * @return
     * @param array $ErrorCode
     * @param array $arrErrorMsgs
     */
    private function throwErrors($ErrorCode, $arrErrorMsgs) {
        $throwMsg = '';
        $arrMsg = array();
        if (is_array($ErrorCode)) {
            foreach ($ErrorCode as $intError)
                $arrMsg[] = $arrErrorMsgs[$intError];
            $throwMsg = implode(' / ', $arrMsg);
        } else
            $throwMsg = $arrErrorMsgs[$ErrorCode];

        throw new Exception($throwMsg);
    }

    /**
     * Format the request to the API
     *
     * @return
     * @param string $Command Oempro API Command Name
     * @param array $arrParams
     */
    private function sendRequest($Command, array $arrParams) {
        if (empty($this->ApiURL))
            throw new Exception("Api URL is missing.");

        $arrParams['Command'] = $Command;
        $arrParams['ResponseFormat'] = $this->ResponseFormat;

        if ($this->bolLogged)
            $arrParams['SessionID'] = $this->SessionID;

        $arrParams = $this->parseParams($arrParams);

        // do http request
        $Response = $this->httpRequest($this->ApiURL, $arrParams);

        if ($this->ResponseFormat == "JSON") {
            $JSON = json_decode($Response, true);
            //echo '<pre>'; var_dump($arrParams);
            return $JSON;
        }
        else
            return $this->xmlParser($Response);
    }

    /**
     * Do HTTP request to the API
     *
     * @return
     * @param string $URL API URL
     * @param array $arrParams Array of Params
     */
    public function httpRequest($URL, $arrParams) {
        $params = array(
            'http' => array('method' => 'POST',
                'header' => "User-Agent: " . $this->UserAgent . "\r\n" .
                "Accept-language: pt-br,pt;q=0.8,en-us;q=0.5,en;q=0.3\r\n" .
                "Cookie: PHPSESSID=" . $this->SessionID . "\r\n" .
                "Content-type: application/x-www-form-urlencoded; charset=UTF-8\r\n",
                'content' => http_build_query($arrParams))
        );
        $ctx = stream_context_create($params);

        $handle = fopen($URL, "rb", false, $ctx);
        $content = stream_get_contents($handle);

        fclose($handle);
        return $content;
    }

    /**
     * Verifies the provided username and password then logs the user in
     *
     * @return integer $UserID
     * @param string $User
     * @param string $Password
     * @link http://octeth.com/wiki/User.Login
     */
    public function userLogin($User, $Password) {
        $arrParams = array();
        $arrParams['Username'] = $User;
        $arrParams['Password'] = $Password;
        $arrParams['RememberMe'] = 'yes';

        $arrResponse = $this->sendRequest('User.Login', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[1] = 'Username is missing';
            $errors[2] = 'Password is missing';
            $errors[3] = 'Invalid login information';
            $errors[4] = 'Invalid image verification';
            $errors[5] = 'Image verification failed';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            $this->SessionID = $arrResponse['SessionID'];
            $this->bolLogged = true;
            return (integer) $arrResponse['UserInfo']['UserID'];
        }
    }

    /**
     * Create new campaign to send out
     *
     * @return integer $CampaingID
     * @param string $campaignName
     * @link http://octeth.com/wiki/Campaign.Create
     */
    public function createCampaign($campaignName) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['CampaignName'] = $campaignName;

        $arrResponse = $this->sendRequest('Campaign.Create', $arrParams);
        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Campaign name is missing';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return (integer) $arrResponse['CampaignID'];
        }
    }

    /**
     * Creates and/or setup a Campaign
     * Update campaign details
     *
     * @return integer $CampaignID
     * @param integer $CampaignID Can be null
     * @param integer $EmailID
     * @param array $arrLists
     * @param string $CampaignName
     * @link http://octeth.com/wiki/Campaign.Update
     */
    public function setupCampaign($CampaignID, $EmailID, array $arrLists, $CampaignName) {
        $this->checkLogin();

        if (is_null($CampaignID))
            $CampaignID = $this->createCampaign($CampaignName);

        // setup recipients
        $arrRecipients = array();
        foreach ($arrLists as $ListID)
            $arrRecipients[] = $ListID . ':0';
        $Recipients = implode(',', $arrRecipients);

        $arrParams = array();
        $arrParams['CampaignID'] = $CampaignID;
        $arrParams['CampaignName'] = $CampaignName;
        $arrParams['GoogleAnalyticsDomains'] = '';
        $arrParams['PublishOnRSS'] = 'Disabled';
        $arrParams['RecipientListsAndSegments'] = $Recipients;
        $arrParams['Recipients'] = $Recipients;
        $arrParams['RelEmailID'] = $EmailID;
        $arrParams['ScheduleRecDaysOfMonth'] = '';
        $arrParams['ScheduleRecDaysOfWeek'] = '';
        $arrParams['ScheduleRecHours'] = '';
        $arrParams['ScheduleRecMinutes'] = '';
        $arrParams['ScheduleRecMonths'] = '';
        $arrParams['ScheduleRecSendMaxInstance'] = 0;
        $arrParams['ScheduleType'] = 'Not Scheduled';
        $arrParams['SendDate'] = '';
        $arrParams['SendDateAndTime'] = '';
        $arrParams['SendTime'] = '';
        $arrParams['SendTimeZone'] = '(GMT-03:00) Brasilia';

        $arrResponse = $this->sendRequest('Campaign.Update', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[1] = 'Missing campaign ID';
            $errors[2] = 'Invalid campaign ID';
            $errors[3] = 'Invalid campaign status value';
            $errors[4] = 'Invalid email ID';
            $errors[5] = 'Invalid campaign schedule type value';
            $errors[6] = 'Missing send date';
            $errors[7] = 'Missing send time';
            $errors[8] = 'Day of month or day of week must be provided for recursive scheduling';
            $errors[9] = 'Months must be provided for recursive scheduling';
            $errors[10] = 'Hours must be provided for recursive scheduling';
            $errors[11] = 'Minutes must be provided for recursive scheduling';
            $errors[12] = 'Number of times to repeat must be provided for recursive scheduling';
            $errors[13] = 'Time zone for scheduling is missing';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            $arrResponse['ErrorMessage'] = $errors[$errorCode];
        } else {
            $arrResponse['CampaignID'] = $CampaignID;
        }
        return $arrResponse;
    }

    /**
     * Retrieves campaigns
     *
     * @return array $Campaigns
     * @param string $OrderField[optional]
     * @param string $OrderType[optional]
     * @param string $RecordsFrom[optional]
     * @param string $RecordsPerRequest[optional]
     * @param string $SearchKeywords[optional]
     * @param string $Status[optional] All | Draft | Ready | Sending | Paused | Pending Approval | Sent | Failed
     */
    public function getCampaigns($OrderField = "CampaignStatus", $OrderType = "ASC", $RecordsFrom = 0, $RecordsPerRequest = 10, $SearchKeywords = "", $Status = "All") {
        $this->checkLogin();

        $arrParams = array();
        $arrParams["OrderField"] = $OrderField;
        $arrParams["OrderType"] = $OrderType;
        $arrParams["RecordsFrom"] = $RecordsFrom;
        $arrParams["RecordsPerRequest"] = $RecordsPerRequest;
        $arrParams["RetrieveTags"] = "true";
        $arrParams["SearchKeyword"] = "";
        $arrParams["Status"] = $Status;

        $arrResponse = $this->sendRequest('Campaigns.Get', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Missing user ID';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse['Campaigns'];
        }
    }

    /**
     * Retrieve a speicifc campaign of a user.
     *
     * @return array $Campaigns
     * @param string $CampaignID = {integer} (required)
     * @param string $RetrieveStatistics = {true | false} (required)
     */
    public function getCampaign($CampaignID, $RetrieveStatistics=false) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams["CampaignID"] = $CampaignID;
        $arrParams["RetrieveStatistics"] = $RetrieveStatistics;

        $arrResponse = $this->sendRequest('Campaign.Get', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Missing campaign ID';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse['Campaign'];
        }
    }

    /**
     * Update Campaing status do Ready and Schedule immediately
     *
     * ATTENTION: check your CRON status to make sure the campaign will be sent
     *
     * @return
     * @param string $CampaignID
     * @link http://octeth.com/wiki/Campaign.Update
     */
    public function sendCampaign($data) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['CampaignStatus'] = $data['CampaignStatus'];
        $arrParams['ScheduleType'] = $data['ScheduleType'];
        $arrParams['SendDate'] = $data['SendDate'];
        $arrParams['SendTime'] = $data['SendTime'];
        $arrParams['SendTimeZone'] = $data['SendTimeZone'];
        $arrParams['CampaignID'] = $data['CampaignID'];

        $arrResponse = $this->sendRequest('Campaign.Update', $arrParams);
        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Missing campaign ID';
            $errors[2] = 'Invalid campaign ID';
            $errors[3] = 'Invalid campaign status value';
            $errors[4] = 'Invalid email ID';
            $errors[5] = 'Invalid campaign schedule type value';
            $errors[6] = 'Missing send date';
            $errors[7] = 'Missing send time';
            $errors[8] = 'Day of month or day of week must be provided for recursive scheduling';
            $errors[9] = 'Months must be provided for recursive scheduling';
            $errors[10] = 'Hours must be provided for recursive scheduling';
            $errors[11] = 'Minutes must be provided for recursive scheduling';
            $errors[12] = 'Number of times to repeat must be provided for recursive scheduling';
            $errors[13] = 'Time zone for scheduling is missing';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse;
        }
    }

    /**
     * Delete campaigns
     *
     * @return array $Campaigns
     * @param string $OrderField[optional]
     * @param string $OrderType[optional]
     * @param string $RecordsFrom[optional]
     * @param string $RecordsPerRequest[optional]
     * @param string $SearchKeywords[optional]
     * @param string $Status[optional] All | Draft | Ready | Sending | Paused | Pending Approval | Sent | Failed
     */
    public function deleteCampaigns($campaigns) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams["Campaigns"] = $campaigns;

        $arrResponse = $this->sendRequest('Campaigns.Delete', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Campaign ids are missing';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            $arrResponse['ErrorMessage'] = $errors[$errorCode];
        }
        return $arrResponse;
    }

    /**
     * Creates a new subscriber list
     *
     * @return integer $ListID
     * @param string $listName
     * @link http://octeth.com/wiki/List.Create
     */
    public function createList($listName) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['SubscriberListName'] = $listName;

        $arrResponse = $this->sendRequest('List.Create', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Campaign name is missing';
            $errors[2] = 'There is already a subscriber list with given name';
            $errors[3] = 'Allowed list amount exceeded';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return (integer) $arrResponse['ListID'];
        }
    }

    /**
     * Retrieves subscriber lists
     *
     * @return array $Lists
     * @param string $OrderField[optional]
     * @param string $OrderType[optional]
     * @param string $RecordsFrom[optional]
     * @param string $RecordsPerRequest[optional]
     * @link http://octeth.com/wiki/Lists.Get
     */
    public function getLists($OrderField = "Name", $OrderType = "ASC") {
        $this->checkLogin();

        $arrParams = array();
        $arrParams["OrderField"] = $OrderField;
        $arrParams["OrderType"] = $OrderType;

        $arrResponse = $this->sendRequest('Lists.Get', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse['Lists'];
        }
    }

    /**
     * Subscribes an email address to provided subscriber list
     *
     * @return integer $SubscriberID
     * @param integer $listID
     * @param string $email
     * @link http://octeth.com/wiki/Subscriber.Subscribe
     */
    public function addSubscriber($listID, $email) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['ListID'] = $listID;
        $arrParams['EmailAddress'] = $email;
        $arrParams['CustomFieldX'] = '';
        $arrParams['IPAddress'] = $_SERVER['SERVER_ADDR'];

        $arrResponse = $this->sendRequest('Subscriber.Subscribe', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[1] = 'Target subscriber list ID is missing';
            $errors[2] = 'Email address is missing';
            $errors[3] = 'IP address of subscriber is missing';
            $errors[4] = 'Invalid subscriber list ID';
            $errors[5] = 'Invalid email address';
            $errors[6] = 'One of the provided custom fields is empty. Custom field ID and title is provided as an additional output parameter';
            $errors[7] = 'One of the provided custom field value already exists in the database. Please enter a different value. Custom field ID and title is provided as an additional output parameter';
            $errors[8] = 'One of the provided custom field value failed in validation checking. Custom field ID and title is provided as an additional output parameter';
            $errors[9] = 'Email address already exists in the list';
            $errors[10] = 'Unknown error occurred';
            $errors[11] = 'Invalid user information';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return (integer) $arrResponse['SubscriberID'];
        }
    }

    /**
     * Adds multiply Subscriber
     *
     * @return array $arrIDs Array of Subscribers IDs
     * @param integer $listID
     * @param array $arrEmails
     */
    public function addSubscribers($listID, array $arrEmails) {
        $arrIDs = array();
        foreach ($arrEmails as $email) {
            $arrIDs[] = $this->addEmail($listID, $email);
        }
        return $arrIDs;
    }

    /**
     * Retrieves subscribers of a subscriber list
     *
     * @return array $Subscribers
     * @param integer $listID
     * @link http://octeth.com/wiki/Subscribers.Get
     */
    public function getSubscribers($listID) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['OrderField'] = 'EmailAddress';
        $arrParams['OrderType'] = 'ASC';
        $arrParams['RecordsFrom'] = 0;
        $arrParams['RecordsPerRequest'] = '';
        $arrParams['SubscriberListID'] = $listID;
        $arrParams['SubscriberSegment'] = 'Active';

        $arrResponse = $this->sendRequest('Subscribers.Get', $arrParams);
        if ($arrResponse['Success'] === false) {

            $errors[1] = 'Target subscriber list ID is missing';
            $errors[2] = 'Target segment ID is missing';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse['Subscribers'];
        }
    }

    /**
     * Delete a Subscriber from a list
     *
     * @return
     * @param integer $listID
     * @param integer $subscriberID
     * @link http://octeth.com/wiki/Subscribers.Delete
     */
    public function deleteSubscriber($listID, $subscriberID) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['SubscriberListID'] = $listID;
        $arrParams['Subscribers'] = $subscriberID;

        $arrReponse = $this->sendRequest('Subscribers.Delete', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[1] = 'Target subscriber list ID is missing';
            $errors[2] = 'Target segment ID is missing';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        }
    }

    /**
     * Creates a blank email record
     *
     * @return integer $EmailID
     * @param object $nomeEmail
     * @link http://octeth.com/wiki/Email.Create
     */
    public function createEmail() {
        $this->checkLogin();

        $arrParams = array();

        $arrResponse = $this->sendRequest('Email.Create', $arrParams);

        if ($arrResponse['Success'] === false) {

            // does not have error codes in documentation
            throw new Exception($arrResponse['ErrorCode']);
        } else {
            return (integer) $arrResponse['EmailID'];
        }
    }

    /**
     * Creates and/or setup a Email throught multiply info
     *
     * @return integer $EmailID
     * @param integer $EmailID
     * @param string $Name
     * @param string $FromEmail
     * @param string $FromName
     * @param string $HTMLContent
     * @param string $PlainContent
     * @param string $ReplyToEmail
     * @param string $ReplyToName
     * @param string $Subject
     * @link http://octeth.com/wiki/Email.Update
     */
    public function setupEmail($params) {
        $this->checkLogin();

        if (is_null($params['EmailID']))
            $params['EmailID'] = $this->createEmail($params['Name']);

        if (is_null($params['ImageEmbedding']))
            $params['ImageEmbedding'] = 'Disabled';

        $arrParams = array();
        $arrParams['EmailID'] = $params['EmailID'];
        $arrParams['EmailName'] = $params['Name'];
        $arrParams['FromEmail'] = $params['FromEmail'];
        $arrParams['FromName'] = $params['FromName'];
        $arrParams['HTMLContent'] = $params['HTMLContent'];
        $arrParams['ImageEmbedding'] = $params['ImageEmbedding'];
        $arrParams['Mode'] = 'Template'; // {Empty | Template | Import} (required)
        $arrParams['PlainContent'] = $params['PlainContent'];
        $arrParams['RelTemplateID'] = $params['RelTemplateID'];
        $arrParams['ReplyToEmail'] = $params['ReplyToEmail'];
        $arrParams['ReplyToName'] = $params['ReplyToName'];
        $arrParams['Subject'] = $params['Subject'];
        $arrParams['ValidateScope'] = 'Campaign';
        $arrParams['Campaign'] = '';

        $arrResponse = $this->sendRequest('Email.Update', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[1] = "Email id is missing";
            $errors[2] = "FetchURL is missing";
            $errors[3] = "Email id is invalid";
            $errors[4] = "RelTemplateID is missing";
            $errors[5] = "Mode is invalid";
            $errors[6] = "FromEmail email address is invalid";
            $errors[7] = "ReplyToEmail email address is invalid";
            $errors[8] = "Plain and HTML content is empty";
            $errors[9] = "Missing validation scope parameter";
            $errors[10] = "Invalid validation scope parameter";
            $errors[11] = "Missing unsubscription link in HTML content";
            $errors[12] = "Missing unsubscription link in plain content";
            $errors[13] = "Missing opt-in confirmation link in HTML content";
            $errors[14] = "Missing opt-in confirmation link in plain content";
            $errors[15] = "Missing opt-in reject link in HTML content";
            $errors[16] = "Missing opt-in reject link in plain content";
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $params['EmailID'];
        }
    }

    /**
     * Returns the list of email contents created so far
     *
     * @return
     */
    public function getEmails() {
        $this->checkLogin();

        $arrParams = array();

        $arrResponse = $this->sendRequest('Emails.Get', $arrParams);

        if ($arrResponse['Success'] === false) {
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse['Emails'];
        }
    }

    /**
     * Sends a preview email to the provided email address
     *
     * @return
     * @param integer $CampaignID
     * @param integer $EmailID
     * @param string $toEmail
     * @link http://octeth.com/wiki/Email.EmailPreview
     */
    public function previewEmail($CampaignID, $EmailID, $toEmail) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['CampaignID'] = $CampaignID;
        $arrParams['EmailID'] = $EmailID;
        $arrParams['EmailAddress'] = $toEmail;

        $arrResponse = $this->sendRequest('Email.EmailPreview', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[1] = 'Missing email ID';
            $errors[2] = 'Missing email address';
            $errors[3] = 'Invalid email ID';
            $errors[4] = 'Invalid email address format';
            $errors[5] = 'Missing list ID';
            $errors[6] = 'Invalid list ID';
            $errors[7] = 'Invalid campaign ID';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        }else
            return $arrResponse['Success'];
    }

    /**
     * Retrieves email templates defined in the system
     *
     * @return
     * @link http://octeth.com/docs/oempro/developers/api/commands/email.templates.get/
     */
    public function getTemplates() {
        $this->checkLogin();

        $arrParams = array();

        $arrResponse = $this->sendRequest('Email.Templates.Get', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse['Templates'];
        }
    }

    /**
     * Retrieves email template defined in the system
     *
     * @return
     * @link http://octeth.com/docs/oempro/developers/api/commands/email.template.get/
     */
    public function getTemplate($id) {
        $this->checkLogin();

        $arrParams = array();
        $arrParams['TemplateID'] = $id;

        $arrResponse = $this->sendRequest('Email.Template.Get', $arrParams);

        if ($arrResponse['Success'] === false) {

            $errors[1] = 'Template ID is missing';
            $errors[2] = 'Template ID is invalid';
            $errors[3] = 'Template does not belong to this user';
            $errors[99998] = 'Authentication failure or session expired';
            $errors[99999] = 'Not enough privileges';

            $errorCode = $arrResponse['ErrorCode'];
            return $errors[$errorCode];
        } else {
            return $arrResponse;
        }
    }

}

?>