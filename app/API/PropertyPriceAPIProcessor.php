<?php
require_once __DIR__ . '/cAPI_Processor.php';
require_once dirname(dirname(__DIR__)) . '/api/dvf_service.php';
require_once dirname(__DIR__) . '/Repositories/PropertyRepository.php';
class PropertyPriceAPIProcessor extends cAPI_Processor
{
    public function process($method, $body)
    {
        $id=(string)$this->params['property_id']; $this->assertPropertyCalculationAllowed($id); $listing=(new PropertyRepository($this->pdo))->find($id);
        try { if($method==='GET'){ $stored=dvfReadAnalysis($id, $this->pdo); if($stored && dvfAnalysisMatchesListing($stored,$listing)) return array('data'=>$stored['result'],'meta'=>array('stored'=>true)); } $stored=dvfCreateAnalysis($id,$listing,array('id'=>$id),true,$this->pdo); return array('data'=>$stored['result'],'meta'=>array('stored'=>true)); }
        catch(InvalidArgumentException $e){throw new APIException(422,'prices.invalid_property',$e->getMessage());} catch(RuntimeException $e){throw new APIException(503,'prices.unavailable',$e->getMessage());}
    }
}
