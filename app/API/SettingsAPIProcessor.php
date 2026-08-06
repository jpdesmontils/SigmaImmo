<?php
require_once __DIR__ . '/APIException.php';
class SettingsAPIProcessor
{
    private $pdo; private $user;
    public function __construct(PDO $pdo,array $route,array $user,array $params=array()){ $this->pdo=$pdo; $this->user=$user; }
    public function process($method,$body){ if($method==='PATCH'){ $city=is_array($body)&&isset($body['primaryResidenceCity'])?mb_substr(trim(strip_tags((string)$body['primaryResidenceCity'])),0,120):''; if($city==='')throw new APIException(422,'settings.city_required','La ville de résidence principale est obligatoire.'); $stmt=$this->pdo->prepare('UPDATE users SET primary_residence_city=:city,updated_at=:now WHERE id=:id'); $stmt->execute(array(':city'=>$city,':now'=>gmdate('c'),':id'=>$this->user['id'])); } else $city=!empty($this->user['primary_residence_city'])?$this->user['primary_residence_city']:'Paris'; return array('data'=>array('primaryResidenceCity'=>$city),'meta'=>array()); }
}
