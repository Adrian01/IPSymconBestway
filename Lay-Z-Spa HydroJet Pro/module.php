<?php

class LayZSpa extends IPSModule
{
    private $apiRoot = 'https://euapi.gizwits.com';
    private $applicationId = '98754e684ec045528b073876c34c7348';

    public function Create()
    {

        parent::Create();
        
        // Properties registrieren
        $this->RegisterPropertyString("Username", "");
        $this->RegisterPropertyString("Password", "");
        $this->RegisterPropertyString("Region", "de");
        $this->RegisterPropertyInteger("UpdateInterval", 30);
        $this->RegisterPropertyBoolean("ModuleActive", true);
        
        // Attribute registrieren
        $this->RegisterAttributeString("UserToken", "");
        $this->RegisterAttributeInteger("TokenExpire", 0);
        $this->RegisterAttributeString("DeviceId", "");

        
        // Variablen Profile erzeugen
        if (!IPS_VariableProfileExists("BW.Switch")) {
            IPS_CreateVariableProfile("BW.Switch", 0); // Boolean Profil
            IPS_SetVariableProfileAssociation("BW.Switch", 0, "Aus", "", -1);
            IPS_SetVariableProfileAssociation("BW.Switch", 1, "Ein", "", -1);
        }

        if (!IPS_VariableProfileExists("BW.HeatActiv")) {
            IPS_CreateVariableProfile("BW.HeatActiv", 0); // Boolean Profil
            IPS_SetVariableProfileAssociation("BW.HeatActiv", 0, "Inaktiv", "", -1);
            IPS_SetVariableProfileAssociation("BW.HeatActiv", 1, "Aktiv", "", -1);
        }

        if (!IPS_VariableProfileExists("BW.Temperature")) {
            IPS_CreateVariableProfile("BW.Temperature", 1); // Integer Profil
            IPS_SetVariableProfileText("BW.Temperature", "", " °C");
            IPS_SetVariableProfileValues("BW.Temperature", 20, 40, 1);
        }
        

        if (!IPS_VariableProfileExists("BW.AirJet")) {
            IPS_CreateVariableProfile("BW.AirJet", 1); // Integer Profil
            IPS_SetVariableProfileAssociation("BW.AirJet", 0, "Aus", "", -1);
            IPS_SetVariableProfileAssociation("BW.AirJet", 40, "Stufe 1", "", -1);
            IPS_SetVariableProfileAssociation("BW.AirJet", 100, "Stufe 2", "", -1);
        }


        // Funktionsvariablen registrieren
        $this->RegisterVariableBoolean("Power", "Power", "BW.Switch", 1);
        $this->EnableAction("Power");

        $this->RegisterVariableBoolean("Filter", "Filter", "BW.Switch", 2);
        $this->EnableAction("Filter");

        $this->RegisterVariableBoolean("Heizung", "Heizung", "BW.Switch", 3);
        $this->EnableAction("Heizung");

        $this->RegisterVariableInteger("Solltemperatur", "Solltemperatur", "BW.Temperature", 5);
        $this->EnableAction("Solltemperatur");

        $this->RegisterVariableInteger("AirJetDuesen", "AirJet Düsen", "BW.AirJet", 7);
        $this->EnableAction("AirJetDuesen");

        $this->RegisterVariableBoolean("HydroJetDuesen", "HydroJet Düsen", "BW.Switch", 8);
        $this->EnableAction("HydroJetDuesen");


        // Statusvariablen registrieren
        $this->RegisterVariableBoolean("HeizungAktiv", "Heizung aktiv", "BW.HeatActiv", 4);
        $this->RegisterVariableInteger("Wassertemperatur", "Wassertemperatur", "BW.Temperature", 6);
        $this->RegisterVariableString("MCUHardVersion", "Hardwareversion", "", 9);
        $this->RegisterVariableString("MCUSoftVersion", "Softwareversion", "", 10);
        $this->RegisterVariableString("ProductName", "Produktname", "", 11);
        $this->RegisterVariableString("Fehlercode", "Fehlercode", "", 12);


        // Timer für Status-Update registrieren (auf 30 Sekunden gesetzt)
        $this->RegisterTimer("UpdateStatus", 30000, 'BW_UpdateStatus($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges()
    {
        
        parent::ApplyChanges();

        // Update-Intervall einstellen
        $updateInterval = $this->ReadPropertyInteger("UpdateInterval");
        $this->SetTimerInterval("UpdateStatus", $updateInterval * 1000);

        // Konfiguration validieren und Status setzen
        $username = $this->ReadPropertyString("Username");
        $password = $this->ReadPropertyString("Password");
        $moduleActive = $this->ReadPropertyBoolean("ModuleActive");

        if (empty($username) || empty($password) || !$moduleActive) {
            $this->SetStatus(104); // Modul inaktiv
            $this->SetTimerInterval("UpdateStatus", 0);
        } else {
            $this->SetStatus(102); // Modul aktiv
            $this->UpdateDevices();
        }
    }

    
    public function Destroy() 
    {
        
        parent::Destroy();


    }


    // Eigentlicher Funktionscode

    public function RequestAction($Ident, $Value)
    {
        switch($Ident) {
            case "Power":
                $this->SetPower($Value);
                break;
            case "Filter":
                $this->SetFilter($Value);
                break;
            case "HydroJetDuesen":
                $this->SetHydroJet($Value);
                break;
            case "Heizung":
                $this->SetHeater($Value);
                break;
            case "Solltemperatur":
                $this->SetTemperature($Value);
                break;
            case "AirJetDuesen":
                $this->SetAirJet($Value);
                break;
            default:
                throw new Exception("Invalid Ident");
        }
    }

    private function UpdateDevices()
    {
        $username = $this->ReadPropertyString("Username");
        $password = $this->ReadPropertyString("Password");

        $tokenData = $this->getUserToken($username, $password, $this->apiRoot, $this->applicationId);
        if ($tokenData !== null) {
            $this->WriteAttributeString("UserToken", $tokenData['token']);
            $this->WriteAttributeInteger("TokenExpire", $tokenData['expire_at']);
            $deviceId = $this->getDeviceId($tokenData['token']);
            if ($deviceId !== null) {
                $this->WriteAttributeString("DeviceId", $deviceId);
                $this->UpdateDeviceInfo();
                $this->UpdateStatus();
                $this->SetStatus(102); // Modul aktiv
                $this->LogMessage('Gerät erfolgreich verbunden!', KL_NOTIFY);
            } else {
                $this->SetStatus(202); // Geräte-ID konnte nicht abgerufen werden
            }
        } else {
            $this->SetStatus(202); // Token konnte nicht abgerufen werden
        }
    }

    public function UpdateStatus()
    {
        if (!$this->ReadPropertyBoolean("ModuleActive")) {
            return; // Modul ist inaktiv
        }

        $token = $this->GetValidToken();
        $deviceId = $this->ReadAttributeString("DeviceId");
        if ($token !== null && $deviceId !== "") {
            $status = $this->getDeviceStatus($token, $deviceId);
            if ($status !== null) {
                $this->SetValueIfExists("Power", isset($status["attr"]["power"]) && $status["attr"]["power"] == 1);
                $this->SetValueIfExists("Filter", isset($status["attr"]["filter"]) && $status["attr"]["filter"] == 2);
                $this->SetValueIfExists("HydroJetDuesen", isset($status["attr"]["jet"]) && $status["attr"]["jet"] == 1);
                $this->SetValueIfExists("Heizung", isset($status["attr"]["heat"]) && $status["attr"]["heat"] != 0);
                $this->SetValueIfExists("Solltemperatur", isset($status["attr"]["Tset"]) ? $status["attr"]["Tset"] : 0);
                $this->SetValueIfExists("AirJetDuesen", isset($status["attr"]["wave"]) ? $status["attr"]["wave"] : 0);
                $this->SetValueIfExists("Wassertemperatur", isset($status["attr"]["Tnow"]) ? $status["attr"]["Tnow"] : 0);
                $this->SetValueIfExists("HeizungAktiv", isset($status["attr"]["heat"]) && in_array($status["attr"]["heat"], [3, 5, 6]));
                $this->SetValueIfExists("Fehlercode", $this->GetErrorCode($status["attr"]));
            } else {
                $this->LogMessage('Statusinformationen konnten nicht abgerufen werden.', KL_WARNING);
            }
        } else {
            $this->LogMessage('Token oder Geräte-ID konnte nicht abgerufen werden.', KL_WARNING);
        }
    }

    public function SetPower(bool $state)
    {
        $this->ControlDevice("power", $state, "Power");
        $this->LogMessage("Der Whirlpool wurde " . ($state ? "ein" : "aus") . "geschalten", KL_NOTIFY);
    }

    public function SetFilter(bool $state)
    {
        $this->ControlDevice("filter", $state ? 2 : 0);
        $this->LogMessage("Der Filter wurde " . ($state ? "ein" : "aus") . "geschalten", KL_NOTIFY);
    }

    public function SetHydroJet(bool $state)
    {
        $this->ControlDevice("jet", $state ? 1 : 0, "HydroJet Düsen");
        $this->LogMessage("Die HydroJet Düsen wurden " . ($state ? "ein" : "aus") . "geschalten", KL_NOTIFY);
    }

    public function SetHeater(bool $state)
    {
        $this->ControlDevice("heat", $state ? 3 : 0, "Heizung");
        $this->LogMessage("Die Heizung wurde " . ($state ? "ein" : "aus") . "geschalten", KL_NOTIFY);
    }

    public function SetTemperature(int $value)
    {
        $this->ControlDevice("Tset", $value, "Solltemperatur");
        $this->LogMessage("Solltemperatur wurde auf " . $value . " °C eingestellt", KL_NOTIFY);
    }

    public function SetAirJet(int $value)
    {
        switch($value)
        {
            case 0: 
                $this->ControlDevice("wave", 0, "AirJet Düsen");
                $this->LogMessage("Die AirJet Düsen wurden ausgeschalten", KL_NOTIFY);
                break;
            
            case 1:
                $this->ControlDevice("wave", 40, "AirJet Düsen");
                $this->LogMessage("Die AirJet Düsen wurden auf Stufe 1 eingestellt", KL_NOTIFY);
                break;
            
            case 2:
                $this->ControlDevice("wave", 100, "AirJet Düsen");
                $this->LogMessage("Die AirJet Düsen wurden auf Stufe 2 eingestellt", KL_NOTIFY);
                break;
        }
        
    }

    private function ControlDevice(string $attribute, $value)
    {
        $token = $this->GetValidToken();
        $deviceId = $this->ReadAttributeString("DeviceId");
        if ($token !== null && $deviceId !== "") {
            $controller = new PoolController([], $token, $this->apiRoot, [], $deviceId, $this->applicationId);
            $controller->setDeviceAttribute($attribute, $value);
            IPS_Sleep(3000); // 3 Sekunde warten

            $this->UpdateStatus();

        } 
        else
        {
            $this->LogMessage('Token oder Geräte-ID konnte nicht abgerufen werden.', KL_WARNING);
        }
    }

    private function GetValidToken()
    {
        $token = $this->ReadAttributeString("UserToken");
        $expire = $this->ReadAttributeInteger("TokenExpire");

        if ($token != "" && $expire > time()) {
            return $token;
        }

        return null;
    }

    private function UpdateDeviceInfo()
    {
        $token = $this->GetValidToken();
        $deviceId = $this->ReadAttributeString("DeviceId");
        if ($token !== null && $deviceId !== "") {
            $devices = $this->getDevices($token);
            if ($devices !== null && count($devices) > 0) {
                $device = $devices[0]; // Annahme das nur ein Gerät vorhanden ist
                $this->SetValueIfExists("ProductName", $device["product_name"]);
                $this->SetValueIfExists("MCUHardVersion", $device["mcu_hard_version"]);
                $this->SetValueIfExists("MCUSoftVersion", $device["mcu_soft_version"]);
            } else {
                $this->LogMessage('Geräteinformationen konnten nicht abgerufen werden.', KL_WARNING);
            }
        } else {
            $this->LogMessage('Token oder Geräte-ID konnte nicht abgerufen werden.', KL_WARNING);
        }
    }

    private function getDevices(string $user_token)
    {
        $url = $this->apiRoot . '/app/bindings?limit=20&skip=0';

        $headers = array(
            'Content-Type: application/json',
            'X-Gizwits-Application-Id: ' . $this->applicationId,
            'X-Gizwits-User-token: ' . $user_token
        );

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);

        if ($data === null && json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Fehler beim Dekodieren der JSON-Antwort: ' . json_last_error_msg());
        }

        return $data['devices'];
    }

    private function getDeviceId(string $user_token)
    {
        try {
            $devices = $this->getDevices($user_token);
            if ($devices !== null && count($devices) > 0) {
                return $devices[0]['did']; // Wir nehmen an, dass nur ein Gerät vorhanden ist
            } else {
                return null;
            }
        } catch (Exception $e) {
            $this->LogMessage('Fehler beim Abrufen der Geräte-ID: ' . $e->getMessage(), KL_ERROR);
            return null;
        }
    }

    private function getDeviceStatus(string $user_token, string $did)
    {
        $controller = new PoolController([], $user_token, $this->apiRoot, [], $did, $this->applicationId);
        return $controller->getDeviceStatus();
    }

    private function getUserToken(string $username, string $password, string $api_root, string $application_id)
    {
        $url = $api_root . '/app/login';
        $data = array(
            "username" => $username,
            "password" => $password,
            "lang" => "de"
        );
        $jsonData = json_encode($data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Gizwits-Application-Id: ' . $application_id
        ));
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

        $response = curl_exec($ch);
        curl_close($ch);

        $api_data = json_decode($response, true);

        if (isset($api_data["uid"], $api_data["token"], $api_data["expire_at"])) {
            return array(
                "uid" => $api_data["uid"],
                "token" => $api_data["token"],
                "expire_at" => $api_data["expire_at"],
                "application_id" => $application_id
            );
        } 
        
        switch ($api_data["error_code"])

        {
            case 9020:
                $this->LogMessage('Verbindungsfehler: Benutzername oder Passwort falsch!', KL_ERROR);
                $this->SetTimerInterval("UpdateStatus", 0);
                break;
    
            case 9005:
                $this->LogMessage('Verbindungsfehler: Benutzer existiert nicht!', KL_ERROR);
                $this->SetTimerInterval("UpdateStatus", 0);
                break;
       
            case 9041:
                $time = substr($response, -14, 3);
                $this->LogMessage('Verbindungsfehler: Zu viele Anfragen in kürzester Zeit, versuche es in ' . $time . ' Sekunden erneut!', KL_ERROR);
                $this->SetTimerInterval("UpdateStatus", 0);
                break;
            
            default:
                $this->LogMessage('Unbekannter Fehler: ' . $response, KL_ERROR);
                $this->SetTimerInterval("UpdateStatus", 0);
                break;
        }      

    }

    private function SetValueIfExists(string $Ident, $Value)
    {
        if ($this->GetIDForIdent($Ident) !== false) {
            SetValue($this->GetIDForIdent($Ident), $Value);
        }
    }

    
    private function GetErrorCode(array $attributes): string
    {
        for ($i = 1; $i <= 12; $i++) {
            $errorKey = sprintf("E%02d", $i);
            if (isset($attributes[$errorKey]) && $attributes[$errorKey] == 1) {
                return $errorKey;
            }
        }
        return "keine Fehler";
    }

}


class PoolController
{
    private $_state_cache;
    private $_user_token;
    private $_api_root;
    private $_headers;
    private $_did;
    private $_app_id;

    public function __construct($state_cache, $user_token, $api_root, $headers, $did, $app_id)
    {
        $this->_state_cache = $state_cache;
        $this->_user_token = $user_token;
        $this->_api_root = $api_root;
        $this->_headers = $headers;
        $this->_did = $did;
        $this->_app_id = $app_id;
    }

    public function setDeviceAttribute($attribute, $value)
    {
        $this->_doControlPost([$attribute => $value]);
    }

    public function getDeviceStatus()
    {
        $url = $this->_api_root . "/app/devdata/" . $this->_did . "/latest";
        
        $headers = [
            'Content-Type: application/json',
            'X-Gizwits-User-token: ' . $this->_user_token,
            'X-Gizwits-Application-Id: ' . $this->_app_id
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->LogMessage('Request Error:' . curl_error($ch), KL_ERROR);
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if ($data === null) {
            $this->LogMessage('Fehler beim Dekodieren der JSON-Antwort\nAntwort: ' . $response, KL_ERROR);
            return null;
        }

        return $data;
    }

    private function _doControlPost($attrs)
    {
        $url = $this->_api_root . "/app/control/" . $this->_did;
        $this->_doPost($url, ['attrs' => $attrs]);
    }


    private function _doPost($url, $body)
    {
        $headers = [
            'Content-Type: application/json',
            'X-Gizwits-User-token: ' . $this->_user_token,
            'X-Gizwits-Application-Id: ' . $this->_app_id
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $this->LogMessage('Request Error:' . curl_error($ch), KL_ERROR);
            curl_close($ch);
            return null;
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if ($data === null) {
            $this->LogMessage('Fehler beim Dekodieren der JSON-Antwort\nAntwort: ' . $response, KL_ERROR);
            return null;
        }

        return $data;
    }
}

?>
